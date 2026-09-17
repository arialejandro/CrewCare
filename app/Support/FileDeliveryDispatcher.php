<?php

namespace App\Support;

use App\Models\FileDelivery;
use App\Models\FileDeliveryRecipient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * DESPACHADOR del outbox — la ÚNICA lógica que envía. La usan por igual:
 *   · la RÁFAGA inline al presionar "Enviar" (unos cuantos salen al instante), y
 *   · el COMANDO agendado `deliveries:dispatch` (cron `schedule:run`, drena el resto cada minuto).
 *
 * Por cada destinatario: marca el PDF base con su nombre en créditos ({@see PdfWatermarker}) y lo
 * manda por correo. Reclamo ATÓMICO (bump de `attempts` + `last_attempt_at`) para que inline y cron no
 * procesen la misma fila; una fila "en vuelo" no se re-toma hasta pasados {@see self::CLAIM_TTL}s.
 * Best-effort: un correo que falla reintenta hasta MAX_ATTEMPTS; nunca propaga.
 */
class FileDeliveryDispatcher
{
    /** Segundos que una fila reclamada queda "en vuelo" antes de poder re-tomarse (reintento). */
    private const CLAIM_TTL = 90;

    /**
     * Drena hasta $limit destinatarios pendientes, respetando un presupuesto de tiempo (0 = sin tope).
     *
     * @return array{processed:int, sent:int, failed:int}
     */
    public static function drain(int $limit = 40, float $budgetSecs = 0): array
    {
        // DEFENSIVO: sin las tablas (instancia sin la actualización) el cron NO truena — no hace nada.
        if (! Schema::hasTable('file_delivery_recipients') || ! Schema::hasTable('file_deliveries')) {
            return ['processed' => 0, 'sent' => 0, 'failed' => 0];
        }

        $start     = microtime(true);
        $sent      = 0;
        $failed    = 0;
        $processed = 0;
        $baseCache = [];   // delivery_id => bytes del PDF base (evita releer disco por persona)

        $cutoff = now()->subSeconds(self::CLAIM_TTL);

        $ids = FileDeliveryRecipient::where('status', FileDeliveryRecipient::PENDING)
            ->where('attempts', '<', FileDeliveryRecipient::MAX_ATTEMPTS)
            ->where(function ($q) use ($cutoff) {
                $q->whereNull('last_attempt_at')->orWhere('last_attempt_at', '<', $cutoff);
            })
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id');

        foreach ($ids as $id) {
            if ($budgetSecs > 0 && (microtime(true) - $start) > $budgetSecs) {
                break;
            }

            // Reclamo atómico: solo procede quien logra el UPDATE guardado (evita doble envío).
            $claimed = FileDeliveryRecipient::whereKey($id)
                ->where('status', FileDeliveryRecipient::PENDING)
                ->where(function ($q) use ($cutoff) {
                    $q->whereNull('last_attempt_at')->orWhere('last_attempt_at', '<', $cutoff);
                })
                ->update(['attempts' => DB::raw('attempts + 1'), 'last_attempt_at' => now()]);

            if (! $claimed) {
                continue;   // otro proceso la tomó
            }

            $r = FileDeliveryRecipient::find($id);
            if (! $r) {
                continue;
            }

            $processed++;
            self::sendOne($r, $baseCache) ? $sent++ : $failed++;
        }

        return compact('processed', 'sent', 'failed');
    }

    /** ¿Quedan pendientes con reintentos? (para saber si el cron aún tiene trabajo). */
    public static function hasPending(): bool
    {
        if (! Schema::hasTable('file_delivery_recipients')) {
            return false;
        }

        return FileDeliveryRecipient::where('status', FileDeliveryRecipient::PENDING)
            ->where('attempts', '<', FileDeliveryRecipient::MAX_ATTEMPTS)
            ->exists();
    }

    /**
     * Marca + envía a UN destinatario. Devuelve true si salió. La fila ya trae `attempts` incrementado
     * (reclamo). En error: si agotó reintentos → `failed`; si no, se queda `pending` para el próximo ciclo.
     */
    private static function sendOne(FileDeliveryRecipient $r, array &$baseCache): bool
    {
        try {
            $delivery = $r->delivery;
            if (! $delivery) {
                throw new \RuntimeException('envío huérfano');
            }
            if (! $r->email) {
                throw new \RuntimeException('sin correo');
            }

            // PDF base (cacheado por envío).
            if (! array_key_exists($delivery->id, $baseCache)) {
                $baseCache[$delivery->id] = ($delivery->base_path && Storage::disk('local')->exists($delivery->base_path))
                    ? Storage::disk('local')->get($delivery->base_path)
                    : null;
            }
            $bytes = $baseCache[$delivery->id];
            if ($bytes === null || $bytes === '') {
                throw new \RuntimeException('PDF base no disponible');
            }

            // Marca de agua por persona (nombre en créditos). Si no hay texto, se manda sin marca.
            if ($delivery->watermark && $r->watermark_text) {
                $bytes = PdfWatermarker::diagonal($bytes, $r->watermark_text);
            }

            self::mail($r, $delivery, $bytes);

            $r->update([
                'status'  => FileDeliveryRecipient::SENT,
                'sent_at' => now(),
                'error'   => null,
            ]);

            return true;
        } catch (\Throwable $e) {
            $agotado = $r->attempts >= FileDeliveryRecipient::MAX_ATTEMPTS;
            $r->update([
                'status' => $agotado ? FileDeliveryRecipient::FAILED : FileDeliveryRecipient::PENDING,
                'error'  => mb_substr($e->getMessage(), 0, 500),
            ]);
            Log::warning('FileDeliveryDispatcher: destinatario ' . $r->id . ' — ' . $e->getMessage());

            return false;
        }
    }

    /** El correo con el PDF (ya marcado) adjunto. */
    private static function mail(FileDeliveryRecipient $r, FileDelivery $delivery, string $pdf): void
    {
        $data = [
            'toName' => $r->name,
            'title'  => $delivery->title,
            'body'   => $delivery->body,
        ];

        Mail::send('correos.file-delivery', $data, function ($m) use ($r, $delivery, $pdf) {
            $m->from('noreply@crewcare.mx', 'CrewCare');
            $m->to($r->email, $r->name ?: null);
            $m->subject($delivery->title);
            $m->attachData($pdf, $delivery->base_name, ['mime' => 'application/pdf']);
        });
    }
}
