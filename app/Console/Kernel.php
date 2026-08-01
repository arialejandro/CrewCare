<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        Commands\CrewWelcomeResend::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // (2026-07-24) AGENDA VACÍA A PROPÓSITO. Aquí vivía `encuestas:task` a las 04:00, el
        // último resto del cuestionario COVID DIARIO: ponía `encuestadiaria=0` a TODOS los
        // usuarios activos y mandaba el correo "DAILY REPORT". Con el módulo convertido en
        // EXPEDIENTE CLÍNICO eso significaba pedir la historia clínica completa cada mañana,
        // y como el expediente no se edita, cada re-llenado habría sembrado una FILA NUEVA.
        //
        // El expediente se llena UNA VEZ por instancia de CrewCare (= una producción). La
        // reapertura queda MANUAL y deliberada: el toggle "Activar encuesta" de usuarioscrud
        // (CrewStatusController@activarencuesta), que sigue intacto.
        //
        // ⚠ DEPLOY: el VPS puede seguir corriendo `php artisan schedule:run`; ya no hace nada.
        // No lo quites por si mañana se agenda algo aquí.
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
