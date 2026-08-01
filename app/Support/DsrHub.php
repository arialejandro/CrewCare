<?php

namespace App\Support;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Models\DailyReport;
use App\Models\DailyLog;
use App\Support\Features;

/**
 * DsrHub — NÚCLEO de inyección al DSR (Pilar 2, Event-Driven).
 *
 * Resuelve el Daily Safety Report (expediente clínico/parte diario) del día y
 * le inyecta un daily_log DE-DUPLICADO por el modelo fuente (accidente,
 * condición insegura, consulta ligada, etc.). Corre DENTRO del evento `created`
 * del reporte fuente, así que es DEFENSIVO y NUNCA lanza: cualquier fallo se
 * registra en Log y regresa null — jamás aborta el guardado del reporte.
 */
class DsrHub
{
    /**
     * Encuentra (o crea mínimamente) el DSR de una fecha.
     *
     * @param  mixed     $date       string|Carbon; se normaliza a Y-m-d.
     * @param  int|null  $creatorId  autofirma del creador (si la col existe).
     * @return \App\Models\DailyReport|null
     */
    public static function resolveForDate($date, $creatorId = null)
    {
        if (! Schema::hasTable('daily_reports')) {
            return null;
        }

        try {
            $d = Carbon::parse($date)->format('Y-m-d');
        } catch (\Throwable $e) {
            $d = Carbon::now()->format('Y-m-d');
        }

        // El más reciente de ese día gana (permite múltiples partes por jornada).
        $existing = DailyReport::whereDate('report_date', $d)
            ->latest('id')
            ->first();
        if ($existing) {
            return $existing;
        }

        // No hay parte del día → crea uno MÍNIMO. Envuelto: si falla, null.
        try {
            $attrs = ['report_date' => $d];
            if (Schema::hasColumn('daily_reports', 'author_name')) {
                $attrs['author_name'] = 'Sistema (auto)';
            }
            if (Schema::hasColumn('daily_reports', 'created_by_id')) {
                $attrs['created_by_id'] = $creatorId;
            }

            $report = DailyReport::create($attrs);

            // (2026-07-22) Sellarlo al nacer, igual que hace DailyReportController@store.
            // Sin esto los DSR creados por esta vía quedaban SIN NINGUNA firma para siempre
            // — verificado en la BD: los reportes #13, #14 y #15 ("Sistema (auto)") son
            // posteriores al código de sellado y aun así no tienen ni una fila en
            // digital_signatures. Se sella como SISTEMA porque aquí no actúa una persona:
            // el parte lo abre un evento (un accidente, una condición insegura, un SFX).
            $report->refresh();
            $report->signDocumentAsSystem('sistema:alta-automatica');

            return $report;
        } catch (\Throwable $e) {
            Log::warning('DsrHub::resolveForDate no pudo crear el DSR mínimo: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Inyecta (idempotente) un daily_log en el DSR del día por el modelo fuente.
     *
     * @param  mixed     $source     modelo fuente (polimórfico).
     * @param  mixed     $date       fecha del hecho (string|Carbon).
     * @param  array     $attrs      ['log_time'?, 'description'?, 'action_taken'?].
     * @param  int|null  $creatorId  autofirma del creador.
     * @return \App\Models\DailyLog|null
     */
    public static function inject($source, $date, array $attrs, $creatorId = null)
    {
        if (! Features::enabled('dsr_injection')) {
            return null;
        }

        // Sin la columna polimórfica no hay a dónde de-duplicar → no-op silencioso.
        if (! Schema::hasColumn('daily_logs', 'sourceable_type')) {
            return null;
        }

        try {
            $dsr = self::resolveForDate($date, $creatorId);
            if (! $dsr) {
                return null;
            }

            $keys = [
                'sourceable_type' => get_class($source),
                'sourceable_id'   => $source->getKey(),
            ];

            $values = array_merge([
                'daily_report_id' => $dsr->id,
                'log_time'        => (isset($attrs['log_time']) ? $attrs['log_time'] : now()->format('H:i')),
                'description'     => (isset($attrs['description']) ? $attrs['description'] : ''),
                'action_taken'    => (isset($attrs['action_taken']) ? $attrs['action_taken'] : null),
            ], (Schema::hasColumn('daily_logs', 'created_by_id') && $creatorId
                ? ['created_by_id' => $creatorId]
                : []));

            // updateOrCreate: un solo log por fuente (idempotente ante re-eventos).
            return DailyLog::updateOrCreate($keys, $values);
        } catch (\Throwable $e) {
            Log::warning('DsrHub::inject falló (no aborta el guardado): ' . $e->getMessage());
            return null;
        }
    }
}
