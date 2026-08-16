<?php

namespace App\Support;

use App\Exceptions\ContractEnvelopeException;
use App\Models\ContractEnvelopeRecipient;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * EL CONTRATO · PASO C — LA RUTA por PUESTO. El puesto DEFINE quién aparece en la ruta (configurado
 * por producción); el sobre CONGELA a la persona al crearse. El puesto NO otorga accesos.
 *
 * Reusa el patrón de {@see \App\Support\SafetyAlertRecipients}/{@see \App\Support\MedevacContacts}:
 * resolver por `production_user.position_id`. La diferencia: aquí el id de puesto es CONFIGURABLE por
 * producción (settings), no una constante en código — porque cada productora asigna distinto.
 *
 * Si el puesto está VACANTE o lo ocupan DOS → error claro (no se elige por cuenta propia).
 */
class SignaturePositions
{
    const KEY_PREPARER = 'contract_preparer_position_id';
    const KEY_BINDER   = 'contract_binder_position_id';
    const KEY_ORDER    = 'contract_route_order';

    // MÓDULO DE FIRMA (config global, al iniciar el proyecto). Dos listas ordenadas de PUESTOS:
    //  - KEY_SIGNERS: los FIRMANTES del contrato (paso 4). Si está configurada, el sobre usa
    //    contratado + N firmantes (generaliza el clásico preparador/obliga, que queda de fallback).
    //  - KEY_AUTHORIZERS: quién AUTORIZA el Infosheet (paso 2). Por defecto el Line Producer.
    const KEY_SIGNERS     = 'contract_signer_position_ids';
    const KEY_AUTHORIZERS = 'infosheet_authorizer_position_ids';
    // B3 · ESCALERA de autorización: si está ON, los autorizadores aprueban EN ORDEN (nivel a nivel);
    // OFF (default) = paralelo (cualquiera en cualquier orden, comportamiento histórico).
    const KEY_AUTH_SEQUENTIAL = 'infosheet_auth_sequential';
    // B4 · RUTEO de firma: si está ON, los FIRMANTES firman en CUALQUIER orden (paralelo, tipo
    // DocuSign "sin orden de firma"); OFF (default) = secuencial estricto (turno a turno).
    const KEY_SIGN_PARALLEL = 'contract_sign_parallel';
    // B5 · RESOLVEDOR CONDICIONAL: reglas por IMPORTE que agregan un firmante EXTRA a la ruta
    // (p.ej. "arriba de $50,000, firma también el Line Producer"). JSON: [{min, entry}].
    const KEY_CONDITIONAL_SIGNERS = 'contract_conditional_signers';

    // Entrada DINÁMICA: "el HOD (jefe) del departamento del contrato" — así el jefe del depto
    // relevante firma sin fijar un puesto por-departamento (que afectaría a todos por igual).
    const DEPT_HOD = 'dept_hod';

    // Solo estos deptos aportan firmantes/autorizadores (cabezas de producción) — no un asistente
    // de arte ni un coordinador de vestuario. El HOD de cada depto entra por la entrada DEPT_HOD.
    const SIGNER_DEPARTMENTS = ['Producción', 'Oficina de Producción', 'Producción Ejecutiva', 'Contabilidad'];

    public static function preparerPositionId(): ?int
    {
        $v = (int) Branding::get(self::KEY_PREPARER, 0);
        return $v > 0 ? $v : null;
    }

    public static function binderPositionId(): ?int
    {
        $v = (int) Branding::get(self::KEY_BINDER, 0);
        return $v > 0 ? $v : null;
    }

    /** Orden de la ruta (configurable). Por defecto: preparador → contratado → obliga. */
    public static function routeOrder(): array
    {
        $default = [
            ContractEnvelopeRecipient::ROLE_PREPARER,
            ContractEnvelopeRecipient::ROLE_CONTRACTED,
            ContractEnvelopeRecipient::ROLE_BINDER,
        ];
        $raw = trim((string) Branding::get(self::KEY_ORDER, ''));
        if ($raw === '') {
            return $default;
        }
        $order = array_values(array_filter(array_map('trim', explode(',', $raw)),
            fn ($r) => in_array($r, $default, true)));
        // Debe cubrir los tres papeles; si no, cae al default para no dejar a nadie fuera de la ruta.
        return count(array_unique($order)) === 3 ? $order : $default;
    }

    /** Entradas de FIRMANTES (paso 4): ids de puesto y/o el token DEPT_HOD, EN ORDEN. */
    public static function signerEntries(): array
    {
        return self::decodeEntries(Branding::get(self::KEY_SIGNERS, ''));
    }

    /** ¿Hay lista de firmantes configurada? Decide entre nuevo (N firmantes) y clásico (prep/obliga). */
    public static function hasSignerList(): bool
    {
        return count(self::signerEntries()) > 0;
    }

    /** Entradas de AUTORIZADORES (paso 2): ids de puesto y/o DEPT_HOD. Vacía = default Line Producer. */
    public static function authorizerEntries(): array
    {
        return self::decodeEntries(Branding::get(self::KEY_AUTHORIZERS, ''));
    }

    /** B3 · ¿La autorización es una ESCALERA secuencial (nivel a nivel)? Default paralelo. */
    public static function authSequential(): bool
    {
        return (bool) (int) Branding::get(self::KEY_AUTH_SEQUENTIAL, 0);
    }

    /** B4 · ¿La firma es PARALELA (cualquier orden)? Default secuencial (turno a turno). */
    public static function signParallel(): bool
    {
        return (bool) (int) Branding::get(self::KEY_SIGN_PARALLEL, 0);
    }

    /**
     * B5 · Reglas condicionales de firma (por importe). Cada regla: {min: float, entry: id|'dept_hod'}.
     * Saneadas (min>0, entry válido). El orden se conserva (JSON de la config).
     *
     * @return array<int,array{min:float, entry:int|string}>
     */
    public static function conditionalSignerRules(): array
    {
        $raw  = Branding::get(self::KEY_CONDITIONAL_SIGNERS, '');
        $list = is_array($raw) ? $raw : (json_decode((string) $raw, true) ?: []);
        $out  = [];
        foreach ((array) $list as $rule) {
            $min   = (float) ($rule['min'] ?? 0);
            $entry = $rule['entry'] ?? null;
            if ($min <= 0 || $entry === null) {
                continue;
            }
            $entry = ($entry === self::DEPT_HOD) ? self::DEPT_HOD : ((int) $entry > 0 ? (int) $entry : null);
            if ($entry === null) {
                continue;
            }
            $out[] = ['min' => $min, 'entry' => $entry];
        }
        return $out;
    }

    /** B5 · Entradas de firmante EXTRA que aplican a un contrato de este importe de honorarios. */
    public static function conditionalSignerEntries(float $amount): array
    {
        $entries = [];
        foreach (self::conditionalSignerRules() as $rule) {
            if ($amount >= $rule['min']) {
                $entries[] = $rule['entry'];
            }
        }
        return $entries;
    }

    /** Decodifica entradas (JSON o CSV): enteros positivos (position_id) + el token 'dept_hod'. */
    private static function decodeEntries($raw): array
    {
        if (is_array($raw)) {
            $list = $raw;
        } else {
            $s = trim((string) $raw);
            if ($s === '') {
                return [];
            }
            $decoded = json_decode($s, true);
            $list = is_array($decoded) ? $decoded : array_map('trim', explode(',', $s));
        }
        $out = [];
        foreach ($list as $v) {
            if ($v === self::DEPT_HOD) {
                $out[] = self::DEPT_HOD;
            } elseif ((int) $v > 0) {
                $out[] = (int) $v;
            }
        }
        return $out;
    }

    /** Etiqueta legible de una entrada (puesto, o "HOD del departamento" para la dinámica). */
    public static function entryLabel($entry): string
    {
        return $entry === self::DEPT_HOD ? __('HOD del departamento') : self::positionLabel((int) $entry);
    }

    /** Nombre legible de un puesto (para errores y para el `cargo` congelado del destinatario). */
    public static function positionLabel(?int $positionId): string
    {
        $name = $positionId ? optional(\App\Models\Position::find($positionId))->name : null;
        return $name ?: __('Firmante');
    }

    /** El JEFE (is_lead) del departamento en la producción — resuelve la entrada dinámica DEPT_HOD. */
    public static function departmentHodUser(int $productionId, ?int $departmentId, string $label): User
    {
        if (! $departmentId) {
            throw ContractEnvelopeException::vacantPosition($label . ' — ' . __('el contrato no tiene departamento'));
        }
        $userIds = DB::table('production_user')
            ->where('production_id', $productionId)
            ->where('department_id', $departmentId)
            ->where('is_lead', 1)
            ->pluck('user_id');

        if ($userIds->count() === 0) {
            throw ContractEnvelopeException::vacantPosition($label);
        }
        if ($userIds->count() > 1) {
            throw ContractEnvelopeException::duplicatePosition($label);
        }
        return User::findOrFail($userIds->first());
    }

    /**
     * Puestos ELEGIBLES como firmantes/autorizadores: SOLO de los deptos de gestión de producción
     * (cabezas de producción). El HOD de cualquier depto entra por la entrada dinámica DEPT_HOD.
     */
    public static function signerEligiblePositions($productionId = null)
    {
        $deptIds = \App\Models\Department::whereIn('name', self::SIGNER_DEPARTMENTS)->pluck('id');

        return \App\Models\Position::query()
            ->where(fn ($q) => $q->whereNull('production_id')->orWhere('production_id', $productionId))
            ->where('active', 1)
            ->whereIn('department_id', $deptIds)
            ->orderBy('sort_order')->orderBy('name')
            ->get(['id', 'name', 'department_id']);
    }

    /**
     * El ÚNICO usuario que ocupa un puesto en la producción. Vacante o duplicado → excepción clara.
     */
    public static function soleUserForPosition(int $productionId, ?int $positionId, string $roleLabel): User
    {
        if (! $positionId) {
            throw ContractEnvelopeException::positionNotConfigured($roleLabel);
        }
        $userIds = DB::table('production_user')
            ->where('production_id', $productionId)
            ->where('position_id', $positionId)
            ->pluck('user_id');

        if ($userIds->count() === 0) {
            throw ContractEnvelopeException::vacantPosition($roleLabel);
        }
        if ($userIds->count() > 1) {
            throw ContractEnvelopeException::duplicatePosition($roleLabel);
        }
        return User::findOrFail($userIds->first());
    }
}
