<?php

namespace Tests\Feature\Payee;

use App\Models\Payee;
use App\Models\PayeeBeneficiary;
use App\Models\User;
use Illuminate\Support\Facades\URL;
use Tests\QaTestCase;

/** VERIFICACIÓN del asistente por pasos (guardado por paso en servidor + retomar + link firmado). */
class PayeeIntakeWizardTest extends QaTestCase
{
    private function signedStore(User $user): string
    {
        return URL::temporarySignedRoute('intake.store', now()->addDay(), ['user' => $user->id]);
    }

    /** Guardar un paso avanza y NO finaliza hasta el último; el último sí marca intake_submitted_at. */
    public function test_step_save_advances_and_finalizes_only_at_last(): void
    {
        $user = $this->makeUser('crew');

        $this->post($this->signedStore($user), ['_step' => 'identity', 'nationality' => 'mexicana', 'addr_cp' => '06000'])
            ->assertRedirect(); // → siguiente paso (show firmado)
        $payee = Payee::where('user_id', $user->id)->firstOrFail();
        $this->assertSame('mexicana', $payee->nationality);
        $this->assertNull($payee->intake_submitted_at, 'no finaliza a media captura');

        $this->post($this->signedStore($user), ['_step' => 'logistics', 'shirt_size' => 'M'])
            ->assertOk(); // pantalla de gracias
        $this->assertNotNull($payee->fresh()->intake_submitted_at, 'el último paso finaliza');
    }

    /** El avance vive en el SERVIDOR: lo guardado en un paso persiste (retoma en otro dispositivo). */
    public function test_progress_is_server_side(): void
    {
        $user = $this->makeUser('crew');
        $this->post($this->signedStore($user), ['_step' => 'fiscal', 'rfc' => 'PEXJ800101AB1',
            'regimes' => [['code' => '605', 'name' => 'Sueldos y salarios']]])->assertRedirect();

        $payee = Payee::where('user_id', $user->id)->firstOrFail();
        $this->assertSame('PEXJ800101AB1', $payee->rfc);
        $this->assertSame(1, $payee->fiscalRegimes()->count()); // disponible desde cualquier dispositivo
    }

    /** El paso de emergencia no deja avanzar si los beneficiarios no suman 100%. */
    public function test_beneficiaries_step_blocks_when_not_100(): void
    {
        $user = $this->makeUser('crew');
        $this->post($this->signedStore($user), ['_step' => 'emergency', 'beneficiaries' => [
            ['full_name' => 'A', 'percentage' => 70],
        ]])->assertSessionHas('error');
        $this->assertSame(0, PayeeBeneficiary::count());
    }

    /** El paso EQUIPO no avanza en blanco: exige declarar equipo o marcar la aceptación. */
    public function test_equipment_step_requires_declaration_or_acceptance(): void
    {
        $user = $this->makeUser('crew');

        // En blanco (sin equipo y sin aceptar) → bloquea.
        $this->post($this->signedStore($user), ['_step' => 'equipment'])->assertSessionHas('error');

        // Marca "Acepto que lo no declarado no queda cubierto" → avanza.
        $this->post($this->signedStore($user), ['_step' => 'equipment', 'accept_equipment' => 1])->assertRedirect();

        // O declara equipo → avanza.
        $this->post($this->signedStore($user), ['_step' => 'equipment',
            'declared_equipment' => [0 => ['description' => 'Starlink', 'declared_value' => 9000]]])->assertRedirect();
    }

    /** Un enlace EXPIRADO no deja entrar. */
    public function test_expired_link_is_denied(): void
    {
        $user = $this->makeUser('crew');
        $expired = URL::temporarySignedRoute('intake.show', now()->subMinutes(5), ['user' => $user->id]);
        $this->get($expired)->assertForbidden();
    }
}
