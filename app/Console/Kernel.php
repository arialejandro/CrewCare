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
        Commands\DispatchFileDeliveries::class,
        Commands\StampSignatureTimestamps::class,
        Commands\PruneClinicalReadLogs::class,
        Commands\AnnouncePeriodOpenings::class,
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

        // (2026-08-24) ENVÍO DE ARCHIVOS CON MARCA DE AGUA — drena el outbox cada minuto. El request
        // solo ENCOLA (más una ráfaga inline chica); este cron manda el resto FLUIDO y sin perderse.
        // `withoutOverlapping` evita que dos corridas pisen las mismas filas (además del reclamo
        // atómico del despachador). Inofensivo si no hay nada encolado.
        $schedule->command('deliveries:dispatch')->everyMinute()->withoutOverlapping();

        // (2026-08-30) SELLO DE TIEMPO TSA (RFC 3161). Timbra en freeTSA los sellos que aún no
        // tienen token, best-effort. NO bloquea el sellado (eso ya ocurrió); si freeTSA no
        // responde, reintenta en la siguiente corrida. Cubre sellos nuevos Y viejos (retroactivo).
        $schedule->command('tsa:stamp')->everyFiveMinutes()->withoutOverlapping();

        // (2026-08-30) Retención de la bitácora de lectura clínica: poda mensual lo mayor a 3 años.
        $schedule->command('clinical-log:prune')->monthlyOn(1, '03:30');

        // (2026-09-05) CALENDARIO · aviso de apertura de ventana — una vez al día avisa por correo los
        // periodos cuya ventana abre HOY (idempotente por announced_at). El "doble en cambio de mes"
        // lo resuelve el propio comando. NO sustituye el recordatorio manual del tablero.
        $schedule->command('periods:announce')->dailyAt('07:00')->withoutOverlapping();

        // (2026-08-30 · estabilidad) LATIDO del cron: cada minuto deja una marca fresca que el
        // healthcheck (/healthz) lee. Si `schedule:run` deja de correr, la marca envejece y /healthz
        // reporta 'stale' → el monitor alerta. Es la forma de saber que el cron sigue vivo.
        $schedule->call(function () {
            \Illuminate\Support\Facades\Cache::put('cron_heartbeat', now()->timestamp, 3600);
        })->everyMinute()->name('cron-heartbeat');
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
