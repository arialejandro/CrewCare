<?php

namespace App\Console\Commands;

use App\Support\CurrentProduction;
use App\Support\ImageCompressor;
use App\Support\PdfExporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

/**
 * crewcare:preflight — REVISIÓN DEL ENTORNO antes de usar el servidor en campo (y después de cada
 * actualización). Revisa lo que ROMPE el día uno y reporta cada punto como OK / FALLA / AVISO con la razón.
 * Es SÓLO LECTURA (salvo un PDF de prueba en archivo temporal y, si se pide, un correo de prueba); se puede
 * correr EN CUALQUIER MOMENTO. Documentado en DEPLOY-RUNBOOK.md como primer paso tras instalar y último antes
 * de usar. NO despliega ni cambia configuración: sólo diagnostica.
 */
class Preflight extends Command
{
    protected $signature = 'crewcare:preflight {--mail-to= : Correo para el envío de prueba (si se omite, no manda)}';
    protected $description = 'Revisa el entorno (Chrome/Node+PDF, HEIC, producción, sesión, cron, correo, sello, storage, demo) y dice qué va a romperse.';

    /** Clave de sello COMMITTEADA para desarrollo — si es ésta en prod, el sello es forjable. */
    private const DEV_SEAL_KEY = 'base64:/DLbhZKFvqVdajRrt4+5mKtvMWFQemnCw6u7CoHqjKI=';

    /** Marcador que estampa el corpus DEMO en campos visibles (DemoProductionSeeder::MARCA). */
    private const DEMO_MARK = '[DEMO]';

    private int $fails = 0;
    private int $warns = 0;
    private int $oks   = 0;

    public function handle(): int
    {
        $this->line('');
        $this->line('  <options=bold>CrewCare · preflight</> — entorno: <options=bold>' . app()->environment() . '</>');
        $this->line('  ' . str_repeat('─', 68));

        $this->check('Chrome + Node + PDF de prueba', fn () => $this->checkPdf());
        $this->check('imagick con libheif (fotos HEIC de iPhone)', fn () => $this->checkHeic());
        $this->check('Nombre/código de producción (no demo)', fn () => $this->checkProduction());
        $this->check('SESSION_DRIVER = database', fn () => $this->checkSession());
        $this->check('Cron vivo (schedule:run)', fn () => $this->checkCron());
        $this->check('Correo (config + envío de prueba)', fn () => $this->checkMail());
        $this->check('CREWCARE_SEAL_KEY puesta y no la de dev', fn () => $this->checkSealKey());
        $this->check('Permisos de escritura en storage', fn () => $this->checkStorageWritable());
        $this->check('storage:link hecho', fn () => $this->checkStorageLink());
        $this->check('La base NO tiene datos de demo', fn () => $this->checkNoDemo());

        $this->line('  ' . str_repeat('─', 68));
        $this->line(sprintf('  <fg=green>%d OK</>   <fg=yellow>%d aviso(s)</>   <fg=red>%d falla(s)</>', $this->oks, $this->warns, $this->fails));
        $this->line('');

        if ($this->fails > 0) {
            $this->line('  <fg=red;options=bold>HAY FALLAS.</> Revisa cada [FALLA] antes de usar el servidor en campo.');
            $this->line('');
            return self::FAILURE;
        }
        $this->line('  <fg=green;options=bold>Sin fallas.</> ' . ($this->warns > 0 ? 'Revisa los avisos.' : 'Entorno listo.'));
        $this->line('');
        return self::SUCCESS;
    }

    /** Corre un check tolerante a excepciones (una excepción = FALLA de ese punto, no un crash). */
    private function check(string $label, callable $fn): void
    {
        try {
            [$status, $reason] = $fn();
        } catch (\Throwable $e) {
            $status = 'FAIL';
            $reason = 'excepción: ' . $e->getMessage();
        }

        if ($status === 'OK') {
            $this->oks++;
            $tag = '<fg=green>[  OK  ]</>';
        } elseif ($status === 'WARN') {
            $this->warns++;
            $tag = '<fg=yellow>[ AVISO]</>';
        } else {
            $this->fails++;
            $tag = '<fg=red>[ FALLA]</>';
        }
        $this->line('  ' . $tag . ' ' . $label);
        if ($reason !== '') {
            $this->line('           <fg=gray>' . $reason . '</>');
        }
    }

    // --- 1) Chrome/Node + PDF de prueba ---------------------------------------------------
    private function checkPdf(): array
    {
        // Resuelve rutas (en no-Windows lanza si no encuentra → lo capturamos como FALLA con el detalle).
        $chrome = PdfExporter::chromePath();
        $node   = PdfExporter::nodePath();
        $pdf = PdfExporter::fromHtml('<!doctype html><html><head><meta charset="utf-8"></head><body><h1>preflight</h1></body></html>');
        if (! is_string($pdf) || strncmp($pdf, '%PDF', 4) !== 0) {
            return ['FAIL', "Chrome=$chrome · Node=$node · el PDF de prueba salió vacío o no es un PDF."];
        }
        return ['OK', "Chrome=$chrome · Node=$node · PDF de prueba OK (" . strlen($pdf) . ' bytes).'];
    }

    // --- 2) imagick + libheif -------------------------------------------------------------
    private function checkHeic(): array
    {
        if (! class_exists(ImageCompressor::class) || ! method_exists(ImageCompressor::class, 'heicSupport')) {
            return ['WARN', 'no pude comprobar heicSupport(); revisa app/Support/ImageCompressor.'];
        }
        if (ImageCompressor::heicSupport()) {
            return ['OK', 'Imagick con HEIC/HEIF disponible: las fotos de iPhone se convierten en el servidor.'];
        }
        return ['WARN', 'Imagick SIN libheif: un HEIC subido directo se RECHAZA (mensaje "cámbiala a JPG"). '
            . 'Mitigado en Safari por public/js/cc-photo.js (convierte en el cliente). Para verificación de '
            . 'vehículos con otros navegadores, instala imagick+libheif.'];
    }

    // --- 3) Producción no-demo ------------------------------------------------------------
    private function checkProduction(): array
    {
        $prod = CurrentProduction::get();
        if (! $prod) {
            return ['FAIL', 'no hay producción vigente (¿corriste db:seed?).'];
        }
        $name = trim((string) ($prod->name ?? ''));
        $code = trim((string) ($prod->code ?? ''));
        $bad = $name === '' || $name === 'Producción Demo' || strtoupper($code) === 'DEMO';
        if ($bad) {
            return ['FAIL', "la producción es \"{$name}\" / \"{$code}\" — es el default de DEMO. Define "
                . 'INSTALL_PRODUCTION_NAME/CODE y re-siembra (esos valores se sellan y no se cambian).'];
        }
        return ['OK', "producción \"{$name}\" (código {$code})."];
    }

    // --- 4) Sesión en base de datos -------------------------------------------------------
    private function checkSession(): array
    {
        $driver = (string) config('session.driver');
        if ($driver === 'database') {
            return ['OK', 'las sesiones se pueden revocar desde el perfil.'];
        }
        return ['FAIL', "SESSION_DRIVER=$driver. En prod debe ser `database` DESDE EL PRIMER DEPLOY "
            . '(activarlo después invalida todas las sesiones vivas).'];
    }

    // --- 5) Cron vivo ---------------------------------------------------------------------
    private function checkCron(): array
    {
        $ts = null;
        try {
            $ts = Cache::get('cron_heartbeat');
        } catch (\Throwable $e) {
            return ['FAIL', 'no pude leer la caché cron_heartbeat: ' . $e->getMessage()];
        }
        if (! $ts) {
            return ['FAIL', 'sin latido: el cron NUNCA ha corrido. Sin `schedule:run` cada minuto, el envío '
                . 'masivo del llamado se atora tras ~20 correos y el timbrado TSA no sale.'];
        }
        $age = now()->timestamp - (int) $ts;
        if ($age > 600) {
            return ['FAIL', "último latido hace {$age}s (>600). El cron dejó de correr — revisa `schedule:run`."];
        }
        return ['OK', "último latido hace {$age}s."];
    }

    // --- 6) Correo ------------------------------------------------------------------------
    private function checkMail(): array
    {
        $mailer = (string) config('mail.default');
        $host   = (string) config('mail.mailers.smtp.host', '');
        $user   = (string) config('mail.mailers.smtp.username', '');
        $from   = (string) config('mail.from.address', '');
        $looksExample = in_array($host, ['smtp.ejemplo.com', 'smtp.mailgun.org', ''], true)
            || str_contains($user, 'ejemplo.com') || $user === '';

        $to = trim((string) $this->option('mail-to'));
        if ($to === '') {
            $status = $looksExample ? 'WARN' : 'OK';
            return [$status, "mailer=$mailer host=" . ($host ?: '(vacío)') . ($looksExample ? ' — parece config de EJEMPLO. ' : '. ')
                . 'Pasa --mail-to=correo@dominio para PROBAR el envío real.'];
        }
        try {
            Mail::raw('CrewCare preflight — si recibes esto, el correo saliente funciona (' . now()->toDateTimeString() . ').',
                fn ($m) => $m->to($to)->subject('CrewCare · preflight'));
        } catch (\Throwable $e) {
            return ['FAIL', "el envío de prueba a $to FALLÓ: " . $e->getMessage()];
        }
        return ['OK', "envío de prueba entregado al transporte hacia $to (revisa la bandeja)."];
    }

    // --- 7) Clave del sello ---------------------------------------------------------------
    //
    // 🪤 SE LEE DE config(), NUNCA DE env(). En producción la config se cachea (`config:cache`), y
    // con la caché presente Laravel NO carga el `.env` en absoluto: cualquier `env()` FUERA de un
    // archivo de config devuelve el DEFAULT. La primera versión de este check usaba
    // `env('CREWCARE_SEAL_KEY')` y por eso reportaba "clave vacía" en un servidor que la tenía
    // perfectamente puesta (flor.crewcare.mx, 2026-09-08). Un falso positivo aquí es peor que no
    // tener el check: es el punto más delicado del sistema, y una alarma que grita en verde es una
    // alarma que se aprende a ignorar.
    //
    // `config('crewcare.seal.key')` es además EXACTAMENTE lo que lee
    // HasDigitalSignatures::computeDocumentHash() —`config('crewcare.seal.key') ?: config('app.key')`—
    // así que este check mira la MISMA clave con la que se firma de verdad, no una aproximación.
    private function checkSealKey(): array
    {
        $key = trim((string) config('crewcare.seal.key'));
        $app = trim((string) config('app.key'));

        // Vacía = el trait cae a APP_KEY. No es un hash sin clave (eso nunca), pero acopla la vida
        // de los documentos a la de la app: rotar APP_KEY pasaría a invalidar TODOS los sellos.
        if ($key === '') {
            return ['FAIL', 'sin clave dedicada → el sello cae a APP_KEY, y entonces rotar APP_KEY invalidaría '
                . 'TODOS los documentos sellados. Pon CREWCARE_SEAL_KEY en el .env ANTES de sellar nada real '
                . '(con sellos vivos ya no se puede cambiar) y corre `config:cache`.'];
        }

        if (hash_equals($app, $key)) {
            return ['FAIL', 'CREWCARE_SEAL_KEY es idéntica a APP_KEY → mismo acoplamiento que no ponerla. '
                . 'Tienen que ser dos secretos distintos.'];
        }

        // La clave de desarrollo viaja committeada en el repo. config() ya decodificó el prefijo
        // `base64:`, así que se comparan sus DOS formas (cruda y decodificada) para reconocerla
        // venga como venga escrita en el .env.
        $dev        = self::DEV_SEAL_KEY;
        $devDecoded = str_starts_with($dev, 'base64:') ? (base64_decode(substr($dev, 7)) ?: $dev) : $dev;
        if (hash_equals($dev, $key) || hash_equals($devDecoded, $key)) {
            return ['FAIL', 'es la clave de DESARROLLO committeada en el repo → cualquiera con acceso al código '
                . 'podría forjar un sello válido. Pon una propia y secreta.'];
        }

        return ['OK', 'clave dedicada del sello configurada (' . strlen($key) . ' bytes), distinta de APP_KEY '
            . 'y de la de desarrollo.'];
    }

    // --- 8) Permisos de escritura en storage ----------------------------------------------
    private function checkStorageWritable(): array
    {
        $paths = [
            storage_path('framework'),
            storage_path('framework/sessions'),
            storage_path('framework/views'),
            storage_path('framework/cache'),
            storage_path('logs'),
            storage_path('app'),
        ];
        $bad = [];
        foreach ($paths as $p) {
            if (! is_dir($p) || ! is_writable($p)) {
                $bad[] = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $p);
            }
        }
        if (! empty($bad)) {
            return ['FAIL', 'no escribible: ' . implode(', ', $bad) . '. El web server necesita escribir ahí.'];
        }
        return ['OK', 'storage/framework, logs y app son escribibles.'];
    }

    // --- 9) storage:link ------------------------------------------------------------------
    private function checkStorageLink(): array
    {
        $link = public_path('storage');
        if (is_link($link) || is_dir($link)) {
            return ['OK', 'public/storage existe (los archivos públicos se sirven).'];
        }
        return ['FAIL', 'falta public/storage. Corre `php artisan storage:link`.'];
    }

    // --- 10) Sin datos de demo ------------------------------------------------------------
    private function checkNoDemo(): array
    {
        // Espeja database/owner-apply/2026-07-24-detectar-produccion-demo.sql: marcador [DEMO] en campos
        // visibles + cuentas @crewcare.test. Degrade-safe por tabla/columna.
        $fields = [
            ['daily_reports', 'location_name'],
            ['daily_logs', 'description'],
            ['scouting_reports', 'location_name'],
            ['hazardnotifications', 'description_hazard_unsafe_act'],
            ['unsafeconds', 'description_unsafe_cond'],
            ['injury_reports', 'what_happened'],
            ['cmedic', 'observations'],
            ['sfx_events', 'effect_label'],
            ['action_items', 'description'],
        ];
        $total = 0;
        $hits  = [];
        foreach ($fields as [$table, $col]) {
            try {
                if (Schema::hasTable($table) && Schema::hasColumn($table, $col)) {
                    $n = DB::table($table)->where($col, 'like', '%' . self::DEMO_MARK . '%')->count();
                    if ($n > 0) { $hits[] = "$table.$col=$n"; $total += $n; }
                }
            } catch (\Throwable $e) {
                // degrade-safe
            }
        }
        try {
            if (Schema::hasTable('users') && Schema::hasColumn('users', 'email')) {
                $n = DB::table('users')->where('email', 'like', '%@crewcare.test')->count();
                if ($n > 0) { $hits[] = "users@crewcare.test=$n"; $total += $n; }
            }
        } catch (\Throwable $e) {
        }

        if ($total > 0) {
            return ['FAIL', "hay {$total} rastro(s) de demo/prueba: " . implode(', ', $hits)
                . '. Límpialos (database/owner-apply/2026-07-24-borrar-produccion-demo.sql) antes de abrir.'];
        }
        return ['OK', 'sin marcador [DEMO] ni cuentas @crewcare.test.'];
    }
}
