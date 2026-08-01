{{-- COPIA DEL EXPEDIENTE CLÍNICO PARA EL TITULAR (dompdf, adjunto del correo de alta).
     (2026-07-24 · PIEZA 3, corrida 2/2)

     Se le manda a la persona para que REVISE que lo registrado a su nombre es real. Es el único
     momento barato para detectar un error de captura: después el expediente está sellado y sólo
     un médico puede corregirlo por anexo, previa valoración.

     dompdf: CSS inline y sencillo, sin flex/grid, sin fuentes externas ni imágenes remotas. El
     QR y el identicon no se incrustan aquí a propósito — este PDF es la copia personal del
     titular, no el documento de cotejo; el sello verificable vive en la ficha de la app. --}}
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('health.title') }} · {{ $expediente->folio() }}</title>
    <style>
        @page { margin: 90px 45px 60px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1f2937; line-height: 1.45; }
        h1 { font-size: 16px; margin: 0 0 2px; }
        h2 { font-size: 11px; margin: 18px 0 6px; padding-bottom: 3px; border-bottom: 1px solid #d1d5db;
             text-transform: uppercase; letter-spacing: .04em; color: #374151; }
        .sub { color: #6b7280; font-size: 9px; margin: 0 0 14px; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 3px 6px 3px 0; vertical-align: top; }
        td.k { width: 34%; color: #6b7280; }
        td.v { color: #111827; }
        .chips span { display: inline-block; border: 1px solid #d1d5db; border-radius: 8px;
                      padding: 2px 7px; margin: 0 4px 4px 0; font-size: 9px; }
        .none { color: #9ca3af; font-style: italic; }
        .revisa { border: 1px solid #fcd34d; background: #fffbeb; padding: 9px 11px; border-radius: 6px;
                  font-size: 9.5px; margin-bottom: 14px; }
        .pie { margin-top: 22px; padding-top: 8px; border-top: 1px solid #e5e7eb; color: #6b7280; font-size: 8.5px; }
    </style>
</head>
<body>

@php
    // Lector defensivo: este PDF se arma dentro del guardado, así que no puede tronar por un
    // campo ausente (una instancia sin el SQL nuevo, por ejemplo).
    $g = function ($campo, $vacio = '—') use ($valores) {
        $x = isset($valores->$campo) ? $valores->$campo : null;
        return ($x === null || $x === '') ? $vacio : $x;
    };
    $si = function ($campo) use ($valores) { return ! empty($valores->$campo); };
    $imc = $expediente->imc();
@endphp

<h1>{{ __('health.title') }}</h1>
<p class="sub">
    {{ trim($titular->name . ' ' . $titular->lname . ' ' . $titular->lname2) }}
    · {{ $expediente->folio() }}
    · {{ __('health.pdf_declared_on', ['fecha' => $expediente->fechaLlenado('d/m/Y')]) }}
</p>

<div class="revisa">{{ __('health.pdf_review_notice') }}</div>

<h2>{{ __('health.s_general') }}</h2>
<table>
    <tr><td class="k">{{ __('health.f_blood') }}</td><td class="v">{{ $g('blod_type') }}</td></tr>
    <tr><td class="k">{{ __('health.f_weight') }} {{ __('health.f_weight_unit') }}</td><td class="v">{{ $g('height') }}</td></tr>
    <tr><td class="k">{{ __('health.f_size') }} {{ __('health.f_size_unit') }}</td><td class="v">{{ $g('size') }}</td></tr>
    <tr><td class="k">{{ __('health.pdf_bmi') }}</td><td class="v">{{ $imc === null ? __('health.pdf_bmi_na') : $imc }}</td></tr>
    <tr><td class="k">{{ __('health.f_contact') }}</td><td class="v">{{ $g('c_emer') }}</td></tr>
    <tr><td class="k">{{ __('health.f_relation') }}</td><td class="v">{{ $g('relation') }}</td></tr>
    <tr><td class="k">{{ __('health.f_phone') }}</td><td class="v">{{ $g('p_emer') }}</td></tr>
</table>

<h2>{{ __('health.s_pathological') }}</h2>
<table>
    <tr><td class="k">{{ __('health.f_hospitalizations') }}</td><td class="v">{{ $g('hospitals', '0') }}</td></tr>
    @for($n = 1; $n <= 10; $n++)
        @if(! empty($valores->{'hsp'.$n}))
            <tr><td class="k">{{ __('health.f_hospitalization_n', ['n' => $n]) }}</td><td class="v">{{ $valores->{'hsp'.$n} }}</td></tr>
        @endif
    @endfor
    <tr><td class="k">{{ __('health.f_surgery') }}</td><td class="v">{{ $g('cirugy') }}</td></tr>
    <tr><td class="k">{{ __('health.f_pathology') }}</td><td class="v">{{ $g('pathology') }}</td></tr>
    <tr><td class="k">{{ __('health.f_allergy') }}</td><td class="v">{{ $g('alergy') }}</td></tr>
    <tr><td class="k">{{ __('health.f_trauma') }}</td><td class="v">{{ $g('trauma') }}</td></tr>
</table>

<h2>{{ __('health.s_family') }}</h2>
@php
    $padecimientos = [3 => 'health.c_diabetes', 4 => 'health.c_hypertension', 5 => 'health.c_heart',
                      6 => 'health.c_kidney', 7 => 'health.c_cancer'];
@endphp
<table>
    @foreach(['momdat' => 'health.s_mother', 'daddat' => 'health.s_father'] as $prefijo => $titulo)
        @php
            $marcas = [];
            if ($si($prefijo . '1')) { $marcas[] = __('health.c_alive'); }
            if ($si($prefijo . '2')) { $marcas[] = __('health.c_dead'); }
            foreach ($padecimientos as $i => $clave) {
                if ($si($prefijo . $i)) { $marcas[] = __($clave); }
            }
        @endphp
        <tr>
            <td class="k">{{ __($titulo) }}</td>
            <td class="v">{!! $marcas ? e(implode(' · ', $marcas)) : '<span class="none">' . e(__('health.pdf_nothing')) . '</span>' !!}</td>
        </tr>
    @endforeach
</table>

<h2>{{ __('health.s_habits') }}</h2>
@php
    $habitos = [];
    foreach (['pers_nopat1' => 'health.c_tobacco', 'pers_nopat2' => 'health.c_alcohol', 'pers_nopat3' => 'health.c_drugs'] as $c => $k) {
        if ($si($c)) { $habitos[] = __($k); }
    }
@endphp
<p>{!! $habitos ? e(implode(' · ', $habitos)) : '<span class="none">' . e(__('health.pdf_nothing')) . '</span>' !!}</p>

<h2>{{ __('health.s_vaccines') }}</h2>
@php
    $vacunas = [];
    foreach (['vacci1' => 'health.c_covid', 'vacci2' => 'health.c_flu', 'vacci3' => 'health.c_tetanus',
              'vacci4' => 'health.c_pneumococcus', 'vacci5' => 'health.c_hepatitis'] as $c => $k) {
        if ($si($c)) {
            $etiqueta = __($k);
            if ($c === 'vacci2' && ! empty($valores->vacci2_date)) {
                $etiqueta .= ' (' . \Carbon\Carbon::parse($valores->vacci2_date)->format('d/m/Y') . ')';
            }
            $vacunas[] = $etiqueta;
        }
    }
@endphp
<div class="chips">
    @forelse($vacunas as $vac)<span>{{ $vac }}</span>@empty<span class="none">{{ __('health.pdf_nothing') }}</span>@endforelse
</div>

@if(($titular->sex ?? '') === 'F')
    <h2>{{ __('health.s_gyneco') }}</h2>
    <table>
        <tr><td class="k">{{ __('health.f_rythm') }}</td><td class="v">{{ $g('rythm') }}</td></tr>
        <tr><td class="k">{{ __('health.f_pregnant') }}</td><td class="v">{{ $g('pregnant') }}</td></tr>
        <tr><td class="k">{{ __('health.s_prevention') }}</td><td class="v">
            @php
                $prev = [];
                if ($si('prevent1')) { $prev[] = __('health.c_pap'); }
                if ($si('prevent2')) { $prev[] = __('health.c_mammography'); }
            @endphp
            {!! $prev ? e(implode(' · ', $prev)) : '<span class="none">' . e(__('health.pdf_nothing')) . '</span>' !!}
        </td></tr>
    </table>
@endif

@if($anexos->count())
    {{-- Si la copia se emite después de un anexo, la traza va incluida: el titular tiene que
         poder ver qué se actualizó y quién lo hizo, no sólo el resultado. --}}
    <h2>{{ __('health.pdf_addenda') }}</h2>
    @foreach($anexos as $anexo)
        <p style="margin:0 0 8px">
            <strong>{{ $anexo->folio() }}</strong> ·
            {{ \Carbon\Carbon::parse($anexo->created_at)->format('d/m/Y') }} ·
            {{ $anexo->motivoLabel() }}<br>
            @foreach($anexo->cambiosLegibles() as $c)
                {{ $c['campo'] }}: {{ $c['valor'] }}@if(! $loop->last) · @endif
            @endforeach
            <br><em>{{ $anexo->notes }}</em>
        </p>
    @endforeach
@endif

<div class="pie">
    {{ __('health.pdf_footer', ['folio' => $expediente->folio()]) }}<br>
    UUID: {{ $expediente->uuid ?: '—' }}
</div>

</body>
</html>
