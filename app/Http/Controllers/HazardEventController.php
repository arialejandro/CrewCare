<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use App\Models\HazardEvent;
use App\Models\SafetyStandard;

/**
 * HazardEventController — CRUD de la pantalla de EVENTOS POSIBLES (`hazard_events`),
 * Paso 4b. Calca a SafetyStandardController (guard / validatedData / store-nace-pendiente
 * / verify / deactivate / reactivate) con el MISMO gateo asimétrico, y añade el editor
 * N:M evento↔norma (pivote `hazard_event_standard`).
 *
 *   1) GATEO ASIMÉTRICO (espejo del 4a): hazardevents.view = index/show;
 *      hazardevents.create = create/store (el safety-officer AGREGA pero NO edita);
 *      hazardevents.MANAGE = edit/update/verify/deactivate/reactivate + el editor N:M
 *      en edit (solo una autoridad corrige, verifica, retira o re-liga normas).
 *
 *   2) RETIRO = is_active=0 (booleano PLANO), NUNCA ->delete() ni SoftDeletes. El hook
 *      `deleting` del modelo PURGA hazard_event_standard (los 342 vínculos + el
 *      histórico); y aunque no lo hiciera, los 5 reportes resuelven el evento y sus
 *      normas EN VIVO (sin snapshot), así que un evento retirado debe seguir resolviéndose
 *      para el histórico ya firmado. is_active (sin scope global) solo lo saca de la
 *      pantalla de captura. Por eso NO hay destroy() aquí.
 *
 *   3) EDITOR N:M = "norm-picker": $event->standards()->sync($ids) al GUARDAR el form
 *      (create y update), NO endpoints attach/detach separados. Idempotente. El pivote
 *      es plano (id, hazard_event_id, safety_standard_id, sin timestamps) → sync() limpio.
 *
 * Guarda transversal (guard()): SIN feature flag (los eventos son core). Solo se corta si
 * la tabla no existe (prod sin el esquema).
 */
class HazardEventController extends Controller
{
    /**
     * Guarda común. Devuelve una respuesta si hay que CORTAR (tabla ausente), o null si se
     * puede continuar. SIN feature flag: el catálogo de eventos es núcleo, no una
     * característica apagable.
     *
     * @return \Illuminate\Http\RedirectResponse|null
     */
    protected function guard()
    {
        if (!Schema::hasTable('hazard_events')) {
            return redirect()->route('home')
                ->with('error', 'El catálogo de eventos posibles aún no está disponible (falta la migración de base de datos).');
        }
        return null;
    }

    /**
     * Listado. Eager-load de `standards` para pintar los chips de marco de cada evento sin
     * N+1. Orden "vigentes primero" (si la BD soporta is_active), luego por contexto y
     * sort_order/nombre. La vista puede agrupar por contexto con ->groupBy('context').
     * El orden por is_active DESC solo se aplica si la columna existe (ordenar por una
     * columna ausente reventaría con MySQL 1054).
     */
    public function index()
    {
        if ($resp = $this->guard()) {
            return $resp;
        }

        $query = HazardEvent::with('standards');
        if (HazardEvent::supportsActiveFlag()) {
            $query->orderByDesc('is_active');
        }
        $events = $query->orderBy('context')->orderBy('sort_order')->orderBy('name_es')->get();

        // Sets para las facetas de filtro (chips de contexto/categoría de la vista).
        $contexts   = HazardEvent::contexts();
        $categories = HazardEvent::categories();

        return view('admin.hazard-events.index', compact('events', 'contexts', 'categories'));
    }

    /**
     * Detalle en solo lectura. Vive en hazardevents.view (junto a index) porque no muta
     * nada. Carga sus normas ligadas y, si la BD lo soporta, quién lo validó.
     */
    public function show($id)
    {
        if ($resp = $this->guard()) {
            return $resp;
        }

        $event = HazardEvent::findOrFail($id);
        $event->load('standards');

        if (HazardEvent::supportsVerification()) {
            $event->load('verifiedBy');
        }

        return view('admin.hazard-events.show', compact('event'));
    }

    /**
     * Alta (form). El norm-picker ofrece las normas ACTIVAS del catálogo; el safety-officer
     * liga las normas iniciales del evento (picker create-gated en la vista). $linkedIds
     * arranca de old('standards') para re-poblar tras un fallo de validación.
     */
    public function create()
    {
        if ($resp = $this->guard()) {
            return $resp;
        }

        $allStandards = SafetyStandard::active()->orderBy('category_name')->orderBy('regulation_code')->get();
        $linkedIds    = collect(old('standards', []));
        $contexts     = HazardEvent::contexts();
        $categories   = HazardEvent::categories();

        return view('admin.hazard-events.create', compact('allStandards', 'linkedIds', 'contexts', 'categories'));
    }

    /**
     * Alta (guardado). Nace VERIFICADO si quien lo crea ya es autoridad verificadora (sin
     * fricción para el manage); si lo captura un safety-officer (hazardevents.create sin
     * manage), nace PENDIENTE hasta que un hazardevents.manage lo valide. code se autogenera
     * único (NO es campo de usuario). is_active NO se toca: lo pone el DEFAULT 1 de la
     * columna. Tras crear, se sincronizan las normas ligadas por el picker.
     */
    public function store(Request $request)
    {
        if ($resp = $this->guard()) {
            return $resp;
        }

        $data = $this->validatedData($request);
        $data['code'] = $this->generateCode();

        if (HazardEvent::supportsVerification() && $request->user() && $request->user()->can('hazardevents.manage')) {
            $data['verified_at']    = now();
            $data['verified_by_id'] = $request->user()->id;
        }

        $event = HazardEvent::create($data);
        $event->standards()->sync($this->standardIds($request));

        return redirect()->route('hazardevents.index')->with('success', 'Evento creado.');
    }

    /**
     * Edición (form). Solo hazardevents.manage llega aquí. El norm-picker ofrece las normas
     * ACTIVAS UNIÓN las YA-ligadas al evento: así una norma retirada-pero-ligada NO se
     * fuerza a soltarse solo por no estar activa. $linkedIds arranca de old() o, en su
     * defecto, de las normas actuales del evento.
     */
    public function edit($id)
    {
        if ($resp = $this->guard()) {
            return $resp;
        }

        $event = HazardEvent::findOrFail($id);
        $event->load('standards');

        if (HazardEvent::supportsVerification()) {
            $event->load('verifiedBy');
        }

        // Activas ∪ ligadas (unique por id) → el picker nunca fuerza a soltar una norma
        // retirada que ya estaba vinculada al evento.
        $allStandards = SafetyStandard::active()->get()
            ->concat($event->standards)
            ->unique('id')
            ->sortBy('category_name')
            ->values();

        $linkedIds  = collect(old('standards', $event->standards->pluck('id')->all()));
        $contexts   = HazardEvent::contexts();
        $categories = HazardEvent::categories();

        return view('admin.hazard-events.edit', compact('event', 'allStandards', 'linkedIds', 'contexts', 'categories'));
    }

    /**
     * Edición (guardado). A DIFERENCIA de flujos que re-abren verificación al tocar campos
     * materiales, aquí NO se re-abre: el gateo asimétrico garantiza que SOLO un
     * hazardevents.manage llega a update() (edit/update viven en permission:hazardevents.manage),
     * y un manage ES la autoridad verificadora — está viendo el cambio en el momento de
     * hacerlo, así que el sello sigue válido a su nombre. Quien puede editar es, por
     * construcción, quien puede avalar. Al final re-sincroniza las normas del picker.
     */
    public function update(Request $request, $id)
    {
        if ($resp = $this->guard()) {
            return $resp;
        }

        $event = HazardEvent::findOrFail($id);
        $data  = $this->validatedData($request, $id);

        $event->update($data);
        $event->standards()->sync($this->standardIds($request));

        return redirect()->route('hazardevents.index')->with('success', 'Evento actualizado.');
    }

    /**
     * Valida un evento capturado en campo. La autoridad la gatea la RUTA
     * (permission:hazardevents.manage), por eso aquí no se re-checa el permiso. Idempotente:
     * re-verificar un evento ya validado NO es un error ni repisa el rastro original.
     */
    public function verify(Request $request, $id)
    {
        if ($resp = $this->guard()) {
            return $resp;
        }

        $event = HazardEvent::findOrFail($id);

        // Sin el delta de verificación aplicado no existe el concepto.
        if (!HazardEvent::supportsVerification()) {
            return redirect()->route('hazardevents.index')
                ->with('error', 'La verificación de eventos no está disponible en esta instancia.');
        }

        if ($event->isVerified()) {
            return redirect()->route('hazardevents.index')
                ->with('success', 'Este evento ya estaba verificado.');
        }

        $event->update([
            'verified_at'    => now(),
            'verified_by_id' => $request->user()->id,
        ]);

        return redirect()->route('hazardevents.index')
            ->with('success', 'Evento verificado: «'.$event->name_es.'».');
    }

    /**
     * RETIRA un evento del catálogo de captura (is_active=0). NUNCA ->delete(): el hook
     * `deleting` del modelo purgaría hazard_event_standard y destruiría el histórico de
     * vínculos; y aunque no lo hiciera, el evento seguiría necesitándose VIVO para resolver
     * el evento/normas de reportes ya firmados. Retirar solo lo saca de la pantalla de
     * captura; el pasado que lo cita lo sigue resolviendo.
     */
    public function deactivate($id)
    {
        if ($resp = $this->guard()) {
            return $resp;
        }

        // Sin la columna is_active no hay concepto de "retirado": no fingimos que lo hay.
        if (!HazardEvent::supportsActiveFlag()) {
            return redirect()->route('hazardevents.index')
                ->with('error', 'El retiro de eventos no está disponible en esta instancia (falta la columna is_active).');
        }

        $event = HazardEvent::findOrFail($id);
        $event->update(['is_active' => 0]);

        return redirect()->route('hazardevents.index')
            ->with('success', 'Evento retirado de captura; el histórico sigue resolviendo.');
    }

    /**
     * Devuelve un evento retirado al catálogo de captura (is_active=1).
     */
    public function reactivate($id)
    {
        if ($resp = $this->guard()) {
            return $resp;
        }

        if (!HazardEvent::supportsActiveFlag()) {
            return redirect()->route('hazardevents.index')
                ->with('error', 'La reactivación de eventos no está disponible en esta instancia (falta la columna is_active).');
        }

        $event = HazardEvent::findOrFail($id);
        $event->update(['is_active' => 1]);

        return redirect()->route('hazardevents.index')
            ->with('success', 'Evento reactivado: «'.$event->name_es.'».');
    }

    /**
     * Validación compartida de store/update de los campos MATERIALES del evento. Reglas de
     * SEGURIDAD:
     *   - name_es/name_en/description_*: texto libre → se pintan por Blade {{ }} (auto-escape)
     *     y cualquier chip dinámico por JS con textContent + enum. Aquí solo se acotan longitudes.
     *   - context: enum cerrado (claves de contexts()) → SELECT.
     *   - category: enum cerrado nullable (claves de categories()).
     *   - default_likelihood: nullable in A..E; default_consequence: nullable int 1..5.
     *
     * NO se validan aquí (NUNCA por POST/form): standards[] (van por standardIds()), code
     * (autogenerado en store, preservado en update), is_active (la mueven deactivate/reactivate)
     * ni verified_* (solo servidor: el flujo de verificación las escribe).
     *
     * @param  int|null  $id  fila en edición (reservado por simetría con el 4a; aquí no hay
     *                        regla unique sobre campos de usuario, pero se conserva la firma).
     */
    protected function validatedData(Request $request, $id = null)
    {
        $contextKeys  = implode(',', array_keys(HazardEvent::contexts()));
        $categoryKeys = implode(',', array_keys(HazardEvent::categories()));

        $data = $request->validate([
            'name_es'             => 'required|string|max:255',
            'name_en'             => 'nullable|string|max:255',
            'description_es'      => 'nullable|string|max:600',
            'description_en'      => 'nullable|string|max:600',
            'context'             => 'required|in:'.$contextKeys,
            'category'            => 'nullable|in:'.$categoryKeys,
            'default_likelihood'  => 'nullable|in:A,B,C,D,E',
            'default_consequence' => 'nullable|integer|between:1,5',
        ]);

        return $data;
    }

    /**
     * Valida y devuelve los IDs de normas ligadas por el norm-picker (input `standards[]`),
     * por SEPARADO de validatedData (no es un campo material del evento sino la relación N:M).
     * standards nullable|array; cada id integer y existente en safety_standards. Default [].
     * Se usa en store/update para $event->standards()->sync($ids) (idempotente).
     */
    protected function standardIds(Request $request)
    {
        $validated = $request->validate([
            'standards'   => 'nullable|array',
            'standards.*' => 'integer|exists:safety_standards,id',
        ]);

        return isset($validated['standards']) ? $validated['standards'] : [];
    }

    /**
     * Autogenera un `code` único para el evento. hazard_events.code es NOT NULL UNIQUE y NO
     * es campo de usuario; los del seed usan prefijos por contexto, los capturados desde la
     * pantalla llevan el prefijo USR- para distinguirlos. Reintenta hasta no colisionar.
     */
    protected function generateCode()
    {
        do {
            $code = 'USR-'.strtoupper(Str::random(5));
        } while (HazardEvent::where('code', $code)->exists());

        return $code;
    }
}
