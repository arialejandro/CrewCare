<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use App\Models\SafetyStandard;

/**
 * SafetyStandardController — CRUD de la pantalla de NORMAS del catálogo normativo
 * (`safety_standards`), Paso 4a. Calca a ConsumableController (guard / validatedData /
 * store-nace-pendiente / verify) pero con dos divergencias DELIBERADAS respecto de las
 * fichas SDS:
 *
 *   1) GATEO ASIMÉTRICO (lo manda la spec): standards.view = index/show;
 *      standards.create = create/store (el safety-officer AGREGA pero NO edita);
 *      standards.MANAGE = edit/update/verify/deactivate/reactivate (solo una autoridad
 *      corrige, verifica o retira). Diverge del gateo simétrico de consumables.
 *
 *   2) RETIRO = is_active=0 (booleano PLANO), NUNCA ->delete() ni SoftDeletes. Injury y
 *      Scouting pintan sus normas SOLO por la relación VIVA ->standards (sin snapshot);
 *      un scope global de SoftDeletes borraría una norma retirada del histórico ya
 *      firmado. is_active (sin scope global) la deja resolviéndose viva para el pasado y
 *      solo la saca de la pantalla de captura. Por eso NO hay destroy() aquí. Ver la NOTA
 *      DURA de database/owner-apply/2026-07-18-safety-standards-is-active.sql.
 *
 * Guarda transversal (guard()): SIN feature flag (las normas son core). Solo se corta si
 * la tabla no existe (prod sin el esquema). Todo lo demás degrada por Schema::hasColumn:
 * sin el delta de is_active la pantalla corre igual (isActive()=true, scopeActive() no-op).
 */
class SafetyStandardController extends Controller
{
    /**
     * Guarda común. Devuelve una respuesta si hay que CORTAR (tabla ausente), o null si se
     * puede continuar. SIN feature flag (a diferencia de ConsumableController::guard, que
     * además checa Features::enabled('sds_sfx')): el catálogo normativo es núcleo, no una
     * característica apagable.
     *
     * @return \Illuminate\Http\RedirectResponse|null
     */
    protected function guard()
    {
        if (!Schema::hasTable('safety_standards')) {
            return redirect()->route('home')
                ->with('error', 'El catálogo de normas aún no está disponible (falta la migración de base de datos).');
        }
        return null;
    }

    /**
     * Listado. Orden "vigentes primero" (si la BD soporta is_active), luego por categoría y
     * código. NO hay $trashed como en consumables: is_active NO tiene scope global, así que
     * las normas retiradas YA salen en esta misma lista (marcadas por su flag), no en una
     * papelera aparte. El orden por is_active DESC solo se aplica si la columna existe:
     * ordenar por una columna ausente reventaría con MySQL 1054.
     */
    public function index()
    {
        if ($resp = $this->guard()) {
            return $resp;
        }

        $query = SafetyStandard::query();
        if (SafetyStandard::supportsActiveFlag()) {
            $query->orderByDesc('is_active');
        }
        $standards = $query->orderBy('category_name')->orderBy('regulation_code')->get();

        return view('admin.standards.index', compact('standards'));
    }

    /**
     * Detalle en solo lectura. Vive en standards.view (junto a index) porque no muta nada.
     */
    public function show($id)
    {
        if ($resp = $this->guard()) {
            return $resp;
        }

        $standard = SafetyStandard::findOrFail($id);

        // El rastro de quién validó solo se puede cargar si la BD tiene el delta de
        // verificación; sin él la relación reventaría por columna inexistente.
        if (SafetyStandard::supportsVerification()) {
            $standard->load('verifiedBy');
        }

        return view('admin.standards.show', compact('standard'));
    }

    public function create()
    {
        if ($resp = $this->guard()) {
            return $resp;
        }

        return view('admin.standards.create');
    }

    /**
     * Alta. Nace VERIFICADA si quien la crea ya es autoridad verificadora (sin fricción
     * para el manage); si la captura un safety-officer (standards.create sin manage), nace
     * PENDIENTE hasta que un standards.manage la valide. is_active NO se toca aquí: lo pone
     * el DEFAULT 1 de la columna (una norma nueva nace VIGENTE por definición).
     */
    public function store(Request $request)
    {
        if ($resp = $this->guard()) {
            return $resp;
        }

        $data = $this->validatedData($request);

        if (SafetyStandard::supportsVerification() && $request->user() && $request->user()->can('standards.manage')) {
            $data['verified_at']    = now();
            $data['verified_by_id'] = $request->user()->id;
        }

        SafetyStandard::create($data);

        return redirect()->route('standards.index')->with('success', 'Norma creada.');
    }

    public function edit($id)
    {
        if ($resp = $this->guard()) {
            return $resp;
        }

        $standard = SafetyStandard::findOrFail($id);

        if (SafetyStandard::supportsVerification()) {
            $standard->load('verifiedBy');
        }

        return view('admin.standards.edit', compact('standard'));
    }

    /**
     * Edición. A DIFERENCIA de ConsumableController::update, aquí NO se re-abre la
     * verificación por tocar campos materiales: el gateo asimétrico ya garantiza que SOLO
     * un standards.manage llega a update() (edit/update viven en permission:standards.manage),
     * y un manage ES la autoridad verificadora — está viendo el cambio en el momento de
     * hacerlo, así que el sello sigue siendo válido a su nombre. Por eso el sello se deja
     * intacto: quien puede editar es, por construcción, quien puede avalar.
     */
    public function update(Request $request, $id)
    {
        if ($resp = $this->guard()) {
            return $resp;
        }

        $standard = SafetyStandard::findOrFail($id);
        $data = $this->validatedData($request, $id);

        $standard->update($data);

        return redirect()->route('standards.index')->with('success', 'Norma actualizada.');
    }

    /**
     * Valida una norma capturada en campo. La autoridad la gatea la RUTA
     * (permission:standards.manage), por eso aquí no se re-checa el permiso. Idempotente:
     * re-verificar una norma ya validada NO es un error ni repisa el rastro original.
     */
    public function verify(Request $request, $id)
    {
        if ($resp = $this->guard()) {
            return $resp;
        }

        $standard = SafetyStandard::findOrFail($id);

        // Sin el delta de verificación aplicado no existe el concepto.
        if (!SafetyStandard::supportsVerification()) {
            return redirect()->route('standards.index')
                ->with('error', 'La verificación de normas no está disponible en esta instancia.');
        }

        if ($standard->isVerified()) {
            return redirect()->route('standards.index')
                ->with('success', 'Esta norma ya estaba verificada.');
        }

        $standard->update([
            'verified_at'    => now(),
            'verified_by_id' => $request->user()->id,
        ]);

        return redirect()->route('standards.index')
            ->with('success', 'Norma verificada: «'.$standard->regulation_code.'».');
    }

    /**
     * RETIRA una norma del catálogo de captura (is_active=0). NUNCA ->delete(): el hook
     * `deleting` del modelo purgaría standardables + hazard_event_standard y destruiría el
     * histórico de vínculos; y aunque no lo hiciera, la norma seguiría necesitándose VIVA
     * para resolver la relación ->standards de reportes ya firmados. Retirar solo la saca
     * de la pantalla de captura; el pasado que la cita la sigue resolviendo.
     */
    public function deactivate($id)
    {
        if ($resp = $this->guard()) {
            return $resp;
        }

        // Sin el delta de is_active no hay concepto de "retirada": no fingimos que lo hay.
        if (!SafetyStandard::supportsActiveFlag()) {
            return redirect()->route('standards.index')
                ->with('error', 'El retiro de normas no está disponible en esta instancia (falta la columna is_active).');
        }

        $standard = SafetyStandard::findOrFail($id);
        $standard->update(['is_active' => 0]);

        return redirect()->route('standards.index')
            ->with('success', 'Norma retirada de captura; el histórico que la referencia sigue resolviéndose.');
    }

    /**
     * Devuelve una norma retirada al catálogo de captura (is_active=1).
     */
    public function reactivate($id)
    {
        if ($resp = $this->guard()) {
            return $resp;
        }

        if (!SafetyStandard::supportsActiveFlag()) {
            return redirect()->route('standards.index')
                ->with('error', 'La reactivación de normas no está disponible en esta instancia (falta la columna is_active).');
        }

        $standard = SafetyStandard::findOrFail($id);
        $standard->update(['is_active' => 1]);

        return redirect()->route('standards.index')
            ->with('success', 'Norma reactivada: «'.$standard->regulation_code.'».');
    }

    /**
     * Validación compartida de store/update. Reglas de SEGURIDAD:
     *   - regulation_badge: enum cerrado (SELECT) → in:{CSATF,OSHA,STPS,DOT,SCT,GENERAL,FAA,CAL,SEDENA}.
     *   - regulation_code: required, charset seguro (alfanumérico + . / - espacio) y UNIQUE
     *     (la tabla tiene uq_safety_standards_reg_code; sin el Rule::unique el insert
     *     reventaría con un 500 por clave duplicada). En update se ignora la propia fila.
     *   - category_name required max150; category_name_en nullable max255; reference_url
     *     nullable url max500.
     *
     * NO se validan verified_* (solo servidor: el flujo de verificación las escribe) ni
     * is_active (no se expone en el form: la mueven deactivate()/reactivate()).
     *
     * @param  int|null  $id  fila a ignorar en la regla unique (null en store).
     */
    protected function validatedData(Request $request, $id = null)
    {
        $data = $request->validate([
            'category_name'    => 'required|string|max:150',
            'category_name_en' => 'nullable|string|max:255',
            'regulation_badge' => 'required|in:CSATF,OSHA,STPS,DOT,SCT,GENERAL,FAA,CAL,SEDENA',
            'regulation_code'  => [
                'required', 'string', 'max:100',
                // Charset seguro: letras/dígitos ASCII + punto, barra, guion y espacio.
                // Barra escapada porque el delimitador es `/`.
                'regex:/^[A-Za-z0-9 .\/-]+$/',
                Rule::unique('safety_standards', 'regulation_code')->ignore($id),
            ],
            'reference_url'    => 'nullable|url|max:500',
        ]);

        return $data;
    }
}
