<?php

namespace App\Listeners;

use App\Support\Branding;
use App\Support\SafetyAlertRecipients;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;

/**
 * SendHighRiskSafetyAlert — aviso de seguridad TRANSACCIONAL por correo cuando se
 * reporta un evento de riesgo ALTO o EXTREMO. Registrado para los TRES eventos:
 *   AccidentReported (accidente) · UnsafeConditionReported (condición) · HazardReported (acto inseguro).
 *
 * Reglas (spec owner 2026-07-19):
 *  - Dispara SOLO si $model->risk_level ∈ {Alto, Extremo}. El nivel se LEE, no se
 *    recalcula → respeta el override del Safety Manager (CalculatesRiskMatrix ya lo fijó).
 *  - Destinatarios = unión deduplicada (manual ∪ rol ∪ puesto) vía SafetyAlertRecipients.
 *  - La vista del correo (correos.safety-alert) está DESACOPLADA del disparador: para
 *    revestirla con el formato Mailchimp del owner se cambia SOLO esa vista.
 *  - ShouldQueue: se desacopla del request (con cola 'sync' corre inline; con una cola real
 *    se procesa aparte). DEFENSIVO: cualquier fallo se loguea y se traga — el aviso es un
 *    efecto colateral y JAMÁS debe romper el guardado del reporte que lo disparó.
 */
class SendHighRiskSafetyAlert implements ShouldQueue
{
    use InteractsWithQueue;

    /** Niveles que ameritan aviso inmediato. */
    const ALERT_LEVELS = ['Alto', 'Extremo'];

    /**
     * @param  mixed  $event  AccidentReported | UnsafeConditionReported | HazardReported
     *                        (los tres exponen ->model).
     */
    public function handle($event): void
    {
        try {
            $model = isset($event->model) ? $event->model : null;
            if (! $model) {
                return;
            }

            // Gate de riesgo: se LEE el valor ya calculado/override, no se recalcula.
            $risk = isset($model->risk_level) ? trim((string) $model->risk_level) : '';
            if (! in_array($risk, self::ALERT_LEVELS, true)) {
                return;
            }

            $recipients = SafetyAlertRecipients::resolve();
            if (empty($recipients)) {
                Log::info('SendHighRiskSafetyAlert: sin destinatarios resueltos; no se envía.');
                return;
            }

            $info     = $this->extract($model);
            $branding = $this->branding();
            $subject  = '[' . ($info['risk_level'] !== '' ? $info['risk_level'] : 'Riesgo') . '] '
                      . $info['type_label']
                      . ($info['production'] !== '' ? ' — ' . $info['production'] : '');

            foreach ($recipients as $r) {
                try {
                    $payload = array_merge($info, [
                        'branding'       => $branding,
                        'recipient_name' => $r['name'],
                        'subject'        => $subject,
                    ]);
                    Mail::send('correos.safety-alert', $payload, function ($m) use ($r, $subject) {
                        // from() global (config/mail.php: noreply@crewcare.mx) — no se sobreescribe.
                        $m->to($r['email'], $r['name']);
                        $m->subject($subject);
                    });
                } catch (\Throwable $e) {
                    // Un correo que falla no detiene a los demás.
                    Log::warning('SendHighRiskSafetyAlert: fallo enviando a ' . $r['email'] . ' — ' . $e->getMessage());
                }
            }
        } catch (\Throwable $e) {
            // Blindaje total: nunca propagar (el store() del reporte no debe verse afectado).
            Log::error('SendHighRiskSafetyAlert: error general — ' . $e->getMessage());
        }
    }

    /**
     * Normaliza QUÉ / CUÁNDO / DÓNDE + enlace desde el modelo fuente. Cada tipo tiene sus
     * propios nombres de columna; todo se lee con isset/?? para no romper si falta una.
     *
     * @return array
     */
    private function extract($model): array
    {
        $id   = isset($model->id) ? $model->id : null;
        $info = [
            'type_label' => 'Evento de seguridad',
            'what'       => '',
            'when_date'  => '',
            'when_time'  => '',
            'where'      => '',
            'gps'        => '',
            'production' => '',
            'risk_level' => isset($model->risk_level) ? (string) $model->risk_level : '',
            'url'        => null,
            'id'         => $id,
        ];

        if ($model instanceof \App\Models\InjuryReport) {
            $info['type_label'] = 'Accidente / Lesión';
            $info['what']       = self::firstNonEmpty([$model->injury_type ?? '', $model->what_happened ?? '']);
            $info['when_date']  = self::dateStr($model->incident_date ?? null);
            $info['when_time']  = self::timeStr($model->time ?? null);
            $info['where']      = self::firstNonEmpty([$model->incident_location ?? '', $model->location ?? '']);
            $info['gps']        = (string) ($model->gps_address ?? '');
            $info['production'] = (string) ($model->production_title ?? '');
            $info['url']        = self::routeUrl('injury_reports.show', $id);
        } elseif ($model instanceof \App\Models\unsafecond) {
            $info['type_label'] = 'Condición insegura';
            $info['what']       = (string) ($model->description_unsafe_cond ?? '');
            $info['when_date']  = self::dateStr($model->date_observed ?? null);
            $info['when_time']  = self::timeStr($model->time_observed ?? null);
            $info['where']      = self::firstNonEmpty([$model->location_unsafe_cond ?? '', $model->name_loc ?? '']);
            $info['gps']        = (string) ($model->gps_address ?? '');
            $info['production'] = (string) ($model->production_name ?? '');
            $info['url']        = self::routeUrl('unsafenotifications.show', $id);
        } elseif ($model instanceof \App\Models\hazardnotification) {
            $info['type_label'] = 'Acto inseguro / Peligro';
            $info['what']       = (string) ($model->description_hazard_unsafe_act ?? '');
            $info['when_date']  = self::dateStr($model->date_observed ?? null);
            $info['when_time']  = self::timeStr($model->time_observed ?? null);
            $info['where']      = self::firstNonEmpty([$model->location_hazard_unsafe_act ?? '', $model->name_loc ?? '']);
            $info['gps']        = (string) ($model->gps_address ?? '');
            $info['production'] = (string) ($model->production_name ?? '');
            $info['url']        = self::routeUrl('hazard_notifications.show', $id);
        }

        return $info;
    }

    private function branding(): array
    {
        try {
            return Branding::all();
        } catch (\Throwable $e) {
            return Branding::DEFAULTS;
        }
    }

    private static function firstNonEmpty(array $vals): string
    {
        foreach ($vals as $v) {
            $v = trim((string) $v);
            if ($v !== '') {
                return $v;
            }
        }
        return '';
    }

    private static function dateStr($v): string
    {
        if (empty($v)) {
            return '';
        }
        try {
            return Carbon::parse($v)->format('d/m/Y');
        } catch (\Throwable $e) {
            return is_string($v) ? $v : '';
        }
    }

    private static function timeStr($v): string
    {
        $s = trim((string) $v);
        if ($s === '') {
            return '';
        }
        // Si viene como datetime completo, quédate con la parte de la hora.
        if (strpos($s, ' ') !== false) {
            $s = substr($s, strpos($s, ' ') + 1);
        }
        return substr($s, 0, 5);
    }

    private static function routeUrl(string $name, $id): ?string
    {
        if (empty($id)) {
            return null;
        }
        try {
            if (Route::has($name)) {
                return route($name, $id);
            }
        } catch (\Throwable $e) {
            // sin URL absoluta disponible → el correo simplemente no muestra el botón.
        }
        return null;
    }
}
