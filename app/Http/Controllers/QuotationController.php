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

        return view('quotations.show', ['quotation' => $quotation]);
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
