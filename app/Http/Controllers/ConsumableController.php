<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use App\Models\Consumable;
use App\Models\SfxEffectType;
use App\Support\Features;

/**
 * ConsumableController — CRUD del catálogo de SDS/consumibles SFX (Pilar 3, flag 'sds_sfx').
 *
 * Guardas transversales (guard()): feature apagada → 404; tabla ausente (prod sin el SQL)
 * → redirect suave con aviso. Así el módulo nunca truena si aún no se migró la BD.
 */
class ConsumableController extends Controller
{
    /**
     * Guarda común. Devuelve una respuesta si hay que CORTAR (tabla ausente), o null
     * si se puede continuar. La feature apagada aborta con 404 (nunca retorna).
     *
     * @return \Illuminate\Http\RedirectResponse|null
     */
    protected function guard()
    {
        if (!Features::enabled('sds_sfx')) {
            abort(404);
        }
        if (!Schema::hasTable('consumables')) {
            return redirect()->route('home')
                ->with('error', 'El módulo SDS/consumibles aún no está disponible (falta la migración de base de datos).');
        }
        return null;
    }

    public function index()
    {
        if ($resp = $this->guard()) {
            return $resp;
        }

        // El global scope de SoftDeletes ya excluye las fichas retiradas de esta lista.
        $consumables = Consumable::orderBy('type')->orderBy('name')->get();

        // Papelera: SOLO super-admin. Para el resto de roles las fichas retiradas NO
        // existen (el global scope las oculta), así que ni se consultan. Sin el delta de
        // soft delete no hay papelera (supportsSoftDelete()=false → onlyTrashed no existe).
        $trashed = null;
        if (Consumable::supportsSoftDelete() && auth()->check() && auth()->user()->hasRole('super-admin')) {
            $trashed = Consumable::onlyTrashed()->orderBy('type')->orderBy('name')->get();
        }

        return view('admin.consumables.index', compact('consumables', 'trashed'));
    }

    /**
     * Ficha completa en SOLO LECTURA (Paso 4d): la HDS de 16 secciones que el Safety
     * consulta antes de disparar un efecto. No muta nada, por eso vive en `sds.view` y
     * no en `sds.create` — y por eso se declara aquí, junto a index(), su hermana del
     * mismo escalón de permiso (este archivo agrupa por autoridad, no por orden REST:
     * verify() ya vive entre update() y destroy() por el mismo criterio).
     */
    public function show($id)
    {
        if ($resp = $this->guard()) {
            return $resp;
        }

        $consumable = Consumable::findOrFail($id);

        // Cada relación SOLO si su respaldo existe en BD. Declarar un belongsTo/belongsToMany
        // es perezoso y nunca truena; EJECUTARLO contra una columna o una tabla ausente sí.
        if (Consumable::supportsVerification()) {
            $consumable->load('verifiedBy');
        }

        // Capa A (Paso 4a): qué EFECTOS usan este insumo. Mismo orden que la lista de
        // efectos (family → sort_order → name) para que el usuario no vea dos criterios.
        if (SfxEffectType::isAvailable()) {
            $consumable->load(['sfxEffectTypes' => function ($q) {
                $q->orderBy('family')->orderBy('sort_order')->orderBy('name');
            }]);
        }

        return view('admin.consumables.show', compact('consumable'));
    }

    public function create()
    {
        if ($resp = $this->guard()) {
            return $resp;
        }

        return view('admin.consumables.create');
    }

    public function store(Request $request)
    {
        if ($resp = $this->guard()) {
            return $resp;
        }

        $data = $this->validatedData($request);

        // Nace VERIFICADA si quien la crea ya es autoridad verificadora (sin fricción para el admin);
        // si la captura un Safety en set, nace pendiente hasta que un sds.manage la valide.
        if (Consumable::supportsVerification() && $request->user() && $request->user()->can('sds.manage')) {
            $data['verified_at']    = now();
            $data['verified_by_id'] = $request->user()->id;
        }

        Consumable::create($data);

        return redirect()->route('consumables.index')->with('success', 'Consumible / SDS creado.');
    }

    public function edit($id)
    {
        if ($resp = $this->guard()) {
            return $resp;
        }

        $consumable = Consumable::findOrFail($id);

        // El rastro de quién validó solo se puede cargar si la BD tiene el delta;
        // sin él, la relación reventaría por columna inexistente.
        if (Consumable::supportsVerification()) {
            $consumable->load('verifiedBy');
        }

        return view('admin.consumables.edit', compact('consumable'));
    }

    /**
     * El sello de verificación NO sobrevive a un cambio MATERIAL hecho por quien no es
     * autoridad: si un Safety cambia `hazards` de "INOCUO" a "ALTAMENTE INFLAMABLE", el
     * sello anterior avalaría contenido que el verificador nunca vio → la ficha vuelve a
     * PENDIENTE. Si el cambio material lo hace un `sds.manage`, se re-sella a su nombre
     * (está viendo el cambio en el momento de hacerlo). Cambios no materiales
     * (sort_order/is_active/description) no tocan el sello.
     */
    public function update(Request $request, $id)
    {
        if ($resp = $this->guard()) {
            return $resp;
        }

        $consumable = Consumable::findOrFail($id);
        $data = $this->validatedData($request);

        // Se decide ANTES del update(): después, getOriginal() ya devolvería los valores
        // nuevos (save() re-sincroniza el original) y la comparación siempre daría "sin cambios".
        $reopened = false;
        if (Consumable::supportsVerification() && $consumable->isVerified()
            && $this->touchesMaterialFields($consumable, $data)) {
            if ($request->user() && $request->user()->can('sds.manage')) {
                $data['verified_at']    = now();
                $data['verified_by_id'] = $request->user()->id;
            } else {
                $data['verified_at']    = null;
                $data['verified_by_id'] = null;
                $reopened = true;
            }
        }

        $consumable->update($data);

        // Volver a pendiente es un CAMBIO DE ESTADO: no puede pasar en silencio detrás del
        // flash genérico de "actualizado", o quien edita creerá que la ficha sigue avalada.
        $message = $reopened
            ? 'Consumible actualizado. Cambiaron datos materiales, así que la ficha volvió a PENDIENTE DE VERIFICACIÓN.'
            : 'Consumible / SDS actualizado.';

        return redirect()->route('consumables.index')->with('success', $message);
    }

    /**
     * Valida una ficha capturada en campo (Paso 1c). La autoridad la gatea la RUTA
     * (permission:sds.manage), por eso aquí no se re-checa el permiso. Idempotente:
     * re-verificar una ficha ya validada NO es un error ni repisa el rastro original.
     */
    public function verify(Request $request, $id)
    {
        if ($resp = $this->guard()) {
            return $resp;
        }

        $consumable = Consumable::findOrFail($id);

        // Sin el delta de BD aplicado no existe el concepto de verificación.
        if (!Consumable::supportsVerification()) {
            return redirect()->route('consumables.index')
                ->with('error', 'La verificación de fichas no está disponible en esta instancia.');
        }

        if ($consumable->isVerified()) {
            return redirect()->route('consumables.index')
                ->with('success', 'Esta ficha ya estaba verificada.');
        }

        $consumable->update([
            'verified_at'    => now(),
            'verified_by_id' => $request->user()->id,
        ]);

        return redirect()->route('consumables.index')
            ->with('success', 'Ficha verificada: «'.$consumable->name.'».');
    }

    /**
     * "Eliminar" = RETIRAR (soft delete, Paso 1b). La ficha se conserva y desaparece del
     * catálogo para todos los roles; solo un super-admin la ve en la papelera y puede
     * restaurarla. Sin el delta de soft delete aplicado, delete() sigue siendo DURO (lo
     * resuelve Consumable::performDeleteOnModel de forma defensiva) y el mensaje se ajusta.
     */
    public function destroy($id)
    {
        if ($resp = $this->guard()) {
            return $resp;
        }

        Consumable::findOrFail($id)->delete();

        $message = Consumable::supportsSoftDelete()
            ? 'Consumible desactivado. Queda archivado (solo visible para un super administrador) y puede restaurarse.'
            : 'Consumible / SDS eliminado.';

        return redirect()->route('consumables.index')->with('success', $message);
    }

    /**
     * Restaura una ficha retirada, devolviéndola al catálogo. SOLO super-admin (lo gatea
     * la RUTA con role:super-admin, por eso aquí no se re-checa). Defensivo: sin el delta,
     * no hay papelera que restaurar.
     */
    public function restore($id)
    {
        if ($resp = $this->guard()) {
            return $resp;
        }

        if (!Consumable::supportsSoftDelete()) {
            return redirect()->route('consumables.index')
                ->with('error', 'La papelera de consumibles no está disponible en esta instancia.');
        }

        $consumable = Consumable::onlyTrashed()->findOrFail($id);
        $consumable->restore();

        return redirect()->route('consumables.index')
            ->with('success', 'Consumible restaurado: «'.$consumable->name.'».');
    }

    /**
     * Destrucción FÍSICA definitiva de una ficha YA retirada (no se puede deshacer). SOLO
     * super-admin. Esto sí purga el pivote N:M (el hook `deleting` ve isForceDeleting()).
     * Se exige que la ficha esté ya en la papelera (onlyTrashed): no hay atajo para
     * saltarse el retiro previo.
     */
    public function forceDestroy($id)
    {
        if ($resp = $this->guard()) {
            return $resp;
        }

        if (!Consumable::supportsSoftDelete()) {
            return redirect()->route('consumables.index')
                ->with('error', 'La papelera de consumibles no está disponible en esta instancia.');
        }

        $consumable = Consumable::onlyTrashed()->findOrFail($id);
        $name = $consumable->name;
        $consumable->forceDelete();

        return redirect()->route('consumables.index')
            ->with('success', 'Consumible eliminado definitivamente: «'.$name.'».');
    }

    /**
     * Validación compartida de store/update. name/type obligatorios; el resto opcional.
     * Normaliza is_active (checkbox → 0/1) y sort_order (default 0).
     */
    protected function validatedData(Request $request)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'type'        => 'required|string|max:40',
            'description' => 'nullable|string',
            'hazards'     => 'nullable|string',
            'precautions' => 'nullable|string',
            'signal_word' => 'nullable|string|max:40',
            'un_number'   => 'nullable|string|max:40',
            'sds_url'     => 'nullable|string|max:2048',
            'is_active'   => 'nullable',
            'sort_order'  => 'nullable|integer',
        ]);

        $data['is_active']  = $request->boolean('is_active') ? 1 : 0;
        $data['sort_order'] = isset($data['sort_order']) ? (int) $data['sort_order'] : 0;

        return $data;
    }

    /**
     * Campos MATERIALES de la ficha (lista cerrada): los que cambian lo que la SDS AFIRMA
     * sobre el material, o sea lo que el verificador realmente avaló al sellarla. Tocar
     * uno invalida el sello. Fuera de la lista a propósito: `sort_order` e `is_active`
     * (presentación/vigencia) y `description` (texto de apoyo, no afirma peligros).
     */
    protected function materialFields()
    {
        return ['name', 'type', 'hazards', 'precautions', 'signal_word', 'un_number', 'sds_url'];
    }

    /**
     * ¿El envío cambia algún campo material respecto a lo guardado en BD?
     *
     * La comparación normaliza a string A PROPÓSITO: para un campo vacío el formulario
     * manda indistintamente `null` o `''`, y compararlos en crudo (null !== '') daría
     * falsos positivos que re-abrirían fichas sin que nadie haya tocado nada.
     */
    protected function touchesMaterialFields(Consumable $consumable, array $data)
    {
        foreach ($this->materialFields() as $f) {
            // Campo que no viene en este envío: no se pudo haber cambiado.
            if (!array_key_exists($f, $data)) {
                continue;
            }
            if ((string) ($consumable->getOriginal($f) ?? '') !== (string) ($data[$f] ?? '')) {
                return true;
            }
        }

        return false;
    }
}
