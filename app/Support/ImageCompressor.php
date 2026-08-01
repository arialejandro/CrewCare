<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * ImageCompressor — redimensionado + recompresión de fotos AL SUBIR (GD, server-side).
 *
 * PORQUÉ EXISTE (2026-07-21): las fotos del DSR se guardaban TAL CUAL llegaban del
 * celular. Medición real sobre las 35 fotos de hallazgo en disco: 4080×1836 px,
 * 3.5 MB de promedio, 9.17 MB la mayor, 122 MB en total. Como el PDF se genera con
 * window.print() del navegador, esas fotos se incrustan a resolución de origen y el
 * documento firmable pesa decenas de MB. En la hoja cada foto ocupa ~3.6 in en la
 * rejilla de 2 columnas → ~1090 px a 300 dpi. Mandar 4080 px es tirar peso.
 *
 * QUÉ HACE: reduce el lado mayor a MAX_EDGE, re-codifica a JPEG QUALITY y guarda en
 * el disco 'public' devolviendo la ruta relativa (misma forma que ->store(), para que
 * el llamador siga usando Storage::url() y NO cambie el formato de ruta en BD).
 *
 * QUÉ NO HACE — y es tan importante como lo anterior:
 *   · NO toca imágenes con transparencia REAL: son ASSETS DE DISEÑO (logotipos, diagramas,
 *     planos), no fotos de evidencia. Ver hasRealTransparency().
 *   · NO devuelve nunca un archivo más pesado que el original: si comprimir no ahorra, se
 *     guarda el original intacto.
 *   · NO encoge por debajo de lo que la vista necesita (ver MAX_EDGE): el objetivo es el
 *     peso, no reducir dimensiones a costa de que la portada se vea suave.
 *
 * MEDICIÓN QUE JUSTIFICA LOS NÚMEROS (4 fotos reales, este mismo GD):
 *   original 15.06 MB → JPEG q82 @1600px = 1.17 MB (−92%) → WebP q82 = 1.00 MB (−93%).
 * O sea: el ahorro lo da el REDIMENSIONADO, no el formato. WebP solo agrega ~14%
 * sobre el JPEG ya reducido, y a cambio arriesga compatibilidad (visores viejos,
 * correo a estudios y, sobre todo, dompdf — que NO renderiza WebP y sí se usa en
 * materialidad/consultas). Por eso el formato de salida por defecto es JPEG; WebP
 * queda disponible en FORMAT por si algún día conviene.
 *
 * NUNCA PIERDE EL ARCHIVO: ante cualquier problema (GD ausente, formato que GD no
 * sabe leer, imagen tan grande que no cabe en memoria, fallo de codificación) hace
 * FALLBACK al guardado normal sin tocar los píxeles. Comprimir es una mejora, no un
 * requisito: que falle la compresión jamás debe costarle al usuario su evidencia.
 *
 * ⚠ ORIENTACIÓN EXIF — la trampa que motivó la mitad de esta clase: GD NO respeta la
 *   etiqueta Orientation. Hoy las fotos verticales de celular se ven bien porque el
 *   NAVEGADOR las rota al vuelo leyendo el EXIF. Al re-codificar, el EXIF se pierde y
 *   los píxeles quedan como estaban → la foto saldría acostada en el documento. Por eso
 *   applyExifOrientation() rota/espeja ANTES de guardar. (Auditoría del 2026-07-21 sobre
 *   las 35 fotos vivas: 30 con Orientation=1 y 5 sin EXIF, o sea hoy ninguna se rompería;
 *   pero cualquier celular sostenido en vertical genera Orientation 6 u 8.)
 *
 * NOTA DE PRIVACIDAD: re-codificar descarta TODO el EXIF, incluido el GPS que muchos
 * celulares incrustan. Es deseable para un documento que se envía por correo a terceros.
 * La app no lee EXIF en ningún lado, así que no se pierde ninguna función.
 *
 * NO REESCRIBE EL HISTÓRICO: solo actúa sobre subidas nuevas. Los 122 MB ya en disco
 * pertenecen a reportes ya sellados y deben quedar exactamente como se firmaron.
 */
class ImageCompressor
{
    /**
     * Lado mayor máximo, en píxeles.
     *
     * 2200 y no 1600: el objetivo es el PESO, no encoger por encoger, y un tope bajo
     * degrada la vista donde más se nota. La hoja mide 860 px CSS, así que una portada
     * a sangre en un monitor retina pide ~1720 px reales, y en un móvil a 3x pide ~1200.
     * 1600 se quedaba justo en el borde de esos números. 2200 los cubre con margen, sigue
     * ahorrando el 89% del peso (134.9 MB → 14.8 MB sobre el corpus real) y además ALINEA
     * el proyecto: es exactamente el tope que ya usa la compresión de cliente del Scouting
     * (resources/views/admin/scoutings/_form.blade.php).
     */
    const MAX_EDGE = 2200;

    /** Calidad JPEG (0-100). 82 es el punto donde el artefacto deja de verse en foto. */
    const QUALITY = 82;

    /**
     * Formato de salida: 'jpg' (por defecto, máxima compatibilidad) o 'webp'.
     * Cambiar a 'webp' solo si se acepta que dompdf no lo renderiza.
     */
    const FORMAT = 'jpg';

    /**
     * Techo de píxeles que se acepta DECODIFICAR. Una imagen truecolor ocupa
     * ancho×alto×4 bytes en memoria: 40 MP ≈ 160 MB, que cabe en el memory_limit
     * de 512M. Por encima de eso se guarda el original sin tocar, porque intentar
     * abrirla mataría el proceso (upload_max_filesize son 2G: un JPEG de pocos MB
     * puede traer una barbaridad de píxeles).
     */
    const MAX_PIXELS = 40000000;

    /**
     * Comprime y guarda una imagen subida. Devuelve la ruta RELATIVA dentro del disco
     * (p. ej. 'daily_reports/logs/1721594400_66a1f2c3d4e5f.jpg'), igual que ->store(),
     * para que el llamador siga haciendo Storage::url($path).
     *
     * @param  \Illuminate\Http\UploadedFile $file
     * @param  string                        $directory  carpeta dentro del disco (sin slash final)
     * @param  string                        $disk
     * @return string|null  ruta relativa, o null si ni siquiera el fallback pudo guardar
     */
    public static function store(UploadedFile $file, $directory, $disk = 'public')
    {
        $directory = trim($directory, '/');

        $bytes = self::process($file);

        // REGLA DE ORO: nos quedamos con el resultado SÓLO si de verdad pesa menos.
        // Sin esta comparación la clase podía ENGORDAR el acta en vez de adelgazarla:
        // toda imagen de 1600 px o menos se re-codificaba igual, y JPEG q82 es más
        // pesado que el original cuando la imagen ya venía chica, ya optimizada, o es
        // de línea y texto (una captura PNG de 31 KB salía de 502 KB). Si no hay
        // ganancia se guarda el original intacto — que además conserva su transparencia
        // y su EXIF, así que el navegador lo sigue mostrando bien.
        if ($bytes === null || strlen($bytes) >= $file->getSize()) {
            return self::fallback($file, $directory, $disk);
        }

        try {
            $path = $directory . '/' . self::filename();
            if (Storage::disk($disk)->put($path, $bytes) === false) {
                // put() devuelve false sin lanzar: sin este control se guardaría en BD
                // la ruta de un archivo que nunca llegó a existir.
                Log::warning('ImageCompressor: Storage::put devolvió false para ' . $path);
                return self::fallback($file, $directory, $disk);
            }

            return $path;
        } catch (\Throwable $e) {
            Log::warning('ImageCompressor: no se pudo escribir la imagen comprimida: ' . $e->getMessage());
            return self::fallback($file, $directory, $disk);
        }
    }

    /**
     * Variante para el patrón MINORITARIO de la app: mover a public_path() en vez de
     * usar el disco de Laravel (lo usa MitigationController, cuya foto de la acción
     * correctiva ahora se imprime en el acta del DSR como evidencia de la solución).
     * Devuelve la ruta con slash inicial ('/uploads/mitigations/xxx.jpg'), que es el
     * formato que esa columna ya guardaba.
     *
     * @return string|null
     */
    public static function storePublic(UploadedFile $file, $directory)
    {
        $directory = trim($directory, '/');
        $dir       = public_path($directory);

        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        try {
            $bytes = self::process($file);
            // Misma regla que store(): sólo nos quedamos con el resultado si adelgaza.
            if ($bytes !== null && strlen($bytes) < $file->getSize()) {
                $name = self::filename();
                if (@file_put_contents($dir . '/' . $name, $bytes) !== false) {
                    return '/' . $directory . '/' . $name;
                }
            }

            // Fallback: mover el original sin tocar los píxeles.
            //
            // ⚠ La extensión se deriva del CONTENIDO, nunca de getClientOriginalExtension():
            //   esto se escribe DENTRO de public/ y el servidor web lo entrega tal cual. La
            //   validación de Laravel ('image|mimes:jpeg,png,jpg') mira el contenido, NO el
            //   nombre, así que un JPEG legítimo llamado "poc.html" la pasa; con la extensión
            //   del cliente acabaría servido como text/html desde el propio dominio de la app
            //   = XSS almacenado. Y es alcanzable sin sesión: la foto de mitigación se sube por
            //   un magic link público, y basta que la imagen supere MAX_PIXELS para caer aquí.
            $ext = self::safeExtension($file);
            if ($ext === null) {
                Log::warning('ImageCompressor: subida descartada, el contenido no es una imagen reconocible.');
                return null;
            }
            $name = time() . '_' . uniqid('', false) . '.' . $ext;
            $file->move($dir, $name);

            return '/' . $directory . '/' . $name;
        } catch (\Throwable $e) {
            Log::error('ImageCompressor: no se pudo guardar en public_path: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Comprime una imagen subida y la devuelve como DATA URI (base64), SIN tocar disco.
     * Para documentos SELLADOS (póster MEDEVAC) que congelan la imagen DENTRO del payload:
     * así los bytes entran al hash (cambiar la imagen marca ALTERADO) y el documento imprime
     * offline sin depender de /storage. Devuelve null si no se pudo (no es imagen, GD ausente,
     * o supera el techo de peso). El llamador debe tolerar el null (emite sin mapa).
     *
     * @param  \Illuminate\Http\UploadedFile $file
     * @param  int  $maxBytes  techo del binario (antes de base64) para no inflar el payload sellado
     * @return string|null
     */
    public static function compressToDataUri(UploadedFile $file, $maxBytes = 1200000)
    {
        $bytes = self::process($file);
        $mime  = 'image/jpeg';

        // process() devuelve null cuando NO conviene recomprimir (transparencia real, GD ausente,
        // imagen fuera del techo de memoria) → se usa el original tal cual.
        if ($bytes === null || strlen($bytes) >= (int) $file->getSize()) {
            $src = $file->getRealPath();
            if ($src === false || ! is_readable($src)) {
                return null;
            }
            $raw = @file_get_contents($src);
            if ($raw === false) {
                return null;
            }
            $bytes = $raw;
            $m = strtolower((string) $file->getMimeType());   // sniff por CONTENIDO, no por nombre
            $mime = in_array($m, ['image/png', 'image/gif', 'image/webp', 'image/jpeg'], true) ? $m : 'image/jpeg';
        }

        if (strlen($bytes) > $maxBytes) {
            return null;   // demasiado pesada para sellarla dentro del payload
        }

        return 'data:' . $mime . ';base64,' . base64_encode($bytes);
    }

    /**
     * Núcleo compartido: abre, corrige orientación, redimensiona y codifica.
     * Devuelve los bytes listos para escribir, o null si NO se pudo comprimir
     * (formato no soportado, imagen fuera del techo de memoria, GD ausente…),
     * en cuyo caso el llamador debe guardar el original.
     *
     * @return string|null
     */
    protected static function process(UploadedFile $file)
    {
        // Ruta física del temporal. Si por lo que sea no es legible, no hay nada que hacer.
        $source = $file->getRealPath();
        if ($source === false || !is_readable($source)) {
            return null;
        }

        // ASSET DE DISEÑO ≠ FOTO DE EVIDENCIA. Si la imagen trae transparencia REAL, no se
        // toca: no se recomprime, no se aplana, no se convierte. Se devuelve intacta.
        //
        // El diseño de CrewCare es propuesta de valor, no decoración — no es el clásico
        // reporte de plantilla de oficina. Aplanar a blanco un logotipo, un diagrama o un
        // plano con transparencia para ahorrar unos KB es mal negocio: rompe la pieza.
        // El aplanado sólo tiene sentido cuando el alfa es un accidente de formato en una
        // FOTO, y una foto no tiene zonas transparentes.
        if (self::hasRealTransparency($source)) {
            return null;
        }

        $resource = self::decode($source);
        if ($resource === null) {
            // Formato que GD no sabe leer, o imagen demasiado grande.
            return null;
        }

        try {
            // 1) Orientación EXIF primero: rotar y LUEGO medir, porque una foto 6/8
            //    intercambia ancho y alto y el lado mayor cambia de eje.
            $resource = self::applyExifOrientation($resource, $source);

            $width  = imagesx($resource);
            $height = imagesy($resource);
            $max    = max($width, $height);

            // 2) Componer SIEMPRE sobre un lienzo blanco, se redimensione o no.
            //
            //    El aplanado no puede vivir sólo en la rama del redimensionado: JPEG no
            //    tiene canal alfa, así que un PNG transparente que NO necesitaba encogerse
            //    llegaba tal cual a imagejpeg() y GD dejaba el RGB de los píxeles
            //    transparentes, que es 0,0,0 → recuadro NEGRO en el acta impresa. El mismo
            //    archivo salía bien sólo si era grande, que es la peor clase de bug: el que
            //    depende del tamaño del archivo que subiste.
            $ratio = ($max > self::MAX_EDGE) ? (self::MAX_EDGE / $max) : 1;
            $newW  = (int) round($width * $ratio);
            $newH  = (int) round($height * $ratio);

            $target = imagecreatetruecolor($newW, $newH);
            if ($target === false) {
                imagedestroy($resource);
                return null;
            }
            imagefill($target, 0, 0, imagecolorallocate($target, 255, 255, 255));
            imagecopyresampled($target, $resource, 0, 0, 0, 0, $newW, $newH, $width, $height);
            imagedestroy($resource);
            $resource = $target;

            // 3) Codificar y devolver los bytes.
            $encoded = self::encode($resource);
            imagedestroy($resource);

            return $encoded;
        } catch (\Throwable $e) {
            // Un fallo de compresión JAMÁS debe costar la evidencia del usuario:
            // devolver null hace que el llamador guarde el original intacto.
            if (is_resource($resource) || $resource instanceof \GdImage) {
                @imagedestroy($resource);
            }
            Log::warning('ImageCompressor: no se pudo comprimir "' . $file->getClientOriginalName() . '": ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Guardado sin tocar los píxeles (el comportamiento de siempre).
     *
     * @return string|null
     */
    protected static function fallback(UploadedFile $file, $directory, $disk)
    {
        try {
            $path = $file->store($directory, $disk);

            // store() devuelve string|false. Sin normalizar el false a null, el llamador
            // (que comprueba `!== null`) guardaría en BD la ruta de un archivo inexistente
            // y la columna acabaría con un '/storage/' pelón.
            return $path === false ? null : $path;
        } catch (\Throwable $e) {
            Log::error('ImageCompressor: falló incluso el guardado sin comprimir: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * ¿La imagen tiene transparencia REAL (píxeles que de verdad dejan ver el fondo)?
     *
     * Distingue el ASSET DE DISEÑO de la FOTO DE EVIDENCIA, y por eso NO basta con mirar
     * si existe el canal alfa: medido sobre las fotos reales del DSR, **4 de los 5 PNG son
     * RGB+ALFA y sin embargo son fotografías** (capturas de celular guardadas como PNG,
     * 6-9 MB cada una). Descartarlas por "tener canal alfa" habría tirado 31.8 MB de los
     * mayores ahorros del corpus. Lo que decide es si hay píxeles efectivamente transparentes.
     *
     * Muestreo, no barrido completo: los bordes enteros (un asset de diseño casi siempre
     * es transparente en el borde) más una rejilla de ~240×240 puntos. Sobre las imágenes
     * reales de 3072×1376 cuesta ~50 ms. Un barrido pixel a pixel serían 4.2 M lecturas en
     * PHP por foto, que no vale lo que aporta.
     *
     * @param  string $source ruta física
     * @return bool
     */
    protected static function hasRealTransparency($source)
    {
        $info = @getimagesize($source);
        if ($info === false || !isset($info[2])) {
            return false;
        }

        // Sólo PNG y WebP pueden llevar alfa. El JPEG jamás, y es el 86% del corpus:
        // así se evita el muestreo en el caso común.
        if ($info[2] !== IMAGETYPE_PNG && $info[2] !== IMAGETYPE_WEBP) {
            return false;
        }

        $image = ($info[2] === IMAGETYPE_PNG)
            ? @imagecreatefrompng($source)
            : (function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($source) : null);

        if (!$image) {
            // Si no se puede abrir, se asume lo conservador: no tocarla.
            return true;
        }

        $w = imagesx($image);
        $h = imagesy($image);
        $paso = max(1, (int) floor(min($w, $h) / 240));

        // Un par de píxeles sueltos pueden ser ruido de compresión; se exige un mínimo
        // para declarar que la transparencia es intencional.
        $encontrados = 0;
        $umbral      = 3;

        // 1) Bordes completos: donde vive la transparencia de un logotipo o un diagrama.
        for ($x = 0; $x < $w && $encontrados < $umbral; $x += $paso) {
            if (((imagecolorat($image, $x, 0) >> 24) & 0x7F) > 0)      { $encontrados++; }
            if (((imagecolorat($image, $x, $h - 1) >> 24) & 0x7F) > 0) { $encontrados++; }
        }
        for ($y = 0; $y < $h && $encontrados < $umbral; $y += $paso) {
            if (((imagecolorat($image, 0, $y) >> 24) & 0x7F) > 0)      { $encontrados++; }
            if (((imagecolorat($image, $w - 1, $y) >> 24) & 0x7F) > 0) { $encontrados++; }
        }

        // 2) Rejilla interior: cubre calados y recortes que no tocan el borde.
        for ($x = 0; $x < $w && $encontrados < $umbral; $x += $paso) {
            for ($y = 0; $y < $h && $encontrados < $umbral; $y += $paso) {
                if (((imagecolorat($image, $x, $y) >> 24) & 0x7F) > 0) { $encontrados++; }
            }
        }

        imagedestroy($image);

        return $encontrados >= $umbral;
    }

    /**
     * Extensión derivada del CONTENIDO real del archivo (nunca del nombre que manda el
     * cliente). Devuelve null si el contenido no es una imagen de las que aceptamos.
     *
     * @return string|null
     */
    /**
     * (2026-07-24) Extensión SEGURA para las superficies que sólo arman un nombre de archivo
     * (hazard, injury, scouting, materialidad, gafetes). Todas derivaban la extensión de
     * getClientOriginalExtension() — el nombre que manda el CLIENTE. Un JPEG legítimo llamado
     * "poc.html" pasa la validación de Laravel ('image|mimes:...'), que mira el CONTENIDO, y
     * acababa escrito con extensión .html en un directorio servido por el propio dominio = XSS
     * almacenado. Aquí la extensión sale de getimagesize().
     *
     * FALLBACK 'bin' en vez de null: estas superficies concatenan el resultado en el nombre, así
     * que un null dejaría el archivo terminado en punto. `.bin` no lo sirve ningún navegador como
     * HTML ni como script — si el contenido no es una imagen reconocible, queda inerte.
     *
     * @return string
     */
    public static function safeExtensionOrBin(UploadedFile $file)
    {
        $ext = self::safeExtension($file);
        return $ext !== null ? $ext : 'bin';
    }

    protected static function safeExtension(UploadedFile $file)
    {
        $source = $file->getRealPath();
        if ($source === false || !is_readable($source)) {
            return null;
        }

        $info = @getimagesize($source);
        if ($info === false || !isset($info[2])) {
            return null;
        }

        $mapa = [
            IMAGETYPE_JPEG => 'jpg',
            IMAGETYPE_PNG  => 'png',
            IMAGETYPE_GIF  => 'gif',
            IMAGETYPE_WEBP => 'webp',
        ];

        return isset($mapa[$info[2]]) ? $mapa[$info[2]] : null;
    }

    /**
     * Abre la imagen con GD si es un formato soportado y su tamaño en píxeles cabe
     * en memoria. Devuelve null (→ fallback) en cualquier otro caso.
     *
     * @param  string $source  ruta física
     * @return resource|\GdImage|null
     */
    protected static function decode($source)
    {
        if (!function_exists('imagecreatetruecolor')) {
            return null; // GD no compilado: la app sigue funcionando sin comprimir.
        }

        $info = @getimagesize($source); // barato: lee la cabecera, no decodifica
        if ($info === false || empty($info[0]) || empty($info[1])) {
            return null;
        }

        if (($info[0] * $info[1]) > self::MAX_PIXELS) {
            Log::info('ImageCompressor: imagen de ' . $info[0] . 'x' . $info[1] . ' px por encima del techo de memoria; se guarda sin comprimir.');
            return null;
        }

        $image = null;
        switch ($info[2]) {
            case IMAGETYPE_JPEG:
                $image = @imagecreatefromjpeg($source);
                break;
            case IMAGETYPE_PNG:
                $image = @imagecreatefrompng($source);
                break;
            case IMAGETYPE_WEBP:
                $image = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($source) : null;
                break;
            case IMAGETYPE_GIF:
                // Un GIF puede estar animado y GD solo leería el primer cuadro: se
                // guarda intacto en vez de mutilarlo.
                return null;
        }

        return $image ?: null;
    }

    /**
     * Rota/espeja según la etiqueta EXIF Orientation. GD la ignora, así que si no se
     * aplica a mano la foto vertical de celular sale acostada tras re-codificar.
     * Solo el JPEG lleva esta etiqueta.
     *
     * @param  resource|\GdImage $image
     * @param  string            $source
     * @return resource|\GdImage
     */
    protected static function applyExifOrientation($image, $source)
    {
        if (!function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data($source);
        if (!is_array($exif) || empty($exif['Orientation'])) {
            return $image;
        }

        // Los 8 valores del estándar EXIF. Los pares (2,4,5,7) además espejan.
        switch ((int) $exif['Orientation']) {
            case 2:
                imageflip($image, IMG_FLIP_HORIZONTAL);
                break;
            case 3:
                $image = imagerotate($image, 180, 0);
                break;
            case 4:
                imageflip($image, IMG_FLIP_VERTICAL);
                break;
            case 5:
                $image = imagerotate($image, -90, 0);
                imageflip($image, IMG_FLIP_HORIZONTAL);
                break;
            case 6:
                $image = imagerotate($image, -90, 0);
                break;
            case 7:
                $image = imagerotate($image, 90, 0);
                imageflip($image, IMG_FLIP_HORIZONTAL);
                break;
            case 8:
                $image = imagerotate($image, 90, 0);
                break;
        }

        return $image;
    }

    /**
     * Codifica el recurso GD al formato de salida y devuelve los bytes.
     *
     * @param  resource|\GdImage $image
     * @return string|null
     */
    protected static function encode($image)
    {
        ob_start();
        $ok = (self::FORMAT === 'webp' && function_exists('imagewebp'))
            ? @imagewebp($image, null, self::QUALITY)
            : @imagejpeg($image, null, self::QUALITY);
        $bytes = ob_get_clean();

        if (!$ok || $bytes === false || $bytes === '') {
            return null;
        }

        return $bytes;
    }

    /**
     * Nombre del archivo. Sigue el patrón dominante de la app (time()_uniqid) en vez
     * del hashName() aleatorio que usaba el DSR, para poder IMPONER la extensión
     * (al re-codificar, la extensión original mentiría sobre el contenido).
     *
     * @return string
     */
    protected static function filename()
    {
        $ext = (self::FORMAT === 'webp') ? 'webp' : 'jpg';

        return time() . '_' . uniqid('', false) . '.' . $ext;
    }
}
