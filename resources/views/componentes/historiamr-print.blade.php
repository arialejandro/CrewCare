{{--
    ============================================================================================
    HISTORIAL MÉDICO — DOCUMENTO DE IMPRESIÓN DEDICADO (clínico claro, autocontenido).
    NO extiende layouts.app: es un HTML completo sin nada del shell de la app. Así NINGÚN
    elemento de GUI (la hamburguesa `position:fixed`, sidebar, command palette) puede colarse
    al papel, y el layout de 2 columnas se controla por entero.

    Se abre desde el botón "Imprimir" de componentes/historiamr → route('historialwr', [id, print=1]);
    el controlador (cmedicController@historialWR) devuelve ESTA vista cuando ?print=1, con los MISMOS
    datos y el MISMO candado (canManageCrewMember) que la pantalla. Ver [[health-record-module]].

    Variables (idénticas a la pantalla): $datos, $usuario, $target, $consultas, $medicos,
    $intakeState, $expedienteModelo, $anexosExpediente, $branding.
    ============================================================================================
--}}
@php
    $primary   = \App\Support\Branding::get('primary_color', '#ff9900');
    $secondary = \App\Support\Branding::get('secondary_color', '#1f2937');
    $clientLogo = \App\Support\Branding::get('client_logo', '');
    // $usuario trae UNA fila (la vigente). Se toma directo: nada de @foreach que repetía la ficha.
    $user = ($usuario ?? collect())->first();
    $fullName = trim(($datos->name ?? '').' '.($datos->lname ?? '').' '.($datos->lname2 ?? ''));
    $heredoLabels = [
        __('Vivo/Sano'), __('Fallecido'), __('Diabetes'), __('Hipertensión'),
        __('Cardiopatías'), __('Nefropatías'), __('Neoplasias'),
    ];
@endphp
<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ __('Historial Médico') }} — {{ $fullName }}</title>
{{-- Fuentes autoalojadas (mismas que los reportes; offline). --}}
<link rel="stylesheet" href="/fonts/reports/report-fonts.css">
<style>
:root{
    --ink:#14181f; --muted:#4a5261; --faint:#6b7382;
    --line:#dfe3ea; --line-2:#c3c9d4; --surface:#f5f7f9; --panel:#fbfcfd;
    --ok:#15803d; --warn:#b45309; --danger:#b91c1c;
    --brand:{{ $primary }}; --brand-2:{{ $secondary }};
    --font:'Poppins',ui-sans-serif,system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;
    --mono:'Roboto Mono',ui-monospace,Consolas,monospace;
    --radius-sm:9px;
    /* Alias para que los parciales compartidos (_seal-cfdi, _consult-attended-by, _icon) que
       esperan los tokens del tema resuelvan a la paleta clínica clara de este documento. */
    --text:var(--ink); --text-muted:var(--muted);
    --stroke:var(--line); --stroke-2:var(--line-2); --border:var(--line); --surface-2:var(--surface);
}
*{box-sizing:border-box}
html,body{margin:0}
body{
    font-family:var(--font); color:var(--ink); background:#fff; font-size:10.5px; line-height:1.5;
    -webkit-font-smoothing:antialiased; -webkit-print-color-adjust:exact; print-color-adjust:exact;
}
@page{ size:letter; margin:13mm 12mm; }
.doc{ max-width:190mm; margin:0 auto; }

/* ===== Membrete clínico branded (CrewCare · título · cliente) — grid 3-col para que el
       título quede REALMENTE centrado en la hoja aunque los logos tengan anchos distintos. ===== */
.mhead{ display:grid; grid-template-columns:1fr auto 1fr; align-items:center; gap:18px; padding-bottom:10px; }
.mhead-side{ display:flex; align-items:center; min-width:0; }
.mhead-left{ justify-content:flex-start; }
.mhead-right{ justify-content:flex-end; }
.mhead-cc{ height:32px; width:auto; display:block; }                       /* logo CrewCare (marca del sitio) */
.mhead-client{ max-height:34px; max-width:150px; width:auto; height:auto; object-fit:contain; display:block; } /* logo del cliente, secundario/más chico */
.mhead-title{ text-align:center; }
.mhead-title h1{ margin:0; font-size:18px; font-weight:800; letter-spacing:.09em; text-transform:uppercase; color:var(--brand-2); line-height:1.1; }
.mhead-title .sub{ font-size:8.5px; letter-spacing:.18em; text-transform:uppercase; color:var(--muted); font-weight:600; margin-top:4px; }
.mrule{ height:2.5px; background:var(--brand); border-radius:2px; }
.mhead-meta{ text-align:center; font-size:8.5px; color:var(--faint); font-family:var(--mono); letter-spacing:.03em; margin:6px 0 15px; }

/* ===== Rejilla y tarjetas ===== */
.grid2{ display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:12px; }
.grid3{ display:grid; grid-template-columns:1.05fr .95fr 1.1fr; gap:18px; }
.card{ border:1px solid var(--line); border-radius:9px; padding:12px 14px; background:#fff; break-inside:avoid; }
.card.wide{ margin-bottom:12px; }
.sect{ display:flex; align-items:baseline; gap:8px; margin:0 0 10px; padding-left:9px; border-left:3px solid var(--brand); }
.sect h2{ margin:0; font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:.04em; color:var(--ink); }
.sect .sub{ font-size:8.5px; color:var(--faint); font-weight:500; }
.grp{ font-size:8.5px; font-weight:800; text-transform:uppercase; letter-spacing:.06em; color:var(--muted); margin:0 0 7px; }
.grp.mt{ margin-top:13px; }

/* ===== Campos label: valor (en línea, densos) ===== */
.kv{ display:flex; flex-direction:column; gap:5px; }
.f{ display:flex; gap:6px; flex-wrap:wrap; line-height:1.4; }
.f .k{ font-weight:700; color:var(--muted); }
.f .k::after{ content:':'; }
.f .v{ color:var(--ink); min-width:0; }

/* ===== Bloque identidad (foto + datos) ===== */
.idb{ display:flex; gap:12px; align-items:flex-start; }
.photo{ width:76px; height:76px; border-radius:10px; overflow:hidden; border:1px solid var(--line-2); background:var(--surface); flex:0 0 auto; }
.photo img{ width:100%; height:100%; object-fit:cover; display:block; }

/* ===== Chips SÍ/NO ===== */
.chips{ display:flex; flex-wrap:wrap; gap:5px; }
.chip{ display:inline-flex; align-items:center; gap:5px; padding:3px 9px; border-radius:999px; font-size:9px; border:1px solid var(--line-2); color:var(--muted); background:var(--surface); white-space:nowrap; }
.chip .s{ font-weight:800; font-size:8.5px; letter-spacing:.03em; }
.chip.yes{ color:var(--danger); background:color-mix(in srgb,var(--danger) 8%,#fff); border-color:color-mix(in srgb,var(--danger) 30%,#fff); }
.chip.yes .s{ color:var(--danger); }
.chip.no .s{ color:var(--faint); }

/* ===== Hospitalizaciones (lista numerada, sin repetir la etiqueta) ===== */
.hosp{ margin:0; padding:0; list-style:none; counter-reset:h; }
.hosp li{ position:relative; padding:4px 0 4px 22px; border-bottom:1px solid var(--line); line-height:1.4; }
.hosp li:last-child{ border-bottom:0; }
.hosp li::before{ counter-increment:h; content:counter(h); position:absolute; left:0; top:3px; width:16px; height:16px; border-radius:50%; background:var(--surface); border:1px solid var(--line-2); font-size:8px; font-weight:700; color:var(--muted); display:flex; align-items:center; justify-content:center; }

/* ===== Tabla de consultas ===== */
.ctab{ width:100%; border-collapse:collapse; }
.ctab th{ font-size:8px; text-transform:uppercase; letter-spacing:.05em; color:var(--muted); font-weight:700; text-align:left; padding:6px 8px; border-bottom:1.5px solid var(--line-2); white-space:nowrap; }
.ctab td{ vertical-align:top; padding:7px 8px; border-bottom:1px solid var(--line); color:var(--ink); font-size:9.5px; line-height:1.4; }
.ctab tr{ break-inside:avoid; }
.ctab tbody tr:last-child td{ border-bottom:0; }
.mgmt{ display:block; font-style:italic; color:var(--muted); font-size:8.5px; margin-top:2px; }
.tag-warn{ display:inline-flex; align-items:center; gap:3px; margin-top:3px; padding:1px 6px; border-radius:999px; font-size:7.5px; font-weight:700; color:var(--warn); background:color-mix(in srgb,var(--warn) 10%,#fff); border:1px solid color-mix(in srgb,var(--warn) 32%,#fff); }
.empty{ text-align:center; color:var(--faint); padding:14px; }

/* ===== Avisos (expediente pendiente/incompleto) ===== */
.alert{ display:flex; gap:8px; align-items:flex-start; padding:9px 12px; border-radius:8px; margin-bottom:12px; font-size:9.5px; background:color-mix(in srgb,var(--warn) 8%,#fff); border:1px solid color-mix(in srgb,var(--warn) 30%,#fff); color:#7a4a09; }
.alert b{ color:#7a4a09; }
.decl{ font-size:9px; color:var(--faint); margin:0 0 12px; font-family:var(--mono); }

/* ===== Integridad (anexos + sellos, compactos) ===== */
.seal-note{ font-size:9px; color:var(--muted); margin:0 0 8px; }
.anexo{ padding:0 0 10px; margin-bottom:10px; border-bottom:1px solid var(--line); }
.anexo:last-of-type{ border-bottom:0; }
.anexo-head{ display:flex; flex-wrap:wrap; align-items:baseline; gap:8px; }
.anexo-head strong{ font-size:10px; } .anexo-head .when{ font-size:8.5px; color:var(--faint); font-family:var(--mono); }
.anexo-by{ font-size:8.5px; color:var(--muted); margin-top:4px; }
.hm-seal-head{ font-size:8.5px; font-weight:700; color:var(--muted); margin:0 0 4px; letter-spacing:.02em; }
.seals-2{ display:grid; grid-template-columns:1fr 1fr; gap:12px 16px; }
@media (max-width:680px){ .seals-2{ grid-template-columns:1fr; } }

/* ===== Sello / cadena CFDI (mismas clases que _seal-cfdi; valores claros de papel) ===== */
.seal{display:flex;align-items:center;gap:11px;margin-top:6px;padding:11px 13px;border-radius:var(--radius-sm)}
.seal svg{width:19px;height:19px;flex:none}
.seal.bad{background:color-mix(in srgb,var(--danger) 10%,#fff);border:1px solid color-mix(in srgb,var(--danger) 35%,#fff)}
.seal.bad svg,.seal.bad b{color:var(--danger)}
.seal.none{background:var(--surface);border:1px solid var(--line)}
.seal .h{font-family:var(--mono);font-size:.68rem;color:var(--muted)}
.cfdi{display:flex;gap:13px;margin-top:8px;padding:13px;border:1px solid var(--line-2);border-radius:var(--radius-sm);background:var(--surface);break-inside:avoid}
.cfdi-mark{flex:none;width:88px;height:88px;border:1px solid var(--line-2);border-radius:10px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:5px}
.cfdi-mark .m{width:34px;height:34px;color:var(--brand)}
.cfdi-mark .m svg{width:34px;height:34px}
.cfdi-mark .lbl{font-family:var(--mono);font-size:.48rem;font-weight:700;letter-spacing:.06em;text-align:center;color:var(--muted);line-height:1.25}
.cfdi-mark--qr{width:100px;height:100px;padding:6px;gap:0;background:#fff;-webkit-print-color-adjust:exact;print-color-adjust:exact}
.cfdi-mark--qr svg{width:100%;height:100%;display:block}
.cfdi-verify{flex:none;width:54px;display:flex;flex-direction:column;align-items:center}
.cfdi-idc{width:54px;height:54px}
.cfdi-idc svg{width:100%;height:100%;display:block;border-radius:8px;border:1px solid var(--line-2)}
.cfdi-body{flex:1;min-width:0}
.cfdi-row{margin-bottom:8px}
.cfdi-k{font-size:.52rem;letter-spacing:.1em;text-transform:uppercase;color:var(--muted);margin-bottom:2px}
.cfdi-v{font-family:var(--mono);font-size:.6rem;color:var(--ink);word-break:break-all;line-height:1.5}
.cfdi-meta{display:flex;flex-wrap:wrap;gap:5px 16px;margin-top:6px;padding-top:8px;border-top:1px solid var(--line);font-size:.64rem;color:var(--muted)}
.cfdi-meta .mono{font-family:var(--mono);font-size:.58rem;word-break:break-all}

/* ===== Pie ===== */
.mfoot{ margin-top:16px; padding-top:10px; border-top:1px solid var(--line); display:flex; justify-content:space-between; align-items:center; gap:12px; font-size:8px; color:var(--faint); font-family:var(--mono); letter-spacing:.02em; }
.mfoot .cc{ font-weight:700; color:var(--muted); font-family:var(--font); }

/* ===== Botón de impresión (no imprime) ===== */
.printbtn{ position:fixed; right:16px; bottom:16px; background:var(--brand); color:#fff; border:0; border-radius:999px; padding:11px 18px; font-size:13px; font-weight:700; cursor:pointer; box-shadow:0 6px 20px rgba(0,0,0,.18); font-family:var(--font); display:inline-flex; align-items:center; gap:7px; }
@media print{ .no-print{ display:none !important; } .doc{ max-width:none; } }
</style>
</head>
<body>
<div class="doc">

    {{-- ===== Membrete: logo CrewCare (marca del sitio) · título centrado · logo del cliente ===== --}}
    <header class="mhead">
        <div class="mhead-side mhead-left">
            {{-- logo-cc-report.svg = marca CrewCare para documentos (ícono azul + texto gris), legible
                 sobre blanco. Es la marca del SITIO, no un texto genérico. --}}
            <img class="mhead-cc" src="/img/logo-cc-report.svg" alt="CrewCare">
        </div>
        <div class="mhead-title">
            <h1>{{ __('Historial Médico') }}</h1>
            <div class="sub">{{ __('Expediente clínico') }}</div>
        </div>
        <div class="mhead-side mhead-right">
            @if($clientLogo !== '')
                <img class="mhead-client" src="{{ $clientLogo }}" alt="{{ $branding['brand_name'] ?? '' }}" onerror="this.style.display='none'">
            @endif
        </div>
    </header>
    <div class="mrule"></div>
    @php
        $metaBits = [];
        if (isset($expedienteModelo) && $expedienteModelo) $metaBits[] = $expedienteModelo->folio();
        if ($user && ! empty($user->created_at)) $metaBits[] = __('Declarado el').' '.\Carbon\Carbon::parse($user->created_at)->format('d/m/Y');
    @endphp
    @if(count($metaBits))<div class="mhead-meta">{{ implode('   ·   ', $metaBits) }}</div>@endif

    {{-- ===== Aviso de estado del expediente ===== --}}
    @if(($intakeState ?? 'ok') === 'missing')
        <div class="alert"><div><b>{{ __('Expediente pendiente') }}.</b> {{ __('Esta persona no ha llenado el cuestionario médico. Las consultas se registraron sin información de alergias ni antecedentes.') }}</div></div>
    @elseif(($intakeState ?? 'ok') === 'incomplete')
        <div class="alert"><div><b>{{ __('Expediente sin alergias registradas') }}.</b> {{ __('Hay expediente, pero el campo de alergias está vacío: no se capturaron.') }}</div></div>
    @endif

    @if($user)
    {{-- ===== Datos personales + Información general ===== --}}
    <div class="grid2">
        <section class="card">
            <div class="sect"><h2>{{ __('Datos personales') }}</h2></div>
            <div class="idb">
                <div class="photo">
                    <img src="{{ \App\Support\Avatar::url(!empty($datos) ? $datos : null) }}" alt="{{ (!empty($datos) && \App\Support\Avatar::has($datos)) ? __('Foto de perfil') : __('Sin foto') }}">
                </div>
                <div class="kv" style="flex:1 1 auto; min-width:0;">
                    <div class="f"><span class="k">{{ __('Nombre') }}</span><span class="v">{{ $fullName ?: '—' }}</span></div>
                    <div class="f"><span class="k">{{ __('Teléfono') }}</span><span class="v">{{ $datos->phone ?: '—' }}</span></div>
                    <div class="f"><span class="k">{{ __('Email') }}</span><span class="v">{{ $datos->email ?: '—' }}</span></div>
                </div>
            </div>
            <div class="grp mt">{{ __('Contacto de emergencia') }}</div>
            <div class="kv">
                <div class="f"><span class="k">{{ __('Nombre') }}</span><span class="v">{{ $user->c_emer ?: '—' }}</span></div>
                <div class="f"><span class="k">{{ $user->relation ?: __('Relación') }}</span><span class="v">{{ $user->p_emer ?: '—' }}</span></div>
            </div>
        </section>

        <section class="card">
            <div class="sect"><h2>{{ __('Información general') }}</h2></div>
            <div class="kv">
                <div class="f"><span class="k">{{ __('Puesto') }}</span><span class="v">{{ (isset($target) ? $target->positionName() : null) ?: '—' }}</span></div>
                <div class="f"><span class="k">{{ __('Edad') }}</span><span class="v">{{ $datos->borndate ? \Carbon\Carbon::parse($datos->borndate)->age.' '.__('años') : '—' }}</span></div>
                <div class="f"><span class="k">{{ __('Tipo de sangre') }}</span><span class="v">{{ $user->blod_type ?: '—' }}</span></div>
                <div class="f"><span class="k">{{ __('Peso') }}</span><span class="v">{{ $user->height ? $user->height.' kg' : '—' }}</span></div>
                <div class="f"><span class="k">{{ __('Talla') }}</span><span class="v">{{ $user->size ? $user->size.' m' : '—' }}</span></div>
                <div class="f"><span class="k">{{ __('IMC') }}</span><span class="v">{{ (empty($user->size) || empty($user->height)) ? 'N/D' : round($user->height / ($user->size * $user->size), 1) }}</span></div>
            </div>
        </section>
    </div>

    {{-- ===== Antecedentes patológicos / no patológicos / vacunación ===== --}}
    <section class="card wide">
        <div class="sect"><h2>{{ __('Antecedentes') }}</h2><span class="sub">{{ __('Historial clínico y esquema de vacunación') }}</span></div>
        <div class="grid3">
            <div>
                <div class="grp">{{ __('Patológicos') }}</div>
                <div class="kv">
                    <div class="f"><span class="k">{{ __('Cirugías') }}</span><span class="v">{{ $user->cirugy ?: '—' }}</span></div>
                    <div class="f"><span class="k">{{ __('Alergias') }}</span><span class="v">{{ $user->alergy ?: '—' }}</span></div>
                    <div class="f"><span class="k">{{ __('Patológicas') }}</span><span class="v">{{ $user->pathology ?: '—' }}</span></div>
                    <div class="f"><span class="k">{{ __('Traumáticos') }}</span><span class="v">{{ $user->trauma ?: '—' }}</span></div>
                </div>
            </div>
            <div>
                <div class="grp">{{ __('No patológicos') }}</div>
                @php
                    $noPat = [
                        __('Tabaquismo')   => (int) ($user->pers_nopat1 ?? 0) === 1,
                        __('Alcoholismo')  => (int) ($user->pers_nopat2 ?? 0) === 1,
                        __('Toxicomanías') => (int) ($user->pers_nopat3 ?? 0) === 1,
                    ];
                @endphp
                <div class="chips">
                    @foreach($noPat as $lbl => $yes)
                        <span class="chip {{ $yes ? 'yes' : 'no' }}">{{ $lbl }} <span class="s">{{ $yes ? __('SÍ') : 'NO' }}</span></span>
                    @endforeach
                </div>
            </div>
            <div>
                <div class="grp">{{ __('Vacunación') }}</div>
                @php
                    $vac = [
                        'COVID-19'        => (int) ($user->vacci1 ?? 0) === 1,
                        __('Influenza')   => (int) ($user->vacci2 ?? 0) === 1,
                        __('Tétanos')     => (int) ($user->vacci3 ?? 0) === 1,
                        __('Neumococo')   => (int) ($user->vacci4 ?? 0) === 1,
                        __('Hepatitis B') => (int) ($user->vacci5 ?? 0) === 1,
                    ];
                @endphp
                <div class="chips">
                    @foreach($vac as $lbl => $yes)
                        <span class="chip {{ $yes ? 'yes' : 'no' }}">{{ $lbl }} <span class="s">{{ $yes ? __('SÍ') : 'NO' }}</span></span>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    {{-- ===== Heredo-familiares + Hospitalizaciones (+ Gineco) ===== --}}
    <div class="grid2">
        <section class="card">
            <div class="sect"><h2>{{ __('Heredo-familiares') }}</h2></div>
            <div class="grp">{{ __('Madre') }}</div>
            <div class="chips" style="margin-bottom:11px;">
                @foreach($heredoLabels as $idx => $lbl)
                    @php $yes = (int) ($user->{'momdat'.($idx + 1)} ?? 0) === 1; @endphp
                    <span class="chip {{ $yes ? 'yes' : 'no' }}">{{ $lbl }} <span class="s">{{ $yes ? __('SÍ') : 'NO' }}</span></span>
                @endforeach
            </div>
            <div class="grp">{{ __('Padre') }}</div>
            <div class="chips">
                @foreach($heredoLabels as $idx => $lbl)
                    @php $yes = (int) ($user->{'daddat'.($idx + 1)} ?? 0) === 1; @endphp
                    <span class="chip {{ $yes ? 'yes' : 'no' }}">{{ $lbl }} <span class="s">{{ $yes ? __('SÍ') : 'NO' }}</span></span>
                @endforeach
            </div>
        </section>

        <section class="card">
            <div class="sect"><h2>{{ __('Hospitalizaciones') }}</h2></div>
            @if(($user->hospitals ?? 0) > 0)
                <ul class="hosp">
                    @for ($i = 1; $i <= $user->hospitals; $i++)
                        <li>{{ $user->{'hsp'.$i} ?: '—' }}</li>
                    @endfor
                </ul>
            @else
                <div class="empty">{{ __('Sin hospitalizaciones registradas.') }}</div>
            @endif

            @if(($datos->sex ?? '') === 'F')
                <div class="grp mt">{{ __('Gineco-obstétrico') }}</div>
                <div class="kv">
                    <div class="f"><span class="k">{{ __('Ritmo') }}</span><span class="v">{{ $user->rythm ?: '—' }}</span></div>
                    <div class="f"><span class="k">{{ __('Embarazos') }}</span><span class="v">{{ $user->pregnant ?: '—' }}</span></div>
                </div>
                @php
                    $gineco = [
                        __('Papanicolaou') => (int) ($user->prevent1 ?? 0) === 1,
                        __('Mastografía')  => (int) ($user->prevent2 ?? 0) === 1,
                    ];
                @endphp
                <div class="chips" style="margin-top:7px;">
                    @foreach($gineco as $lbl => $yes)
                        <span class="chip {{ $yes ? 'yes' : 'no' }}">{{ $lbl }} <span class="s">{{ $yes ? __('SÍ') : 'NO' }}</span></span>
                    @endforeach
                </div>
            @endif
        </section>
    </div>
    @endif

    {{-- ===== Consultas ===== --}}
    <section class="card wide">
        <div class="sect"><h2>{{ __('Consultas') }}</h2><span class="sub">{{ __('Atenciones médicas registradas en el expediente') }}</span></div>
        <table class="ctab">
            <thead>
                <tr>
                    <th>{{ __('Fecha') }}</th>
                    <th>{{ __('Atendió') }}</th>
                    <th>{{ __('Diagnóstico') }}</th>
                    <th>{{ __('Medicamento') }}</th>
                    <th>{{ __('Observaciones') }}</th>
                    <th>{{ __('Información adicional') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($consultas as $consulta)
                    <tr>
                        <td>
                            {{ \Carbon\Carbon::parse($consulta->created_at)->format('d/m/Y H:i') }}
                            @if((int) ($consulta->without_record ?? 0) === 1)
                                <span class="tag-warn">{{ __('Sin expediente') }}</span>
                            @endif
                        </td>
                        <td>@include('componentes._consult-attended-by', ['consulta' => $consulta, 'medicos' => $medicos])</td>
                        <td>{{ $consulta->diagnosis }}</td>
                        @php $mgmtLabels = $consulta->managementLabels(); @endphp
                        <td>
                            @if($consulta->medication !== '' && $consulta->medication !== null){{ $consulta->medication }}@elseif(empty($mgmtLabels)){{ __('Ninguno') }}@endif
                            @if(! empty($mgmtLabels))<span class="mgmt">{{ implode(' · ', array_map('__', $mgmtLabels)) }}</span>@endif
                        </td>
                        <td>{{ $consulta->observations ?: __('Ninguna') }}</td>
                        <td>{{ $consulta->aditional ?: __('Ninguna') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty">{{ __('No hay consultas registradas.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>

    {{-- ===== Integridad del expediente (anexos + sello) — SÓLO SI EXISTE algo que mostrar =====
         Regla del owner: "si no existe, no se muestra". No se pinta el hueco de un sello ausente
         (un "aún sin sellar" vacío hace ver el documento incompleto). Un sello ALTERADO sí aparece:
         se guarda por EXISTENCIA de firma (signatures()->exists()), y el parcial detecta la alteración. --}}
    @php
        $anexos    = $anexosExpediente ?? collect();
        $expSealed = isset($expedienteModelo) && $expedienteModelo && $expedienteModelo->signatures()->exists();
    @endphp
    @if($anexos->count() || $expSealed)
    <section class="card wide">
        <div class="sect"><h2>{{ __('Integridad del expediente') }}</h2><span class="sub">{{ __('Anexos médicos y sello del documento') }}</span></div>

        @foreach($anexos as $anexo)
            <div class="anexo">
                <div class="anexo-head">
                    <strong>{{ $anexo->folio() }}</strong>
                    <span class="when">{{ \Carbon\Carbon::parse($anexo->created_at)->format('d/m/Y H:i') }}</span>
                    <span class="chip no"><span class="s">{{ $anexo->motivoLabel() }}</span></span>
                </div>
                <div style="margin-top:5px;">
                    @foreach($anexo->cambiosLegibles() as $cambio)
                        <div class="f"><span class="k">{{ $cambio['campo'] }}</span><span class="v">{{ $cambio['valor'] }}</span></div>
                    @endforeach
                </div>
                @if($anexo->notes)<p style="margin:5px 0 0; font-style:italic; font-size:9.5px;">{{ $anexo->notes }}</p>@endif
                <div class="anexo-by">
                    {{ __('health.trace_by') }}: {{ $anexo->medic_name ?: '—' }}@if($anexo->medic_cedula) · {{ __('health.trace_cedula') }} {{ $anexo->medic_cedula }}@if($anexo->medic_cedula_verified) ✓ @endif @endif
                </div>
            </div>
        @endforeach

        @if($expSealed)
            <p class="hm-seal-head" style="margin-top:10px;">{{ __('health.trace_seal_head', ['folio' => $expedienteModelo->folio()]) }}</p>
            @include('componentes._seal-cfdi', ['doc' => $expedienteModelo, 'folio' => $expedienteModelo->folio(), 'prefix' => 'CREWCARE-EXP'])
        @endif
    </section>
    @endif

    {{-- ===== Sellos por consulta (compactos, 2 por fila) — SÓLO las que SÍ tienen sello =====
         Regla del owner "si no existe, no se muestra": las consultas sin firma no pintan un recuadro
         vacío. Si NINGUNA está sellada, la sección entera no aparece. --}}
    @php $sealedConsultas = $consultas->filter(function ($c) { return $c->signatures()->exists(); }); @endphp
    @if($sealedConsultas->count())
    <section class="card wide">
        <div class="sect"><h2>{{ __('Sellos de integridad') }}</h2><span class="sub">{{ __('Firma SHA-256 y cadena CFDI cotejable de cada consulta') }}</span></div>
        <div class="seals-2">
            @foreach($sealedConsultas as $consulta)
                <div>
                    <p class="hm-seal-head">{{ __('Consulta') }} MED-{{ str_pad((string) $consulta->id_cmedic, 4, '0', STR_PAD_LEFT) }} · {{ \Carbon\Carbon::parse($consulta->created_at)->format('d/m/Y H:i') }}</p>
                    @include('componentes._seal-cfdi', ['doc' => $consulta, 'folio' => 'MED-'.str_pad((string) $consulta->id_cmedic, 4, '0', STR_PAD_LEFT), 'prefix' => 'CREWCARE-MED'])
                </div>
            @endforeach
        </div>
    </section>
    @endif

    <footer class="mfoot">
        <span class="cc">{{ $branding['brand_name'] ?? 'CrewCare' }} · {{ __('Historial Médico') }}</span>
        <span>{{ now()->format('d/m/Y H:i') }}</span>
    </footer>

</div>

<button type="button" class="printbtn no-print" onclick="window.print()" aria-label="{{ __('Imprimir') }}">
    @include('componentes._icon', ['name' => 'printer', 'class' => 'cc-ico-16', 'label' => null])
    <span>{{ __('Imprimir') }}</span>
</button>
<script>
    // Abrir el diálogo de impresión al cargar (documento listo para papel). Si el usuario cancela,
    // sigue viendo el expediente limpio y puede reimprimir con el botón.
    window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 400); });
</script>
</body>
</html>
