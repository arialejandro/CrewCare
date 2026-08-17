<?php

namespace App\Support;

use App\Models\DocumentType;
use App\Models\Payee;
use App\Models\Quotation;
use App\Models\User;

/**
 * COTIZACIÓN · PASO F — qué pasa al ACEPTAR. Enganche ADITIVO (no modifica flujos en uso):
 *
 *  1. BUSCA COINCIDENCIA DE CORREO antes de crear nada — el correo de la cotización suele diferir
 *     del de alta (el personal contra el de la empresa, o el del contador). Se busca contra
 *     `payees.email` y contra `users.email` (→ su payee).
 *  2. Si coincide, LIGA al payee existente. Si no, provisiona el EXTERNO LITE que ya existe
 *     ({@see ExternalParty}) — es la primera vez que se crea usuario (nunca al cotizar).
 *  3. La cotización aceptada SATISFACE el requisito "cotización" del paquete documental del payee
 *     (se registra un documento COTIZACION recibido; idempotente, no se pide dos veces).
 *
 * Los importes quedan DISPONIBLES para el Infosheet y la cotización disponible como ANEXO del
 * sobre por CONSULTA ({@see Quotation::acceptedForPayee}), sin empujar nada a esos flujos.
 *
 * ⚠ Fija `$quotation->payee_id` EN MEMORIA (no guarda la cotización): el que acepta la guarda y la
 * SELLA después, para que `payee_id` entre al hash del sello.
 */
class QuotationAcceptance
{
    public static function wire(Quotation $quotation, ?User $actor): void
    {
        $payee = self::resolvePayee($quotation, $actor);
        $quotation->payee_id = $payee->id;   // en memoria; accept() lo guarda + sella
        self::satisfyQuotationRequirement($quotation, $payee, $actor);
    }

    /** Coincidencia de correo → payee existente; si no, externo-lite recién provisionado. */
    private static function resolvePayee(Quotation $quotation, ?User $actor): Payee
    {
        $email = $quotation->emitter_email; // ya normalizado (lower/trim)

        if ($email) {
            // (a) un payee con ese correo.
            $payee = Payee::whereRaw('LOWER(email) = ?', [$email])->first();
            if ($payee) {
                return $payee;
            }
            // (b) un usuario con ese correo → su payee (o uno recién ligado a ese user).
            $user = User::whereRaw('LOWER(email) = ?', [$email])->first();
            if ($user) {
                return Payee::firstOrCreate(
                    ['user_id' => $user->id],
                    ['legal_nature' => Payee::NATURE_FISICA, 'name' => trim($user->name.' '.($user->lname ?? '')), 'is_active' => 1]
                );
            }
        }

        // (c) sin coincidencia → payee EXTERNO nuevo + usuario lite (activo=0, fuera de listados).
        $payee = Payee::create([
            'legal_nature' => Payee::NATURE_FISICA,
            'name'         => $quotation->emitter_name,
            'email'        => $email,
            'is_active'    => 1,
            'created_by_id' => $actor?->id,
        ]);
        if ($email) {
            ExternalParty::provisionForPayee($payee, $email, $quotation->emitter_name);
        }

        return $payee->fresh();
    }

    /** Registra el documento COTIZACION como RECIBIDO en el paquete del payee (idempotente). */
    private static function satisfyQuotationRequirement(Quotation $quotation, Payee $payee, ?User $actor): void
    {
        $type = DocumentType::where('code', 'COTIZACION')->first();

        $exists = $payee->documents()
            ->where('is_active', 1)
            ->when($type, fn ($q) => $q->where('document_type_id', $type->id), fn ($q) => $q->where('document_type', 'Cotización'))
            ->exists();
        if ($exists) {
            return; // ya satisfecha; no se pide dos veces
        }

        $version = $quotation->currentVersion;
        $payee->documents()->create([
            'level'            => 'persona',
            'document_type'    => $type ? $type->name : 'Cotización',
            'document_type_id' => $type?->id,
            'status'           => 'presentado',   // RECIBIDO (el cotejo es aparte)
            'is_active'        => 1,
            'origen'           => 'contractual',
            'created_by_id'    => $actor?->id,
            // Referencia al soporte: la hoja de aceptación si ya existe, o el PDF cotizado.
            'photo_path'       => $quotation->acceptance_sheet_path ?: optional($version)->pdf_path,
        ]);
    }
}
