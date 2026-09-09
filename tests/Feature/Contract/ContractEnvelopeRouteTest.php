<?php

namespace Tests\Feature\Contract;

use App\Models\ContractEnvelope;
use App\Models\ContractEnvelopeRecipient;
use App\Models\Payee;
use App\Models\PayeeContract;
use Illuminate\Support\Facades\DB;
use Tests\QaTestCase;

/**
 * EL CONTRATO · PASO C — el RUNTIME de la ruta (sobre) como cadena con estados vivos.
 * La vista `contracts.envelope.show` pinta un stepper (mismo lenguaje que /contratos/ruta-config):
 * cada destinatario es un paso con estado HECHO (firmado) / EN TURNO (el actual) / EN ESPERA.
 * Aquí se arma un sobre con los tres estados y se verifica que el stepper los refleje.
 */
class ContractEnvelopeRouteTest extends QaTestCase
{
    private function envelopeWithRoute(): ContractEnvelope
    {
        $payee = Payee::create(['legal_nature' => 'fisica', 'name' => 'Juan Pérez López']);
        $contract = $payee->contracts()->create([
            'concept'       => PayeeContract::CONCEPT_CREW,
            'is_active'     => 1,
            'production_id' => DB::table('productions')->min('id'),
        ]);

        $env = ContractEnvelope::create([
            'payee_contract_id' => $contract->id,
            'production_id'     => $contract->production_id,
            'status'            => ContractEnvelope::STATUS_SENT,
            'sent_at'           => now(),
        ]);

        // #1 Contratado — YA FIRMÓ (estado HECHO + bloque de firma).
        $svg = 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" width="200" height="48"><text x="6" y="34">Juan</text></svg>');
        $env->recipients()->create([
            'role' => ContractEnvelopeRecipient::ROLE_CONTRACTED, 'sort_order' => 0,
            'name' => 'Juan Pérez López', 'email' => 'juan@demo.test', 'cargo' => 'Contratado',
            'status' => ContractEnvelopeRecipient::STATUS_SIGNED, 'signed_at' => now(),
            'ip_address' => '187.190.0.1', 'sign_method' => 'autograph', 'signature_image' => $svg,
        ]);

        // #2 Firmante — EL ACTUAL (estado EN TURNO + enlace "Abrir firma").
        $current = $env->recipients()->create([
            'role' => ContractEnvelopeRecipient::ROLE_SIGNER, 'sort_order' => 1,
            'name' => 'Ana García', 'email' => 'ana@demo.test', 'cargo' => 'Gerente de Producción',
            'status' => ContractEnvelopeRecipient::STATUS_SENT, 'sent_at' => now(),
        ]);

        // #3 Firmante — todavía EN ESPERA.
        $env->recipients()->create([
            'role' => ContractEnvelopeRecipient::ROLE_SIGNER, 'sort_order' => 2,
            'name' => 'Luis Martínez', 'email' => 'luis@demo.test', 'cargo' => 'Contador Fiscal',
            'status' => ContractEnvelopeRecipient::STATUS_PENDING,
        ]);

        $env->update(['current_recipient_id' => $current->id]);

        return $env->fresh();
    }

    public function test_envelope_show_renders_route_stepper_with_live_states(): void
    {
        $this->actingAsRole('super-admin');
        $env = $this->envelopeWithRoute();

        $res = $this->get(route('contracts.envelope.show', $env));
        $res->assertOk();
        $res->assertSee(__('Ruta de firma'));

        // Tres estados vivos — se afirma por CONTENIDO (el nombre de clase también vive en el CSS
        // del partial, así que solo el texto que aparece por-estado es prueba real del render).
        $res->assertSee(__('Firmado'));        // el contratado ya firmó (chip + marca de tiempo)
        $res->assertSee(__('En turno'));       // Ana es la actual
        $res->assertSee(__('Pendiente'));      // Luis aún en espera

        // El actual muestra su enlace de firma; los otros no.
        $res->assertSee(__('Abrir firma'));

        // Papeles y personas congeladas — prueba que el loop pintó cada paso.
        $res->assertSee(__('Contratado'));
        $res->assertSee(__('Firmante'));
        $res->assertSee('Juan Pérez López');
        $res->assertSee('Ana García');
        $res->assertSee('Luis Martínez');
    }

    public function test_completed_envelope_has_no_current_turn(): void
    {
        $this->actingAsRole('super-admin');
        $env = $this->envelopeWithRoute();
        // Todos firmaron y el sobre está completado → nadie "en turno".
        $env->recipients()->update(['status' => ContractEnvelopeRecipient::STATUS_SIGNED, 'signed_at' => now()]);
        $env->update(['status' => ContractEnvelope::STATUS_COMPLETED, 'current_recipient_id' => null]);

        $res = $this->get(route('contracts.envelope.show', $env->fresh()));
        $res->assertOk();
        // Nadie "en turno" ni enlace de firma abierto; pero los pasos (firmados) siguen ahí.
        $res->assertSee(__('Firmado'));
        $res->assertDontSee(__('En turno'));
        $res->assertDontSee(__('Abrir firma'));
    }
}
