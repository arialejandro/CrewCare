<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * MedevacContacts — resuelve los TRES contactos de emergencia del póster MEDEVAC
 * desde el CREW de la producción vigente (decisión del owner: híbrido — si existe en
 * el crew se llama desde ahí, si no, el emisor lo captura al emitir).
 *
 *   · SET MEDIC        → rol Spatie `medic` (fuente única User::isMedic()).
 *   · RISK ASSESSMENT  → rol Spatie `safety-officer`.
 *   · PRODUCTION MANAGER → por PUESTO (production_user.position_id ∈ {13,14,16} =
 *     Gerente de Producción / Gerente de Unidad / Coordinador de Producción — el ROL no
 *     los distingue, por eso van por puesto; mismos IDs que SafetyAlertRecipients).
 *
 * ⚠ SÓLO EL CREW DE ESTA PRODUCCIÓN. No hay fallback global a "cualquier médico de la
 *   app": pre-llenar en el póster de emergencias a alguien que NO está en este rodaje —
 *   con su teléfono — es la misma clase de bug que el hospital heredado de otra locación:
 *   un dato equivocado con apariencia de correcto en el campo que se lee corriendo cuando
 *   hay un herido. Si el puesto está vacante en el crew, el hueco se deja para que el
 *   emisor escriba al contacto REAL en set. El resultado final se CONGELA en el póster.
 *
 * Este resolutor sólo SUGIERE (pre-llena el formulario de emisión). Lo que se sella es lo
 * que el emisor confirmó, no lo que este método devolvió.
 *
 * DEFENSIVO: cada consulta se salta si su tabla no existe y nunca lanza. Devuelve los tres
 * slots siempre (name/phone vacíos si no hay quién).
 */
class MedevacContacts
{
    /** Los 3 slots del póster, con su etiqueta de oficio (convención del gremio: en inglés). */
    const SLOTS = [
        ['key' => 'set_medic',          'label' => 'Set Medic'],
        ['key' => 'risk_assessment',    'label' => 'Risk Assessment'],
        ['key' => 'production_manager', 'label' => 'Production Manager'],
    ];

    /** Puestos de "production manager" (production_user.position_id), en orden de preferencia. */
    const PM_POSITION_IDS = [13, 14, 16];

    /**
     * Devuelve [['key','label','name','phone'], x3]. name/phone = '' si el slot está vacante
     * en el crew de la producción vigente.
     *
     * @param  int|null $productionId  por omisión, la producción vigente
     * @return array
     */
    public static function resolve($productionId = null): array
    {
        $pid = $productionId ?: CurrentProduction::id();
        $crewIds = self::crewUserIds($pid);

        $picked = [
            'set_medic'          => self::firstByRole($crewIds, 'medic'),
            'risk_assessment'    => self::firstByRole($crewIds, 'safety-officer'),
            'production_manager' => self::firstByPosition($pid, self::PM_POSITION_IDS),
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
