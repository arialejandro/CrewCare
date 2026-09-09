<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * TsaStamper — motor de fondo del sello de tiempo (RFC 3161), calcado del outbox de envíos
 * (FileDeliveryDispatcher). Con la cola en `sync`, el cron `tsa:stamp` ES el motor: escanea
 * los sellos (digital_signatures) que aún no tienen token, los timbra best-effort en freeTSA
 * y guarda el resultado. NO toca el sellado ni el trait: descubre las firmas y las timbra aparte.
 *
 * Cubre sellos NUEVOS y VIEJOS (retroactivo): siembra una fila 'pending' por cada firma sin token.
 */
class TsaStamper
{
    private const MAX_ATTEMPTS = 5;
    private const CLAIM_TTL     = 120;   // segundos: ventana de reintento tras un intento fallido

    public const ST_PENDING = 'pending';
    public const ST_STAMPED = 'stamped';
    public const ST_FAILED  = 'failed';

    /** @return array{seeded:int, processed:int, stamped:int, failed:int} */
    public static function drain(int $limit = 40): array
    {
        $res = ['seeded' => 0, 'processed' => 0, 'stamped' => 0, 'failed' => 0];

        if (! Schema::hasTable('signature_timestamps') || ! Schema::hasTable('digital_signatures')) {
            return $res;   // defensivo: instancia sin el SQL aplicado → no revienta.
        }

        $res['seeded'] = self::seedPending($limit * 4);

        // Reclama filas pendientes con reintentos restantes y cuya ventana de reintento venció.
        $cutoff = now()->subSeconds(self::CLAIM_TTL);
        $ids = DB::table('signature_timestamps')
            ->where('status', self::ST_PENDING)
            ->where('attempts', '<', self::MAX_ATTEMPTS)
            ->where(function ($q) use ($cutoff) {
                $q->whereNull('last_attempt_at')->orWhere('last_attempt_at', '<', $cutoff);
            })
            ->orderBy('id')->limit($limit)->pluck('id');

        foreach ($ids as $id) {
            // Reclamo ATÓMICO: sólo el ganador incrementa attempts y procede (evita doble timbrado).
            $claimed = DB::table('signature_timestamps')
                ->where('id', $id)->where('status', self::ST_PENDING)
                ->where('attempts', '<', self::MAX_ATTEMPTS)
                ->update(['attempts' => DB::raw('attempts + 1'), 'last_attempt_at' => now(), 'updated_at' => now()]);
            if (! $claimed) {
                continue;
            }
            $res['processed']++;

            try {
                $row  = DB::table('signature_timestamps')->where('id', $id)->first();
                $hash = DB::table('digital_signatures')->where('id', $row->signature_id)->value('document_hash');
                if (! $hash) {
                    // La firma ya no existe → marca fallida terminal, no reintenta eternamente.
                    DB::table('signature_timestamps')->where('id', $id)->update(['status' => self::ST_FAILED, 'error' => 'firma inexistente', 'updated_at' => now()]);
                    $res['failed']++;
                    continue;
                }

                $stamp = Rfc3161::stamp((string) $hash);
                if ($stamp === null) {
                    // freeTSA no respondió → sigue 'pending'; el cron reintenta. Si agotó intentos, falla.
                    $attempts = (int) DB::table('signature_timestamps')->where('id', $id)->value('attempts');
                    if ($attempts >= self::MAX_ATTEMPTS) {
                        DB::table('signature_timestamps')->where('id', $id)->update(['status' => self::ST_FAILED, 'error' => 'TSA sin respuesta', 'updated_at' => now()]);
                        $res['failed']++;
                    }
                    continue;
                }

                DB::table('signature_timestamps')->where('id', $id)->update([
                    'status'     => self::ST_STAMPED,
                    'authority'  => $stamp['authority'],
                    'imprint'    => $stamp['imprint'],
                    'tsr'        => base64_encode($stamp['tsr']),
                    'gen_time'   => $stamp['gen_time'],   // tiempo autoritativo de la TSA (puede ser null)
                    'stamped_at' => now(),
                    'error'      => null,
                    'updated_at' => now(),
                ]);
                $res['stamped']++;
            } catch (\Throwable $e) {
                Log::warning("TsaStamper: firma {$id} falló: " . $e->getMessage());
                // No propaga: best-effort. Queda 'pending' para el siguiente ciclo (o failed si agotó).
            }
        }

        return $res;
    }

    /** Siembra una fila 'pending' por cada digital_signatures sin token. Devuelve cuántas sembró. */
    private static function seedPending(int $limit): int
    {
        $ids = DB::table('digital_signatures as s')
            ->leftJoin('signature_timestamps as t', 't.signature_id', '=', 's.id')
            ->whereNull('t.id')
            ->orderBy('s.id')->limit($limit)->pluck('s.id');

        $seeded = 0;
        foreach ($ids as $sid) {
            // insertOrIgnore por el UNIQUE(signature_id) → dos corridas no duplican.
            $seeded += DB::table('signature_timestamps')->insertOrIgnore([
                'signature_id' => $sid, 'status' => self::ST_PENDING, 'attempts' => 0,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        return $seeded;
    }

    /** Token STAMPED de una firma (para el verificador público). null si no hay. Incluye el
     *  `imprint` (SHA-256 del document_hash en hex) = el valor que el timbre atestigua, para
     *  mostrarlo junto al acuse (no es PII: es un hash, misma clase que el folio). */
    public static function stampedFor(int $signatureId): ?object
    {
        if (! Schema::hasTable('signature_timestamps')) {
            return null;
        }
        return DB::table('signature_timestamps')
            ->where('signature_id', $signatureId)->where('status', self::ST_STAMPED)
            ->first(['authority', 'gen_time', 'stamped_at', 'imprint']);
    }

    /** Bytes CRUDOS del token RFC 3161 (.tsr) de una firma, para descargarlo desde el verificador
     *  público. Se guarda en base64 → se decodifica aquí. null si no hay timbre. NO es sensible:
     *  es un timbre sobre un hash; su razón de ser es que un tercero verifique SIN CrewCare. */
    public static function tokenBytesFor(int $signatureId): ?string
    {
        if (! Schema::hasTable('signature_timestamps')) {
            return null;
        }
        $b64 = DB::table('signature_timestamps')
            ->where('signature_id', $signatureId)->where('status', self::ST_STAMPED)
            ->value('tsr');
        if (! $b64) {
            return null;
        }
        $raw = base64_decode((string) $b64, true);
        return ($raw === false || $raw === '') ? null : $raw;
    }
}
