<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Rfc3161 — cliente MÍNIMO de sello de tiempo RFC 3161 (freeTSA por defecto).
 *
 * Timbra el HASH de un sello (imprint = SHA-256 del document_hash) contra una TSA externa y
 * devuelve el token (TSR) + el tiempo autoritativo. SOLO el hash viaja → confidencialidad intacta.
 *
 * Sin dependencias: arma el TimeStampReq en DER a mano (es una estructura fija) y extrae el
 * genTime del token con un walk mínimo de ASN.1. Best-effort: ante CUALQUIER fallo devuelve null
 * y el llamador (TsaStamper) reintenta luego — nunca revienta ni bloquea el sellado.
 *
 * `$transport` es inyectable en pruebas: recibe el DER del request y devuelve los bytes del TSR
 * (o null). Por defecto hace el POST a la TSA.
 */
class Rfc3161
{
    /** OID de SHA-256 (2.16.840.1.101.3.4.2.1) como AlgorithmIdentifier { OID, NULL }. */
    private const SHA256_ALGID_HEX = '300d06096086480165030402010500';

    /** OID id-ct-TSTInfo (1.2.840.113549.1.9.16.1.4) — marca dónde empieza el TSTInfo en el token. */
    private const TSTINFO_OID_HEX = '060b2a864886f70d0109100104';

    /** @var null|callable fn(string $tsqDer): ?string  — inyectable en pruebas. */
    public static $transport = null;

    /**
     * Timbra un document_hash. Devuelve ['imprint'=>hex, 'tsr'=>rawBytes, 'gen_time'=>?string,
     * 'authority'=>string] o null si no se pudo (best-effort).
     */
    public static function stamp(string $documentHash): ?array
    {
        if (! config('crewcare.tsa.enabled', true)) {
            return null;
        }
        try {
            $imprintRaw = hash('sha256', $documentHash, true);   // 32 bytes
            $tsq        = self::buildTsq($imprintRaw);

            // Transporte inyectado (pruebas): una sola vía, sin lista de autoridades.
            if (self::$transport) {
                $tsr = (self::$transport)($tsq);
                return (is_string($tsr) && $tsr !== '') ? self::pack($imprintRaw, $tsr, 'test') : null;
            }

            // AUTORIDADES EN ORDEN: la primera que responda gana; se registra CUÁL fue (para poder
            // verificar a años vista contra su certificado). Si la principal no responde, cae al respaldo.
            foreach (self::authorities() as $auth) {
                $tsr = self::post($tsq, $auth['url']);
                if (is_string($tsr) && $tsr !== '') {
                    return self::pack($imprintRaw, $tsr, $auth['name']);
                }
                Log::warning("Rfc3161: la TSA '{$auth['name']}' ({$auth['url']}) no respondió; probando la siguiente.");
            }
            return null;   // ninguna respondió → best-effort, el cron reintenta luego.
        } catch (\Throwable $e) {
            Log::warning('Rfc3161::stamp falló: ' . $e->getMessage());
            return null;
        }
    }

    /** Empaqueta la respuesta: imprint + token + genTime + qué autoridad lo emitió. */
    private static function pack(string $imprintRaw, string $tsr, string $authority): array
    {
        return [
            'imprint'   => bin2hex($imprintRaw),
            'tsr'       => $tsr,
            'gen_time'  => self::genTimeFromTsr($tsr),
            'authority' => $authority,
        ];
    }

    /** Lista ORDENADA de autoridades [{name,url}]. Retro-compat con el esquema viejo de una sola URL. */
    private static function authorities(): array
    {
        $list = config('crewcare.tsa.authorities');
        if (is_array($list) && $list !== []) {
            $out = [];
            foreach ($list as $a) {
                if (! empty($a['url'])) {
                    $out[] = ['name' => (string) ($a['name'] ?? 'TSA'), 'url' => (string) $a['url']];
                }
            }
            if ($out !== []) {
                return $out;
            }
        }
        // Esquema viejo (una sola URL) por si sigue en el .env de alguien.
        return [[
            'name' => (string) config('crewcare.tsa.authority', 'freeTSA'),
            'url'  => (string) config('crewcare.tsa.url', 'https://freetsa.org/tsr'),
        ]];
    }

    /** POST del request a la TSA con el content-type de RFC 3161. Devuelve los bytes del TSR o null.
     *  NUNCA lanza: una conexión caída o un timeout se traducen a null para que el fallback pase a la
     *  siguiente autoridad — si la excepción escapara del bucle, se saltaría el respaldo por completo. */
    private static function post(string $tsqDer, string $url): ?string
    {
        $timeout = (int) config('crewcare.tsa.timeout', 8);

        try {
            $resp = Http::withHeaders(['Content-Type' => 'application/timestamp-query'])
                ->timeout($timeout)
                ->withBody($tsqDer, 'application/timestamp-query')
                ->post($url);

            return $resp->successful() ? $resp->body() : null;
        } catch (\Throwable $e) {
            Log::warning("Rfc3161: POST a {$url} falló: " . $e->getMessage());
            return null;
        }
    }

    /**
     * TimeStampReq ::= SEQUENCE { version INTEGER(1), messageImprint, certReq BOOLEAN TRUE }
     * messageImprint ::= SEQUENCE { AlgorithmIdentifier(SHA-256), OCTET STRING(hash) }
     */
    public static function buildTsq(string $imprintRaw): string
    {
        $algId          = hex2bin(str_replace(' ', '', self::SHA256_ALGID_HEX));
        $messageImprint = self::der(0x30, $algId . self::der(0x04, $imprintRaw));
        $version        = self::der(0x02, chr(1));
        $certReq        = self::der(0x01, chr(0xFF));   // pedimos el cert de la TSA en la respuesta
        return self::der(0x30, $version . $messageImprint . $certReq);
    }

    /** Extrae el genTime (GeneralizedTime) del TSTInfo dentro del token. null si no se puede. */
    public static function genTimeFromTsr(string $tsr): ?string
    {
        $oid = hex2bin(str_replace(' ', '', self::TSTINFO_OID_HEX));
        $at  = strpos($tsr, $oid);
        if ($at === false) {
            return null;
        }
        // El genTime es el PRIMER GeneralizedTime (tag 0x18) después del OID de TSTInfo (los
        // GeneralizedTime de los certificados, si los hay, vienen después, en otra sección).
        $n = strlen($tsr);
        for ($i = $at + strlen($oid); $i < $n - 2; $i++) {
            if (ord($tsr[$i]) !== 0x18) {
                continue;
            }
            $len = ord($tsr[$i + 1]);
            if ($len < 13 || $len > 24) {
                continue;
            }
            $val    = substr($tsr, $i + 2, $len);   // "YYYYMMDDHHMMSS[.fff]Z"
            $parsed = self::parseGeneralizedTime($val);
            if ($parsed !== null) {                 // un 0x18 espurio (bytes del hash) NO parsea → sigue
                return $parsed;
            }
        }
        return null;
    }

    private static function parseGeneralizedTime(string $v): ?string
    {
        if (! preg_match('/^(\d{4})(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})/', $v, $m)) {
            return null;
        }
        return "{$m[1]}-{$m[2]}-{$m[3]} {$m[4]}:{$m[5]}:{$m[6]}";   // UTC
    }

    /** TLV DER: tag + longitud (forma corta/larga) + contenido. */
    private static function der(int $tag, string $content): string
    {
        return chr($tag) . self::derLen(strlen($content)) . $content;
    }

    private static function derLen(int $n): string
    {
        if ($n < 0x80) {
            return chr($n);
        }
        $bytes = '';
        while ($n > 0) {
            $bytes = chr($n & 0xFF) . $bytes;
            $n >>= 8;
        }
        return chr(0x80 | strlen($bytes)) . $bytes;
    }
}
