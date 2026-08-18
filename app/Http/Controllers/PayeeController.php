<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\ExternalAuthorization;
use App\Models\Payee;
use App\Models\PayeeDocumentDownload;
use App\Support\CurrentProduction;
use App\Support\PayeePackage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * PASO 4 · VISIBILIDAD de "quien cobra". SOLO LECTURA (la captura vive en {@see IntakeController}):
 *  - index  → listado ACOTADO por {@see Payee::scopeVisibleTo()} ("quien contrata es quien ve").
 *  - show   → ficha: contratos + documentos, con enlaces al serve GATEADO.
 *  - document → sirve un PDF privado (RFC/CLABE/domicilio) SOLO a quien tiene alcance; nunca
 *    público, nunca desde /storage, nunca por URL adivinable.
 *
 * Gate de RUTA: `permission:payees.view` (puerta del módulo). El SCOPE fino lo pone el modelo/
 * Policy: tener el permiso no basta, hay que estar en el alcance del payee.
 */
class PayeeController extends Controller
{
    /** Tope de documentos por descarga masiva (evita zips gigantes; el zip AVISA si se topa). */
    private const BULK_MAX_DOCS = 800;

    public function index(Request $request)
    {
        $viewer = $request->user();
        $q      = trim((string) $request->query('q', ''));
        $deptId = (int) $request->query('dept', 0);

        // Departamentos presentes en el alcance del viewer (nunca opciones vacías/ajenas).
        $departments = $this->visibleDepartments($viewer);
        if ($deptId > 0 && ! $departments->pluck('id')->contains($deptId)) {
            $deptId = 0; // pidieron un depto fuera de su alcance → se ignora
        }

        $payees = Payee::query()->active()
            ->visibleTo($viewer)
            ->when($q !== '', function ($w) use ($q) {
                $like = '%' . $q . '%';
                $w->where(function ($x) use ($like) {
                    $x->where('name', 'like', $like)->orWhere('rfc', 'like', $like);
                });
            })
            ->when($deptId > 0, fn ($w) => $w->inDepartment($deptId))
            ->withCount(['contracts', 'documents'])
            ->orderBy('name')
            ->paginate(30)
            ->appends(array_filter(['q' => $q, 'dept' => $deptId ?: null], fn ($v) => $v !== null && $v !== ''));

        return view('payee.index', compact('payees', 'q', 'departments', 'deptId'));
    }

    /**
     * Departamentos que aparecen en el alcance visible del viewer, para el filtro del listado.
     * Deriva por los DOS ejes (crew ligado + contratante), acotado a la producción actual si la
     * hay. Devuelve una colección de {id, name} ordenada; vacía si el viewer no ve nada.
     */
    private function visibleDepartments($viewer)
    {
        $visibleIds = Payee::query()->active()->visibleTo($viewer)->pluck('id');
        if ($visibleIds->isEmpty()) {
            return collect();
        }
        $prodId = CurrentProduction::id();

        $byCrew = DB::table('production_user')
            ->join('payees', 'payees.user_id', '=', 'production_user.user_id')
            ->whereIn('payees.id', $visibleIds)
            ->when($prodId, fn ($w) => $w->where('production_user.production_id', $prodId))
            ->pluck('production_user.department_id');

        $byContractor = DB::table('payee_contracts')
            ->join('production_user', 'production_user.user_id', '=', 'payee_contracts.contracted_by_user_id')
            ->whereIn('payee_contracts.payee_id', $visibleIds)
            ->when($prodId, fn ($w) => $w->where('production_user.production_id', $prodId))
            ->pluck('production_user.department_id');

        $ids = $byCrew->merge($byContractor)->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return collect();
        }

        return Department::whereIn('id', $ids)->orderBy('name')->get(['id', 'name']);
    }

    public function show(Request $request, Payee $payee)
    {
        $this->authorize('view', $payee);

        $payee->load([
            'user',
            'contracts.contractedBy', 'contracts.fiscalRegime',
            'fiscalRegimes',
            'documents.documentType',
            'beneficiaries', 'declaredEquipment',
            'ambulanceProvider', // Paso 5: si esta identidad es un proveedor de ambulancias
        ]);
        $cutDay = PayeePackage::cutDay(optional(CurrentProduction::get())->id);

        return view('payee.show', compact('payee', 'cutDay'));
    }

    /**
     * Serve GATEADO de un documento privado. La puerta es la MISMA visibilidad del payee
     * (PayeePolicy). Un usuario sin alcance recibe 403 (por la Policy), no el archivo.
     */
    public function document(Request $request, Payee $payee, ExternalAuthorization $doc)
    {
        $this->authorize('view', $payee);

        // El documento debe COLGAR de este payee y estar activo (no confiar en el id suelto).
        abort_unless(
            $doc->holder_type === $payee->getMorphClass()
                && (int) $doc->holder_id === (int) $payee->id
                && $doc->is_active,
            404
        );

        $path = (string) $doc->photo_path;
        abort_unless($path !== '' && Storage::disk('local')->exists($path), 404);

        // BITÁCORA (decisión del owner): registra QUIÉN descargó QUÉ y CUÁNDO. Append-only y
        // NUNCA bloquea — si el rastro falla, el documento igual se entrega (no es un candado).
        try {
            $actor = $request->user();
            PayeeDocumentDownload::create([
                'payee_id'       => $payee->id,
                'document_id'    => $doc->id,
                'document_label' => optional($doc->documentType)->name ?: $doc->document_type,
                'user_id'        => optional($actor)->id,
                'user_name'      => $actor ? trim($actor->name . ' ' . $actor->lname) : null,
                'ip'             => $request->ip(),
                'downloaded_at'  => now(),
            ]);
        } catch (\Throwable $e) {
            // Silencioso a propósito: el rastro no puede impedir el acceso legítimo.
        }

        // Nombre legible al descargar, SIN PII (el del disco es opaco: doc_<uniqid>.pdf).
        $nice = 'documento-' . (optional($doc->documentType)->code ?: 'payee') . '-' . $payee->id . '.pdf';

        return Storage::disk('local')->response($path, $nice, ['Content-Type' => 'application/pdf'], 'inline');
    }

    /**
     * Descarga en ZIP TODOS los documentos activos de una persona. Puerta = la MISMA visibilidad
     * del payee (Policy). Cada documento incluido se registra en la bitácora, igual que el serve
     * individual. Para bajar por DEPARTAMENTO o GLOBAL, ver {@see downloadBulk}.
     */
    public function downloadDocuments(Request $request, Payee $payee)
    {
        $this->authorize('view', $payee);

        $disk = Storage::disk('local');
        $docs = $payee->documents()
            ->where('is_active', 1)
            ->whereNotNull('photo_path')
            ->with(['documentType', 'paymentPeriod'])
            ->get()
            ->filter(fn ($d) => (string) $d->photo_path !== '' && $disk->exists($d->photo_path))
            ->values();

        if ($docs->isEmpty()) {
            return back()->with('error', __('Esta persona no tiene documentos para descargar.'));
        }

        $tmp = tempnam(sys_get_temp_dir(), 'payeedocs_');
        $zip = new \ZipArchive();
        if ($zip->open($tmp, \ZipArchive::OVERWRITE) !== true) {
            @unlink($tmp);
            return back()->with('error', __('No se pudo preparar la descarga.'));
        }

        $used  = [];
        $actor = $request->user();
        foreach ($docs as $doc) {
            $zip->addFromString($this->zipEntryName($doc, $used), (string) $disk->get($doc->photo_path));

            // BITÁCORA append-only, best-effort: el rastro nunca bloquea la descarga legítima.
            try {
                PayeeDocumentDownload::create([
                    'payee_id'       => $payee->id,
                    'document_id'    => $doc->id,
                    'document_label' => optional($doc->documentType)->name ?: $doc->document_type,
                    'user_id'        => optional($actor)->id,
                    'user_name'      => $actor ? trim($actor->name . ' ' . $actor->lname) : null,
                    'ip'             => $request->ip(),
                    'downloaded_at'  => now(),
                ]);
            } catch (\Throwable $e) {
                // silencioso a propósito
            }
        }
        $zip->close();

        // Nombre SIN PII (el zip va gateado; adentro las entradas son por tipo de documento).
        return response()->download($tmp, 'documentos-payee-' . $payee->id . '.zip', [
            'Content-Type' => 'application/zip',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Descarga MASIVA (por DEPARTAMENTO o GLOBAL) de los documentos de varios payees en un solo
     * zip, agrupados por carpeta {departamento}/{persona}/{tipo}. Respeta EXACTAMENTE el mismo
     * alcance del listado (`visibleTo` + los filtros `q`/`dept` de la vista), así que un HOD solo
     * empaqueta lo de SU departamento — nunca es una fuga: es el mismo PII que ya puede ver uno por
     * uno, sólo que junto. Cada documento incluido queda en la bitácora. Tope de seguridad
     * {@see BULK_MAX_DOCS}: si se topa, el zip incluye un _AVISO.txt (nunca truncado silencioso).
     */
    public function downloadBulk(Request $request)
    {
        $viewer = $request->user();
        $q      = trim((string) $request->query('q', ''));
        $deptId = (int) $request->query('dept', 0);

        $disk = Storage::disk('local');

        $payees = Payee::query()->active()
            ->visibleTo($viewer)
            ->when($q !== '', function ($w) use ($q) {
                $like = '%' . $q . '%';
                $w->where(function ($x) use ($like) {
                    $x->where('name', 'like', $like)->orWhere('rfc', 'like', $like);
                });
            })
            ->when($deptId > 0, fn ($w) => $w->inDepartment($deptId))
            ->with(['documents' => function ($d) {
                $d->where('is_active', 1)->whereNotNull('photo_path')->with(['documentType', 'paymentPeriod']);
            }])
            ->orderBy('name')
            ->get();

        // Aplana a pares (payee, doc) SOLO con archivo real en disco.
        $pairs = [];
        foreach ($payees as $payee) {
            foreach ($payee->documents as $doc) {
                if ((string) $doc->photo_path !== '' && $disk->exists($doc->photo_path)) {
                    $pairs[] = [$payee, $doc];
                }
            }
        }
        if (empty($pairs)) {
            return back()->with('error', __('No hay documentos para descargar en este filtro.'));
        }

        $tmp = tempnam(sys_get_temp_dir(), 'payeesbulk_');
        $zip = new \ZipArchive();
        if ($zip->open($tmp, \ZipArchive::OVERWRITE) !== true) {
            @unlink($tmp);
            return back()->with('error', __('No se pudo preparar la descarga.'));
        }

        $actor  = $viewer;
        $used   = [];      // dedupe de nombres POR carpeta de persona
        $count  = 0;
        $capped = false;
        foreach ($pairs as [$payee, $doc]) {
            if ($count >= self::BULK_MAX_DOCS) { $capped = true; break; }

            $folder = $this->payeeFolderPath($payee);
            $used[$folder] ??= [];
            $zip->addFromString($folder . '/' . $this->zipEntryName($doc, $used[$folder]), (string) $disk->get($doc->photo_path));

            // BITÁCORA append-only, best-effort: el rastro nunca bloquea la descarga legítima.
            try {
                PayeeDocumentDownload::create([
                    'payee_id'       => $payee->id,
                    'document_id'    => $doc->id,
                    'document_label' => optional($doc->documentType)->name ?: $doc->document_type,
                    'user_id'        => optional($actor)->id,
                    'user_name'      => $actor ? trim($actor->name . ' ' . $actor->lname) : null,
                    'ip'             => $request->ip(),
                    'downloaded_at'  => now(),
                ]);
            } catch (\Throwable $e) {
                // silencioso a propósito
            }
            $count++;
        }

        // NO al truncado silencioso: si topamos el límite, el propio zip lo dice.
        if ($capped) {
            $zip->addFromString('_AVISO.txt',
                "Se incluyeron los primeros {$count} documentos (límite de esta descarga).\n" .
                "Hay más en el filtro: acota por departamento para bajar el resto por partes.\n");
        }
        $zip->close();

        $tag = $deptId > 0 ? ('dept-' . $deptId) : 'global';
        return response()->download($tmp, 'documentos-payees-' . $tag . '.zip', [
            'Content-Type' => 'application/zip',
        ])->deleteFileAfterSend(true);
    }

    /** Carpeta de la persona dentro del zip masivo: {deptSlug}/{nombre} ({id}). El id garantiza unicidad. */
    private function payeeFolderPath(Payee $payee): string
    {
        $name = trim((string) $payee->name) !== '' ? $payee->name : ('payee-' . $payee->id);

        return $payee->departmentSlug() . '/' . $this->safeSegment($name) . ' (' . $payee->id . ')';
    }

    /** Segmento seguro para carpeta de zip: sin separadores de ruta ni saltos de línea. */
    private function safeSegment(string $s): string
    {
        return trim(preg_replace('/\s+/', ' ', preg_replace('#[/\\\\:*?"<>|\r\n]+#', '-', $s)));
    }

    /**
     * Nombre de entrada legible y ÚNICO dentro del zip: {tipo}[_{periodo|fecha}].pdf. El
     * discriminador (periodo de pago o fecha de emisión) separa los documentos que se re-piden
     * cada periodo (CSF/32-D); ante colisión se numera.
     */
    private function zipEntryName(ExternalAuthorization $doc, array &$used): string
    {
        $type = \Illuminate\Support\Str::slug(
            optional($doc->documentType)->code ?: (optional($doc->documentType)->name ?: ($doc->document_type ?: 'documento'))
        );

        $period = $doc->paymentPeriod;
        $disc = $period
            ? ($period->label ?: ($period->opens_on ? \Illuminate\Support\Carbon::parse($period->opens_on)->format('Y-m-d') : null))
            : null;
        if (! $disc && $doc->issued_at) {
            $disc = \Illuminate\Support\Carbon::parse($doc->issued_at)->format('Y-m-d');
        }

        $base = $type . ($disc ? '_' . \Illuminate\Support\Str::slug((string) $disc) : '');
        $name = $base . '.pdf';
        $n = 2;
        while (isset($used[$name])) {
            $name = $base . '-' . $n . '.pdf';
            $n++;
        }
        $used[$name] = true;

        return $name;
    }
}
