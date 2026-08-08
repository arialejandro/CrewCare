<?php

namespace App\Support;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\Schema;

/**
 * SealVerifier — soporte del VERIFICADOR PÚBLICO de sellos (2026-07-24).
 *
 * PARA QUÉ: hoy el sello CFDI que se imprime en los 6 documentos dice "confía en mí". Un tercero
 * (auditoría, el estudio) no tiene forma de comprobarlo sin entrar a la app. Esta clase resuelve
 * un (tipo, uuid) público a un ACUSE DE INTEGRIDAD y nada más.
 *
 * ⚠ LA DEFENSA DE PRIVACIDAD ES EL DTO, NO LA VISTA.
 *   Ningún modelo sellable tiene $hidden ni $appends: si a la vista pública le llegara el modelo,
 *   cualquier serialización accidental sacaría la fila completa (InjuryReport lleva nombre,
 *   teléfono, parte del cuerpo, tratamiento, hospital, causa raíz y GPS; cmedic lleva diagnóstico,
 *   medicamentos y cédula). Por eso resolve() devuelve un ARRAY PLANO ACOTADO (5 claves de INTEGRIDAD
 *   + 4 de VIGENCIA, ninguna con contenido ni identidad) y el modelo muere dentro de este método.
 *   La vista no puede ver otra cosa aunque quiera.
 *
 * POR QUÉ SE INSTANCIA EL MODELO (y no basta Query Builder): verificar de verdad exige RECOMPUTAR
 * el hash, y canonicalSignaturePayload() se arma con attributesToArray(). Un verificador que sólo
 * leyera digital_signatures probaría que existe un sello, no que el documento no fue alterado —
 * que es todo el punto. El modelo se carga en memoria y se descarta; nunca sale de aquí.
 */
class SealVerifier
{
    /**
     * Mapa TIPO PÚBLICO → [clase, etiqueta genérica, prefijo de folio, prefijo de cadena].
     *
     * La ETIQUETA es deliberadamente genérica: dice qué CLASE de documento es, nunca de quién ni
     * sobre qué. "Reporte de accidente" no revela que hubo un lesionado con nombre y apellido.
     *
     * El tipo viaja en la URL porque los UUID son v4 ALEATORIOS: no llevan el tipo dentro, así que
     * sin él habría que probar las 6 tablas (UNION o 6 consultas). Con el tipo es 1 consulta.
     */
    const TYPES = [
        'injury' => ['App\Models\InjuryReport',       'Reporte de accidente',        'INJ',   'CREWCARE-INJ'],
        'dsr'    => ['App\Models\DailyReport',        'Reporte diario de seguridad', 'DSR',   'CREWCARE-DSR'],
        'scout'  => ['App\Models\ScoutingReport',     'Scouting de locación',        'SCOUT', 'CREWCARE-SCOUT'],
        'haz'    => ['App\Models\hazardnotification', 'Acto inseguro',               'HAZ',   'CREWCARE-HAZ'],
        'uns'    => ['App\Models\unsafecond',         'Condición insegura',          'UNS',   'CREWCARE-UNS'],
        'med'    => ['App\Models\cmedic',             'Consulta médica',             'MED',   'CREWCARE-MED'],
        // (2026-07-24) El wrap es el documento que MÁS necesita esto: es el único que se entrega
        // a un tercero (casa productora, estudio) como cierre, meses después del rodaje y sin que
        // ese tercero tenga acceso a la app. El folio del anexo lo calcula WrapReport::folio()
        // (WRAP / WRAPA), así que aquí el prefijo sólo cubre el caso general del acuse.
        'wrap'   => ['App\Models\WrapReport',         'Reporte final de wrap',       'WRAP',  'CREWCARE-WRAP'],
        // (2026-07-24 · PIEZA 3) El expediente clínico y sus anexos. La etiqueta es lo bastante
        // genérica para un acuse público: dice que es un expediente, nunca de quién ni qué
        // contiene — y aquí eso pesa más que en los otros seis, porque el documento lleva
        // alergias, patologías y antecedentes familiares.
        'exp'    => ['App\Models\formulario',            'Expediente clínico',        'EXP',   'CREWCARE-EXP'],
        'expa'   => ['App\Models\HealthRecordAddendum',  'Anexo a expediente clínico', 'EXPA', 'CREWCARE-EXPA'],
        // (2026-07-26 · delta #42) Acta de inspección de herramienta. Etiqueta genérica
        // para el acuse público: dice que es un acta de inspección, nunca de qué herramienta
        // ni el veredicto. El folio lo calcula ToolInspection::folio() (INSP-####).
        'insp'   => ['App\Models\ToolInspection',        'Acta de inspección',        'INSP',  'CREWCARE-INSP'],
        // (2026-07-30 · delta #44) Permiso de trabajo emitido. Etiqueta genérica para el acuse
        // público: dice que es un permiso de trabajo, nunca de qué actividad ni de quién. El folio
        // lo calcula IssuedPermit::folio() (PERM-####). Vigencia de 3 estados: abierto (vigente),
        // CERRADO o SUSPENDIDO (con su fecha, vía sealRetirement() con etiqueta), ALTERADO (sólo
        // si el hash no coincide). Cerrar/suspender NUNCA se lee como ALTERADO.
        'perm'   => ['App\Models\IssuedPermit',          'Permiso de trabajo',        'PERM',  'CREWCARE-PERM'],
        // (2026-07-31 · delta #45) Estudio de brote (documento clínico firmado por el médico). La
        // etiqueta es genérica para el acuse público: dice que es un estudio de brote, nunca de
        // qué ni de quién. El folio lo calcula OutbreakStudy::folio() (BRO-####). No tiene concepto
        // de retiro → dos estados: vigente / alterado.
        'brote'  => ['App\Models\OutbreakStudy',         'Estudio de brote',          'BRO',   'CREWCARE-BRO'],
        // (2026-07-31 · delta #46) Póster MEDEVAC — protocolo de emergencias por locación, primera
        // plantilla del motor de documentos. Etiqueta genérica para el acuse público: dice que es un
        // póster MEDEVAC, nunca de qué locación. El folio lo calcula MedevacPoster::folio() (MDVC-####).
        // Cada emisión es INDEPENDIENTE (sin cadena/sustituye-a) → NO tiene concepto de retiro: dos
        // estados, vigente / alterado.
        'mdvc'   => ['App\Models\MedevacPoster',          'Póster MEDEVAC',            'MDVC',  'CREWCARE-MDVC'],
        // (2026-08-03 · delta #50) Mapeo de riesgos y recursos. Documento por locación con
        // una página por vista (imagen anotada + marcadores). Etiqueta genérica para el acuse
        // público: dice que es un mapeo, nunca de qué locación. El folio lo calcula
        // RiskMap::folio() (RMAP-####). Emisiones INDEPENDIENTES (sin cadena/sustituye-a) → sin
        // concepto de retiro: dos estados, vigente / alterado.
        'rmap'   => ['App\Models\RiskMap',                'Mapeo de riesgos y recursos', 'RMAP',  'CREWCARE-RMAP'],
        // (2026-08-06) PAE — Plan de Atención a Emergencias, UNO por llamado (puede cubrir dos
        // locaciones en company move). Segundo documento del motor de salida (hermano del MEDEVAC).
        // Etiqueta genérica para el acuse público: dice que es un PAE, nunca de qué producción. El
        // folio lo calcula EmergencyActionPlan::folio() (PAE-####). Emisiones INDEPENDIENTES (sin
        // cadena/sustituye-a) → sin concepto de retiro: dos estados, vigente / alterado.
        'pae'    => ['App\Models\EmergencyActionPlan',    'Plan de Atención a Emergencias', 'PAE', 'CREWCARE-PAE'],
        // (2026-08-08 · delta #52) Acta de verificación de ambulancia en sitio. Etiqueta genérica
        // para el acuse público: dice que es un acta de verificación de ambulancia, nunca de qué
        // unidad ni el veredicto. El folio lo calcula AmbulanceInspection::folio() (AMBU-####).
        // Vigencia de 3 estados: vigente / RETIRADO (con fecha y, si existe, folio que sustituye,
        // vía sealRetirement()) / ALTERADO (solo si el hash no coincide). Retirar ≠ alterar.
        'ambu'   => ['App\Models\AmbulanceInspection',    'Acta de verificación de ambulancia', 'AMBU', 'CREWCARE-AMBU'],
    ];

    /**
     * Resuelve (tipo, uuid) al ACUSE DE INTEGRIDAD.
     *
     * @param  string  $tipo
     * @param  string  $uuid
     * @return array|null  ['type_label','folio','uuid','sealed_at','verdict'] (integridad) +
     *                     ['retired','retired_at','retired_label','superseded_folio'] (vigencia), o null si no se
     *                     encontró. null NO distingue "tipo inválido" de "uuid inexistente":
     *                     el controlador responde igual en ambos casos para no filtrar por qué falló.
     */
    public static function resolve($tipo, $uuid)
    {
        if (! isset(self::TYPES[$tipo])) {
            return null;
        }
        list($class, $label, $folioPrefix) = self::TYPES[$tipo];

        if (! class_exists($class)) {
            return null;
        }

        $model = new $class;
        // Defensivo: una instancia sin el ALTER de uuid aplicado no puede resolverse.
        if (! Schema::hasColumn($model->getTable(), 'uuid')) {
            return null;
        }

        $doc = $class::where('uuid', $uuid)->first();
        if (! $doc) {
            return null;
        }

        // Recomputa el hash contra el sello guardado: true = íntegro, false = alterado,
        // null = nunca se selló (documento anterior al código de sellado).
        $integro = $doc->verifyLatestSignature();
        $firma   = $doc->signatures()->latest('id')->first();

        // RETIRO (tres estados, no dos). Un documento retirado o sustituido SIGUE ÍNTEGRO —
        // cambió su estado, no su sello: NUNCA se marca ALTERADO por retirarse. Se pregunta al
        // modelo por su propio concepto de retiro vía sealRetirement(); los tipos que NO tienen
        // concepto de retiro (9 de 10 hoy) no implementan el método → null → "válido y vigente"
        // (hueco declarado, nunca un estado inventado). Solo aplica si el sello ES íntegro:
        // ALTERADO manda sobre RETIRADO (la integridad es lo grave).
        $retiro = ($integro === true && method_exists($doc, 'sealRetirement'))
            ? $doc->sealRetirement()
            : null;

        // FOLIO: el patrón general es prefijo + id acolchado, y así lo arman las seis vistas. Pero
        // un modelo puede tener DOS folios legítimos según su tipo —el wrap emite WRAP-0001 para
        // el cierre y WRAPA-0002 para un anexo— y entonces el acuse público debe decir EXACTAMENTE
        // el mismo folio que está impreso en el papel que el tercero tiene en la mano. Si el modelo
        // sabe su folio, manda él; si no, se mantiene el patrón de siempre.
        $folio = method_exists($doc, 'folio')
            ? (string) $doc->folio()
            : $folioPrefix . '-' . str_pad((string) $doc->getKey(), 4, '0', STR_PAD_LEFT);

        $dto = [
            'type_label' => $label,
            'folio'      => $folio,
            'uuid'       => (string) $doc->uuid,
            'sealed_at'  => ($firma && $firma->signed_at)
                ? \Carbon\Carbon::parse($firma->signed_at)->format('d/m/Y H:i:s')
                : null,
            'verdict'    => $integro === true ? 'ok' : ($integro === false ? 'altered' : 'unsealed'),
            // Tres estados: el verdict de INTEGRIDAD (arriba) + el de VIGENCIA (abajo). Un doc
            // puede ser 'ok' (íntegro) y a la vez estar retirado. No es PII (misma clase que folio).
            // `retired_label` deja que cada tipo nombre su estado terminal ('Retirado' para el acta,
            // 'Cerrado'/'Suspendido' para el permiso); NULL → la vista cae a "Retirado".
            'retired'          => $retiro !== null,
            'retired_at'       => $retiro['retired_at'] ?? null,
            'retired_label'    => $retiro['retired_label'] ?? null,
            'superseded_folio' => $retiro['superseded_folio'] ?? null,
        ];

        // El modelo muere aquí: fuera de este método sólo viajan esas claves (5 de integridad
        // + 4 de vigencia). Ninguna revela contenido ni identidad.
        unset($doc, $firma, $model);

        return $dto;
    }

    /**
     * Tipo público de un modelo sellado (para armar el QR desde _seal-cfdi sin que cada una de las
     * 6 vistas tenga que pasar un parámetro nuevo). null si el modelo no es de los seis.
     *
     * @return string|null
     */
    public static function typeFor($doc)
    {
        if (! is_object($doc)) {
            return null;
        }
        foreach (self::TYPES as $key => $meta) {
            if ($doc instanceof $meta[0]) {
                return $key;
            }
        }
        return null;
    }

    /**
     * URL pública del verificador para un documento. null si no aplica (sin uuid o tipo no mapeado)
     * → el sello simplemente no pinta el QR, en vez de imprimir un enlace muerto en papel.
     *
     * @return string|null
     */
    public static function urlFor($doc)
    {
        $tipo = self::typeFor($doc);
        if (! $tipo || empty($doc->uuid)) {
            return null;
        }
        // url() toma la raíz del REQUEST, que es lo correcto: el sello se pinta al servir la página
        // y al imprimirla desde el navegador. dompdf NO es una excepción: corre dentro de la acción
        // del controlador, o sea dentro de la petición, así que también ve la raíz buena.
        //
        // ⚠ FUERA de una petición (comando de consola, cola real) url() cae a APP_URL. El
        // 2026-07-24 esa variable valía '127.0.0.1' SIN esquema y devolvía
        // 'http://localhost/127.0.0.1/verificar/...'. Ya está corregida (con http://), y en
        // producción debe ser 'https://crewcarer.mx'. Se deja anotado porque el QR SE IMPRIME EN
        // PAPEL y un papel entregado no se corrige: si algún día este sello se pinta desde un job
        // o un correo, la única defensa es que APP_URL esté bien.
        return url('/verificar/' . $tipo . '/' . $doc->uuid);
    }

    /**
     * QR en SVG (bacon/bacon-qr-code): sin GD, sin extensiones nativas, sin peticiones externas —
     * la CSP de la app bloquea CDNs y el documento se imprime en papel.
     *
     * @param  string  $url
     * @param  int     $size  lado en px
     * @return string|null    SVG listo para incrustar, sin la declaración <?xml
     */
    public static function qrSvg($url, $size = 132)
    {
        try {
            $writer = new Writer(new ImageRenderer(new RendererStyle($size, 0), new SvgImageBackEnd()));
            $svg = $writer->writeString($url);
            // Fuera la declaración XML: el SVG se incrusta dentro de un HTML, no se sirve como archivo.
            return preg_replace('/<\?xml.*?\?>\s*/s', '', $svg);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * IDENTICON derivado del hash — VERIFICABILIDAD HUMANA, no criptografía adicional.
     *
     * Nadie compara 64 caracteres hexadecimales a ojo, pero dos identicons distintos se distinguen
     * al instante, incluso en papel y sin internet. Es un ayudante de lectura del MISMO hash que ya
     * está impreso arriba: no aporta seguridad, aporta que un humano note el cambio.
     *
     * Rejilla 5×5 con simetría vertical (como los de GitHub): 15 celdas independientes (3 columnas
     * × 5 filas) decididas por 15 bits del hash, y el color por otros 3 bytes. Cambiar un solo bit
     * del documento cambia el hash entero (SHA-256) y con él el dibujo completo.
     *
     * @param  string  $hash  hex de 64 chars
     * @param  int     $size  lado en px
     * @return string|null
     */
    public static function identiconSvg($hash, $size = 60)
    {
        $hash = strtolower(preg_replace('/[^0-9a-f]/i', '', (string) $hash));
        if (strlen($hash) < 40) {
            return null;
        }

        $bytes = [];
        for ($i = 0; $i < 20; $i++) {
            $bytes[] = hexdec(substr($hash, $i * 2, 2));
        }

        // Color desde los 3 últimos bytes, forzado a un tono legible (ni casi-blanco ni casi-negro).
        $h = ($bytes[17] / 255) * 360;
        $color = self::hslToHex($h, 62, 42);

        $cell = $size / 5;
        $rects = '';
        $bit = 0;
        for ($col = 0; $col < 3; $col++) {
            for ($row = 0; $row < 5; $row++) {
                $on = ($bytes[$bit] & 1) === 1;
                $bit++;
                if (! $on) {
                    continue;
                }
                $x = $col * $cell;
                $y = $row * $cell;
                $rects .= '<rect x="' . round($x, 2) . '" y="' . round($y, 2) . '" width="' . round($cell, 2) . '" height="' . round($cell, 2) . '"/>';
                if ($col < 2) { // espejo vertical: col 0↔4, col 1↔3; la 2 es el eje
                    $mx = (4 - $col) * $cell;
                    $rects .= '<rect x="' . round($mx, 2) . '" y="' . round($y, 2) . '" width="' . round($cell, 2) . '" height="' . round($cell, 2) . '"/>';
                }
            }
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $size . '" height="' . $size . '" '
             . 'viewBox="0 0 ' . $size . ' ' . $size . '" role="img" aria-label="Sello visual derivado del hash">'
             . '<rect width="' . $size . '" height="' . $size . '" fill="#f1f5f9"/>'
             . '<g fill="' . $color . '">' . $rects . '</g></svg>';
    }

    /** HSL → hex. Sin dependencias; sólo para el color del identicon. */
    private static function hslToHex($h, $s, $l)
    {
        $s /= 100; $l /= 100;
        $c = (1 - abs(2 * $l - 1)) * $s;
        $x = $c * (1 - abs(fmod($h / 60, 2) - 1));
        $m = $l - $c / 2;
        if ($h < 60)       { $r = $c; $g = $x; $b = 0; }
        elseif ($h < 120)  { $r = $x; $g = $c; $b = 0; }
        elseif ($h < 180)  { $r = 0; $g = $c; $b = $x; }
        elseif ($h < 240)  { $r = 0; $g = $x; $b = $c; }
        elseif ($h < 300)  { $r = $x; $g = 0; $b = $c; }
        else               { $r = $c; $g = 0; $b = $x; }
        return sprintf('#%02x%02x%02x',
            (int) round(($r + $m) * 255), (int) round(($g + $m) * 255), (int) round(($b + $m) * 255));
    }
}
