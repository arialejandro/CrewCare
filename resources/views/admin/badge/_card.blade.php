{{--
    Partial COMPARTIDO del gafete — usado por la pantalla (idcard, diseñador, bulk_jpg)
    Y por dompdf (single_pdf, bulk_pdf). dompdf v2 NO soporta CSS variables → aquí todo
    es estilo inline concreto (mm/pt/px) calculado en PHP desde $tpl.

    Cada elemento posicionable lleva data-role="..." para que el diseñador lo mueva en
    vivo por rol (no por índice de hijo).

    Espera:
      $user   → modelo User
      $tpl    → array de config ya fusionado (BadgeTemplate::activeConfig())
      $forPdf → bool (default false). true = rutas de disco (public_path) para dompdf.
--}}
@php
    $forPdf = $forPdf ?? false;
    $tpl = array_merge(\App\Models\BadgeTemplate::DEFAULTS, is_array($tpl ?? null) ? $tpl : []);

    // --- Assets: dompdf lee de disco (public_path); la pantalla usa asset() (URL). ---
    $resolve = function ($rel) use ($forPdf) {
        $rel = ltrim((string) $rel, '/');
        return $forPdf ? public_path($rel) : asset($rel);
    };

    $bgRel = $tpl['card_bg'] ?: 'img/nlogo25.png';
    $bgSrc = $resolve($bgRel);
    if ($forPdf && ! is_file($bgSrc)) {
        $bgSrc = public_path('img/nlogo25.png'); // degradar a fondo por defecto si falta
    }

    // Foto de perfil.
    $imgperfil = $user->imgperfil ?? '';
    $hasPhoto = ($imgperfil !== '' && $imgperfil !== null);
    if ($hasPhoto) {
        $photoSrc = $resolve('imagesprf/usrs/' . $imgperfil);
        if ($forPdf && ! is_file($photoSrc)) {
            $hasPhoto = false;
        }
    }

    // Logo de producción (opcional).
    $prodLogoRel = $tpl['production_logo'] ?? '';
    $hasProdLogo = ($prodLogoRel !== '' && $prodLogoRel !== null);
    if ($hasProdLogo) {
        $prodLogoSrc = $resolve($prodLogoRel);
        if ($forPdf && ! is_file($prodLogoSrc)) {
            $hasProdLogo = false;
        }
    }

    // Sello "POWERED BY" CrewCare (marca de agua): PNG PLANO pre-generado (gris/blanco,
    // transparente) desde logo-cc-report.svg. Se usa PNG porque dompdf NO renderiza el SVG
    // inline de forma fiable; el PNG sí se ve igual en pantalla, JPG (html2canvas) y PDF.
    $poweredTone = (($tpl['powered_tone'] ?? 'gris') === 'blanco') ? 'blanco' : 'gris';
    $poweredColor = $poweredTone === 'blanco' ? '#ffffff' : '#565656';
    $poweredOpacity = $poweredTone === 'blanco' ? '0.55' : '0.60';
    $ccLogoRel = 'img/logo-cc-' . $poweredTone . '.png';
    $ccLogoSrc = $resolve($ccLogoRel);
    $hasCcLogo = ! $forPdf || is_file(public_path($ccLogoRel));

    // --- Forma de la foto → border-radius. ---
    $shape = $tpl['photo_shape'] ?? 'circle';
    if ($shape === 'circle') {
        $photoRadius = '50%';
    } elseif ($shape === 'rounded') {
        $photoRadius = '8mm';
    } else {
        $photoRadius = '0';
    }

    // --- Sanitizar allow-lists (defensivo). ---
    $fontFamily = in_array($tpl['font_family'] ?? '', \App\Models\BadgeTemplate::FONTS, true)
        ? $tpl['font_family'] : 'Poppins';
    $textColor = preg_match('/^#[0-9A-Fa-f]{6}$/', (string) ($tpl['text_color'] ?? ''))
        ? $tpl['text_color'] : '#111111';
    $fontStack = $fontFamily . ", sans-serif";

    // Datos. project_name vacío → cae a branding.brand_name (o 'CrewCare').
    $brandName   = $branding['brand_name'] ?? 'CrewCare';
    $projectName = (isset($tpl['project_name']) && trim((string) $tpl['project_name']) !== '')
        ? $tpl['project_name'] : $brandName;
    // El nombre del gafete proviene de "Nombre en Créditos" (ncreditos); si está vacío,
    // cae al nombre + apellido del registro.
    $creditsName = trim((string) ($user->ncreditos ?? ''));
    $fullName    = $creditsName !== '' ? $creditsName : trim(($user->name ?? '') . ' ' . ($user->lname ?? ''));
    $position    = \App\Models\User::positionNameFor($user->id ?? null, $user->puestodepartamento ?? null) ?? '';
    $consecutive = '0000' . ($user->id ?? '');

    // Números (cast a float para concatenar mm/pt/px sin sorpresas).
    $n = function ($k) use ($tpl) { return (float) ($tpl[$k] ?? 0); };

    // Auto-ajuste del NOMBRE y del PUESTO: si el texto es largo, se reduce el tamaño para
    // que quepa a lo ancho del gafete y NO se desborde ni se encime con lo de abajo.
    $fitSize = function ($text, $sizePt, $usableMm = 96.0, $factor = 0.60) {
        $len = max(1, mb_strlen((string) $text));
        $estMm = $len * $factor * $sizePt * 0.3528; // ancho aprox del texto en mm
        if ($estMm > $usableMm) {
            $sizePt = $sizePt * $usableMm / $estMm;
        }
        return round($sizePt, 1);
    };
    $nameSizePt = $fitSize($fullName, $n('name_size'), 96.0, 0.60);
    $posSizePt  = $fitSize($position, $n('position_size'), 98.0, 0.55);
@endphp

<div class="gft-card" style="
    position: relative;
    width: 108mm;
    height: 172mm;
    background: #fff;
    overflow: hidden;
    background-image: url('{{ $bgSrc }}');
    background-size: cover;
    background-repeat: no-repeat;
    background-position: center;
    font-family: {{ $fontStack }};
    color: {{ $textColor }};
">

    {{-- Logo de producción (opcional) --}}
    @if ($hasProdLogo)
        <img data-role="prodlogo" src="{{ $prodLogoSrc }}" alt="" style="
            position: absolute;
            top: {{ $n('production_logo_top') }}mm;
            left: {{ $n('production_logo_left') }}mm;
            width: {{ $n('production_logo_width') }}mm;
            height: auto;
        ">
    @else
        {{-- Marcador vacío para que el índice/selección sea estable en el diseñador --}}
        <img data-role="prodlogo" src="" alt="" style="
            position: absolute;
            top: {{ $n('production_logo_top') }}mm;
            left: {{ $n('production_logo_left') }}mm;
            width: {{ $n('production_logo_width') }}mm;
            height: auto;
            display: none;
        ">
    @endif

    {{-- Nombre del proyecto --}}
    <div data-role="brand" style="
        position: absolute;
        top: {{ $n('brand_top') }}mm;
        left: {{ $n('brand_left') }}mm;
        text-align: center;
        font-weight: {{ (int) ($tpl['brand_weight'] ?? 300) }};
        font-size: {{ $n('brand_size') }}pt;
        font-family: {{ $fontStack }};
        color: {{ $textColor }};
        margin: 0;
    ">{{ $projectName }}</div>

    {{-- Foto (o placeholder gris si falta) --}}
    @if ($hasPhoto)
        <img data-role="photo" src="{{ $photoSrc }}" alt="" style="
            position: absolute;
            width: {{ $n('photo_size') }}mm;
            height: {{ $n('photo_size') }}mm;
            top: {{ $n('photo_top') }}mm;
            left: {{ $n('photo_left') }}mm;
            border-radius: {{ $photoRadius }};
            object-fit: cover;
        ">
    @else
        <div data-role="photo" style="
            position: absolute;
            width: {{ $n('photo_size') }}mm;
            height: {{ $n('photo_size') }}mm;
            top: {{ $n('photo_top') }}mm;
            left: {{ $n('photo_left') }}mm;
            border-radius: {{ $photoRadius }};
            background: #cccccc;
        "></div>
    @endif

    {{-- Etiqueta NOMBRE (ocultable) --}}
    <div data-role="labelName" style="
        position: absolute;
        top: {{ $n('label_name_top') }}mm;
        left: 0;
        width: 100%;
        text-align: center;
        font-weight: 300;
        font-size: 8pt;
        font-family: {{ $fontStack }};
        color: {{ $textColor }};
        margin: 0;
        display: {{ (isset($tpl['label_name_show']) && ! $tpl['label_name_show']) ? 'none' : 'block' }};
    ">{{ $tpl['label_name'] ?? 'NOMBRE' }}</div>

    {{-- Nombre completo --}}
    <div data-role="name" style="
        position: absolute;
        top: {{ $n('name_top') }}mm;
        left: 0;
        width: 100%;
        text-align: center;
        font-size: {{ $nameSizePt }}pt;
        font-family: {{ $fontStack }};
        color: {{ $textColor }};
        margin: 0;
        line-height: 1.1;
    ">{{ $fullName }}</div>

    {{-- Etiqueta PUESTO (ocultable) --}}
    <div data-role="labelPosition" style="
        position: absolute;
        top: {{ $n('label_position_top') }}mm;
        left: 0;
        width: 100%;
        text-align: center;
        font-weight: 300;
        font-size: 8pt;
        font-family: {{ $fontStack }};
        color: {{ $textColor }};
        margin: 0;
        display: {{ (isset($tpl['label_position_show']) && ! $tpl['label_position_show']) ? 'none' : 'block' }};
    ">{{ $tpl['label_position'] ?? 'PUESTO' }}</div>

    {{-- Puesto / departamento --}}
    <div data-role="position" style="
        position: absolute;
        top: {{ $n('position_top') }}mm;
        left: 0;
        width: 100%;
        text-align: center;
        font-size: {{ $posSizePt }}pt;
        font-family: {{ $fontStack }};
        color: {{ $textColor }};
        margin: 0;
        line-height: 1.1;
    ">{{ $position }}</div>

    {{-- Pie: sello POWERED BY CrewCare (marca de agua, tono gris/blanco). Alineado a la derecha, compacto, por encima del consecutivo. --}}
    <div data-role="powered" style="
        position: absolute;
        top: {{ $n('powered_top') }}mm;
        right: 6mm;
        text-align: right;
        margin: 0;
        line-height: 1;
        opacity: {{ $poweredOpacity }};
    ">
        <span style="display: block; font-size: 3.2pt; letter-spacing: 1pt; text-transform: uppercase; color: {{ $poweredColor }}; line-height: 1; margin: 0 0 0.6mm 0;">Powered by</span>
        @if ($hasCcLogo)
            <img src="{{ $ccLogoSrc }}" alt="CrewCare" style="height: 4.5mm; width: auto; display: inline-block;">
        @else
            <span style="font-size: 6pt; font-family: {{ $fontStack }}; color: {{ $poweredColor }};">CrewCare</span>
        @endif
    </div>

    {{-- Consecutivo (SIEMPRE visible; comparte el tono del sello). --}}
    <div data-role="consecutive" style="
        position: absolute;
        top: {{ $n('consecutive_top') }}mm;
        right: 6mm;
        font-size: 6pt;
        font-family: {{ $fontStack }};
        color: {{ $poweredColor }};
        margin: 0;
        white-space: nowrap;
    ">{{ $consecutive }}</div>

</div>
