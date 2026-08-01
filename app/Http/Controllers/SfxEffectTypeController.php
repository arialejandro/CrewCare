<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Consumable;
use App\Models\SfxEffectType;
use App\Support\Features;

/**
 * SfxEffectTypeController — CATÁLOGO de tipos de efecto especial (Capa A, Pilar 3,
 * flag 'sds_sfx'). Lectura de la doctrina + mantenimiento del puente N:M con los
 * insumos/SDS (Capa B).
 *
 * ── NO CONFUNDIR CON SfxController ──────────────────────────────────────────
 * `SfxController` (/sfx) es la BITÁCORA EN VIVO: un disparo real en set, con toggle
 * iniciar/detener e inyección al DSR del día. ESTO es el CATÁLOGO (/sfx-effects): no
 * ocurre, no se dispara, no tiene fecha. Aquí NO se captura un reporte y NO se toca
 * SfxEvent; los nombres se parecen a una letra de distancia, el eje es "tipo" vs
 * "instancia" (ver la cabecera del modelo SfxEffectType).
 *
 * Guardas en DOS niveles (ver guard() y layerGuard()): el FLAG apagado esconde el
 * módulo entero (404); la Capa A ausente en BD (prod sin el SQL del 4a) NO es un
 * error — degrada a empty-state en la lista y a corte suave en la ficha.
 */
class SfxEffectTypeController extends Controller
{
    /**
     * Guarda del FLAG — idéntica en criterio a la de ConsumableController: módulo
     * apagado ⇒ 404 (esconder Y bloquear, no solo ocultar el enlace del sidebar).
     *
     * OJO: aquí NO se comprueba la tabla, y su hermano SÍ lo hace dentro de guard().
     * La divergencia es deliberada: "Capa A ausente" no significa lo mismo en todos los
     * métodos de este controlador —index() debe pintar un empty-state y show()/attach()/
     * detach() sí deben cortar—, así que esa decisión la toma cada método con
     * isAvailable() / layerGuard() y no se puede centralizar aquí sin mentir.
     *
     * @return void
     */
    protected function guard()
    {
        if (!Features::enabled('sds_sfx')) {
            abort(404);
        }
    }

    /**
     * Corte suave para los métodos que no tienen NADA que enseñar ni que hacer sin la
     * Capa A. Devuelve una respuesta si hay que cortar, o null si se puede continuar.
     *
     * @return \Illuminate\Http\RedirectResponse|null
     */
    protected function layerGuard()
    {
        if (!SfxEffectType::isAvailable()) {
            return redirect()->route('consumables.index')
                ->with('error', 'El catálogo de tipos de efecto aún no está disponible (falta la migración de base de datos).');
        }
        return null;
    }

    /**
     * Lista de la doctrina, agrupable por familia.
     *
     * NO filtra por `is_active`: es la vista de CATÁLOGO y los pendientes/inactivos son
     * justo los que hay que poder ver para atenderlos (scopeActive() es para los
     * selectores del panel en vivo, no para esto).
     *
     * DECISIÓN — Capa A ausente ⇒ se pasan LAS DOS COSAS a la vista:
     *   · `$effects` = colección VACÍA, para que un @forelse ingenuo pinte su empty-state
     *     y quede correcto sin que la vista tenga que saber nada de esquemas; y
     *   · `$layerAvailable` = bandera, para que la vista PUEDA distinguir "aún no hay
     *     efectos capturados" de "este módulo no está instalado en esta instancia" —
     *     dos mensajes muy distintos para el owner.
     * Solo la colección vacía sería correcta pero MENTIROSA; solo la bandera rompería a
     * una vista que no la conociera. Las dos juntas degradan bien en ambos sentidos.
     */
    public function index()
    {
        $this->guard();

        $available = SfxEffectType::isAvailable();

        // withCount ⇒ el conteo de insumos ligados llega en la MISMA consulta (subselect):
        // la lista no dispara una consulta por fila para pintar su badge. Va detrás de
        // isAvailable() porque toca el pivote.
        $effects = $available
            ? SfxEffectType::withCount('consumables')
                ->orderBy('family')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
            : collect();

        return view('admin.sfx-effects.index', [
            'effects'        => $effects,
            'layerAvailable' => $available,
        ]);
    }

    /**
     * Ficha de doctrina + los insumos que usa. A diferencia de index(), sin la Capa A
     * aquí SÍ se corta (layerGuard): el usuario pidió ver UN registro concreto que no
     * puede existir; un empty-state en su lugar sería teatro.
     */
    public function show($id)
    {
        $this->guard();
        if ($resp = $this->layerGuard()) {
            return $resp;
        }

        // Se cargan las columnas completas del insumo A PROPÓSITO (sin select acotado):
        // la vista pinta el badge de pendiente, que sale de isPendingVerification() →
        // `verified_at`. Acotar el select ahorraría nada y rompería el badge en silencio.
        $effect = SfxEffectType::with([
            'consumables' => function ($q) {
                $q->orderBy('type')->orderBy('name');
            },
            'verifiedBy',
        ])->findOrFail($id);

        // Candidatos del selector de asociación: SOLO activos y SOLO los que aún no están
        // ligados (ofrecer uno ya ligado sería un no-op disfrazado de acción). Y solo si
        // quien mira puede asociar: sin `sds.manage` no hay selector que llenar, así que
        // no se paga la consulta.
        $candidates = collect();
        if (auth()->user() && auth()->user()->can('sds.manage')) {
            $candidates = Consumable::active()
                ->whereNotIn('id', $effect->consumables->pluck('id'))
                ->orderBy('type')
                ->orderBy('name')
                ->get();
        }

        return view('admin.sfx-effects.show', compact('effect', 'candidates'));
    }

    /**
     * Liga un insumo a este tipo de efecto (alta en el pivote).
     *
     * IDEMPOTENTE por syncWithoutDetaching(): asociar dos veces el mismo insumo no
     * duplica la fila ni revienta contra la UNIQUE del delta 4a — un doble clic, o un
     * "atrás + reenviar formulario", es un no-op silencioso y no un 500. Y, a diferencia
     * de sync(), NO desprende lo ya ligado: aquí se AÑADE uno, no se reemplaza la lista.
     */
    public function attach(Request $request, $id)
    {
        $this->guard();
        if ($resp = $this->layerGuard()) {
            return $resp;
        }

        // `exists` cierra el paso a un id inventado a mano en el POST: sin él, el pivote
        // aceptaría un vínculo huérfano (no hay FK dura garantizada en toda instancia).
        $data = $request->validate([
            'consumable_id' => 'required|integer|exists:consumables,id',
        ]);

        $effect     = SfxEffectType::findOrFail($id);
        $consumable = Consumable::findOrFail($data['consumable_id']);

        $effect->consumables()->syncWithoutDetaching([$consumable->id]);

        return redirect()->route('sfx-effects.show', $effect->id)
            ->with('success', 'Insumo asociado: «'.$consumable->name.'».');
    }

    /**
     * Desliga un insumo de este tipo de efecto (baja en el pivote). NO borra la ficha
     * del insumo: solo el vínculo.
     *
     * IDEMPOTENTE de nacimiento: detach() de algo que no está ligado devuelve 0 y no
     * lanza. El insumo se busca con find() (no findOrFail) porque el vínculo puede
     * sobrevivir a la ficha en una instancia sin FK dura; en ese caso se desliga igual y
     * el flash cae al id. Deshacer una asociación NUNCA debe morir con un 404.
     */
    public function detach($id, $consumableId)
    {
        $this->guard();
        if ($resp = $this->layerGuard()) {
            return $resp;
        }

        $effect     = SfxEffectType::findOrFail($id);
        $consumable = Consumable::find($consumableId);

        $effect->consumables()->detach($consumableId);

        $label = $consumable ? $consumable->name : '#'.$consumableId;

        return redirect()->route('sfx-effects.show', $effect->id)
            ->with('success', 'Insumo desasociado: «'.$label.'».');
    }
}
