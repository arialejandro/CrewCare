<?php

namespace App\Http\Controllers;

use App\Models\ContractClause;
use App\Models\PayeeContract;
use App\Support\CurrentProduction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * EL CONTRATO · PASO B — BIBLIOTECA DE CLAUSULADOS. La productora sube su clausulado (PDF), que
 * se conserva BYTE-INTACT. Gestión gateada por `settings.manage` (config de la productora, como
 * Marca) — SIN permiso nuevo. Subir uno nuevo NO altera los contratos ya emitidos (versionado por
 * `root_id`); se puede desactivar sin borrar.
 */
class ContractClauseController extends Controller
{
    public function index(Request $request)
    {
        $prodId = CurrentProduction::id();

        // Agrupado por familia (root); dentro, la versión más alta arriba.
        $clauses = ContractClause::query()
            ->when($prodId, fn ($q) => $q->forProduction($prodId))
            ->orderByDesc('version')->orderByDesc('id')
            ->get()
            ->groupBy(fn ($c) => $c->rootId());

        return view('contracts.clauses.index', [
            'families'  => $clauses,
            'subtypes'  => ContractClause::subtypes(),
            'languages' => ContractClause::languages(),
            'prodId'    => $prodId,
        ]);
    }

    public function store(Request $request)
    {
        $prodId = CurrentProduction::id();
        abort_unless($prodId, 409, 'No hay una producción activa.');

        $subtypeKeys = array_keys(ContractClause::subtypes());
        $data = $request->validate([
            'name'        => 'nullable|string|max:191',
            'file'        => 'required|file|mimes:pdf|max:20480',
            'applies_to'  => 'required|array|min:1',
            'applies_to.*' => 'in:' . implode(',', $subtypeKeys),
            'language'    => 'required|in:' . implode(',', array_keys(ContractClause::languages())),
            'replaces_id' => 'nullable|integer|exists:contract_clauses,id',
        ]);

        $file = $request->file('file');
        $hash = hash_file('sha256', $file->getRealPath());           // byte-intact: se conserva tal cual
        $path = $file->storeAs('contracts/clauses/' . $prodId, uniqid('clause_') . '.pdf', 'local');

        // ¿Nueva VERSIÓN de una familia, o clausulado NUEVO?
        $replaces = ($data['replaces_id'] ?? null) ? ContractClause::find($data['replaces_id']) : null;
        if ($replaces) {
            $root    = $replaces->rootId();
            $version = (int) ContractClause::forProduction($prodId)
                ->where(fn ($q) => $q->where('id', $root)->orWhere('root_id', $root))
                ->max('version') + 1;
            $name = $replaces->name;   // la familia conserva su nombre
        } else {
            $root = null;
            $version = 1;
            $name = trim((string) ($data['name'] ?? '')) ?: 'Clausulado';
        }

        ContractClause::create([
            'production_id'     => $prodId,
            'name'              => $name,
            'applies_to'        => array_values($data['applies_to']),
            'language'          => $data['language'],
            'file_path'         => $path,
            'original_filename' => $file->getClientOriginalName(),
            'file_hash'         => $hash,
            'version'           => $version,
            'root_id'           => $root,
            'is_active'         => 1,
            'uploaded_by_id'    => optional($request->user())->id,
        ]);

        return back()->with('status', __('Clausulado subido.'));
    }

    /** Desactivar/reactivar sin borrar: los contratos que lo usaron siguen apuntando a su fila. */
    public function toggle(Request $request, ContractClause $clause)
    {
        $clause->update(['is_active' => ! $clause->is_active]);
        return back()->with('status', __('Clausulado actualizado.'));
    }

    /** Descarga BYTE-INTACT del clausulado tal como se subió. */
    public function download(Request $request, ContractClause $clause)
    {
        abort_unless($clause->file_path && Storage::disk('local')->exists($clause->file_path), 404);
        $nice = ($clause->original_filename ?: ('clausulado-' . $clause->id)) ;
        return Storage::disk('local')->response($clause->file_path, $nice, ['Content-Type' => 'application/pdf'], 'inline');
    }
}
