<?php

namespace App\Support;

use App\Models\Department;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * InvolvedResolver — todo lo que gira alrededor de la "persona involucrada" (involved_user_id) de
 * un reporte de seguridad, apoyado en el pivote production_user (fuente de verdad del departamento).
 *
 * (2026-07-24) El nombre del involucrado NO viaja al documento (nombrar individuos crea cultura
 * punitiva). Este resolver da lo que SÍ se necesita:
 *   - departmentNames()          → el ÁREA (lo único que se imprime).
 *   - viewerLeadsInvolvedDept()  → ¿el que mira es el JEFE (is_lead) del depto del involucrado?
 *                                  (gateo de visibilidad del nombre en la app, además de Safety).
 *   - leadRecipients()           → correos de los lead(s) del depto del involucrado (a quién avisar).
 *
 * Alcance = producción activa (convención del resto del código: "Producción Demo"). Todo defensivo:
 * si falta el pivote o la producción, regresa vacío/false sin romper nada.
 */
class InvolvedResolver
{
    /**
     * Id de la producción activa. (2026-07-24) Delegado a App\Support\CurrentProduction: era el
     * sexto lugar que buscaba la producción por el nombre literal 'Producción Demo'. Aquí ese
     * acoplamiento costaba caro: si el nombre cambia, esto devuelve null, y con null el aviso al
     * JEFE DEL DEPARTAMENTO del involucrado deja de salir — sin error, sin log, sin correo.
     */
    private static function activeProductionId()
    {
        return CurrentProduction::id();
    }

    /** department_id(s) del usuario en la producción activa (colección de enteros). */
    public static function departmentIds($userId): array
    {
        $pid = self::activeProductionId();
        if (!$pid || !$userId || !Schema::hasTable('production_user')) {
            return [];
        }
        try {
            return DB::table('production_user')
                ->where('production_id', $pid)
                ->where('user_id', $userId)
                ->whereNotNull('department_id')
                ->pluck('department_id')->unique()->values()->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** Nombre(s) de departamento del involucrado, unidos con " · " (lo ÚNICO que se imprime). */
    public static function departmentNames($userId): string
    {
        $ids = self::departmentIds($userId);
        if (!$ids) {
            return '';
        }
        try {
            return Department::whereIn('id', $ids)->orderBy('name')->pluck('name')->implode(' · ');
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * ¿$viewerId es lead (is_lead=1) de ALGÚN departamento del involucrado, en la producción activa?
     * Sirve para revelar el nombre del involucrado al JEFE DIRECTO además de a Safety.
     */
    public static function viewerLeadsInvolvedDept($viewerId, $involvedUserId): bool
    {
        $ids = self::departmentIds($involvedUserId);
        $pid = self::activeProductionId();
        if (!$ids || !$pid || !$viewerId) {
            return false;
        }
        try {
            return DB::table('production_user')
                ->where('production_id', $pid)
                ->where('user_id', $viewerId)
                ->whereIn('department_id', $ids)
                ->where('is_lead', 1)
                ->exists();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Destinatarios (name/email) = lead(s) del/los depto(s) del involucrado, en la producción activa.
     * Se excluye al propio involucrado (por si él mismo es el lead). Correos válidos y deduplicados.
     *
     * @return array<int, array{name:string, email:string}>
     */
    public static function leadRecipients($involvedUserId): array
    {
        $ids = self::departmentIds($involvedUserId);
        $pid = self::activeProductionId();
        if (!$ids || !$pid) {
            return [];
        }
        try {
            $rows = DB::table('users')
                ->join('production_user as pu', 'pu.user_id', '=', 'users.id')
                ->where('pu.production_id', $pid)
                ->whereIn('pu.department_id', $ids)
                ->where('pu.is_lead', 1)
                ->where('users.id', '!=', $involvedUserId)
                ->distinct()
                ->get(['users.name', 'users.email']);

            $out = [];
            $seen = [];
            foreach ($rows as $r) {
                $email = trim((string) $r->email);
                $key = mb_strtolower($email);
                if ($email === '' || isset($seen[$key]) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    continue;
                }
                $seen[$key] = true;
                $out[] = ['name' => (string) $r->name, 'email' => $email];
            }
            return $out;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * (2026-07-26) Variante para la vertical de INSPECCIÓN DE HERRAMIENTA: el acta
     * guarda un department_id DIRECTO (el safety lo eligió), no un usuario involucrado.
     * Destinatarios = lead(s) is_lead=1 de ESE departamento en la producción activa.
     * A quién avisar cuando una inspección produce PARO. Mismo blindaje: correos
     * válidos, deduplicados; vacío si falta pivote/producción/departamento.
     *
     * @return array<int, array{name:string, email:string}>
     */
    public static function leadRecipientsForDepartment($departmentId): array
    {
        $pid = self::activeProductionId();
        if (! $departmentId || ! $pid || ! Schema::hasTable('production_user')) {
            return [];
        }
        try {
            $rows = DB::table('users')
                ->join('production_user as pu', 'pu.user_id', '=', 'users.id')
                ->where('pu.production_id', $pid)
                ->where('pu.department_id', $departmentId)
                ->where('pu.is_lead', 1)
                ->distinct()
                ->get(['users.name', 'users.email']);

            $out = [];
            $seen = [];
            foreach ($rows as $r) {
                $email = trim((string) $r->email);
                $key = mb_strtolower($email);
                if ($email === '' || isset($seen[$key]) || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    continue;
                }
                $seen[$key] = true;
                $out[] = ['name' => (string) $r->name, 'email' => $email];
            }
            return $out;
        } catch (\Throwable $e) {
            return [];
        }
    }
}
