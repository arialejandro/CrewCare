<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PaeOrgChart — resuelve el ORGANIGRAMA DE EMERGENCIA del PAE desde el CREW de la
 * producción vigente (mismo criterio híbrido que MedevacContacts: si existe en el
 * crew se pre-llena desde ahí con su users.phone; donde no exista, el emisor lo
 * captura al emitir; el resultado final se CONGELA en el PAE).
 *
 * A diferencia del MEDEVAC —que colapsa la producción en un solo "Production
 * Manager"— el PAE distingue CUATRO puestos:
 *   · LINE PRODUCER → rol Spatie `line-producer` (o, si nadie lo tiene, el puesto
 *     "Productor en Línea", position_id 12).
 *   · UPM (Gerente de Unidad) → por PUESTO (production_user.position_id ∈ {14,15});
 *     el rol no lo distingue.
 *   · SAFETY → rol Spatie `safety-officer`.
 *   · SET MEDIC → rol Spatie `medic` (fuente única User::isMedic()).
 * Más DOS servicios FIJOS que no salen del crew: 911 (nacional) y el hospital (éste
 * es POR LOCACIÓN, así que lo pinta cada bloque de locación, no el organigrama).
 *
 * ⚠ SÓLO EL CREW DE ESTA PRODUCCIÓN. No hay fallback global a "cualquier safety de la
 *   app": pre-llenar en un plan de emergencias a alguien que NO está en este rodaje —
 *   con su teléfono— es un dato equivocado con apariencia de correcto en el campo que
 *   se lee corriendo cuando hay un herido. Si el puesto está vacante en el crew, el
 *   hueco se deja para que el emisor escriba al contacto REAL en set.
 *
 * Este resolutor sólo SUGIERE (pre-llena el formulario). Lo que se sella es lo que el
 * emisor confirmó, no lo que este método devolvió.
 *
 * DEFENSIVO: cada consulta se salta si su tabla no existe y nunca lanza. Devuelve los
 * cuatro slots siempre (name/phone vacíos si no hay quién).
 */
class PaeOrgChart
{
    /** Los 4 puestos del organigrama, con su etiqueta de oficio (convención del gremio). */
    const SLOTS = [
        ['key' => 'line_producer', 'label' => 'Line Producer'],
        ['key' => 'upm',           'label' => 'UPM'],
        ['key' => 'safety',        'label' => 'Safety'],
        ['key' => 'set_medic',     'label' => 'Set Medic'],
    ];

    /** Puesto "Productor en Línea" (respaldo del Line Producer si nadie tiene el rol). */
    const LINE_PRODUCER_POSITION_IDS = [12];

    /** Puestos de UPM (production_user.position_id), en orden de preferencia. */
    const UPM_POSITION_IDS = [14, 15];

    /** Servicio de emergencia FIJO (no sale del crew). El hospital va por locación. */
    const EMERGENCY_SERVICE = ['label' => 'Emergencias (911)', 'phone' => '911'];

    /**
     * Devuelve [['key','label','name','phone'], x4]. name/phone = '' si el slot está
     * vacante en el crew de la producción vigente.
     *
     * @param  int|null $productionId  por omisión, la producción vigente
     * @return array
     */
    public static function resolve($productionId = null): array
    {
        $pid = $productionId ?: CurrentProduction::id();
        $crewIds = self::crewUserIds($pid);

        $picked = [
            'line_producer' => self::firstByRole($crewIds, 'line-producer')
                               ?: self::firstByPosition($pid, self::LINE_PRODUCER_POSITION_IDS),
            'upm'           => self::firstByPosition($pid, self::UPM_POSITION_IDS),
            'safety'        => self::firstByRole($crewIds, 'safety-officer'),
            'set_medic'     => self::firstByRole($crewIds, 'medic'),
        ];

        $out = [];
        foreach (self::SLOTS as $slot) {
            $u = $picked[$slot['key']] ?? null;
            $out[] = [
                'key'   => $slot['key'],
                'label' => $slot['label'],
                'name'  => $u ? trim((string) $u->name) : '',
                'phone' => $u ? trim((string) ($u->phone ?? '')) : '',
            ];
        }
        return $out;
    }

    /** IDs de usuario del crew de la producción (pivote production_user). [] si no hay. */
    private static function crewUserIds($pid): array
    {
        if (! $pid || ! Schema::hasTable('production_user')) {
            return [];
        }
        try {
            return DB::table('production_user')
                ->where('production_id', $pid)
                ->pluck('user_id')
                ->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** Primer usuario del crew con el rol Spatie dado (con su teléfono). null si ninguno. */
    private static function firstByRole(array $crewIds, string $role)
    {
        if (! $crewIds) {
            return null;
        }
        try {
            return User::role($role)->whereIn('id', $crewIds)->orderBy('id')->first();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Usuario del crew por puesto (position_id), respetando el orden de preferencia. null si ninguno. */
    private static function firstByPosition($pid, array $positionIds)
    {
        if (! $pid || ! $positionIds || ! Schema::hasTable('production_user')) {
            return null;
        }
        try {
            $uid = DB::table('production_user')
                ->where('production_id', $pid)
                ->whereIn('position_id', $positionIds)
                ->orderByRaw('FIELD(position_id, ' . implode(',', array_map('intval', $positionIds)) . ')')
                ->value('user_id');
            return $uid ? User::find($uid) : null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
