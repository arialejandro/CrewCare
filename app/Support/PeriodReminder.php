<?php

namespace App\Support;

use App\Http\Controllers\IntakeController;
use App\Models\PaymentPeriod;
use App\Models\User;
use App\Support\Features;
use App\Support\Phone;

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
 * ⚠ Dos niveles: el NUDGE por WhatsApp (texto que dice qué falta) se arma para CUALQUIER payee con
 * teléfono —propio del intake (`payees.phone`) o del user ligado, vía `Payee::contactPhone()`—; el
 * AUTOSERVICIO (link firmado `IntakeController::invitationUrl`) solo se añade al mensaje cuando el
 * payee está ligado a un usuario Y Magic Links está encendido. El payee EXTERNO recibe el nudge SIN
 * enlace (que pide enviarlo a producción): NUNCA un enlace firmado a un desconocido (sería phishing).
 * El `emergency_contact_phone` es de un tercero → NUNCA se usa como destino.
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

            $user = $payee->user;   // liga OPCIONAL (crew) — null en el payee EXTERNO

            // Teléfono PROPIO del payee (capturado en el intake) o el del user ligado. NUNCA el de
            // emergencia (es de un tercero). Un externo con su tel capturado SÍ recibe nudge.
            // Normalizado para wa.me (código de país; un local de 10 dígitos → +52), {@see Phone}.
            $phone = Phone::whatsapp($payee->contactPhone());

            // Autoservicio (link firmado que sube documentos) = solo con user ligado + Magic Links ON.
            // El NUDGE por WhatsApp funciona SIEMPRE (es texto, no un enlace firmado).
            $selfServe = $user !== null && Features::enabled('magic_links');

            $msg = $selfServe
                ? self::message($payee->name, $prod, $period->displayLabel(), $missing, IntakeController::invitationUrl($user))
                : self::messagePlain($payee->name, $prod, $period->displayLabel(), $missing);

            // Con teléfono → chat directo; sin teléfono → selector de contacto de WhatsApp.
            $wa = $phone !== ''
                ? 'https://wa.me/' . $phone . '?text=' . rawurlencode($msg)
                : 'https://wa.me/?text=' . rawurlencode($msg);

            $out[] = [
                'payee'      => $payee,
                'missing'    => $missing,
                'self_serve' => $selfServe,
                'has_phone'  => $phone !== '',
                'wa'         => $wa,   // SIEMPRE hay nudge (con número directo o con selector de contacto)
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

    /** Mensaje SIN enlace (payee externo o Magic Links apagado): pide enviarlo a producción. */
    private static function messagePlain(string $name, string $prod, string $periodLabel, array $missing): string
    {
        $lista = implode(', ', $missing);
        return "Hola {$name}. Te escribe {$prod}. Para tu pago del periodo \"{$periodLabel}\" "
            . "aún falta recibir: {$lista}. Por favor envíalo a la oficina de producción lo antes posible.";
    }
}
