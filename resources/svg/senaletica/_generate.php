<?php
/**
 * Generador de la biblioteca de señales del Mapeo de riesgos (delta #50 · Opción A).
 *
 * SOLO LECTURA de *.svg de esta carpeta → escribe resources/rm-signs.generated.php
 * (mapa  slug => data:image/svg+xml;base64,...). NO toca las fuentes ni el código.
 * Cada señal se sirve como <img data:>, aislada, así NO colisionan los IDs/clases
 * internos de Illustrator/Inkscape (.st0, SVGID_2_, …) al poner varias en una página.
 *
 * Regenerar tras añadir/renombrar SVG:
 *     php resources/svg/senaletica/_generate.php
 */
$srcDir = __DIR__;
$out    = dirname(__DIR__, 2) . '/rm-signs.generated.php'; // resources/rm-signs.generated.php

$files = glob($srcDir . '/*.svg');
if (!$files) { fwrite(STDERR, "SIN SVG en $srcDir\n"); exit(1); }

$slugify = function ($name) {
    $s = strtolower($name);
    $s = preg_replace('/[^a-z0-9]+/', '_', $s);
    return trim($s, '_');
};

$map = [];
$collisions = [];
foreach ($files as $f) {
    $base = pathinfo($f, PATHINFO_FILENAME);
    $slug = $slugify($base);
    $svg  = file_get_contents($f);
    if ($svg === false || strpos($svg, '<svg') === false) { fwrite(STDERR, "OMITIDO (no svg): $base\n"); continue; }
    if (isset($map[$slug])) { $collisions[] = $slug; }
    $map[$slug] = 'data:image/svg+xml;base64,' . base64_encode($svg);
}
ksort($map);
if (count($map) < 1) { fwrite(STDERR, "MAPA VACIO — no escribo\n"); exit(1); }

$php = "<?php\n"
     . "/* GENERADO por resources/svg/senaletica/_generate.php — NO editar a mano. " . count($map) . " señales. */\n"
     . "return [\n";
foreach ($map as $k => $uri) {
    $php .= "    '" . $k . "' => '" . $uri . "',\n";
}
$php .= "];\n";

// Guardarraíl: solo escribo si el contenido es sano (no vacío, con data URIs).
if (strpos($php, 'data:image/svg+xml;base64,') === false || strlen($php) < 500) {
    fwrite(STDERR, "CONTENIDO SOSPECHOSO — no escribo\n"); exit(1);
}
file_put_contents($out, $php);

echo "OK: " . count($map) . " señales → $out (" . strlen($php) . " bytes)\n";
if ($collisions) { echo "COLISIONES de slug: " . implode(', ', array_unique($collisions)) . "\n"; }
