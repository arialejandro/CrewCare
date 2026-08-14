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
