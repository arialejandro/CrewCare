@extends('layouts.app')

@section('title', __('Historial Médico').' - '.($branding['brand_name'] ?? 'CrewCare'))

@section('content')

{{-- Sistema de estilos de formularios/tarjetas reutilizable (mismo lenguaje que la consulta médica). --}}
@include('componentes._form-kit')

{{-- CSS específico de ESTA vista: sólo lo que el kit no cubre
     (foto de perfil, chip neutro SÍ/NO, tabla de consultas y aislamiento de impresión). --}}
@push('styles')
<style>
    .hm-report { color: var(--text); }

    /* Bloque foto + datos: se apila en pantallas chicas. */
    .hm-idblock { display: flex; gap: 1rem; align-items: flex-start; flex-wrap: wrap; }
    .hm-photo {
        width: 84px; height: 84px; border-radius: 50%; overflow: hidden;
        border: 2px solid var(--stroke, var(--border)); flex: 0 0 auto; background: var(--surface-2);
    }
    .hm-photo img { width: 100%; height: 100%; object-fit: cover; }

    /* Ficha de datos en una sola columna dentro de las columnas Bootstrap estrechas. */
    .hm-info-1 { grid-template-columns: 1fr !important; gap: .55rem !important; }

    /* Chips SÍ/NO: el kit trae danger/ok/brand; agregamos el neutro "NO". */
    .hm-chips { display: flex; flex-wrap: wrap; gap: .5rem; }
    .cc-chip--muted { color: var(--text-muted); background: var(--surface-2); border: 1px solid var(--stroke-2, var(--border)); }
    .cc-chip .st { font-weight: 800; font-size: .72rem; letter-spacing: .04em; }
    .cc-chip--danger .st { color: var(--danger); }
    .cc-chip--muted .st { color: var(--text-muted); }

    /* Chips de CÉDULA (PASO B). OJO CON EL NOMBRE: son UN guion (.cc-chip-ok / .cc-chip-warn),
       no dos. Los `--modificador` de arriba son del kit de esta pantalla; estos otros vienen
       del patrón de verificación que ya usan standards/consumables, y el parcial
       componentes/_medic-credential-badge los da por existentes. Son familias DISTINTAS que
       conviven sobre la misma clase base .cc-chip — no unificar sin repasar las dos. */
    .cc-chip-ok{color:var(--ok);background:color-mix(in srgb,var(--ok) 15%,transparent);border:1px solid color-mix(in srgb,var(--ok) 32%,transparent)}
    .cc-chip-warn{color:var(--warn);background:color-mix(in srgb,var(--warn) 16%,transparent);border:1px solid color-mix(in srgb,var(--warn) 32%,transparent)}

    .hm-relative { font-weight: 700; color: var(--text); margin: 0 0 .55rem; }
    .hm-hr { border: 0; border-top: 1px solid var(--stroke, var(--border)); margin: 1.1rem 0; }

    /* Tabla de consultas: cabecera discreta, filas aireadas (igual que la consulta médica). */
    .hm-med-table { margin: 0; width: 100%; min-width: 640px; border-collapse: collapse; }
    .hm-med-table th {
        font-size: .7rem; text-transform: uppercase; letter-spacing: .04em;
        color: var(--text-muted); font-weight: 600; border-bottom: 1px solid var(--border);
        padding: .55rem .6rem; text-align: left; white-space: nowrap;
    }
    .hm-med-table td {
        vertical-align: top; padding: .55rem .6rem; border-bottom: 1px solid var(--border);
        color: var(--text); font-size: .9rem;
    }
    .hm-med-table tbody tr:last-child td { border-bottom: 0; }
    .hm-empty { text-align: center; padding: 2rem 1rem; color: var(--text-muted); }
    /* Manejo/conducta bajo el medicamento: subordinado, pero legible al imprimir. */
    .hm-mgmt { font-size: .85rem; font-style: italic; color: var(--text-muted); margin-top: .15rem; }

    /* ===== Sello CFDI por consulta (item 5, 2026-07-24). El parcial _seal-cfdi usa estas clases;
       su CSS vive en los REPORTES (_report-v2-head) con otro set de tokens, así que aquí se replica
       con FALLBACKS a lo que esta pantalla sí tiene (--panel/--mono/--muted/--brand/--stroke-2/
       --radius-sm pueden no existir en layouts.app → degradan a --surface-2/monospace/--text-muted). */
    .hm-seals { margin-top: 1rem; display: flex; flex-direction: column; gap: 1.1rem; }
    .hm-seal-head { font-size: .78rem; font-weight: 700; color: var(--text-muted); margin: 0 0 .3rem; letter-spacing: .02em; }
    .seal{display:flex;align-items:center;gap:11px;margin-top:6px;padding:12px 14px;border-radius:var(--radius-sm,10px)}
    .seal svg{width:20px;height:20px;flex:none}
    .seal.bad{background:color-mix(in srgb,var(--danger) 10%,transparent);border:1px solid color-mix(in srgb,var(--danger) 35%,transparent)}
    .seal.bad svg,.seal.bad b{color:var(--danger)}
    .seal.none{background:var(--surface-2);border:1px solid var(--stroke,var(--border))}
    .seal .h{font-family:var(--mono,ui-monospace,Menlo,monospace);font-size:.7rem;color:var(--text-muted)}
    .cfdi{display:flex;gap:14px;margin-top:8px;padding:14px;border:1px solid var(--stroke-2,var(--stroke,var(--border)));border-radius:var(--radius-sm,10px);background:var(--surface-2);break-inside:avoid}
    .cfdi-mark{flex:none;width:92px;height:92px;border:1px solid var(--stroke-2,var(--stroke,var(--border)));border-radius:10px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:5px}
    .cfdi-mark .m{width:36px;height:36px;color:var(--brand,var(--ok))}
    .cfdi-mark .m svg{width:36px;height:36px}
    .cfdi-mark .lbl{font-family:var(--mono,ui-monospace,Menlo,monospace);font-size:.5rem;font-weight:700;letter-spacing:.06em;text-align:center;color:var(--text-muted);line-height:1.25}
    /* QR + identicon: hasta hoy esta pantalla NO replicaba estas reglas y los dos SVG salían
       a su tamaño intrínseco (el QR a 132px, descuadrando el recuadro). Van con el mismo
       tamaño que en los reportes para que la consulta médica se vea igual que los otros 5. */
    .cfdi-mark--qr{width:106px;height:106px;padding:6px;gap:0;background:#fff;
      -webkit-print-color-adjust:exact;print-color-adjust:exact}
    .cfdi-mark--qr svg{width:100%;height:100%;display:block}
    .cfdi-verify{flex:none;width:56px;display:flex;flex-direction:column;align-items:center}
    .cfdi-idc{width:56px;height:56px}
    .cfdi-idc svg{width:100%;height:100%;display:block;border-radius:8px;border:1px solid var(--stroke-2,var(--stroke,var(--border)))}
    .cfdi-body{flex:1;min-width:0}
    .cfdi-row{margin-bottom:9px}
    .cfdi-k{font-size:.54rem;letter-spacing:.1em;text-transform:uppercase;color:var(--text-muted);margin-bottom:2px}
    .cfdi-v{font-family:var(--mono,ui-monospace,Menlo,monospace);font-size:.62rem;color:var(--text);word-break:break-all;line-height:1.5}
    .cfdi-meta{display:flex;flex-wrap:wrap;gap:5px 18px;margin-top:6px;padding-top:9px;border-top:1px solid var(--stroke,var(--border));font-size:.66rem;color:var(--text-muted)}
    .cfdi-meta .mono{font-family:var(--mono,ui-monospace,Menlo,monospace);font-size:.6rem;word-break:break-all}
    @media (max-width:720px){ .cfdi{flex-direction:column} .cfdi-mark{width:74px;height:74px} .cfdi-mark--qr{width:106px;height:106px} .cfdi-verify{width:auto} }

    /* Aislar el reporte al imprimir (window.print) sin tocar el layout. */
    @media print {
        body * { visibility: hidden !important; }
        #hm-report, #hm-report * { visibility: visible !important; }
        #hm-report { position: absolute; left: 0; top: 0; width: 100%; }
        .hm-no-print { display: none !important; }
        .cc-form-card { break-inside: avoid; box-shadow: none !important; }
        #hm-report .collapse { display: block !important; height: auto !important; } /* abre todo en papel */
    }
</style>
@endpush

<div class="hm-report container-fluid py-4" id="hm-report" style="max-width: 1100px;">

    {{-- ===== Encabezado (mismo lenguaje que la consulta médica) ===== --}}
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div class="d-flex align-items-center gap-3">
            <span class="cc-form-ico">
                @include('componentes._icon', ['name' => 'clipboard-list', 'class' => 'cc-ico-20', 'label' => null])
            </span>
            <div>
                <div class="cc-muted small text-uppercase" style="letter-spacing:.08em; font-weight:700;">
                    {{ $branding['brand_name'] ?? 'CrewCare' }} · {{ __('Historial Médico') }}
                </div>
                <h1 class="h4 fw-bold mb-0">{{ $datos->name }} {{ $datos->lname }} {{ $datos->lname2 }}</h1>
                <div class="cc-muted small">{{ __('Expediente clínico — uso interno') }}</div>
            </div>
        </div>
        <button type="button" class="cc-btn-ghost hm-no-print" onclick="window.print()" aria-label="{{ __('Imprimir historial médico') }}">
            @include('componentes._icon', ['name' => 'printer', 'class' => 'cc-ico-16', 'label' => null])
            <span>{{ __('Imprimir') }}</span>
        </button>
    </div>

{{-- (2026-07-24 · PASO 3/3, item 1) La persona puede tener consultas SIN haber llenado el
     cuestionario: el expediente ya no se niega, se marca. --}}
@if(($intakeState ?? 'ok') === 'missing')
    <div class="alert alert-warning shadow-sm d-flex align-items-start gap-2" role="alert">
        @include('componentes._icon', ['name' => 'alert-triangle', 'class' => 'cc-ico-18 mt-1', 'label' => null])
        <div>
            <strong>{{ __('Expediente pendiente') }}</strong>
            <div class="small mb-0">{{ __('Esta persona no ha llenado el cuestionario médico. Las consultas de abajo se registraron sin información de alergias ni antecedentes.') }}</div>
        </div>
    </div>
@elseif(($intakeState ?? 'ok') === 'incomplete')
    <div class="alert alert-warning shadow-sm d-flex align-items-start gap-2" role="alert">
        @include('componentes._icon', ['name' => 'alert-triangle', 'class' => 'cc-ico-18 mt-1', 'label' => null])
        <div>
            <strong>{{ __('Expediente sin alergias registradas') }}</strong>
            <div class="small mb-0">{{ __('Hay expediente, pero el campo de alergias está vacío: no se capturaron.') }}</div>
        </div>
    </div>
@endif

@foreach ($usuario as $user)

    {{-- (2026-07-24 · PIEZA 3) FECHA DE LLENADO. El expediente no la decía en ninguna parte, y
         `users.lastwr` no sirve de sustituto: CrewController lo pone en now() al DAR DE ALTA, así
         que los 92 usuarios lo tienen aunque sólo 3 hayan llenado su expediente. La fecha real es
         el `created_at` de la fila. Un antecedente sin fecha no se puede pesar: "sin alergias
         conocidas" dice cosas distintas si se declaró ayer o hace tres producciones.

         `$usuario` trae UNA sola fila (la vigente, ver cmedicController@expedienteVigente). Antes
         este @foreach recorría TODAS y repetía la ficha completa —con los mismos ids HTML— sin
         decir a cuál correspondía cada copia. --}}
    @if(! empty($user->created_at))
        <p class="cc-muted small mb-2">
            {{ __('Expediente declarado por la persona el') }}
            <strong>{{ \Carbon\Carbon::parse($user->created_at)->format('d/m/Y') }}</strong>
        </p>
    @endif

    {{-- ===== Datos personales e información general ===== --}}
    <div class="cc-form-card">
        <button class="cc-collapse-head" type="button" data-bs-toggle="collapse" data-bs-target="#hm-personales" aria-expanded="true" aria-controls="hm-personales">
            <span class="cc-form-ico">
                @include('componentes._icon', ['name' => 'user', 'class' => 'cc-ico-20', 'label' => null])
            </span>
            <span class="cc-form-card__titles">
                <span class="cc-form-card__title d-block">{{ __('Datos personales e información general') }}</span>
                <span class="cc-form-card__sub d-block">{{ __('Identificación, contacto y datos clínicos base') }}</span>
            </span>
            @include('componentes._icon', ['name' => 'chevron-down', 'class' => 'cc-collapse-caret cc-ico-18', 'label' => null])
        </button>
        <div class="collapse show" id="hm-personales">
            <div class="cc-form-card__body border-top" style="border-color: var(--stroke, var(--border)) !important;">
                <div class="row g-4">
                    <div class="col-md-5">
                        <div class="hm-idblock">
                            <div class="hm-photo">
                                <img src="{{ \App\Support\Avatar::url(!empty($datos) ? $datos : null) }}"
                                     alt="{{ (!empty($datos) && \App\Support\Avatar::has($datos)) ? __('Foto de perfil') : __('Sin foto') }}">
                            </div>
                            <div class="cc-info hm-info-1" style="flex:1 1 200px; min-width:0;">
                                <div class="cc-info-item"><span class="cc-info-lbl">{{ __('Nombre') }}</span><span class="cc-info-val">{{ $datos->name }} {{ $datos->lname }} {{ $datos->lname2 }}</span></div>
                                <div class="cc-info-item"><span class="cc-info-lbl">{{ __('Teléfono') }}</span><span class="cc-info-val">{{ $datos->phone ?: '—' }}</span></div>
                                <div class="cc-info-item"><span class="cc-info-lbl">{{ __('Email') }}</span><span class="cc-info-val">{{ $datos->email ?: '—' }}</span></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="cc-group-title">
                            @include('componentes._icon', ['name' => 'phone', 'class' => 'cc-ico-14', 'label' => null])
                            {{ __('Contacto de emergencia') }}
                        </div>
                        <div class="cc-info hm-info-1">
                            <div class="cc-info-item"><span class="cc-info-lbl">{{ __('Nombre') }}</span><span class="cc-info-val">{{ $user->c_emer ?: '—' }}</span></div>
                            <div class="cc-info-item"><span class="cc-info-lbl">{{ $user->relation ?: __('Relación') }}</span><span class="cc-info-val">{{ $user->p_emer ?: '—' }}</span></div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="cc-group-title">
                            @include('componentes._icon', ['name' => 'activity', 'class' => 'cc-ico-14', 'label' => null])
                            {{ __('Datos clínicos') }}
                        </div>
                        <div class="cc-info hm-info-1">
                            <div class="cc-info-item"><span class="cc-info-lbl">{{ __('Puesto') }}</span><span class="cc-info-val">{{ $target->positionName() ?: '—' }}</span></div>
                            <div class="cc-info-item"><span class="cc-info-lbl">{{ __('Edad') }}</span><span class="cc-info-val">{{ $datos->borndate ? \Carbon\Carbon::parse($datos->borndate)->age.' '.__('años') : '—' }}</span></div>
                            <div class="cc-info-item"><span class="cc-info-lbl">{{ __('Tipo de sangre') }}</span><span class="cc-info-val">{{ $user->blod_type ?: '—' }}</span></div>
                            <div class="cc-info-item"><span class="cc-info-lbl">{{ __('Peso') }}</span><span class="cc-info-val">{{ $user->height ?: '—' }}</span></div>
                            <div class="cc-info-item"><span class="cc-info-lbl">{{ __('Talla') }}</span><span class="cc-info-val">{{ $user->size ?: '—' }}</span></div>
                            {{-- (2026-07-24) El IMC exige AMBOS datos. Antes sólo miraba la talla: con
                                 peso vacío imprimía "0", que se lee como un valor medido. Peso y talla
                                 son opcionales a propósito (ver el SQL de health-record-fixes), así
                                 que el hueco se declara N/D, no se calcula sobre un cero. --}}
                            <div class="cc-info-item"><span class="cc-info-lbl">{{ __('IMC') }}</span><span class="cc-info-val">{{ (empty($user->size) || empty($user->height)) ? 'N/D' : round($user->height / ($user->size * $user->size), 1) }}</span></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ===== Antecedentes patológicos, no patológicos y vacunación ===== --}}
    <div class="cc-form-card">
        <button class="cc-collapse-head" type="button" data-bs-toggle="collapse" data-bs-target="#hm-patologicas" aria-expanded="true" aria-controls="hm-patologicas">
            <span class="cc-form-ico">
                @include('componentes._icon', ['name' => 'file-text', 'class' => 'cc-ico-20', 'label' => null])
            </span>
            <span class="cc-form-card__titles">
                <span class="cc-form-card__title d-block">{{ __('Antecedentes, no patológicos y vacunación') }}</span>
                <span class="cc-form-card__sub d-block">{{ __('Historial clínico y esquema de vacunación') }}</span>
            </span>
            @include('componentes._icon', ['name' => 'chevron-down', 'class' => 'cc-collapse-caret cc-ico-18', 'label' => null])
        </button>
        <div class="collapse show" id="hm-patologicas">
            <div class="cc-form-card__body border-top" style="border-color: var(--stroke, var(--border)) !important;">
                <div class="row g-4">
                    <div class="col-md-4">
                        <div class="cc-group-title">
                            @include('componentes._icon', ['name' => 'clipboard-list', 'class' => 'cc-ico-14', 'label' => null])
                            {{ __('Patológicos') }}
                        </div>
                        <div class="cc-info hm-info-1">
                            <div class="cc-info-item"><span class="cc-info-lbl">{{ __('Cirugías') }}</span><span class="cc-info-val">{{ $user->cirugy ?: '—' }}</span></div>
                            <div class="cc-info-item"><span class="cc-info-lbl">{{ __('Alergias') }}</span><span class="cc-info-val">{{ $user->alergy ?: '—' }}</span></div>
                            <div class="cc-info-item"><span class="cc-info-lbl">{{ __('Patológicas') }}</span><span class="cc-info-val">{{ $user->pathology ?: '—' }}</span></div>
                            <div class="cc-info-item"><span class="cc-info-lbl">{{ __('Traumáticos') }}</span><span class="cc-info-val">{{ $user->trauma ?: '—' }}</span></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="cc-group-title">
                            @include('componentes._icon', ['name' => 'activity', 'class' => 'cc-ico-14', 'label' => null])
                            {{ __('No patológicos') }}
                        </div>
                        @php
                            $noPat = [
                                __('Tabaquismo')  => (int) ($user->pers_nopat1 ?? 0) === 1,
                                __('Alcoholismo') => (int) ($user->pers_nopat2 ?? 0) === 1,
                                __('Toxicomanías')=> (int) ($user->pers_nopat3 ?? 0) === 1,
                            ];
                        @endphp
                        <div class="hm-chips">
                            @foreach($noPat as $lbl => $yes)
                                <span class="cc-chip {{ $yes ? 'cc-chip--danger' : 'cc-chip--muted' }}">{{ $lbl }} <span class="st">{{ $yes ? __('SÍ') : 'NO' }}</span></span>
                            @endforeach
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="cc-group-title">
                            @include('componentes._icon', ['name' => 'shield', 'class' => 'cc-ico-14', 'label' => null])
                            {{ __('Vacunación') }}
                        </div>
                        @php
                            $vac = [
                                'COVID-19'          => (int) ($user->vacci1 ?? 0) === 1,
                                __('Influenza')     => (int) ($user->vacci2 ?? 0) === 1,
                                __('Tétanos')       => (int) ($user->vacci3 ?? 0) === 1,
                                __('Neumococo')     => (int) ($user->vacci4 ?? 0) === 1,
                                __('Hepatitis B')   => (int) ($user->vacci5 ?? 0) === 1,
                            ];
                        @endphp
                        <div class="hm-chips">
                            @foreach($vac as $lbl => $yes)
                                <span class="cc-chip {{ $yes ? 'cc-chip--danger' : 'cc-chip--muted' }}">{{ $lbl }} <span class="st">{{ $yes ? __('SÍ') : 'NO' }}</span></span>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ===== Antecedentes heredo-familiares ===== --}}
    <div class="cc-form-card">
        <button class="cc-collapse-head" type="button" data-bs-toggle="collapse" data-bs-target="#hm-heredo" aria-expanded="true" aria-controls="hm-heredo">
            <span class="cc-form-ico">
                @include('componentes._icon', ['name' => 'users', 'class' => 'cc-ico-20', 'label' => null])
            </span>
            <span class="cc-form-card__titles">
                <span class="cc-form-card__title d-block">{{ __('Antecedentes heredo-familiares') }}</span>
                <span class="cc-form-card__sub d-block">{{ __('Padecimientos por línea materna y paterna') }}</span>
            </span>
            @include('componentes._icon', ['name' => 'chevron-down', 'class' => 'cc-collapse-caret cc-ico-18', 'label' => null])
        </button>
        <div class="collapse show" id="hm-heredo">
            <div class="cc-form-card__body border-top" style="border-color: var(--stroke, var(--border)) !important;">
                @php
                    $heredoLabels = [
                        __('Vivo/Sano'), __('Fallecido'), __('Diabetes'), __('Hipertensión'),
                        __('Cardiopatías'), __('Nefropatías'), __('Neoplasias'),
                    ];
                @endphp

                <div class="hm-relative">{{ __('Madre') }}</div>
                <div class="hm-chips">
                    @foreach($heredoLabels as $idx => $lbl)
                        @php $yes = (int) ($user->{'momdat'.($idx + 1)} ?? 0) === 1; @endphp
                        <span class="cc-chip {{ $yes ? 'cc-chip--danger' : 'cc-chip--muted' }}">{{ $lbl }} <span class="st">{{ $yes ? __('SÍ') : 'NO' }}</span></span>
                    @endforeach
                </div>

                <hr class="hm-hr">

                <div class="hm-relative">{{ __('Padre') }}</div>
                <div class="hm-chips">
                    @foreach($heredoLabels as $idx => $lbl)
                        @php $yes = (int) ($user->{'daddat'.($idx + 1)} ?? 0) === 1; @endphp
                        <span class="cc-chip {{ $yes ? 'cc-chip--danger' : 'cc-chip--muted' }}">{{ $lbl }} <span class="st">{{ $yes ? __('SÍ') : 'NO' }}</span></span>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    {{-- ===== Hospitalizaciones ===== --}}
    <div class="cc-form-card">
        <button class="cc-collapse-head" type="button" data-bs-toggle="collapse" data-bs-target="#hm-hospital" aria-expanded="true" aria-controls="hm-hospital">
            <span class="cc-form-ico">
                @include('componentes._icon', ['name' => 'building-2', 'class' => 'cc-ico-20', 'label' => null])
            </span>
            <span class="cc-form-card__titles">
                <span class="cc-form-card__title d-block">{{ __('Hospitalizaciones') }}</span>
                <span class="cc-form-card__sub d-block">{{ __('Ingresos hospitalarios registrados') }}</span>
            </span>
            @include('componentes._icon', ['name' => 'chevron-down', 'class' => 'cc-collapse-caret cc-ico-18', 'label' => null])
        </button>
        <div class="collapse show" id="hm-hospital">
            <div class="cc-form-card__body border-top" style="border-color: var(--stroke, var(--border)) !important;">
                @if(($user->hospitals ?? 0) > 0)
                    <div class="cc-info hm-info-1">
                        @for ($i = 1; $i <= $user->hospitals; $i++)
                            <div class="cc-info-item"><span class="cc-info-lbl">{{ __('¿Qué sucedió?') }}</span><span class="cc-info-val">{{ $user->{'hsp'.$i} ?: '—' }}</span></div>
                        @endfor
                    </div>
                @else
                    <div class="hm-empty">{{ __('Sin hospitalizaciones registradas.') }}</div>
                @endif
            </div>
        </div>
    </div>

    {{-- ===== Gineco-obstétrico (solo si aplica) ===== --}}
    @if(($datos->sex ?? '') === 'F')
    <div class="cc-form-card">
        <button class="cc-collapse-head" type="button" data-bs-toggle="collapse" data-bs-target="#hm-gineco" aria-expanded="true" aria-controls="hm-gineco">
            <span class="cc-form-ico">
                @include('componentes._icon', ['name' => 'heart-pulse', 'class' => 'cc-ico-20', 'label' => null])
            </span>
            <span class="cc-form-card__titles">
                <span class="cc-form-card__title d-block">{{ __('Gineco-obstétrico') }}</span>
                <span class="cc-form-card__sub d-block">{{ __('Antecedentes y detección preventiva') }}</span>
            </span>
            @include('componentes._icon', ['name' => 'chevron-down', 'class' => 'cc-collapse-caret cc-ico-18', 'label' => null])
        </button>
        <div class="collapse show" id="hm-gineco">
            <div class="cc-form-card__body border-top" style="border-color: var(--stroke, var(--border)) !important;">
                <div class="row g-4">
                    <div class="col-md-6">
                        <div class="cc-info hm-info-1">
                            <div class="cc-info-item"><span class="cc-info-lbl">{{ __('Ritmo') }}</span><span class="cc-info-val">{{ $user->rythm ?: '—' }}</span></div>
                            <div class="cc-info-item"><span class="cc-info-lbl">{{ __('Embarazos') }}</span><span class="cc-info-val">{{ $user->pregnant ?: '—' }}</span></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="cc-group-title">
                            @include('componentes._icon', ['name' => 'shield', 'class' => 'cc-ico-14', 'label' => null])
                            {{ __('Detección preventiva') }}
                        </div>
                        @php
                            $gineco = [
                                __('Papanicolaou') => (int) ($user->prevent1 ?? 0) === 1,
                                __('Mastografía')  => (int) ($user->prevent2 ?? 0) === 1,
                            ];
                        @endphp
                        <div class="hm-chips">
                            @foreach($gineco as $lbl => $yes)
                                <span class="cc-chip {{ $yes ? 'cc-chip--danger' : 'cc-chip--muted' }}">{{ $lbl }} <span class="st">{{ $yes ? __('SÍ') : 'NO' }}</span></span>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif

@endforeach

    {{-- ===== Anexos médicos del expediente + su sello (2026-07-24 · PIEZA 3, corrida 2/2) =====

         EL EXPEDIENTE NO SE EDITA, NI SIQUIERA SU TITULAR. Lo de arriba es el estado VIGENTE
         (declarado + anexos aplicados); aquí abajo está la TRAZA de cómo llegó a serlo y el
         sello del documento original.

         Por qué la traza se imprime y no se esconde en un log: el efecto disuasorio es el punto.
         Quien intentara maquillar un dato dejaría un anexo firmado, con su nombre y su cédula,
         justo al lado del original que dice lo contrario. --}}
    @if(isset($expedienteModelo) && $expedienteModelo)
    <div class="cc-form-card">
        <button class="cc-collapse-head" type="button" data-bs-toggle="collapse" data-bs-target="#hm-anexos" aria-expanded="true" aria-controls="hm-anexos">
            <span class="cc-form-ico">
                @include('componentes._icon', ['name' => 'files', 'class' => 'cc-ico-20', 'label' => null])
            </span>
            <span class="cc-form-card__titles">
                <span class="cc-form-card__title d-block">{{ __('health.trace_title') }}</span>
                <span class="cc-form-card__sub d-block">{{ __('health.trace_sub') }}</span>
            </span>
            @include('componentes._icon', ['name' => 'chevron-down', 'class' => 'cc-collapse-caret cc-ico-18', 'label' => null])
        </button>
        <div class="collapse show" id="hm-anexos">
            <div class="cc-form-card__body border-top" style="border-color: var(--stroke, var(--border)) !important;">

                {{-- Anexar es un acto clínico: el botón sólo existe para el rol médico, y el
                     controlador lo vuelve a exigir (ocultar un botón no es una guarda). --}}
                @if(auth()->check() && auth()->user()->isMedic() && \App\Models\HealthRecordAddendum::supported())
                    <div class="hm-no-print mb-3">
                        <a href="{{ route('expediente.anexo.create', $datos->id ?? $expedienteModelo->id_user) }}" class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-2">
                            @include('componentes._icon', ['name' => 'file-plus', 'class' => 'cc-ico-16', 'label' => null])
                            {{ __('health.trace_add') }}
                        </a>
                        <span class="cc-help d-block mt-1">{{ __('health.trace_add_help') }}</span>
                    </div>
                @endif

                @forelse(($anexosExpediente ?? collect()) as $anexo)
                    <div class="cc-info-item d-block mb-3 pb-3" style="border-bottom:1px solid var(--stroke, var(--border));">
                        <div class="d-flex flex-wrap align-items-baseline gap-2">
                            <strong>{{ $anexo->folio() }}</strong>
                            <span class="cc-muted small">{{ \Carbon\Carbon::parse($anexo->created_at)->format('d/m/Y H:i') }}</span>
                            <span class="cc-chip cc-chip--muted"><span class="st">{{ $anexo->motivoLabel() }}</span></span>
                        </div>
                        <div class="mt-2">
                            @foreach($anexo->cambiosLegibles() as $cambio)
                                <div><span class="cc-info-lbl">{{ $cambio['campo'] }}</span>
                                     <span class="cc-info-val">{{ $cambio['valor'] }}</span></div>
                            @endforeach
                        </div>
                        <p class="mb-1 mt-2" style="font-style:italic">{{ $anexo->notes }}</p>
                        {{-- Quién anexó, con su cédula CONGELADA al momento. Si mañana pierde la
                             verificación, este anexo sigue diciendo la verdad de ayer. --}}
                        <div class="cc-muted small">
                            {{ __('health.trace_by') }}: {{ $anexo->medic_name ?: '—' }}
                            @if($anexo->medic_cedula) · {{ __('health.trace_cedula') }} {{ $anexo->medic_cedula }}
                                @if($anexo->medic_cedula_verified) ✓ @endif
                            @endif
                        </div>
                        @include('componentes._seal-cfdi', [
                            'doc' => $anexo, 'folio' => $anexo->folio(), 'prefix' => 'CREWCARE-EXPA',
                        ])
                    </div>
                @empty
                    <p class="cc-muted small mb-3">{{ __('health.trace_empty') }}</p>
                @endforelse

                {{-- Sello del expediente ORIGINAL. Se recomputa sobre el documento declarado, no
                     sobre el estado vigente: los anexos no lo alteran, lo acompañan. --}}
                <p class="hm-seal-head mt-3">{{ __('health.trace_seal_head', ['folio' => $expedienteModelo->folio()]) }}</p>
                @include('componentes._seal-cfdi', [
                    'doc' => $expedienteModelo, 'folio' => $expedienteModelo->folio(), 'prefix' => 'CREWCARE-EXP',
                ])
            </div>
        </div>
    </div>
    @endif

    {{-- ===== Consultas registradas ===== --}}
    <div class="cc-form-card">
        <button class="cc-collapse-head" type="button" data-bs-toggle="collapse" data-bs-target="#hm-consultas" aria-expanded="true" aria-controls="hm-consultas">
            <span class="cc-form-ico">
                @include('componentes._icon', ['name' => 'stethoscope', 'class' => 'cc-ico-20', 'label' => null])
            </span>
            <span class="cc-form-card__titles">
                <span class="cc-form-card__title d-block">{{ __('Consultas') }}</span>
                <span class="cc-form-card__sub d-block">{{ __('Atenciones médicas registradas en el expediente') }}</span>
            </span>
            @include('componentes._icon', ['name' => 'chevron-down', 'class' => 'cc-collapse-caret cc-ico-18', 'label' => null])
        </button>
        <div class="collapse show" id="hm-consultas">
            <div class="cc-form-card__body border-top" style="border-color: var(--stroke, var(--border)) !important;">
                <div class="table-responsive">
                    <table class="hm-med-table">
                        <thead>
                            <tr>
                                <th>{{ __('Consulta realizada el') }}</th>
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
                                        {{-- Constancia de que se atendió a ciegas (2026-07-24 · PASO 3/3).
                                             null = consulta anterior a la columna: no se sabe, no se afirma. --}}
                                        @if((int) ($consulta->without_record ?? 0) === 1)
                                            <span class="cc-chip cc-chip-warn d-inline-flex mt-1" title="{{ __('Se registró sin expediente: sin alergias ni antecedentes disponibles.') }}">
                                                @include('componentes._icon', ['name' => 'alert-triangle']) {{ __('Sin expediente') }}
                                            </span>
                                        @endif
                                    </td>
                                    {{-- Quién atendió + cédula (snapshot congelado; fallback en vivo) → componente compartido. --}}
                                    <td>@include('componentes._consult-attended-by', ['consulta' => $consulta, 'medicos' => $medicos])</td>
                                    <td>{{ $consulta->diagnosis }}</td>
                                    {{-- `?:` y no `??`: medication/observations son NOT NULL en la tabla,
                                         así que un campo vacío llega como '' (no null) y `??` lo dejaría
                                         en blanco en vez de decir «Ninguno».
                                         El MANEJO viaja en esta misma celda (2026-07-24 · PASO 3/3): es
                                         "qué se le hizo", la misma unidad de lectura que el medicamento,
                                         y evita una séptima columna en una tabla que ya se apila. --}}
                                    @php $mgmtLabels = $consulta->managementLabels(); @endphp
                                    <td>
                                        @if($consulta->medication !== '' && $consulta->medication !== null)
                                            {{ $consulta->medication }}
                                        @elseif(empty($mgmtLabels))
                                            {{ __('Ninguno') }}
                                        @endif
                                        @if(! empty($mgmtLabels))
                                            <span class="hm-mgmt d-block">{{ implode(' · ', array_map('__', $mgmtLabels)) }}</span>
                                        @endif
                                    </td>
                                    <td>{{ $consulta->observations ?: __('Ninguna') }}</td>
                                    <td>{{ $consulta->aditional ?: __('Ninguna') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="hm-empty">{{ __('No hay consultas registradas.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- ===== Sellos de integridad por consulta (2026-07-24, item 5) =====
         Cada consulta se sella al crearse (SHA-256 + cadena CFDI cotejable). El sello cubre el
         SNAPSHOT de cédula de quien atendió. Las consultas anteriores al sellado se muestran como
         "aún sin sellar" (honesto: nunca se sellaron), no como pendientes de firma autógrafa. --}}
    @if($consultas->count())
    <div class="cc-form-card">
        <button class="cc-collapse-head" type="button" data-bs-toggle="collapse" data-bs-target="#hm-sellos" aria-expanded="true" aria-controls="hm-sellos">
            <span class="cc-form-ico">
                @include('componentes._icon', ['name' => 'shield', 'class' => 'cc-ico-20', 'label' => null])
            </span>
            <span class="cc-form-card__titles">
                <span class="cc-form-card__title d-block">{{ __('Sellos de integridad') }}</span>
                <span class="cc-form-card__sub d-block">{{ __('Firma SHA-256 y cadena CFDI cotejable de cada consulta') }}</span>
            </span>
            @include('componentes._icon', ['name' => 'chevron-down', 'class' => 'cc-collapse-caret cc-ico-18', 'label' => null])
        </button>
        <div class="collapse show" id="hm-sellos">
            <div class="cc-form-card__body border-top" style="border-color: var(--stroke, var(--border)) !important;">
                <div class="hm-seals">
                    @foreach($consultas as $consulta)
                        <div class="hm-seal-item">
                            <p class="hm-seal-head">
                                {{ __('Consulta') }} MED-{{ str_pad((string) $consulta->id_cmedic, 4, '0', STR_PAD_LEFT) }}
                                · {{ \Carbon\Carbon::parse($consulta->created_at)->format('d/m/Y H:i') }}
                            </p>
                            @include('componentes._seal-cfdi', [
                                'doc'    => $consulta,
                                'folio'  => 'MED-'.str_pad((string) $consulta->id_cmedic, 4, '0', STR_PAD_LEFT),
                                'prefix' => 'CREWCARE-MED',
                            ])
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
    @endif

</div>
@endsection
