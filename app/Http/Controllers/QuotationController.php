<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Quotation;
use App\Models\QuotationVersion;
use App\Support\CurrentProduction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * COTIZACIÓN — captura ADENTRO (la cotización llegó por correo y alguien la sube o teclea sus
 * partidas). El paso PREVIO al contrato. Módulo autenticado (gate quotations.manage); la
 * ACEPTACIÓN (Line Producer, sellada) y la SOLICITUD por enlace firmado van en fases aparte.
 *
 * Dos formas, mismo objeto: PDF subido byte-intact (se hashea, NUNCA se modifica) o partidas
 * capturadas (importes calculados, respetando días e IVA incluido/no). Negociar es versionar:
 * la primera captura es la versión 1; los ajustes crean versiones nuevas (fase de versionado).
 */
class QuotationController extends Controller
{
    /** Estatus válidos para editar la versión en curso (antes de aceptar). */
    private function editable(Quotation $q): bool
    {
        return ! $q->isAccepted();
    }

    public function index(Request $request)
    {
        $prodId = optional(CurrentProduction::get())->id;

        $quotations = Quotation::query()
            ->where('production_id', $prodId)
            ->visibleTo($request->user())
            ->with(['currentVersion', 'department', 'payee'])
            ->orderByDesc('id')
            ->paginate(25);

        return view('quotations.index', ['quotations' => $quotations]);
    }

    public function create()
    {
        return view('quotations.create', [
            'departments' => Department::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request, true);
        $prodId = optional(CurrentProduction::get())->id;
        abort_if($prodId === null, 422, 'No hay una producción activa.');

        $quotation = null;
        DB::transaction(function () use ($request, $data, $prodId, &$quotation) {
            $quotation = Quotation::create([
                'production_id' => $prodId,
                'department_id' => $data['department_id'] ?? null,
                'location_name' => $data['location_name'] ?? null,
                'emitter_name'  => $data['emitter_name'],
                'emitter_email' => $data['emitter_email'] ?? null,
                'status'        => Quotation::STATUS_RECEIVED,
                'created_by_id' => $request->user()->id,
                'uuid'          => (string) \Illuminate\Support\Str::uuid(),
            ]);

            $version = new QuotationVersion([
                'quotation_id' => $quotation->id,
                'version_no'   => 1,
                'created_by_id' => $request->user()->id,
            ]);
            $this->applyVersionData($version, $request, $data);
            $version->save();
            $this->syncItems($version, $request, $data);
            $version->recomputeTotals();
            $version->save();

            $quotation->current_version_id = $version->id;
            $quotation->save();
        });

        return redirect()->route('quotations.show', $quotation)->with('status', 'Cotización registrada.');
    }

    public function show(Quotation $quotation)
    {
        abort_unless($quotation->isVisibleTo(auth()->user()), 403);
        $quotation->load(['currentVersion.items', 'versions.items', 'department', 'payee', 'acceptedBy', 'createdBy']);

        // Enlace FIRMADO para pedirle al proveedor que la llene sin login (SE SOLICITA).
        $requestUrl = $quotation->isAccepted() ? null
            : \Illuminate\Support\Facades\URL::temporarySignedRoute('quotations.request.show', now()->addDays(14), ['quotation' => $quotation->id]);

        return view('quotations.show', ['quotation' => $quotation, 'requestUrl' => $requestUrl]);
    }

    public function edit(Quotation $quotation)
    {
        abort_unless($quotation->isVisibleTo(auth()->user()), 403);
        abort_unless($this->editable($quotation), 403, 'Una cotización aceptada no se edita.');
        $quotation->load('currentVersion.items');

        return view('quotations.edit', [
            'quotation'   => $quotation,
            'version'     => $quotation->currentVersion,
            'departments' => Department::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Quotation $quotation)
    {
        abort_unless($quotation->isVisibleTo(auth()->user()), 403);
        abort_unless($this->editable($quotation), 403, 'Una cotización aceptada no se edita.');
        $data = $this->validated($request, false);

        DB::transaction(function () use ($request, $data, $quotation) {
            $quotation->update([
                'department_id' => $data['department_id'] ?? null,
                'location_name' => $data['location_name'] ?? null,
                'emitter_name'  => $data['emitter_name'],
                'emitter_email' => $data['emitter_email'] ?? null,
            ]);

            // Edita la versión EN CURSO en su lugar (aún no hay sucesora ni aceptación → no
            // rompe "la anterior no se altera"; el versionado real llega en la fase de negociación).
            $version = $quotation->currentVersion;
            $this->applyVersionData($version, $request, $data);
            $version->save();
            $this->syncItems($version, $request, $data);
            $version->recomputeTotals();
            $version->save();
        });

        return redirect()->route('quotations.show', $quotation)->with('status', 'Cotización actualizada.');
    }

    // ── Versionado (negociar es versionar) ─────────────────────────────────────
    /** Formulario de NUEVA versión, prellenado desde la versión en curso (para ajustar el monto). */
    public function newVersion(Quotation $quotation)
    {
        abort_unless($quotation->isVisibleTo(auth()->user()), 403);
        abort_unless(! $quotation->isAccepted(), 403, 'Una cotización aceptada no se renegocia.');
        $quotation->load('currentVersion.items');

        return view('quotations.new-version', [
            'quotation'   => $quotation,
            'version'     => $quotation->currentVersion,
            'departments' => Department::orderBy('name')->get(),
        ]);
    }

    /**
     * Crea una VERSIÓN nueva que referencia la anterior (`supersedes_id`); la anterior NO se
     * altera ni se borra. La cotización pasa a "en negociación". Si es PDF y no se sube uno
     * nuevo, se arrastra el de la versión previa (byte-intact, archivo inmutable compartido).
     */
    public function storeVersion(Request $request, Quotation $quotation)
    {
        abort_unless($quotation->isVisibleTo(auth()->user()), 403);
        abort_unless(! $quotation->isAccepted(), 403, 'Una cotización aceptada no se renegocia.');
        $data = $this->validated($request, false);

        DB::transaction(function () use ($request, $data, $quotation) {
            $prev   = $quotation->currentVersion;
            $nextNo = (int) $quotation->versions()->max('version_no') + 1;

            $version = new QuotationVersion([
                'quotation_id'  => $quotation->id,
                'version_no'    => $nextNo,
                'supersedes_id' => $prev?->id,
                'created_by_id' => $request->user()->id,
            ]);
            $this->applyVersionData($version, $request, $data);
            // PDF sin archivo nuevo → arrastra el de la versión previa (no se re-sube ni se toca).
            if ($version->source_kind === QuotationVersion::SOURCE_PDF && ! $request->hasFile('pdf') && $prev && $prev->isPdf()) {
                $version->pdf_path          = $prev->pdf_path;
                $version->pdf_original_name = $prev->pdf_original_name;
                $version->pdf_sha256        = $prev->pdf_sha256;
            }
            $version->save();
            $this->syncItems($version, $request, $data);
            $version->recomputeTotals();
            $version->save();

            $quotation->current_version_id = $version->id;
            if ($quotation->status === Quotation::STATUS_RECEIVED) {
                $quotation->status = Quotation::STATUS_NEGOTIATING;
            }
            $quotation->save();
        });

        return redirect()->route('quotations.show', $quotation)->with('status', 'Nueva versión registrada. La anterior quedó en el historial.');
    }

    /** Sirve el PDF de una versión TAL CUAL se subió (byte-intact); nunca se modifica. */
    public function versionPdf(Quotation $quotation, QuotationVersion $version)
    {
        abort_unless($quotation->isVisibleTo(auth()->user()), 403);
        abort_unless((int) $version->quotation_id === (int) $quotation->id, 404);
        abort_unless($version->pdf_path && \Illuminate\Support\Facades\Storage::disk('local')->exists($version->pdf_path), 404);

        return response(\Illuminate\Support\Facades\Storage::disk('local')->get($version->pdf_path), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . ($version->pdf_original_name ?: 'cotizacion.pdf') . '"',
        ]);
    }

    // ── Aceptación (Line Producer) ─────────────────────────────────────────────
    /** Pantalla de aceptación: resumen + pad de firma. Vencida advierte, no bloquea. */
    public function showAccept(Quotation $quotation)
    {
        abort_unless($quotation->isVisibleTo(auth()->user()), 403);
        abort_if($quotation->isAccepted(), 409, 'La cotización ya fue aceptada.');
        $quotation->load('currentVersion.items');

        return view('quotations.accept', [
            'quotation' => $quotation,
            'version'   => $quotation->currentVersion,
        ]);
    }

    /**
     * Acepta la cotización: sella (HasDigitalSignatures) con la autógrafa y genera la HOJA DE
     * ACEPTACIÓN (dompdf). NO toca el PDF subido (byte-intact). El enganche con payee / Infosheet /
     * anexo del sobre (PASO F) vive en {@see \App\Support\QuotationAcceptance}.
     */
    public function accept(Request $request, Quotation $quotation)
    {
        abort_unless($quotation->isVisibleTo($request->user()), 403);
        abort_if($quotation->isAccepted(), 409, 'La cotización ya fue aceptada.');
        $version = $quotation->currentVersion;
        abort_if(! $version, 422, 'La cotización no tiene contenido que aceptar.');

        $data = $request->validate([
            'signature_image' => 'required|string|min:100',
            'save_signature'  => 'nullable|boolean',
        ]);

        DB::transaction(function () use ($request, $data, $quotation, $version) {
            // PASO F PRIMERO: fija payee_id (en memoria) + provisiona externo-lite + satisface el
            // requisito COTIZACION. Se hace ANTES de sellar para que `payee_id` entre al hash.
            \App\Support\QuotationAcceptance::wire($quotation, $request->user());

            $quotation->accepted_by_user_id         = $request->user()->id;
            $quotation->accepted_at                 = now();
            $quotation->accepted_version_id         = $version->id;
            $quotation->accepted_doc_hash           = $version->contentHash();
            $quotation->acceptance_signature_image  = $data['signature_image'];
            $quotation->status                      = Quotation::STATUS_ACCEPTED;
            $quotation->save();

            // ⚠ refresh ANTES de sellar: alinea microsegundos/decimales con la BD para que el
            // hash recomputado desde fresh() case (landmine conocido del sellado).
            $quotation->refresh();
            $quotation->signDocument($request->user());

            if ($request->boolean('save_signature')) {
                $u = $request->user();
                $u->adopted_signature = $data['signature_image'];
                $u->save();
            }

            // Hoja de aceptación (render de datos YA sellados). El path se excluye del hash.
            $pdf  = \App\Support\QuotationAcceptanceSheet::pdf($quotation->fresh());
            $path = 'quotations/'.$quotation->id.'/aceptacion_'.$quotation->id.'.pdf';
            \Illuminate\Support\Facades\Storage::disk('local')->put($path, $pdf);
            $quotation->acceptance_sheet_path = $path;
            $quotation->save();
        });

        return redirect()->route('quotations.show', $quotation)->with('status', 'Cotización aceptada y sellada.');
    }

    /** Sirve la hoja de aceptación (dompdf) ya generada; visible para quien ve la cotización. */
    public function acceptanceSheet(Quotation $quotation)
    {
        abort_unless($quotation->isVisibleTo(auth()->user()), 403);
        abort_unless($quotation->acceptance_sheet_path && \Illuminate\Support\Facades\Storage::disk('local')->exists($quotation->acceptance_sheet_path), 404);

        return response(\Illuminate\Support\Facades\Storage::disk('local')->get($quotation->acceptance_sheet_path), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="aceptacion-cotizacion.pdf"',
        ]);
    }

    // ── SE SOLICITA (enlace firmado, sin login) ───────────────────────────────
    /** Página pública (firmada) donde el proveedor/crew llena su cotización. La firma es su llave. */
    public function publicShow(Request $request, Quotation $quotation)
    {
        abort_if($quotation->isAccepted(), 410, 'La cotización ya fue aceptada.');
        $quotation->load('currentVersion.items');

        return view('quotations.request', [
            'quotation' => $quotation,
            'version'   => $quotation->currentVersion,
            'postUrl'   => \Illuminate\Support\Facades\URL::temporarySignedRoute('quotations.request.submit', now()->addDays(14), ['quotation' => $quotation->id]),
        ]);
    }

    /** Recibe la cotización llena (firmado, sin login). Rellena el shell vacío o versiona. */
    public function publicSubmit(Request $request, Quotation $quotation)
    {
        abort_if($quotation->isAccepted(), 410, 'La cotización ya fue aceptada.');
        $data = $this->validated($request, false);

        DB::transaction(function () use ($request, $data, $quotation) {
            $quotation->emitter_name = $data['emitter_name'];
            if (! empty($data['emitter_email'])) {
                $quotation->emitter_email = $data['emitter_email'];
            }

            $prev      = $quotation->currentVersion;
            $reuseShell = $prev && $prev->isEmpty();
            $version = $reuseShell ? $prev : new QuotationVersion([
                'quotation_id'  => $quotation->id,
                'version_no'    => $prev ? ((int) $quotation->versions()->max('version_no') + 1) : 1,
                'supersedes_id' => ($prev && ! $reuseShell) ? $prev->id : null,
            ]);
            $this->applyVersionData($version, $request, $data);
            if ($version->source_kind === QuotationVersion::SOURCE_PDF && ! $request->hasFile('pdf') && $prev && $prev->isPdf() && ! $reuseShell) {
                $version->pdf_path          = $prev->pdf_path;
                $version->pdf_original_name = $prev->pdf_original_name;
                $version->pdf_sha256        = $prev->pdf_sha256;
            }
            $version->save();
            $this->syncItems($version, $request, $data);
            $version->recomputeTotals();
            $version->save();

            $quotation->current_version_id = $version->id;
            $quotation->status = Quotation::STATUS_RECEIVED;
            $quotation->save();
        });

        return view('quotations.request-thanks', ['name' => $quotation->emitter_name]);
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    /** Validación compartida. $isCreate exige el PDF cuando la fuente es 'pdf'. */
    private function validated(Request $request, bool $isCreate): array
    {
        $rules = [
            'emitter_name'     => 'required|string|max:191',
            'emitter_email'    => 'nullable|email|max:191',
            'department_id'    => 'nullable|integer|exists:departments,id',
            'location_name'    => 'nullable|string|max:191',
            'source_kind'      => 'required|in:pdf,items',
            'quotation_number' => 'nullable|string|max:60',
            'issued_at'        => 'nullable|date',
            'valid_until'      => 'nullable|date',
            'payment_terms'    => 'nullable|string|max:500',
            'bank_details'     => 'nullable|string|max:500',
            'iva_included'     => 'nullable|boolean',
            'iva_rate'         => 'nullable|numeric|min:0|max:100',
            'change_note'      => 'nullable|string|max:500',
            // Partidas (source=items)
            'items'                 => 'nullable|array',
            'items.*.description'   => 'nullable|string|max:255',
            'items.*.detail'        => 'nullable|string|max:2000',
            'items.*.quantity'      => 'nullable|numeric|min:0',
            'items.*.days'          => 'nullable|numeric|min:0',
            'items.*.unit_price'    => 'nullable|numeric|min:0',
            // Importes capturados a mano (source=pdf)
            'subtotal'   => 'nullable|numeric|min:0',
            'iva_amount' => 'nullable|numeric|min:0',
            'total'      => 'nullable|numeric|min:0',
        ];
        if ($request->input('source_kind') === 'pdf') {
            $rules['pdf'] = ($isCreate ? 'required' : 'nullable') . '|file|mimes:pdf|max:20480';
        }

        return $request->validate($rules);
    }

    /** Vuelca meta + PDF (byte-intact) o marca la fuente de partidas en la versión. */
    private function applyVersionData(QuotationVersion $version, Request $request, array $data): void
    {
        $version->source_kind      = $data['source_kind'];
        $version->quotation_number = $data['quotation_number'] ?? null;
        $version->issued_at        = $data['issued_at'] ?? null;
        $version->valid_until      = $data['valid_until'] ?? null;
        $version->payment_terms    = $data['payment_terms'] ?? null;
        $version->bank_details     = $data['bank_details'] ?? null;
        $version->iva_included     = $request->boolean('iva_included');
        $version->iva_rate         = $data['iva_rate'] ?? 16;
        $version->change_note      = $data['change_note'] ?? null;

        if ($data['source_kind'] === QuotationVersion::SOURCE_PDF && $request->hasFile('pdf')) {
            $file = $request->file('pdf');
            // BYTE-INTACT: se guarda tal cual llegó (sin normalizar) y se hashea su contenido.
            $path = $file->storeAs(
                'quotations/' . $version->quotation_id,
                uniqid('quote_') . '.pdf',
                'local'
            );
            $version->pdf_path          = $path;
            $version->pdf_original_name = $file->getClientOriginalName();
            $version->pdf_sha256        = hash('sha256', \Illuminate\Support\Facades\Storage::disk('local')->get($path));
        }

        if ($data['source_kind'] === QuotationVersion::SOURCE_PDF) {
            // El total (y opcionalmente subtotal/IVA) se captura a mano para la hoja de aceptación.
            $version->subtotal   = $data['subtotal'] ?? 0;
            $version->iva_amount = $data['iva_amount'] ?? 0;
            $version->total      = $data['total'] ?? 0;
        }
    }

    /** Reemplaza las partidas de la versión (solo cuando la fuente es 'items'). */
    private function syncItems(QuotationVersion $version, Request $request, array $data): void
    {
        if ($data['source_kind'] !== QuotationVersion::SOURCE_ITEMS) {
            $version->items()->delete();
            return;
        }
        $version->items()->delete();
        foreach ((array) ($data['items'] ?? []) as $i => $row) {
            $desc = trim((string) ($row['description'] ?? ''));
            if ($desc === '') {
                continue; // fila vacía
            }
            $version->items()->create([
                'sort_order'  => $i,
                'description' => $desc,
                'detail'      => $row['detail'] ?? null,
                'quantity'    => $row['quantity'] ?? 1,
                'days'        => (isset($row['days']) && $row['days'] !== '' && $row['days'] !== null) ? $row['days'] : null,
                'unit_price'  => $row['unit_price'] ?? 0,
                // line_total lo recalcula el modelo al guardar.
            ]);
        }
    }
}
