<?php

namespace App\Support;

use App\Http\Controllers\IntakeController;
use App\Models\PaymentPeriod;
use App\Models\User;

/**
 * RECORDATORIO MANUAL a quienes faltan (§a del bloque de periodos). Contabilidad ya está
 * mirando el tablero y sabe cosas que el sistema no (que fulano dijo que lo manda mañana, que
 * mengano está de vacaciones) → el envío es de UN CLIC POR PERSONA, humano en el circuito. NO
 * un job automático: un WhatsApp automático a un proveedor externo, con enlace firmado que pide
 * datos fiscales, se lee como PHISHING.
 *
 * Reglas: el mensaje dice QUÉ LE FALTA a esa persona (no genérico), NOMBRA A LA PRODUCCIÓN (para
 * que el destinatario sepa que es real) y **nunca se arma para quien ya entregó**. Canal = Magic
 * Links por WhatsApp (el mismo `wa.me` + URL firmada que usa Safety, {@see \App\Traits\HasMagicMitigation}).
 *
 * ⚠ Solo hay autoservicio (teléfono propio `users.phone` + link firmado `IntakeController::invitationUrl`)
 * para payees LIGADOS a un usuario. El payee EXTERNO (user_id NULL) no tiene teléfono propio
 * (`emergency_contact_phone` es de un tercero, NO se usa) ni link de autoservicio → se marca
 * "sin autoservicio (lo captura producción)". No se le arma botón.
 */
class PeriodReminder
{
    /**
     * Filas de recordatorio del periodo (solo quienes FALTAN), acotadas por la visibilidad del
     * viewer (reusa {@see PeriodBoard}). Cada fila: payee, lista de documentos faltantes, si
     * tiene autoservicio y el link `wa.me` listo (o null).
     *
     * @return array<int, array{payee: \App\Models\Payee, missing: array, self_serve: bool, has_phone: bool, wa: ?string}>
     */
    public static function build(PaymentPeriod $period, User $viewer): array
    {
        $board   = PeriodBoard::build($period, $viewer);
        $columns = $board['columns']->keyBy('id');
        $prod    = optional($period->production)->name ?: config('app.name');

        $out = [];
        foreach ($board['rows'] as $row) {
            if ($row['delivered'] || $row['no_reqs']) {
                continue;   // NUNCA se arma para quien ya entregó (ni para quien no debe nada)
            }

            $payee = $row['payee'];

            // QUÉ LE FALTA: los tipos cuyo estado no es "recibido" (32-D no positiva incluida).
            $missing = collect($row['cells'])
                ->filter(fn ($state) => $state !== PayeePackage::ST_RECEIVED)
                ->keys()
                ->map(fn ($id) => optional($columns->get($id))->name)
                ->filter()->values()->all();

            $user      = $payee->user;                 // liga OPCIONAL (crew) — puede ser null
            $selfServe = $user !== null;
            $phone     = $selfServe ? preg_replace('/\D/', '', (string) $user->phone) : '';

            $wa = null;
            if ($selfServe) {
                $link = IntakeController::invitationUrl($user);
                $msg  = self::message($payee->name, $prod, $period->displayLabel(), $missing, $link);
                // Con teléfono → chat directo; sin teléfono → selector de contacto (mismo patrón que Safety).
                $wa = $phone !== ''
                    ? 'https://wa.me/' . $phone . '?text=' . rawurlencode($msg)
                    : 'https://wa.me/?text=' . rawurlencode($msg);
            }

            $out[] = [
                'payee'      => $payee,
                'missing'    => $missing,
                'self_serve' => $selfServe,
                'has_phone'  => $phone !== '',
                'wa'         => $wa,
            ];
        }

        return $out;
    }

    /** Mensaje: saluda, NOMBRA la producción, dice qué falta y lleva el link de autoservicio. */
    private static function message(string $name, string $prod, string $periodLabel, array $missing, string $link): string
    {
        $lista = implode(', ', $missing);
        return "Hola {$name}. Te escribe {$prod}. Para tu pago del periodo \"{$periodLabel}\" "
            . "aún falta recibir: {$lista}. Puedes subir tus documentos aquí: {$link}";
    }
}
