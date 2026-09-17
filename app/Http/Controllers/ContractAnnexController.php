<?php

namespace App\Http\Controllers;

use App\Models\ContractAnnex;
use App\Support\CurrentProduction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * EL CONTRATO · PASO C — BIBLIOTECA DE ANEXOS. La productora sube sus anexos (PDF byte-intact,
 * nombre libre). Gateado por `settings.manage` (config de la productora) — SIN permiso nuevo.
 */
class ContractAnnexController extends Controller
{
    public function index(Request $request)
    {
        $prodId  = CurrentProduction::id();
        $annexes = ContractAnnex::query()
            ->when($prodId, fn ($q) => $q->forProduction($prodId))
            ->orderBy('sort_order')->orderByDesc('id')->get();

        return view('contracts.annexes.index', compact('annexes', 'prodId'));
    }

    public function store(Request $request)
    {
        $prodId = CurrentProduction::id();
        abort_unless($prodId, 409, 'No hay una producción activa.');

        $request->validate([
            'name' => 'required|string|max:191',
            'file' => 'required|file|mimes:pdf|max:20480',
        ]);

        $file = $request->file('file');
        $hash = hash_file('sha256', $file->getRealPath());
        $path = $file->storeAs('contracts/annexes/' . $prodId, uniqid('annex_') . '.pdf', 'local');

        ContractAnnex::create([
            'production_id'     => $prodId,
            'name'              => $request->input('name'),
            'file_path'         => $path,
            'original_filename' => $file->getClientOriginalName(),
            'file_hash'         => $hash,
            'is_active'         => 1,
            'uploaded_by_id'    => optional($request->user())->id,
        ]);

        return back()->with('status', __('Anexo subido.'));
    }

    public function toggle(Request $request, ContractAnnex $annex)
    {
        $annex->update(['is_active' => ! $annex->is_active]);
        return back()->with('status', __('Anexo actualizado.'));
    }

    public function download(Request $request, ContractAnnex $annex)
    {
        abort_unless($annex->file_path && Storage::disk('local')->exists($annex->file_path), 404);
        $nice = $annex->original_filename ?: ('anexo-' . $annex->id);
        return Storage::disk('local')->response($annex->file_path, $nice, ['Content-Type' => 'application/pdf'], 'inline');
    }
}
