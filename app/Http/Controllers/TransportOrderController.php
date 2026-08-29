<?php

namespace App\Http\Controllers;

use App\Models\CallPlace;
use App\Models\ScoutingReport;
use App\Models\TransportAddress;
use App\Models\TransportEquipment;
use App\Models\TransportOrder;
use App\Models\TransportOrderRun;
use App\Models\TransportParty;
use App\Models\TransportPickupPoint;
use App\Models\TransportRunOccupant;
use App\Models\Vehicle;
use App\Support\CurrentProduction;
use App\Support\DayRosterBuilder;
use App\Support\TransportAccess;
use App\Support\TransportCrew;
use App\Support\TransportOrderSnapshot;
use App\Support\TransportPickupDeriver;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Transportación · Bloque 2 (§1-§6) — ORDEN DE TRANSPORTACIÓN (captura).
 *
 * La orden se CONGELA, NO se sella ni firma, y NO entra al verificador público. Autorización
 * HÍBRIDA (misma que el Bloque 1, {@see TransportAccess}): `canFull` construye/edita; `canLite`
 * (producción) sólo consulta. Cada acción hace su propio abort_unless. Una orden CONGELADA es
 * inmutable: sólo se edita un borrador (draft).
 */
class TransportOrderController extends Controller
{
    // ── Listado por día ──────────────────────────────────────────────────────
    public function index(Request $request)
    {
        abort_unless(TransportAccess::canLite($request->user()), 403);

        $pid = CurrentProduction::id();

        $orders = TransportOrder::query()
            ->where('production_id', $pid)
            ->where('is_active', 1)
            ->orderByDesc('order_date')
            ->orderByDesc('version')
            ->get()
            ->groupBy(fn (TransportOrder $o) => optional($o->order_date)->toDateString());

        return view('transport.orders.index', [
            'ordersByDate' => $orders,
            'canFull'      => TransportAccess::canFull($request->user()),
            'today'        => now()->toDateString(),
        ]);
    }

    // ── Crear (o retomar) el borrador de un día ──────────────────────────────
    public function create(Request $request)
    {
        abort_unless(TransportAccess::canFull($request->user()), 403);

        $data = $request->validate(['order_date' => 'required|date']);
        $pid  = CurrentProduction::id();
        $date = Carbon::parse($data['order_date'])->toDateString();

        // 1) Borrador abierto ese día → retomarlo (no dupliques borradores).
        $draft = TransportOrder::where('production_id', $pid)
            ->whereDate('order_date', $date)
            ->where('status', TransportOrder::STATUS_DRAFT)
            ->where('is_active', 1)
            ->orderByDesc('version')
            ->first();
        if ($draft) {
            return redirect()->route('transport.order.show', $draft);
        }

        // 2) Ya hay una versión CONGELADA ese día → emitir una NUEVA versión clonándola
        //    (conserva los run_key para que el diff empareje por corrida, no por fila).
        $latestFrozen = TransportOrder::latestFrozenFor($pid, $date);
        if ($latestFrozen) {
            $draft = $this->cloneToNewDraft($latestFrozen, $request->user());
            return redirect()->route('transport.order.show', $draft)
                ->with('ok', 'Nueva versión en borrador a partir de v' . $latestFrozen->version . '.');
        }

        // 3) Primera orden del día → se PRECARGA desde el conjunto efectivo (base + marcados),
        //    agrupado por vehículo (Fase 3). Snapshot: sin vínculo a la asignación. Propone, no obliga.
        $draft = TransportOrder::create([
            'production_id' => $pid,
            'order_date'    => $date,
            'version'       => TransportOrder::nextVersionFor($pid, $date),
            'status'        => TransportOrder::STATUS_DRAFT,
            'created_by_id' => $request->user()->id,
        ]);

        $made = \App\Support\TransportPreload::into($draft);

        return redirect()->route('transport.order.show', $draft)
            ->with('ok', $made > 0 ? ($made . ' corrida(s) precargada(s) desde el marcado — edítalas o bórralas.') : null);
    }

    // ── Alta INLINE de vehículo (day player que llega con su van) ─────────────
    /**
     * Crea (o REÚSA) un vehículo sin salir del editor de la orden. Busca por PLACA primero —llave
     * natural, dedup FUERTE—: si ya existe una unidad con esa placa, la devuelve sin duplicar. Si no,
     * nace mínima y ACTIVA: sin tipo, sin verificación, sin asignación fija, como ya lo permite
     * {@see VehicleController::storeVehicle}. La verificación es acto SEPARADO y no bloquea. Gateado
     * por canFull. Responde JSON {id, name, search, driver, reused} para el typeahead.
     */
    public function quickStoreVehicle(Request $request)
    {
        abort_unless(TransportAccess::canFull($request->user()), 403);

        $data  = $request->validate(['plate' => 'required|string|max:40']);
        $plate = trim($data['plate']);
        $norm  = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $plate));

        // Dedup por placa normalizada (ignora espacios/guiones/puntos): la placa es la llave natural.
        $existing = $norm === '' ? null : Vehicle::whereRaw(
            "UPPER(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(plate,''),' ',''),'-',''),'.',''),'_','')) = ?",
            [$norm]
        )->orderByDesc('is_active')->orderBy('id')->first();

        $vehicle = $existing ?: Vehicle::create([
            'plate'         => $plate,
            'owner_kind'    => Vehicle::OWNER_OTHER,   // sin exigir proveedor/persona
            'attr_values'   => \App\Support\VehicleChecklist::normalizeAttributes([]),
            'is_active'     => 1,
            'created_by_id' => $request->user()->id,
        ]);

        return response()->json([
            'id'     => $vehicle->id,
            'name'   => $this->vehicleQuickLabel($vehicle),
            'search' => (string) $vehicle->plate,
            'driver' => $vehicle->driver_user_id,
            'reused' => (bool) $existing,
        ]);
    }

    /** Rótulo del vehículo para el typeahead: "Marca Modelo — PLACA" (o "Vehículo #id"). */
    private function vehicleQuickLabel(Vehicle $v): string
    {
        $base = trim(($v->make ?: '') . ' ' . ($v->model ?: '')) ?: ('Vehículo #' . $v->id);
        return $v->plate ? ($base . ' — ' . $v->plate) : $base;
    }

    // ── Congelar / emitir versión ────────────────────────────────────────────
    public function freeze(Request $request, TransportOrder $order)
    {
        $this->authorizeEdit($request, $order);

        if ($order->runs()->count() === 0 && trim((string) $order->notes_general) === '') {
            return back()->withErrors(['freeze' => 'La orden está vacía: agrega al menos una corrida o una nota antes de emitir.']);
        }

        // Congela el documento resuelto (etiquetas materializadas) + la leyenda derivada.
        $snapshot = TransportOrderSnapshot::build($order);
        $order->frozen_snapshot = $snapshot;
        $order->legend          = $snapshot['legend'] ?? null;
        $order->status          = TransportOrder::STATUS_FROZEN;
        $order->frozen_at       = now();
        $order->frozen_by_id    = $request->user()->id;
        $order->save();

        // Fase 5: al CERRAR, el pick up refinado vuelve al back (y N/A a quien no lleva).
        $this->syncCloseToBack($order);

        return redirect()->route('transport.order.show', $order)
            ->with('ok', 'Orden emitida: v' . $order->version . ' congelada.');
    }

    // ── Fase 5 · la vuelta al back + propuesta + contador ─────────────────────
    /** Siembra el pick up de una corrida en el back. Best-effort: no bloquea la edición si algo falla. */
    private function syncRunToBack(TransportOrder $order, TransportOrderRun $run): void
    {
        try {
            \App\Support\TransportBackSync::seedRun($order, $run);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** Siembra todas las corridas + N/A al cerrar. Best-effort. */
    private function syncCloseToBack(TransportOrder $order): void
    {
        try {
            \App\Support\TransportBackSync::seedClose($order);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * PROPUESTA (§2): transpo ACEPTA a los efectivos marcados que aún no estaban en la orden. Crea sus
     * corridas agrupadas por vehículo (reusa la precarga). No se agregan corridas solas: es un acto
     * explícito de transpo.
     */
    public function acceptProposal(Request $request, TransportOrder $order)
    {
        $this->authorizeEdit($request, $order);

        $pending = \App\Support\TransportPreload::proposal($order)['pending_ids'];
        $made    = \App\Support\TransportPreload::intoUsers($order, $pending);

        return back()->with('ok', $made > 0
            ? ($made . ' corrida(s) agregada(s) desde la propuesta — edítalas o bórralas.')
            : 'No hay marcados nuevos que agregar.');
    }

    /** Conteo de atención de transportación (JSON) para el poll del topbar. */
    public function attentionCount(Request $request)
    {
        return response()->json([
            'count' => \App\Support\TransportAttention::countForUser((int) $request->user()->id),
        ]);
    }

    // ── PDF congelado (§1 · Capa 4) ──────────────────────────────────────────
    /**
     * PDF de una versión CONGELADA (dompdf, como el resto de documentos de producción).
     * Sólo exporta congeladas (un borrador no se imprime). Enmascara SIEMPRE las direcciones
     * privadas a 'CASA' (sin excepción): rinde sobre la vista PÚBLICA del snapshot y el diff
     * público, así la doble señal marca cambios reales, no privada↔privada fantasma. El UUID
     * discreto va al pie de todas las páginas (control interno; NO es sello, NO hay verificador).
     */
    public function pdf(Request $request, TransportOrder $order)
    {
        abort_unless(TransportAccess::canLite($request->user()), 403);

        if (! $order->isFrozen()) {
            return redirect()->route('transport.order.show', $order)
                ->withErrors(['pdf' => __('Sólo se exporta una versión congelada; emite la orden antes de descargar el PDF.')]);
        }

        // Vista PÚBLICA (privadas → CASA) de la versión actual y la inmediata anterior + diff público.
        $current = TransportOrderSnapshot::publicView(TransportOrderSnapshot::resolvedFor($order));
        $prev = null;
        if ($order->prev_version_id && ($p = TransportOrder::find($order->prev_version_id))) {
            $prev = TransportOrderSnapshot::publicView(TransportOrderSnapshot::resolvedFor($p));
        }
        $diff = TransportOrderSnapshot::diff($current, $prev);

        $roster = DayRosterBuilder::build($request->user(), $order->order_date);

        $pdf = Pdf::loadView('transport.orders.pdf', [
            'order'      => $order,
            'snapshot'   => $current,
            'diff'       => $diff,
            'roster'     => $roster,
            'production' => CurrentProduction::get(),
        ])->setPaper('letter', 'portrait');

        $name = 'orden-transportacion-' . optional($order->order_date)->format('Y-m-d') . '-v' . $order->version . '.pdf';

        return $pdf->stream($name);
    }

    // ── Agenda por vehículo (Fase 4) ─────────────────────────────────────────
    /**
     * La orden vista como AGENDA por unidad: cada vehículo con su día en orden de hora (corridas de
     * set, fuera y eventos), y qué podría ADELANTARSE en la misma unidad (cálculo híbrido traslado/hueco,
     * {@see TransportAgenda}). SÓLO informa, nunca mueve. Producción (canLite) NO ve las discretas.
     */
    public function agenda(Request $request, TransportOrder $order)
    {
        abort_unless(TransportAccess::canLite($request->user()), 403);
        $isFull = TransportAccess::canFull($request->user());

        return view('transport.orders.agenda', [
            'order'   => $order,
            'agenda'  => \App\Support\TransportAgenda::build($order, $isFull),
            'canFull' => $isFull,
        ]);
    }

    // ── Pantalla del DRIVER (§2 · Capa 4) ────────────────────────────────────
    /**
     * "Mis corridas" del día para el driver. NO exige permiso de transpo: cualquiera autenticado
     * ve SÓLO las suyas (driver_user_id = él). Ve la DIRECCIÓN REAL de las privadas de SUS corridas:
     * se la gana por ASIGNACIÓN, sin necesidad de estar en la allowlist (ambos son concesiones
     * independientes con OR en {@see TransportAddress::realAddressVisibleTo}). Trabaja sobre la
     * versión EMITIDA (congelada) del día; un borrador no opera en la calle. Móvil primero.
     */
    public function driverRuns(Request $request)
    {
        $user = $request->user();
        $date = $request->query('date');
        $date = $date ? Carbon::parse($date)->toDateString() : now()->toDateString();
        $pid  = CurrentProduction::id();

        $order = TransportOrder::latestFrozenFor($pid, $date);

        $runs = collect();
        if ($order) {
            $order->load(['runs.occupants']);
            $runs = $order->runs->where('driver_user_id', $user->id)->values();
        }

        $callById = CallPlace::where('production_id', $pid)->pluck('name', 'id');
        $privById = TransportAddress::where('production_id', $pid)->get()->keyBy('id');
        $vehById  = Vehicle::whereIn('id', $runs->pluck('vehicle_id')->filter()->all())->get()->keyBy('id');
        $deptById = \App\Models\Department::pluck('name', 'id');
        // Fase 2: para las corridas de SET el pick up es DERIVADO (no está en pickup_literal).
        $ctx       = $order ? TransportPickupDeriver::context($order) : [];
        $pointById = TransportPickupPoint::where('production_id', $pid)->get(['id', 'name', 'address'])->keyBy('id');

        // Devuelve [rótulo, calle-real|null]. La calle sólo existe para privadas y aquí el driver
        // SIEMPRE la ve (son sus corridas → asignación).
        $place = function ($kind, $id, $text) use ($callById, $privById) {
            if ($kind === 'text')    return [(string) $text, null];
            if ($kind === 'call')    return [(string) ($callById[$id] ?? '—'), null];
            if ($kind === 'private') { $a = $privById->get($id); return [$a ? $a->label : '—', $a ? $a->address : null]; }
            return ['—', null];
        };

        $cards = $runs->map(function (TransportOrderRun $run) use ($place, $vehById, $deptById, $ctx, $pointById) {
            $veh = $run->vehicle_id ? $vehById->get($run->vehicle_id) : null;

            if ($run->isEvento()) {
                // Evento: ventana de horario + descripción; ocupa la unidad, sin ocupantes ni lugares.
                $pickupTime   = $run->pickup_literal;
                $pickupPlace  = '—';
                $pickupStreet = null;
                $destPlace    = (string) ($run->dest_text ?? '—');
                $destStreet   = null;
            } elseif ($run->isSet()) {
                // Derivado: hora calculada + el PUNTO como origen (público) + la locación como destino.
                $d  = TransportPickupDeriver::derive($run, $ctx);
                $pt = $run->pickup_point_id ? $pointById->get($run->pickup_point_id) : null;
                $pickupTime   = ! empty($d['ok']) ? $d['time'] : null;
                $pickupPlace  = $pt ? $pt->name : ($d['point'] ?? '—');
                $pickupStreet = $pt ? $pt->address : null;
                $destPlace    = $d['dest'] ?? '—';
                $destStreet   = null;
            } else {
                [$pickupPlace, $pickupStreet] = $place($run->pickup_place_kind, $run->pickup_place_id, $run->pickup_place_text);
                [$destPlace, $destStreet]     = $place($run->dest_place_kind, $run->dest_place_id, $run->dest_text);
                $pickupTime = $run->pickup_literal;
            }

            $occ = $run->occupants->map(function (TransportRunOccupant $o) use ($deptById) {
                $line = $o->displayName();
                if ($o->department_id && isset($deptById[$o->department_id])) $line .= ' · ' . $deptById[$o->department_id];
                if ($o->load_note) $line .= ' · ' . $o->load_note;
                return $line;
            })->all();

            return [
                'type'          => $run->isEvento() ? __('Evento') : $run->typeLabel(),
                'is_evento'     => $run->isEvento(),
                'event_end'     => $run->end_literal,
                'pickup_time'   => $pickupTime,
                'pickup_place'  => $pickupPlace,
                'pickup_street' => $pickupStreet,
                'dest_place'    => $destPlace,
                'dest_street'   => $destStreet,
                'vehicle'       => $veh ? (trim(($veh->make ?: '') . ' ' . ($veh->model ?: '')) ?: ('#' . $veh->id)) : null,
                'plate'         => $veh->plate ?? null,
                'occupants'     => $occ,
                'notes'         => $run->notes,
            ];
        })->all();

        return view('transport.orders.driver', [
            'date'    => $date,
            'order'   => $order,
            'cards'   => $cards,
            'hasDate' => (bool) $order,
        ]);
    }

    /** Clona una orden CONGELADA a un borrador nuevo (versión+1), copiando corridas y ocupantes
     *  CON su run_key (identidad estable) y encadenando con prev_version_id. */
    private function cloneToNewDraft(TransportOrder $frozen, $user): TransportOrder
    {
        return DB::transaction(function () use ($frozen, $user) {
            $draft = TransportOrder::create([
                'production_id'   => $frozen->production_id,
                'order_date'      => $frozen->order_date,
                'version'         => TransportOrder::nextVersionFor($frozen->production_id, $frozen->order_date),
                'status'          => TransportOrder::STATUS_DRAFT,
                'notes_general'   => $frozen->notes_general,
                'prev_version_id' => $frozen->id,
                'created_by_id'   => $user->id,
            ]);

            foreach ($frozen->runs()->with('occupants')->get() as $run) {
                $newRun = $run->replicate(['created_at', 'updated_at']); // conserva run_key
                $newRun->transport_order_id = $draft->id;
                $newRun->save();
                foreach ($run->occupants as $occ) {
                    $newOcc = $occ->replicate(['created_at', 'updated_at']);
                    $newOcc->transport_order_run_id = $newRun->id;
                    $newOcc->save();
                }
            }

            return $draft;
        });
    }

    // ── Notas generales (§1: hitos sin vehículo) ─────────────────────────────
    public function updateOrder(Request $request, TransportOrder $order)
    {
        $this->authorizeEdit($request, $order);
        $data = $request->validate([
            'notes_general' => 'nullable|string',
            'pickup_mode'   => 'nullable|in:ligero,masivo',
        ]);
        $order->notes_general = $data['notes_general'] ?? null;
        if (! empty($data['pickup_mode'])) {
            $order->pickup_mode = $data['pickup_mode'];
        }
        $order->save();

        return back()->with('ok', 'Orden actualizada.');
    }

    // ── Editor / consulta de una orden ───────────────────────────────────────
    public function show(Request $request, TransportOrder $order)
    {
        abort_unless(TransportAccess::canLite($request->user()), 403);

        $order->load(['runs.occupants']);
        $canEdit  = TransportAccess::canFull($request->user()) && $order->isDraft();
        $isFrozen = $order->isFrozen();

        // Diff contra la versión inmediata anterior (§4), emparejado por run_key.
        // CONGELADA: se lee el snapshot MATERIALIZADO (no recalcula). BORRADOR: se deriva en vivo.
        $current = $isFrozen ? TransportOrderSnapshot::resolvedFor($order) : TransportOrderSnapshot::build($order);
        $prev = null;
        if ($order->prev_version_id) {
            $prevOrder = TransportOrder::find($order->prev_version_id);
            if ($prevOrder) {
                $prev = is_array($prevOrder->frozen_snapshot) && ! empty($prevOrder->frozen_snapshot)
                    ? $prevOrder->frozen_snapshot
                    : TransportOrderSnapshot::build($prevOrder);
            }
        }
        $diff = TransportOrderSnapshot::diff($current, $prev);

        // §3/§4: qué direcciones privadas puede ver el viewer con su CALLE/rótulo real en ESTA vista.
        // Regla = allowlist (no basta canFull). Quien no esté en la lista ve 'CASA'. La visibilidad
        // por ASIGNACIÓN del driver vive en la pantalla del driver, no aquí (así no se pisan).
        $realAddrIds = TransportAddress::where('production_id', $order->production_id)
            ->whereHas('viewers', fn ($q) => $q->where('users.id', $request->user()->id))
            ->pluck('id')->map(fn ($x) => (int) $x)->all();

        $editor = $this->editorData($order, $request->user());

        // Parte 1 · el PICKER lista SÓLO lo que este viewer puede ver (misma regla realAddressVisibleTo):
        // no privadas (visibles para todos) + privadas en su allowlist. La etiqueta interna identifica
        // (dice de quién es la casa) → no se muestra a quien no está en la lista.
        $pickAddresses = $editor['privateAddresses']->filter(
            fn ($a) => ! $a->is_private || in_array((int) $a->id, $realAddrIds, true)
        )->values();

        // Fase 2: el pick up mostrado sale del snapshot ($current) por run_key → así una CONGELADA
        // muestra lo MATERIALIZADO y un BORRADOR lo derivado en vivo, con la misma pieza.
        $snapByKey = [];
        foreach (($current['runs'] ?? []) as $r) {
            $snapByKey[$r['run_key'] ?? ''] = $r;
        }

        // Ayuda de captura (traslado/ajuste, "sin ancla") SÓLO en borrador; en congelada no se recalcula.
        $ctx = $isFrozen ? [] : TransportPickupDeriver::context($order);
        $derived = [];
        $discreetElig = [];
        foreach ($order->runs as $run) {
            if (! $isFrozen && $run->isSet()) {
                $derived[$run->id] = TransportPickupDeriver::derive($run, $ctx);
            }
            $discreetElig[$run->id] = TransportCrew::discreetEligible($run, (int) $order->production_id);
        }

        // Fase 5: propuesta (marcados que faltan) + traslapes driver/vehículo — sólo tiene sentido en borrador.
        $proposal = $order->isDraft() ? \App\Support\TransportPreload::proposal($order) : ['count' => 0, 'groups' => [], 'loose' => []];
        $overlaps = $order->isDraft() ? \App\Support\TransportBackSync::overlaps($order) : [];

        return view('transport.orders.edit', array_merge(
            [
                'order' => $order, 'canEdit' => $canEdit, 'diff' => $diff, 'legend' => $current['legend'],
                'realAddrIds' => $realAddrIds, 'pickAddresses' => $pickAddresses,
                'derived' => $derived, 'discreetElig' => $discreetElig, 'snapByKey' => $snapByKey,
                'viewerIsFull' => TransportAccess::canFull($request->user()),
                'proposal' => $proposal, 'overlaps' => $overlaps,
            ],
            $editor
        ));
    }

    // ── Corridas ─────────────────────────────────────────────────────────────
    public function storeRun(Request $request, TransportOrder $order)
    {
        $this->authorizeEdit($request, $order);

        $data = $this->validateRun($request);

        if ($e = $this->runVehicleError($data)) {
            return back()->withErrors(['vehicle_id' => $e])->withInput();
        }

        $run = new TransportOrderRun($this->runAttributes($data));
        $run->transport_order_id = $order->id;
        $run->sort_order = (int) $order->runs()->max('sort_order') + 1;
        $run->save();

        $this->syncRunToBack($order, $run);   // Fase 5: la orden devuelve el pick up al back

        return back()->with('ok', 'Corrida agregada.');
    }

    public function updateRun(Request $request, TransportOrder $order, TransportOrderRun $run)
    {
        $this->authorizeEdit($request, $order);
        abort_unless($run->transport_order_id === $order->id, 404);

        $data = $this->validateRun($request);

        if ($e = $this->runVehicleError($data)) {
            return back()->withErrors(['vehicle_id' => $e])->withInput();
        }

        $oldVehicleId = (int) $run->vehicle_id;
        $run->fill($this->runAttributes($data));
        $run->save();

        $this->syncRunToBack($order, $run);   // Fase 5: al ajustar, el pick up refinado vuelve al back

        // Fase 4: mover una corrida INFORMA qué podría adelantarse en su(s) unidad(es). No mueve nada.
        $hint = $this->advanceHint($order, array_filter([$oldVehicleId, (int) $run->vehicle_id]));

        return back()->with('ok', 'Corrida actualizada.')->with('info', $hint);
    }

    public function destroyRun(Request $request, TransportOrder $order, TransportOrderRun $run)
    {
        $this->authorizeEdit($request, $order);
        abort_unless($run->transport_order_id === $order->id, 404);

        $vehicleId = (int) $run->vehicle_id;
        $run->is_active = 0;
        $run->save();

        // Fase 4: al cancelar, informa qué podría adelantarse en esa unidad (sin mover nada).
        $hint = $this->advanceHint($order, array_filter([$vehicleId]));

        return back()->with('ok', 'Corrida eliminada.')->with('info', $hint);
    }

    /** Aviso combinado "podría adelantarse" para una o más unidades (null si nada). */
    private function advanceHint(TransportOrder $order, array $vehicleIds): ?string
    {
        $order = $order->fresh();
        $lines = [];
        foreach (array_unique(array_map('intval', $vehicleIds)) as $vid) {
            if ($vid && ($h = \App\Support\TransportAgenda::vehicleHint($order, $vid))) {
                $lines[] = $h;
            }
        }
        return $lines ? implode(' ', $lines) : null;
    }

    /**
     * Marca/desmarca DISCRETA una corrida (capa de privacidad). Sólo es elegible para jefatura, cast
     * o transporte por aplicación ({@see TransportCrew::discreetEligible}); si no, se rechaza (la UI
     * ni siquiera ofrece el toggle, y el server lo blinda). Discreta = no aparece en la orden/PDF.
     */
    public function toggleDiscreet(Request $request, TransportOrder $order, TransportOrderRun $run)
    {
        $this->authorizeEdit($request, $order);
        abort_unless($run->transport_order_id === $order->id, 404);

        $run->loadMissing('occupants');
        if (! TransportCrew::discreetEligible($run, (int) $order->production_id)) {
            return back()->withErrors(['discreet' => __('Sólo jefatura, cast o transporte por aplicación pueden marcarse discretos.')]);
        }

        $run->is_discreet = ! $run->is_discreet;
        $run->save();

        return back()->with('ok', $run->is_discreet ? __('Corrida marcada como discreta.') : __('Corrida visible de nuevo.'));
    }

    // ── Ocupantes ────────────────────────────────────────────────────────────
    public function storeOccupant(Request $request, TransportOrder $order, TransportOrderRun $run)
    {
        $this->authorizeEdit($request, $order);
        abort_unless($run->transport_order_id === $order->id, 404);

        if ($run->isEvento()) {
            return back()->withErrors(['source' => __('Un evento de vehículo no lleva ocupantes.')]);
        }

        $data = $request->validate([
            'source'        => 'required|in:crew,cast,agency,client,free',
            'user_id'       => 'nullable|integer',
            'name'          => 'nullable|string|max:160',
            'department_id' => 'nullable|integer',
            'load_note'     => 'nullable|string|max:120',
        ]);

        $occ = new TransportRunOccupant([
            'source'        => $data['source'],
            'department_id' => $data['department_id'] ?? null,
            'load_note'     => $data['load_note'] ?? null,
        ]);
        $occ->transport_order_run_id = $run->id;
        $occ->sort_order = (int) $run->occupants()->max('sort_order') + 1;

        if ($data['source'] === TransportRunOccupant::SOURCE_CREW && ! empty($data['user_id'])) {
            $occ->user_id = (int) $data['user_id'];
            $occ->name_snapshot = optional(\App\Models\User::find($data['user_id']))->name
                ? \App\Models\User::displayName(\App\Models\User::find($data['user_id']))
                : ($data['name'] ?? null);
        } elseif (in_array($data['source'], [TransportRunOccupant::SOURCE_CAST, TransportRunOccupant::SOURCE_AGENCY, TransportRunOccupant::SOURCE_CLIENT], true)) {
            $name = trim((string) ($data['name'] ?? ''));
            if ($name === '') {
                return back()->withErrors(['name' => 'Escribe el nombre del ocupante.'])->withInput();
            }
            // Alta / reuso en el PADRÓN LIGERO de la producción (se sugiere la 2ª vez).
            $party = TransportParty::firstOrCreate(
                ['production_id' => $order->production_id, 'kind' => $data['source'], 'name' => $name],
                ['created_by_id' => $request->user()->id, 'is_active' => 1]
            );
            $occ->party_id = $party->id;
            $occ->name_snapshot = $name;
        } else { // free
            $name = trim((string) ($data['name'] ?? ''));
            if ($name === '') {
                return back()->withErrors(['name' => 'Escribe el nombre del ocupante.'])->withInput();
            }
            $occ->name_snapshot = $name;
        }

        $occ->save();

        $this->syncRunToBack($order, $run);   // Fase 5: nuevo ocupante → siembra su pick up en el back

        // (SET) si este ocupante entra MÁS TEMPRANO que el resto, ADELANTA el pick up → avisa en pantalla.
        if ($run->run_class === TransportOrderRun::CLASS_SET && $occ->source === TransportRunOccupant::SOURCE_CREW && $occ->user_id) {
            $run->load('occupants');
            if ($run->occupants->count() > 1) {
                $d = TransportPickupDeriver::derive($run, TransportPickupDeriver::context($order));
                if (! empty($d['ok']) && (int) $d['anchor_user_id'] === (int) $occ->user_id) {
                    return back()->with('warn', __('El pick up se adelantó a :t por :n.', ['t' => $d['time'], 'n' => $occ->displayName()]));
                }
            }
        }

        return back()->with('ok', 'Ocupante agregado.');
    }

    public function destroyOccupant(Request $request, TransportOrder $order, TransportRunOccupant $occupant)
    {
        $this->authorizeEdit($request, $order);
        abort_unless(optional($occupant->run)->transport_order_id === $order->id, 404);

        $occupant->delete();

        return back()->with('ok', 'Ocupante eliminado.');
    }

    // ── Helpers ──────────────────────────────────────────────────────────────
    private function authorizeEdit(Request $request, TransportOrder $order): void
    {
        abort_unless(TransportAccess::canFull($request->user()), 403);
        abort_unless($order->isDraft(), 403, 'Una orden congelada no se edita.');
    }

    /** Reglas comunes de una corrida. Dos EJES: run_class (set|fuera) y run_type. */
    private function validateRun(Request $request): array
    {
        return $request->validate([
            'run_class'             => 'nullable|in:set,fuera,evento', // default 'fuera' (lo legado convive)
            'run_type'              => 'required|in:normal,aeropuerto,aplicacion',
            'vehicle_id'            => 'nullable|integer',
            'driver_user_id'        => 'nullable|integer',
            // SET (derivado): origen del catálogo + destino (scouting) + traslado tecleado (fallback) + ajuste.
            'pickup_point_id'       => 'nullable|integer',
            'dest_location_ref'     => 'nullable|integer',
            'travel_minutes'        => 'nullable|integer|min:0|max:1440',
            'travel_adjust_minutes' => 'nullable|integer|min:-600|max:600',
            // FUERA (a mano): hora literal + lugar de inicio + lugar de fin (ref combinado).
            'pickup_literal'        => 'nullable|string|max:16',
            'pickup_ref'            => 'nullable|string|max:24',
            'pickup_place_text'     => 'nullable|string|max:255',
            'dest_ref'              => 'nullable|string|max:24',
            'dest_text'             => 'nullable|string|max:255',
            // EVENTO de vehículo: descripción + inicio + fin (nombres propios para no chocar con FUERA).
            'event_desc'            => 'nullable|string|max:255',
            'event_start'           => 'nullable|string|max:16',
            'event_end'             => 'nullable|string|max:16',
            // Fin de corrida (opcional, set/fuera): wrap de la unidad → insumo del cálculo Fase 4.
            'run_end_literal'       => 'nullable|string|max:16',
            'equipment'             => 'nullable|array',
            'equipment.*'           => 'string|max:40',
            'notes'                 => 'nullable|string',
        ]);
    }

    /**
     * Regla de vehículo por corrida. El EVENTO SIEMPRE exige vehículo (ocupa su agenda); las demás,
     * salvo transporte de aplicación (§2). Devuelve el mensaje de error o null si está bien.
     */
    private function runVehicleError(array $data): ?string
    {
        $class = $data['run_class'] ?? TransportOrderRun::CLASS_FUERA;
        if ($class === TransportOrderRun::CLASS_EVENTO && empty($data['vehicle_id'])) {
            return __('El evento de vehículo requiere un vehículo: ocupa su agenda.');
        }
        if ($class !== TransportOrderRun::CLASS_EVENTO
            && $data['run_type'] !== TransportOrderRun::TYPE_APLICACION && empty($data['vehicle_id'])) {
            return __('El vehículo es obligatorio salvo transporte de aplicación.');
        }
        return null;
    }

    /** Traduce un ref combinado del formulario a [kind, id]. "" → nada; "text" → libre. */
    private function parseRef(?string $ref): array
    {
        $ref = trim((string) $ref);
        if ($ref === '' || $ref === 'none') {
            return [null, null];
        }
        if ($ref === 'text') {
            return ['text', null];
        }
        if (strpos($ref, ':') !== false) {
            [$kind, $id] = explode(':', $ref, 2);
            if (in_array($kind, ['call', 'private'], true) && ctype_digit($id)) {
                return [$kind, (int) $id];
            }
        }
        return [null, null];
    }

    /** Normaliza los campos de una corrida desde el request validado, SEGÚN LA CLASE. */
    private function runAttributes(array $data): array
    {
        $class = $data['run_class'] ?? TransportOrderRun::CLASS_FUERA;
        $isSet = $class === TransportOrderRun::CLASS_SET;

        $common = [
            'run_class'      => $class,
            'run_type'       => $data['run_type'],
            'vehicle_id'     => $data['vehicle_id'] ?? null,
            'driver_user_id' => $data['driver_user_id'] ?? null,
            'equipment'      => array_values($data['equipment'] ?? []),
            'notes'          => $data['notes'] ?? null,
        ];

        // EVENTO de vehículo: mapeo FORZADO documentado — descripción→dest_text, inicio→pickup_literal,
        // fin→end_literal. Sin ocupantes, sin lugares del catálogo; el vehículo es obligatorio (storeRun).
        if ($class === TransportOrderRun::CLASS_EVENTO) {
            return $common + [
                'pickup_point_id'       => null,
                'dest_location_ref'     => null,
                'travel_minutes'        => null,
                'travel_adjust_minutes' => 0,
                'pickup_literal'        => $data['event_start'] ?? null,
                'end_literal'           => $data['event_end'] ?? null,
                'pickup_place_kind'     => null, 'pickup_place_id' => null, 'pickup_place_text' => null,
                'dest_place_kind'       => 'text', 'dest_place_id' => null, 'dest_text' => $data['event_desc'] ?? null,
            ];
        }

        if ($isSet) {
            // El pick up se DERIVA: no se guarda literal ni lugar libre. Sí el origen/destino/traslado.
            return $common + [
                'pickup_point_id'       => $data['pickup_point_id'] ?? null,
                'dest_location_ref'     => $data['dest_location_ref'] ?? null,
                'travel_minutes'        => $data['travel_minutes'] ?? null,   // fallback si el par no está en la matriz
                'travel_adjust_minutes' => (int) ($data['travel_adjust_minutes'] ?? 0),
                'pickup_literal'        => null,
                'end_literal'           => $data['run_end_literal'] ?? null,   // wrap opcional (Fase 4)
                'pickup_place_kind'     => null, 'pickup_place_id' => null, 'pickup_place_text' => null,
                'dest_place_kind'       => null, 'dest_place_id' => null, 'dest_text' => null,
            ];
        }

        // FUERA: a mano (hora literal + lugar inicio + lugar fin).
        [$pk, $pid] = $this->parseRef($data['pickup_ref'] ?? null);
        [$dk, $did] = $this->parseRef($data['dest_ref'] ?? null);

        return $common + [
            'pickup_point_id'       => null,
            'dest_location_ref'     => null,
            'travel_minutes'        => null,
            'travel_adjust_minutes' => 0,
            'pickup_literal'        => $data['pickup_literal'] ?? null,
            'end_literal'           => $data['run_end_literal'] ?? null,   // wrap opcional (Fase 4)
            'pickup_place_kind'     => $pk,
            'pickup_place_id'       => $pk === 'text' ? null : $pid,
            'pickup_place_text'     => $pk === 'text' ? ($data['pickup_place_text'] ?? null) : null,
            'dest_place_kind'       => $dk,
            'dest_place_id'         => $dk === 'text' ? null : $did,
            'dest_text'             => $dk === 'text' ? ($data['dest_text'] ?? null) : null,
        ];
    }

    /** Datos para el editor: pickers de vehículo/driver/lugares/equipamiento + encabezado por puesto. */
    private function editorData(TransportOrder $order, $user): array
    {
        $pid = $order->production_id;

        $vehicles = Vehicle::query()
            ->where('is_active', 1)
            ->with('driver')
            ->orderBy('make')->orderBy('model')
            ->get()
            ->map(fn (Vehicle $v) => [
                'id'            => $v->id,
                'label'         => trim(($v->make ?: '') . ' ' . ($v->model ?: '')) ?: ('Vehículo #' . $v->id),
                'plate'         => $v->plate,
                'driver_id'     => $v->driver_user_id,
                'driver_label'  => $v->driverLabel(),
            ])
            ->values();

        $callPlaces = CallPlace::query()
            ->where('production_id', $pid)
            ->where('active', 1)
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        $privateAddresses = TransportAddress::query()
            ->where('production_id', $pid)
            ->where('is_active', 1)
            ->orderBy('label')
            ->get(['id', 'label', 'public_label', 'is_private']);

        $equipment = TransportEquipment::query()
            ->where('is_active', 1)
            ->orderBy('sort_order')->orderBy('name_es')
            ->get(['code', 'name_es', 'name_en', 'icon']);

        // SET: orígenes del catálogo (Fase 1) + destinos = locaciones del día (scouting con coords).
        $pickupPoints = TransportPickupPoint::where('production_id', $pid)
            ->where('is_active', 1)
            ->orderBy('sort_order')->orderBy('name')
            ->get(['id', 'name']);

        $dayLocations = ScoutingReport::where('production_id', $pid)
            ->whereNotNull('latitude')->whereNotNull('longitude')
            ->orderBy('location_name')
            ->get(['id', 'location_name']);

        // Encabezado por puesto (§1) + opciones de crew para ocupantes/driver — dinámico.
        $roster = DayRosterBuilder::build($user, $order->order_date);
        $crew = [];
        foreach ($roster['groups'] as $g) {
            foreach ($g['people'] as $p) {
                $crew[] = [
                    'user_id' => $p['user_id'],
                    'name'    => $p['name'],
                    'cargo'   => $p['cargo'],
                    'dept'    => $g['label'],
                    'dept_id' => $p['dept_id'],
                ];
            }
        }

        // Padrón ligero por tipo (para el autocompletado de cast/agencia/cliente).
        $parties = TransportParty::query()
            ->where('production_id', $pid)
            ->where('is_active', 1)
            ->orderBy('name')
            ->get(['id', 'kind', 'name'])
            ->groupBy('kind');

        $departments = \App\Models\Department::orderBy('sort_order')->orderBy('name')->get(['id', 'name']);

        return [
            'vehicles'         => $vehicles,
            'callPlaces'       => $callPlaces,
            'privateAddresses' => $privateAddresses,
            'equipment'        => $equipment,
            'pickupPoints'     => $pickupPoints,
            'dayLocations'     => $dayLocations,
            'roster'           => $roster,
            'crew'             => $crew,
            'parties'          => $parties,
            'departments'      => $departments,
        ];
    }
}
