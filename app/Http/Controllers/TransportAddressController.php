<?php

namespace App\Http\Controllers;

use App\Models\TransportAddress;
use App\Models\User;
use App\Support\CurrentProduction;
use App\Support\TransportAccess;
use Illuminate\Http\Request;

/**
 * Transportación · DIRECCIONES PRIVADAS + allowlist (§3 · Capa 4).
 *
 * Transpo (canFull) presetea direcciones por adelantado y decide, con una ALLOWLIST explícita
 * (`transport_address_viewers`), quién ve la CALLE REAL. Es un señalamiento simple, sin jerarquía.
 *
 * Dos concesiones INDEPENDIENTES para ver la calle real ({@see TransportAddress::realAddressVisibleTo}):
 *   - ALLOWLIST: transpo agrega usuarios que verán la calle en la vista de la orden.
 *   - ASIGNACIÓN: el driver de una corrida ve la calle de ESA corrida aunque NO esté en la allowlist
 *     (lo resuelve la pantalla del driver pasando isRunDriver=true).
 * No se pisan: se combinan con OR; ninguna depende de la otra. Quien no cae en ninguna ve 'CASA'.
 */
class TransportAddressController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(TransportAccess::canFull($request->user()), 403);

        $pid = CurrentProduction::id();

        $addresses = TransportAddress::where('production_id', $pid)
            ->where('is_active', 1)
            ->with('viewers')
            ->orderBy('label')
            ->get();

        // Picker de la allowlist: miembros de la producción vigente.
        $prod = CurrentProduction::get();
        $people = $prod
            ? $prod->members()->orderBy('name')->get()->map(fn (User $u) => ['id' => $u->id, 'name' => User::displayName($u)])->values()
            : collect();

        return view('transport.addresses.index', ['addresses' => $addresses, 'people' => $people]);
    }

    public function store(Request $request)
    {
        abort_unless(TransportAccess::canFull($request->user()), 403);

        $data = $this->validateAddress($request);

        TransportAddress::create([
            'production_id' => CurrentProduction::id(),
            'label'         => $data['label'],
            'address'       => $data['address'] ?? null,
            'public_label'  => $data['public_label'] ?? null,
            'is_private'    => $request->boolean('is_private', true),
            'created_by_id' => $request->user()->id,
            'is_active'     => 1,
        ]);

        return back()->with('ok', __('Dirección guardada.'));
    }

    public function update(Request $request, TransportAddress $address)
    {
        $this->authorizeAddress($request, $address);

        $data = $this->validateAddress($request);
        $address->fill([
            'label'        => $data['label'],
            'address'      => $data['address'] ?? null,
            'public_label' => $data['public_label'] ?? null,
            'is_private'   => $request->boolean('is_private', true),
        ]);
        $address->save();

        return back()->with('ok', __('Dirección actualizada.'));
    }

    public function destroy(Request $request, TransportAddress $address)
    {
        $this->authorizeAddress($request, $address);

        $address->is_active = 0;
        $address->save();

        return back()->with('ok', __('Dirección dada de baja.'));
    }

    // ── Allowlist (quién ve la calle real) ───────────────────────────────────
    public function addViewer(Request $request, TransportAddress $address)
    {
        $this->authorizeAddress($request, $address);

        $data = $request->validate(['user_id' => 'required|integer|exists:users,id']);
        $address->viewers()->syncWithoutDetaching([$data['user_id']]);

        return back()->with('ok', __('Agregado a la lista.'));
    }

    public function removeViewer(Request $request, TransportAddress $address, User $user)
    {
        $this->authorizeAddress($request, $address);

        $address->viewers()->detach($user->id);

        return back()->with('ok', __('Quitado de la lista.'));
    }

    // ── Helpers ──────────────────────────────────────────────────────────────
    private function authorizeAddress(Request $request, TransportAddress $address): void
    {
        abort_unless(TransportAccess::canFull($request->user()), 403);
        // Aislamiento por producción: no tocar direcciones de otra producción.
        abort_unless((int) $address->production_id === (int) CurrentProduction::id(), 404);
    }

    private function validateAddress(Request $request): array
    {
        return $request->validate([
            'label'        => 'required|string|max:120',
            'address'      => 'nullable|string|max:255',
            'public_label' => 'nullable|string|max:60',
            'is_private'   => 'nullable|boolean',
        ]);
    }
}
