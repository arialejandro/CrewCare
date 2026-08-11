<?php

namespace Tests\Feature\Incident;

use App\Events\AccidentReported;
use App\Events\HazardReported;
use App\Events\UnsafeConditionReported;
use App\Listeners\SendHighRiskSafetyAlert;
use App\Models\hazardnotification;
use App\Models\InjuryReport;
use App\Models\unsafecond;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Tests\QaTestCase;

/**
 * QA — VERTICAL INJURY/HAZARD/UNSAFE: ALERTA DE RIESGO Alto/Extremo.
 *
 * Al reportar un evento de seguridad se despacha su evento (AccidentReported /
 * UnsafeConditionReported / HazardReported); el listener SendHighRiskSafetyAlert
 * envía un correo transaccional SÓLO si risk_level ∈ {Alto, Extremo} (el nivel se LEE,
 * no se recalcula → respeta el override del Safety Manager).
 *
 * Se prueba en dos niveles:
 *  1. CABLEADO: los tres eventos están registrados al listener y el modelo dispara su
 *     evento al crearse (Event::fake + assertDispatched/assertListening).
 *  2. EFECTO: el gate de nivel del listener — se inspecciona el transporte de correo
 *     'array' (SwiftMailer) para confirmar que Alto/Extremo ENVÍAN y Bajo/Medio NO.
 */
class IncidentRiskAlertTest extends QaTestCase
{
    // ==================================================================================
    //  1. CABLEADO
    // ==================================================================================

    public function test_los_tres_eventos_estan_registrados_al_listener(): void
    {
        Event::fake();
        Event::assertListening(AccidentReported::class, SendHighRiskSafetyAlert::class);
        Event::assertListening(UnsafeConditionReported::class, SendHighRiskSafetyAlert::class);
        Event::assertListening(HazardReported::class, SendHighRiskSafetyAlert::class);
    }

    public function test_crear_un_acto_inseguro_despacha_HazardReported(): void
    {
        // Sólo se falsea el evento de dominio: el resto (uuid, sellado) sigue funcionando.
        Event::fake([HazardReported::class]);

        $haz = hazardnotification::create([
            'production_name'               => 'Demo',
            'description_hazard_unsafe_act' => 'Trabajo en altura sin arnés',
            'risk_level'                    => 'Alto',
        ]);

        Event::assertDispatched(HazardReported::class, function ($e) use ($haz) {
            return $e->model instanceof hazardnotification
                && (int) $e->model->id === (int) $haz->id
                && $e->model->risk_level === 'Alto';
        });
    }

    // ==================================================================================
    //  2. EFECTO — gate de nivel del listener (transporte 'array').
    // ==================================================================================

    /** Nº de mensajes acumulados en el transporte de correo 'array'. */
    private function mailCount(): int
    {
        return Mail::getSwiftMailer()->getTransport()->messages()->count();
    }

    public function test_un_acto_alto_dispara_correo_y_uno_bajo_no(): void
    {
        // Un destinatario resoluble por rol (SafetyAlertRecipients incluye safety-officer).
        $this->makeUser('safety-officer', ['email' => 'alert-recipient@qa.test']);
        $listener = new SendHighRiskSafetyAlert();

        // Bajo → el gate corta: NO se envía nada.
        $before = $this->mailCount();
        $listener->handle(new HazardReported(new hazardnotification([
            'production_name'               => 'Demo',
            'description_hazard_unsafe_act' => 'Cinta suelta en piso',
            'risk_level'                    => 'Bajo',
        ])));
        $this->assertSame($before, $this->mailCount(), 'Un acto de riesgo Bajo NO debe enviar correo.');

        // Alto → sí se envía (al menos un mensaje nuevo).
        $listener->handle(new HazardReported(new hazardnotification([
            'production_name'               => 'Demo',
            'description_hazard_unsafe_act' => 'Trabajo en altura sin arnés',
            'risk_level'                    => 'Alto',
        ])));
        $this->assertGreaterThan($before, $this->mailCount(), 'Un acto de riesgo Alto debe enviar correo.');
    }

    public function test_un_accidente_extremo_dispara_correo(): void
    {
        $this->makeUser('safety-officer', ['email' => 'alert-inj@qa.test']);
        $listener = new SendHighRiskSafetyAlert();

        $before = $this->mailCount();
        $listener->handle(new AccidentReported(new InjuryReport([
            'name'          => 'Lesionado',
            'what_happened' => 'Caída de altura',
            'risk_level'    => 'Extremo',
        ])));
        $this->assertGreaterThan($before, $this->mailCount(), 'Un accidente Extremo debe enviar correo.');
    }

    public function test_una_condicion_media_no_dispara_correo(): void
    {
        $this->makeUser('safety-officer', ['email' => 'alert-cond@qa.test']);
        $listener = new SendHighRiskSafetyAlert();

        $before = $this->mailCount();
        $listener->handle(new UnsafeConditionReported(new unsafecond([
            'production_name'         => 'Demo',
            'name_loc'                => 'Set',
            'description_unsafe_cond' => 'Iluminación insuficiente',
            'risk_level'              => 'Medio',
        ])));
        $this->assertSame($before, $this->mailCount(), 'Una condición de riesgo Medio NO debe enviar correo.');
    }

    public function test_sin_destinatarios_no_truena_y_no_envia(): void
    {
        // Sin ningún recipient resoluble, el listener registra y regresa sin enviar (blindado).
        $listener = new SendHighRiskSafetyAlert();
        $before = $this->mailCount();
        $listener->handle(new HazardReported(new hazardnotification([
            'production_name'               => 'Demo',
            'description_hazard_unsafe_act' => 'x',
            'risk_level'                    => 'Extremo',
        ])));
        $this->assertSame($before, $this->mailCount());
    }
}
