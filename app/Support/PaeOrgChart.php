<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PaeOrgChart — resuelve el ORGANIGRAMA DE EMERGENCIA del PAE desde el CREW de la
 * producción vigente (criterio híbrido: si existe en el crew se pre-llena con su
 * users.phone; donde no exista, el emisor lo captura al emitir; el resultado final
 * se CONGELA en el PAE).
 *
 * Los roles siguen el organigrama estándar de un PAE de rodaje (Rol · Nombre ·
 * Teléfono · Canal de radio). CrewCare RESUELVE los tres que conoce por rol/puesto;
 * el resto son captura manual (nadie en el crew tiene ese rol como dato):
 *   · COORDINADOR DE EMERGENCIA (Producción/UPM) → puesto 14/13/15, si no rol `line-producer`.
 *   · SEGURIDAD EN SET (Safety)                   → rol `safety-officer`.
 *   · COORDINACIÓN MÉDICA (Set Medic)             → rol `medic` (User::isMedic()).
 *   · LOCACIONES Y TRANSPORTACIÓN · BRIGADA CONTRA INCENDIOS · SEGURIDAD SPFX/STUNTS ·
 *     EXTRAS/BACKGROUND                            → manual (se capturan antes del rodaje).
 *
 * ⚠ SÓLO EL CREW DE ESTA PRODUCCIÓN. No hay fallback global: pre-llenar en un plan de
 *   emergencias a alguien que NO está en este rodaje es un dato equivocado con apariencia
 *   de correcto. Si el puesto está vacante, el hueco se deja para el contacto REAL en set.
 *
 * Este resolutor sólo SUGIERE. Lo que se sella es lo que el emisor confirmó.
 * DEFENSIVO: cada consulta se salta si su tabla no existe y nunca lanza.
 */
class PaeOrgChart
{
    /**
     * Los puestos del organigrama, con su etiqueta de oficio y si CrewCare los resuelve.
     * `resolve` = clave de resolución (role:x / pos / null=manual).
     */
    const SLOTS = [
        ['key' => 'coordinador_emergencia', 'label' => 'Coordinador de emergencia'],   // = Safety (owner 2026-08-06)
        ['key' => 'set_medic',              'label' => 'Coordinación médica (Set Medic)'],
        ['key' => 'produccion_upm',         'label' => 'Producción / UPM'],
        ['key' => 'locaciones_transporte',  'label' => 'Locaciones y transportación'],
        ['key' => 'brigada_incendios',      'label' => 'Brigada contra incendios'],
        ['key' => 'spfx_stunts',            'label' => 'Seguridad SPFX / Stunts'],
        ['key' => 'extras_background',      'label' => 'Extras / Background'],
    ];

    /** Puestos de "coordinador de emergencia / producción" (production_user.position_id). */
    const COORDINATOR_POSITION_IDS = [14, 13, 15];

    /** Servicio de emergencia FIJO (no sale del crew). El hospital va por locación. */
    const EMERGENCY_SERVICE = ['label' => 'Emergencias (911)', 'phone' => '911'];

    /**
     * Devuelve [['key','label','name','phone','radio'], ...]. name/phone = '' si el slot
     * está vacante en el crew; radio siempre '' (captura manual, no hay fuente).
     *
     * @param  int|null $productionId  por omisión, la producción vigente
     * @return array
     */
    public static function resolve($productionId = null): array
    {
        $pid = $productionId ?: CurrentProduction::id();
        $crewIds = self::crewUserIds($pid);

        $picked = [
            // El coordinador de emergencia es el SAFETY (no el UPM) — decisión del owner.
            'coordinador_emergencia' => self::firstByRole($crewIds, 'safety-officer'),
            'set_medic'              => self::firstByRole($crewIds, 'medic'),
            'produccion_upm'         => self::firstByPosition($pid, self::COORDINATOR_POSITION_IDS)
                                        ?: self::firstByRole($crewIds, 'line-producer'),
            // locaciones_transporte, brigada_incendios, spfx_stunts, extras_background → manual
        ];

        $out = [];
        foreach (self::SLOTS as $slot) {
            $u = $picked[$slot['key']] ?? null;
            $out[] = [
                'key'   => $slot['key'],
                'label' => $slot['label'],
                'name'  => $u ? self::displayName($u) : '',
                'phone' => $u ? trim((string) ($u->phone ?? '')) : '',
                'radio' => '',
            ];
        }
        return $out;
    }

    /**
     * Nombre a mostrar: el NOMBRE EN CRÉDITOS (`users.ncreditos`) cuando existe; si no, el
     * nombre de pila (parcial, como se hacía). Nunca se inventa. (owner 2026-08-06)
     */
    private static function displayName($u): string
    {
        $cred = trim((string) ($u->ncreditos ?? ''));
        if ($cred !== '') {
            return $cred;
        }
        return trim((string) $u->name);
    }

    /** IDs de usuario del crew de la producción (pivote production_user). [] si no hay. */
    private static function crewUserIds($pid): array
    {
        if (! $pid || ! Schema::hasTable('production_user')) {
            return [];
        }
        try {
            return DB::table('production_user')->where('production_id', $pid)->pluck('user_id')->all();
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
