{{--
    _ghs-pictogram.blade.php — Pictograma GHS/SGA inline, fuente ÚNICA de simbología de peligro.

    Renderiza el rombo normativo (cuadrado a 45°) en un viewBox 100×100:
    MARCO ROJO + FONDO BLANCO + SÍMBOLO NEGRO. Esa paleta la fija la norma
    (SGA/GHS de la ONU, NOM-018-STPS, CLP): por eso NO usa currentColor ni los
    tokens del tema. Un pictograma que cambia de color con el tema deja de ser el
    pictograma. Lo único que escala es el TAMAÑO (vía la clase que le pases).
    El rojo es Pantone 485 C (#DA291C), el rojo de referencia del rombo.

    Props (mismas que componentes/_icon):
      - $code   (obligatorio) 'GHS01'..'GHS09'. Case-insensitive y tolera espacios
                              ('ghs 02', ' GHS02 ' → GHS02).
      - $class  (opcional)    clases CSS del <svg>. Default 'cc-ghs'.
      - $label  (opcional)    texto del aria-label. Si NO viene, el parcial pone el
                              NOMBRE OFICIAL en español (ver CC_GHS_PICTOGRAM_NAMES).
                              Nunca queda un símbolo normativo sin nombre accesible:
                              aquí NO existe el modo decorativo de _icon.

    Uso:
      @include('componentes._ghs-pictogram', ['code' => 'GHS02', 'class' => 'cc-ghs', 'label' => null])

    DIFERENCIA CLAVE CON _icon: si $code no existe en el mapa (o viene null/''),
    _icon caería a un círculo; ESTE NO RENDERIZA NADA (return silencioso, sin caja
    rota ni placeholder). Inventar un símbolo de peligro es peor que no pintar ninguno.

    Nombres oficiales: constante CC_GHS_PICTOGRAM_NAMES (array código => nombre ES),
    definida por este parcial la primera vez que se incluye. Sirve para leyendas y
    filtros sin duplicar el diccionario.

    El <svg> trae width/height="1em" como ATRIBUTO de presentación: es solo una red de
    seguridad si la vista olvida dimensionar .cc-ghs — cualquier regla CSS de clase le
    gana a un atributo de presentación, así que no estorba.

    Tamaño mínimo legible: pensado para leerse desde 26px (fila de tabla) hacia arriba.
    PHP 7.4: sin nullsafe ni match.
--}}
@php
    $code  = $code  ?? '';
    $class = $class ?? 'cc-ghs';
    $label = $label ?? null;

    if (! defined('CC_GHS_PICTOGRAM_NAMES')) {
        define('CC_GHS_PICTOGRAM_NAMES', [
            'GHS01' => 'Explosivo',
            'GHS02' => 'Inflamable',
            'GHS03' => 'Comburente',
            'GHS04' => 'Gas a presión',
            'GHS05' => 'Corrosivo',
            'GHS06' => 'Toxicidad aguda',
            'GHS07' => 'Irritante / nocivo',
            'GHS08' => 'Peligro para la salud',
            'GHS09' => 'Peligro para el medio ambiente',
        ]);
    }

    // 'ghs 02' / ' GHS02 ' / 'Ghs02' → 'GHS02'.
    $__code = strtoupper(preg_replace('/\s+/', '', (string) $code));

    // La llama de GHS02 y GHS03 es la MISMA silueta a dos escalas: se define una vez.
    $__flame = '<path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 2.5z"/>';
    // El tubo de ensayo de GHS05 se dibuja una vez y el derecho es su espejo en x=50.
    $__tube  = '<path d="M20 0H4.5a4.5 4.5 0 0 0 0 9H20z"/><rect x="17.4" y="-1.8" width="3.4" height="12.6" rx=".8"/>';

    // Geometría del SÍMBOLO NEGRO de cada pictograma, sobre el rombo blanco.
    // Todo vive dentro del rombo inscrito: |x-50| + |y-50| <= ~37.
    $symbols = [
        // Bomba explotando: ráfaga de 8 puntas + la bomba (esfera) debajo.
        'GHS01' => '<polygon points="50,22 53.06,34.61 64.14,27.86 57.39,38.94 70,42 57.39,45.06 64.14,56.14 53.06,49.39 50,62 46.94,49.39 35.86,56.14 42.61,45.06 30,42 42.61,38.94 35.86,27.86 46.94,34.61"/>'
                 . '<circle cx="50" cy="63" r="12"/>',

        // Llama sobre la línea de superficie.
        'GHS02' => '<g transform="translate(25.4 17.85) scale(2.05)">' . $__flame . '</g>'
                 . '<rect x="33" y="63.5" width="34" height="4.5" rx="2"/>',

        // Comburente: la misma llama, más chica, SOBRE un círculo (anillo).
        'GHS03' => '<g transform="translate(29.84 12.96) scale(1.68)">' . $__flame . '</g>'
                 . '<circle cx="50" cy="63" r="11" fill="none" stroke="#000000" stroke-width="5"/>',

        // Cilindro de gas: válvula + collarín + cuerpo con hombro redondeado.
        'GHS04' => '<rect x="45.5" y="24" width="9" height="8" rx="1.5"/>'
                 . '<rect x="42" y="30" width="16" height="4.5" rx="1.5"/>'
                 . '<path d="M39 70V42a11 11 0 0 1 22 0v28a4 4 0 0 1-4 4H43a4 4 0 0 1-4-4z"/>',

        // Corrosión: dos tubos vertiendo; izquierda sobre una placa, derecha sobre una mano.
        // Las muescas blancas en "V" son el material comido por el ácido.
        'GHS05' => '<g transform="translate(49.78 31.44) rotate(122.9)">' . $__tube . '</g>'
                 . '<g transform="translate(100 0) scale(-1 1)"><g transform="translate(49.78 31.44) rotate(122.9)">' . $__tube . '</g></g>'
                 . '<ellipse cx="35" cy="51.5" rx="2.2" ry="3"/><ellipse cx="34.6" cy="56.5" rx="1.8" ry="2.5"/>'
                 . '<ellipse cx="65" cy="51.5" rx="2.2" ry="3"/><ellipse cx="65.4" cy="56.5" rx="1.8" ry="2.5"/>'
                 . '<path d="M28 59h20v6H28z"/><path d="M32 59l3 4.5 3-4.5z" fill="#FFFFFF"/>'
                 . '<rect x="55" y="49.5" width="3.6" height="8" rx="1.8" transform="rotate(-25 56.8 53.5)"/>'
                 . '<path d="M69 68H52a6 6 0 0 1 0-12h17z"/>'
                 . '<rect x="45" y="59" width="12" height="1.7" fill="#FFFFFF"/>'
                 . '<rect x="45" y="62" width="12" height="1.7" fill="#FFFFFF"/>'
                 . '<rect x="45" y="65" width="12" height="1.7" fill="#FFFFFF"/>'
                 . '<path d="M62 56l3 4.5 3-4.5z" fill="#FFFFFF"/>',

        // Calavera y tibias cruzadas: las tibias cruzan POR DEBAJO del cráneo y asoman
        // por los cuatro extremos; los detalles del cráneo son huecos blancos.
        'GHS06' => '<path d="M33 51 67 65" fill="none" stroke="#000000" stroke-width="6" stroke-linecap="round"/>'
                 . '<circle cx="31.78" cy="53.96" r="3.4"/><circle cx="34.22" cy="48.04" r="3.4"/>'
                 . '<circle cx="65.78" cy="67.96" r="3.4"/><circle cx="68.22" cy="62.04" r="3.4"/>'
                 . '<path d="M33 65 67 51" fill="none" stroke="#000000" stroke-width="6" stroke-linecap="round"/>'
                 . '<circle cx="34.22" cy="67.96" r="3.4"/><circle cx="31.78" cy="62.04" r="3.4"/>'
                 . '<circle cx="68.22" cy="53.96" r="3.4"/><circle cx="65.78" cy="48.04" r="3.4"/>'
                 . '<path d="M50 18a14 14 0 0 0-14 14c0 4.8 2.4 9.1 6 11.9V49a3 3 0 0 0 3 3h10a3 3 0 0 0 3-3v-5.1c3.6-2.8 6-7.1 6-11.9a14 14 0 0 0-14-14z"/>'
                 . '<circle cx="43" cy="33" r="5" fill="#FFFFFF"/><circle cx="57" cy="33" r="5" fill="#FFFFFF"/>'
                 . '<path d="M50 39l-3.5 7h7z" fill="#FFFFFF"/>'
                 . '<rect x="43.5" y="46.2" width="13" height="1.4" fill="#FFFFFF"/>'
                 . '<rect x="45.4" y="47.6" width="1.6" height="4.4" fill="#FFFFFF"/>'
                 . '<rect x="49.2" y="47.6" width="1.6" height="4.4" fill="#FFFFFF"/>'
                 . '<rect x="53" y="47.6" width="1.6" height="4.4" fill="#FFFFFF"/>',

        // Signo de admiración: barra ligeramente ahusada + punto. Sin círculo (así es GHS07).
        'GHS07' => '<path d="M50 22a5.5 5.5 0 0 1 5.5 5.5L54 55.5a4 4 0 0 1-8 0l-1.5-28A5.5 5.5 0 0 1 50 22z"/>'
                 . '<circle cx="50" cy="70" r="6"/>',

        // Peligro para la salud: busto (contorno negro, relleno blanco) con la estrella en el pecho.
        'GHS08' => '<path d="M50 21a9 9 0 0 1 9 9 9 9 0 0 1-5.4 8.25c8.4 2.2 13.4 8.75 13.4 17.75v14H33V56c0-9 5-15.55 13.4-17.75A9 9 0 0 1 41 30a9 9 0 0 1 9-9z" fill="#FFFFFF" stroke="#000000" stroke-width="3" stroke-linejoin="round"/>'
                 . '<polygon points="50,45 51.68,52.93 58.49,48.51 54.07,55.32 62,57 54.07,58.68 58.49,65.49 51.68,61.07 50,69 48.32,61.07 41.51,65.49 45.93,58.68 38,57 45.93,55.32 41.51,48.51 48.32,52.93"/>',

        // Medio ambiente: árbol muerto sobre la línea de agua y pez muerto (ojo en X) debajo.
        'GHS09' => '<path d="M26 59q6-5 12 0t12 0t12 0t12 0" fill="none" stroke="#000000" stroke-width="3" stroke-linecap="round"/>'
                 . '<path d="M38 60V31M38 47l-8-7M38 47l8-7M38 39l-6-6M38 39l6-6" fill="none" stroke="#000000" stroke-width="3" stroke-linecap="round"/>'
                 . '<ellipse cx="56" cy="68" rx="12" ry="6"/><path d="M45 68l-8-5.5v11z"/>'
                 . '<path d="M60 64l4 4M64 64l-4 4" fill="none" stroke="#FFFFFF" stroke-width="2" stroke-linecap="round"/>',
    ];

    $__known   = isset($symbols[$__code]);
    // Nunca un símbolo normativo sin nombre: si no dan $label, va el oficial.
    $__a11yTxt = $label !== null ? $label : ($__known ? CC_GHS_PICTOGRAM_NAMES[$__code] : '');
@endphp
@if ($__known)
<svg class="{{ $class }}" width="1em" height="1em" viewBox="0 0 100 100" role="img" aria-label="{{ $__a11yTxt }}" focusable="false">
    <path d="M50 6 94 50 50 94 6 50Z" fill="#FFFFFF" stroke="#DA291C" stroke-width="6" stroke-linejoin="miter"/>
    <g fill="#000000">{!! $symbols[$__code] !!}</g>
</svg>
@endif
