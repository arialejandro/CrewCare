<?php

namespace App\Http\Controllers;

use App\Models\ExternalAuthorization;
use App\Models\Payee;
use App\Models\PayeeDocumentDownload;
use App\Support\CurrentProduction;
use App\Support\PayeePackage;
use Illuminate\Http\Request;
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
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $payees = Payee::query()->active()
            ->visibleTo($request->user())
            ->when($q !== '', function ($w) use ($q) {
                $like = '%' . $q . '%';
                $w->where(function ($x) use ($like) {
                    $x->where('name', 'like', $like)->orWhere('rfc', 'like', $like);
                });
            })
            ->withCount(['contracts', 'documents'])
            ->orderBy('name')
            ->paginate(30)
            ->appends(['q' => $q]);

        return view('payee.index', compact('payees', 'q'));
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
}
