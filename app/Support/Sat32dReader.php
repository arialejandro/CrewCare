<?php

namespace App\Support;

use App\Models\ExternalAuthorization;
use Illuminate\Support\Carbon;
use Smalot\PdfParser\Parser;

/**
 * LECTOR DE LA 32-D (Opinión de cumplimiento) — extrae del acuse los cuatro datos del enlace del SAT
 * directo de su CADENA ORIGINAL, que es un texto ESTRUCTURADO (no OCR, no adivinar):
 *
 *     ||RFC|FOLIO|dd-mm-aaaa|P||serie||
 *
 * El 32-D SIEMPRE llega como PDF con capa de texto (nunca escaneado), así que la cadena está ahí. Si
 * por lo que sea el PDF no la trae (o no se puede leer), devuelve null → el folio se captura a mano
 * (fallback intacto). Best-effort: NUNCA lanza; una lectura fallida no rompe la recepción del documento.
 *
 * 🔴 NADA de esto consulta al SAT ni valida: solo LEE el papel que subió el contribuyente para PRE-LLENAR
 * lo que arma el enlace (que abre un humano). Recibir no es validar.
 */
class Sat32dReader
{
    /** @return null|array{rfc:string, folio:string, fecha:?string, sentido:string} */
    public static function fromPdf(string $absolutePath): ?array
    {
        if (! is_file($absolutePath)) {
            return null;
        }
        try {
            $text = (new Parser())->parseFile($absolutePath)->getText();
        } catch (\Throwable $e) {
            return null;   // PDF ilegible/escaneado/protegido → captura manual
        }

        return self::fromText($text);
    }

    /** Extrae la Cadena Original de un texto ya extraído. Público para poder probarlo sin un PDF. */
    public static function fromText(string $text): ?array
    {
        // ||RFC(12-13)|FOLIO|dd-mm-aaaa|P o N|...  — RFC = 3-4 letras + 6 dígitos + 3 de homoclave.
        if (! preg_match('/\|\|([A-ZÑ&]{3,4}\d{6}[A-Z0-9]{3})\|([0-9A-Z]{5,20})\|(\d{2}-\d{2}-\d{4})\|([PN])\|/u', $text, $m)) {
            return null;
        }

        $fecha = null;
        try {
            $fecha = Carbon::createFromFormat('!d-m-Y', $m[3])->format('Y-m-d');
        } catch (\Throwable $e) {
            $fecha = null;
        }

        return [
            'rfc'     => strtoupper($m[1]),
            'folio'   => strtoupper($m[2]),
            'fecha'   => $fecha,
            'sentido' => $m[4] === 'P' ? ExternalAuthorization::RESULT_POSITIVE : ExternalAuthorization::RESULT_NEGATIVE,
        ];
    }
}
