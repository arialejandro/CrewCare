<?php

namespace App\Providers;

use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],

        // (2026-07-13) Pilar 2 — DSR Master Hub: inyección silenciosa de eventos
        // críticos al Daily Safety Report del día (find-or-create). Los Listeners y
        // DsrHub son defensivos (no rompen el guardado del reporte fuente).
        // (2026-07-19) + SendHighRiskSafetyAlert: aviso por correo si risk_level es
        // Alto/Extremo. Cubre los TRES tipos (accidente, condición, acto inseguro).
        \App\Events\AccidentReported::class => [
            \App\Listeners\InjectAccidentIntoDsr::class,
            \App\Listeners\SendHighRiskSafetyAlert::class,
        ],
        \App\Events\UnsafeConditionReported::class => [
            \App\Listeners\InjectUnsafeConditionIntoDsr::class,
            \App\Listeners\SendHighRiskSafetyAlert::class,
        ],
        \App\Events\HazardReported::class => [
            \App\Listeners\SendHighRiskSafetyAlert::class,
        ],
        \App\Events\MedicalConsultRecorded::class => [
            \App\Listeners\InjectLinkedConsultIntoDsr::class,
        ],

        // (2026-08-13) EL INFOSHEET · Fase 3.4 — ruta de firma completada: entrega al contratado su
        // paquete firmado (PDF adjuntos + certificado). Listener defensivo (nunca rompe la firma).
        \App\Events\ContractEnvelopeCompleted::class => [
            \App\Listeners\EmailSignedContractToParty::class,
        ],
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
