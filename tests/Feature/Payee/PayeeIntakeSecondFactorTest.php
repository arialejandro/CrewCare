<?php

namespace Tests\Feature\Payee;

use App\Http\Controllers\IntakeController;
use App\Models\Payee;
use App\Models\User;
use Illuminate\Support\Facades\URL;
use Tests\QaTestCase;

/**
 * VERIFICACIÓN del SEGUNDO FACTOR del enlace de intake (solo SELF): coteja la fecha de
 * nacimiento antes de abrir el asistente. No es muro (avisa sin bloquear), intentos limitados,
 * y se OMITE si el registro no tiene fecha.
 */
class PayeeIntakeSecondFactorTest extends QaTestCase
{
    private function signedShow(User $u): string
    {
        return URL::temporarySignedRoute('intake.show', now()->addDay(), ['user' => $u->id]);
    }

    private function signedVerify(User $u): string
    {
        return URL::temporarySignedRoute('intake.verify', now()->addDay(), ['user' => $u->id]);
    }

    /** Con fecha en el registro, el enlace pide el segundo factor ANTES del asistente. */
    public function test_link_asks_for_birthdate_before_wizard(): void
    {
        $user = $this->makeUser('crew', ['borndate' => '1990-05-15']);

        $res = $this->get($this->signedShow($user))->assertOk();
        $res->assertSee('Confirma que eres tú');
        $res->assertSee('name="borndate"', false);
        $res->assertDontSee('Paso 1 de'); // el asistente aún no
    }

    /** Fecha correcta → entra al asistente; la sesión recuerda el cotejo. */
    public function test_correct_birthdate_unlocks_wizard(): void
    {
        $user = $this->makeUser('crew', ['borndate' => '1990-05-15']);

        $this->post($this->signedVerify($user), ['borndate' => '1990-05-15'])->assertRedirect();

        // Ya con la sesión marcada, el asistente abre.
        $this->get($this->signedShow($user))->assertOk()->assertSee('Paso 1 de');
    }

    /** Fecha incorrecta → NO es muro: avisa sin bloquear y no abre el asistente. */
    public function test_wrong_birthdate_warns_without_blocking(): void
    {
        $user = $this->makeUser('crew', ['borndate' => '1990-05-15']);

        $res = $this->post($this->signedVerify($user), ['borndate' => '2000-01-01'])->assertOk();
        $res->assertSee('no coincide');
        $res->assertSee('avísale a la producción');

        // Sigue pidiendo el factor (no quedó marcada la sesión).
        $this->get($this->signedShow($user))->assertOk()->assertSee('Confirma que eres tú');
    }

    /** Tras demasiados intentos, se enfría (server-side, no depende de cookies). */
    public function test_attempts_are_rate_limited(): void
    {
        $user = $this->makeUser('crew', ['borndate' => '1990-05-15']);

        for ($i = 0; $i < IntakeController::SF_MAX; $i++) {
            $this->post($this->signedVerify($user), ['borndate' => '2000-01-01'])->assertOk();
        }
        // El siguiente ya viene enfriado.
        $this->post($this->signedVerify($user), ['borndate' => '1990-05-15'])
            ->assertOk()->assertSee('Demasiados intentos');
    }

    /** Sin fecha en el registro NO se puede cotejar → el factor se OMITE (no deja fuera). */
    public function test_missing_birthdate_skips_the_gate(): void
    {
        $user = $this->makeUser('crew'); // sin borndate

        $this->get($this->signedShow($user))->assertOk()->assertSee('Paso 1 de');
    }

    /** El POST directo a store tampoco salta el factor (no escribe sin cotejar). */
    public function test_store_is_guarded_by_second_factor(): void
    {
        $user = $this->makeUser('crew', ['borndate' => '1990-05-15']);
        $storeUrl = URL::temporarySignedRoute('intake.store', now()->addDay(), ['user' => $user->id]);

        $this->post($storeUrl, ['name' => 'Hacker'])->assertOk()->assertSee('Confirma tu fecha');
        $this->assertNull(Payee::where('user_id', $user->id)->first()->intake_submitted_at ?? null);
    }
}
