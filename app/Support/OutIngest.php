<?php

namespace App\Support;

use App\Models\DepartmentOut;
use App\Models\IndividualOut;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * OutIngest — el SERVICIO que junta parser (§5) + ventana (§2) + registro, SEPARADO de la pantalla,
 * para que cualquier canal lo llame igual: la app (pegar un mensaje) y, apagado, el webhook de Meta.
 *
 * Construye los catálogos reales (departamentos + crew de la producción), pasa el texto por el parser
 * PURO, resuelve a qué día pertenece cada hora, y registra vía OutRegistrar. Devuelve un reporte con lo
 * que registró, lo que no entendió, y un `reply` humano (para que el bot confirme de vuelta). 🔑 Ante la
 * duda NO adivina: lo no resuelto se reporta, no se asigna.
 */
class OutIngest
{
    /**
     * Catálogos para el parser: departamentos activos (+ alias name_en) y crew de la producción.
     *
     * @return array{departments: array, crew: array}
     */
    public static function catalogs(int $productionId): array
    {
        $departments = [];
        $hasEn = Schema::hasColumn('departments', 'name_en');
        foreach (DB::table('departments')->where('active', 1)->get() as $d) {
            $aliases = [];
            if ($hasEn && ! empty($d->name_en)) {
                $aliases[] = $d->name_en;
            }
            $departments[] = ['id' => (int) $d->id, 'name' => $d->name, 'aliases' => $aliases];
        }

        $crew = [];
        $rows = DB::table('production_user as pu')
            ->join('users', 'users.id', '=', 'pu.user_id')
            ->where('pu.production_id', $productionId)
            ->where('users.activo', 1)
            ->get(['users.id as user_id', 'users.name', 'users.lname', 'users.lname2', 'users.ncreditos', 'pu.department_id']);
        foreach ($rows as $r) {
            $crew[] = [
                'user_id'       => (int) $r->user_id,
                'name'          => User::displayName($r),
                'department_id' => $r->department_id ? (int) $r->department_id : null,
            ];
        }

        return ['departments' => $departments, 'crew' => $crew];
    }

    /**
     * La ocurrencia PASADA más reciente de una hora 'HH:MM' respecto a $now (el out ya sucedió):
     * hoy a esa hora si ya pasó, si no ayer a esa hora.
     */
    public static function resolveOutAt(string $timeHHMM, Carbon $now): Carbon
    {
        $today = Carbon::parse($now->toDateString() . ' ' . $timeHHMM . ':00');

        return $today->lte($now) ? $today : $today->copy()->subDay();
    }

    /**
     * Procesa un mensaje de salida y registra lo que se entendió.
     *
     * @param  int[]|null  $allowedDeptIds  autoridad: deptos que el emisor puede registrar (null = todos).
     * @return array{registered: array, issues: array, reply: string}
     */
    public static function ingestText(
        string $text,
        int $productionId,
        ?int $unitId,
        string $source = DepartmentOut::SOURCE_APP,
        ?int $registeredById = null,
        ?Carbon $now = null,
        ?array $allowedDeptIds = null
    ): array {
        $now = $now ?: Carbon::now();
        $cat = self::catalogs($productionId);
        $parsed = OutMessageParser::parse($text, $cat['departments'], $cat['crew']);

        $registered = [];
        $issues = $parsed['issues'];
        $replyLines = [];

        // Mapa user_id → department_id (para la salida individual).
        $deptByUser = [];
        foreach ($cat['crew'] as $c) {
            $deptByUser[$c['user_id']] = $c['department_id'];
        }

        // --- DEPARTAMENTO ---
        if ($parsed['department'] !== null && $parsed['department_time'] !== null) {
            $deptId = (int) $parsed['department']['id'];
            if ($allowedDeptIds !== null && ! in_array($deptId, $allowedDeptIds, true)) {
                $issues[] = 'forbidden_department:' . $parsed['department']['name'];
                $replyLines[] = 'No tienes autoridad para registrar salidas de ' . $parsed['department']['name'] . '.';
            } else {
                $outAt = self::resolveOutAt($parsed['department_time'], $now);
                $win = OutWindow::resolveShootDate($outAt, $unitId, $productionId);
                if (! $win['resolved']) {
                    $issues[] = 'unassignable_department:' . $win['reason'];
                    $replyLines[] = 'La hora ' . $parsed['department_time'] . ' de ' . $parsed['department']['name']
                        . ' no cae en la ventana de ningún día. ¿A qué día de rodaje pertenece?';
                } else {
                    OutRegistrar::departmentOut(
                        $productionId, $unitId, $win['shoot_date'], $deptId, $outAt, $source, $registeredById
                    );
                    $registered[] = ['scope' => 'dept', 'department' => $parsed['department']['name'],
                                     'time' => $parsed['department_time'], 'shoot_date' => $win['shoot_date']];
                    $replyLines[] = $parsed['department']['name'] . ' salió ' . $parsed['department_time'] . '.';
                }
            }
        }

        // --- PERSONAS ---
        foreach ($parsed['individuals'] as $ind) {
            $uid = (int) $ind['user_id'];
            $deptId = $deptByUser[$uid] ?? null;
            if ($allowedDeptIds !== null && ($deptId === null || ! in_array((int) $deptId, $allowedDeptIds, true))) {
                $issues[] = 'forbidden_person:' . $ind['name'];

                continue;
            }
            $outAt = self::resolveOutAt($ind['time'], $now);
            $win = OutWindow::resolveShootDate($outAt, $unitId, $productionId);
            if (! $win['resolved']) {
                $issues[] = 'unassignable_person:' . $ind['name'] . ':' . $win['reason'];
                $replyLines[] = 'La hora ' . $ind['time'] . ' de ' . $ind['name']
                    . ' no cae en la ventana de ningún día.';

                continue;
            }
            OutRegistrar::individualOut(
                $productionId, $unitId, $win['shoot_date'], $uid, $deptId ? (int) $deptId : null,
                $outAt, $source, $registeredById
            );
            $registered[] = ['scope' => 'individual', 'name' => $ind['name'],
                             'time' => $ind['time'], 'shoot_date' => $win['shoot_date']];
            $replyLines[] = $ind['name'] . ' salió ' . $ind['time'] . '.';
        }

        // --- Reply humano (el bot confirma de vuelta lo que entendió) ---
        $reply = self::buildReply($parsed, $registered, $issues, $replyLines);

        return ['registered' => $registered, 'issues' => $issues, 'reply' => $reply];
    }

    /** Arma el mensaje de vuelta según lo entendido / no entendido. */
    private static function buildReply(array $parsed, array $registered, array $issues, array $replyLines): string
    {
        if (empty($registered) && empty($replyLines)) {
            if (in_array('empty', $issues, true) || in_array('no_time', $issues, true)) {
                return 'No entendí ninguna salida. Escríbelo así: «Cámara 18:30» (departamento y hora); '
                    . 'puedes añadir «Nombre Apellido 21:00».';
            }
            foreach ($issues as $iss) {
                if (strpos($iss, 'unknown_department:') === 0) {
                    $name = substr($iss, strlen('unknown_department:'));

                    return 'No reconocí el departamento «' . $name . '». Revisa el nombre y vuelve a mandarlo.';
                }
            }

            return 'No pude registrar la salida. Escríbelo así: «Departamento 18:30».';
        }

        $out = [];
        if (! empty($registered)) {
            $out[] = 'Registré: ' . implode(' ', $replyLines);
        } elseif (! empty($replyLines)) {
            $out[] = implode(' ', $replyLines);
        }

        return trim(implode(' ', $out));
    }
}
