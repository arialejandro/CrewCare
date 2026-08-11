<?php

namespace App\Support;

use Spatie\Browsershot\Browsershot;

class PdfExporter
{
    /** Renderiza HTML (de una vista) a bytes PDF con Chrome headless, reusando el diseño tal cual. */
    public static function fromHtml(string $html, array $margins = [0, 0, 0, 0]): string
    {
        $public    = public_path();
        $publicUrl = 'file:///' . str_replace('\\', '/', $public);

        // 1) Assets raíz-relativos -> file:// public/
        foreach (['fonts', 'storage', 'img', 'images', 'css', 'js', 'build', 'vendor'] as $dir) {
            $html = str_replace('="/' . $dir, '="' . $publicUrl . '/' . $dir, $html);
            $html = str_replace("url('/" . $dir, "url('" . $publicUrl . '/' . $dir, $html);
            $html = str_replace('url("/' . $dir, 'url("' . $publicUrl . '/' . $dir, $html);
            $html = str_replace('url(/'  . $dir, 'url('  . $publicUrl . '/' . $dir, $html);
        }

        // 2) Inline report-fonts.css (sus url() son raíz-absolutas y no sobreviven a file://)
        $cssPath = $public . DIRECTORY_SEPARATOR . 'fonts' . DIRECTORY_SEPARATOR . 'reports' . DIRECTORY_SEPARATOR . 'report-fonts.css';
        if (is_file($cssPath)) {
            $css = file_get_contents($cssPath);
            $css = str_replace('url(/fonts/reports/', 'url(' . $publicUrl . '/fonts/reports/', $css);
            $new = preg_replace('#<link[^>]*report-fonts\.css[^>]*>#i', '<style>' . $css . '</style>', $html, 1);
            if ($new !== null) {
                $html = $new;
            }
        }

        // 2b) Imágenes lazy: en render headless (PDF) las de ABAJO del viewport no se disparan
        // (no hay scroll/intersección) → salen en NEGRO. Forzar carga inmediata para el PDF.
        $html = str_replace(['loading="lazy"', "loading='lazy'"], 'loading="eager"', $html);

        // 3) Archivo temporal (htmlFromFilePath salta el check anti-file:// de setHtml)
        $tmpHtml = tempnam(sys_get_temp_dir(), 'ccpdf') . '.html';
        $tmpPdf  = tempnam(sys_get_temp_dir(), 'ccpdf') . '.pdf';
        file_put_contents($tmpHtml, $html);

        $m = array_values($margins) + [0, 0, 0, 0];

        // (Windows) El proceso web (Apache/php-fpm o `php artisan serve`) hereda un entorno PELADO:
        // sin SystemRoot/windir/TEMP. Node (puppeteer) calcula os.tmpdir() = (SystemRoot||windir)+'\temp'
        // → 'undefined\temp' → ENOENT al crear el perfil de Chrome. Browsershot lanza node con `new
        // Process(...)` heredando el entorno de PHP (Browsershot.php:931), así que sembramos aquí las
        // variables (en CLI ya venían; por eso ahí sí funcionaba). setEnvironmentOptions NO sirve:
        // sólo pasa env a Chrome, no a node.
        if (stripos(PHP_OS, 'WIN') === 0) {
            $winRoot = getenv('SystemRoot') ?: (!empty($_SERVER['SystemRoot']) ? $_SERVER['SystemRoot'] : 'C:\\Windows');
            $winTmp  = sys_get_temp_dir() ?: ($winRoot . '\\Temp');
            // symfony/process arma el env del hijo (node) como `$_ENV + (getenv() ∩ $_SERVER)`
            // (Process::getDefaultEnv, línea ~1648). putenv() SOLO toca getenv() → el `∩ $_SERVER`
            // lo tira si $_SERVER no la tiene (caso del servidor web). Por eso hay que ponerla en
            // $_ENV y $_SERVER además de putenv. Con esto node recibe SystemRoot/TEMP y os.tmpdir()
            // deja de dar 'undefined\temp'.
            foreach (['SystemRoot' => $winRoot, 'windir' => $winRoot, 'TEMP' => $winTmp, 'TMP' => $winTmp] as $k => $v) {
                putenv($k . '=' . $v);
                $_ENV[$k]    = $v;
                $_SERVER[$k] = $v;
            }
        }

        // 4) Browsershot -> savePdf (en Windows pdf() por stdout corrompe binarios grandes)
        try {
            Browsershot::htmlFromFilePath($tmpHtml)
                ->setChromePath(env('BROWSERSHOT_CHROME', 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe'))
                ->setNodeBinary(env('BROWSERSHOT_NODE', 'C:\\Program Files\\nodejs\\node.exe'))
                ->setNodeModulePath(base_path('node_modules'))
                ->noSandbox()
                ->setOption('preferCSSPageSize', true)
                ->showBackground()
                ->margins((float) $m[0], (float) $m[1], (float) $m[2], (float) $m[3])
                ->waitUntilNetworkIdle()
                ->timeout(120)
                ->savePdf($tmpPdf);
        } catch (\Symfony\Component\Process\Exception\ProcessFailedException $e) {
            @unlink($tmpHtml);
            @unlink($tmpPdf);
            // Superficie el stderr REAL de node/Chrome (si no, el whoops sólo muestra el comando).
            // Útil donde el spawn de Chrome falla por entorno del servidor (p. ej. `php artisan serve`,
            // de un solo hilo y entorno mínimo). El camino normal es el vhost de laragon / Apache.
            $err = trim($e->getProcess()->getErrorOutput());
            throw new \RuntimeException('Export PDF (Browsershot) falló: ' . ($err !== '' ? $err : $e->getMessage()), 0, $e);
        }

        $bytes = file_get_contents($tmpPdf);
        @unlink($tmpHtml);
        @unlink($tmpPdf);

        return $bytes;
    }

    /** Respuesta de descarga (attachment) a partir del HTML de una vista. */
    public static function download(string $html, string $filename, array $margins = [0, 0, 0, 0])
    {
        $pdf = self::fromHtml($html, $margins);

        return response($pdf, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '.pdf"',
        ]);
    }
}
