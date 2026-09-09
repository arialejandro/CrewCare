{{-- ORDEN DE TRANSPORTACIÓN — PDF congelado (dompdf · Capa 4 §1).
     Self-contained: CSS inline concreto (dompdf v2 no soporta CSS variables), DejaVu Sans (acentos
     ES/EN sin config). El pie con el UUID va en position:fixed → dompdf lo repite en CADA hoja; el
     @page reserva el margen inferior para que no encime. Las direcciones privadas ya vienen
     enmascaradas a 'CASA' en el snapshot público (sin excepción). NO se sella, NO hay verificador. --}}
@php
    $snap    = $snapshot;
    $runs    = $snap['runs'] ?? [];
    $byKey   = $diff['byKey'] ?? [];
    $dsum    = $diff['summary'] ?? ['has_prev' => false, 'counts' => ['nueva' => 0, 'modificada' => 0, 'baja' => 0], 'dropped' => []];
    $counts  = $dsum['counts'] ?? ['nueva' => 0, 'modificada' => 0, 'baja' => 0];
    $totalCh = ($counts['nueva'] ?? 0) + ($counts['modificada'] ?? 0) + ($counts['baja'] ?? 0);
    $prevV   = $dsum['prev_version'] ?? (max(1, (int) $order->version - 1));
    $legend  = $snap['legend'] ?? ['types' => [], 'equipment' => []];
    $longDate = \Carbon\Carbon::parse($order->order_date)->locale(app()->getLocale())->isoFormat('dddd, D MMMM YYYY');
    // Doble señal (color + forma ▸): sobrevive impresión B/N. $chg(runKey, campo) → clase o ''.
    $chg = function ($key, $field) use ($byKey) {
        $d = $byKey[$key] ?? null;
        return ($d && in_array($field, $d['changed'] ?? [], true)) ? 'chg' : '';
    };
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}"><head><meta charset="utf-8">
<style>
    @page { margin: 78px 46px 70px 46px; }
    * { box-sizing: border-box; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 8pt; color: #111; margin: 0; line-height: 1.28; }

    .hd { width: 100%; border-collapse: collapse; margin-bottom: 4pt; }
    .hd td { vertical-align: top; padding: 0; }
    .hd .l { font-size: 14pt; font-weight: bold; letter-spacing: -.01em; }
    .hd .l .sub { font-size: 7.5pt; font-weight: normal; color: #444; letter-spacing: 0; }
    .hd .r { text-align: right; font-size: 7.5pt; color: #333; }
    .hd .r .ver { font-size: 11pt; font-weight: bold; color: #000; }
    .hd .r .frozen { display: inline-block; margin-top: 2pt; border: 0.6pt solid #6b7280; border-radius: 3pt;
        padding: 0.5pt 4pt; font-size: 6.6pt; text-transform: uppercase; letter-spacing: .04em; color: #374151; }
    .hrule { border: 0; border-top: 1.4pt solid #111; margin: 3pt 0 5pt; }

    .band { border: 0.7pt solid #1d4ed8; background: #eff4ff; color: #1e3a8a; padding: 2.4pt 6pt;
        font-size: 7pt; margin-bottom: 6pt; border-radius: 3pt; }
    .band b { text-transform: uppercase; letter-spacing: .02em; }
    .pill { display: inline-block; border-radius: 3pt; padding: 0.4pt 4pt; font-size: 6.4pt; font-weight: bold;
        text-transform: uppercase; letter-spacing: .03em; }
    .pill-new { background: #dcfce7; color: #166534; border: 0.5pt solid #16a34a; }

    .sec { font-size: 7pt; text-transform: uppercase; letter-spacing: .05em; color: #6b7280; font-weight: bold;
        margin: 8pt 0 3pt; border-bottom: 0.5pt solid #d1d5db; padding-bottom: 1.5pt; }

    /* Encabezado por PUESTO (figuras del día). */
    .roster { width: 100%; border-collapse: collapse; }
    .roster td { vertical-align: top; width: 33.33%; padding: 0 5pt 4pt 0; }
    .roster .grp { font-size: 6.8pt; font-weight: bold; text-transform: uppercase; color: #374151; }
    .roster .ppl { font-size: 6.8pt; color: #4b5563; }

    /* Corridas. */
    .run { border: 0.6pt solid #cbd1d9; border-radius: 4pt; padding: 5pt 7pt; margin-bottom: 5pt; }
    .run.new { border-color: #16a34a; }
    .run .top { margin-bottom: 2.5pt; }
    .run .type { display: inline-block; border: 0.5pt solid #9ca3af; background: #f3f4f6; border-radius: 3pt;
        padding: 0.4pt 4pt; font-size: 6.8pt; font-weight: bold; }
    .run .veh { font-weight: bold; margin-left: 4pt; }
    .run .plate { font-family: DejaVu Sans Mono, monospace; color: #6b7280; font-size: 7pt; }
    .grid { width: 100%; border-collapse: collapse; margin-top: 1pt; }
    .grid td { vertical-align: top; width: 25%; padding: 1pt 4pt 1pt 0; font-size: 7.4pt; }
    .k { color: #6b7280; text-transform: uppercase; font-size: 6.2pt; letter-spacing: .02em; }
    .occ { margin-top: 2.5pt; padding-top: 2.5pt; border-top: 0.5pt solid #e5e7eb; }
    .occ .k { display: block; margin-bottom: 1pt; }
    .occ ul { margin: 0; padding-left: 12pt; }
    .occ li { font-size: 7.2pt; }
    .notes { margin-top: 2pt; font-size: 7.4pt; }

    /* Doble señal del diff (igual que en pantalla). */
    .chg { color: #c2410c; font-weight: bold; }
    .chg-pre::before { content: "\25B8\00a0"; color: #c2410c; font-weight: bold; }

    .drop { border: 0.6pt solid #ef4444; background: #fef2f2; border-radius: 4pt; padding: 4pt 7pt; margin-bottom: 5pt; }
    .drop .k { color: #b91c1c; }
    .drop ul { margin: 1pt 0 0; padding-left: 12pt; }
    .drop li { font-size: 7.2pt; }

    .notesgen { border: 0.6pt solid #d1d5db; border-radius: 4pt; padding: 4pt 7pt; font-size: 7.6pt; white-space: pre-line; }
    .leg { font-size: 6.8pt; color: #4b5563; margin-top: 6pt; }
    .leg b { color: #374151; text-transform: uppercase; }
    .leg .it { display: inline-block; margin-right: 8pt; }

    /* Pie con UUID — position:fixed → dompdf lo estampa al fondo de TODAS las páginas. */
    .print-foot { position: fixed; left: 0; right: 0; bottom: -52px; height: 40px;
        border-top: 0.5pt solid #e5e7eb; padding-top: 4pt; font-size: 6.6pt; color: #6b7280; }
    .print-foot .u { font-family: DejaVu Sans Mono, monospace; }
    .print-foot .rt { float: right; }
    .empty { color: #9ca3af; }
</style></head>
<body>

<div class="print-foot">
    <span class="rt">{{ optional($production)->name ?? '' }}</span>
    {{ __('Control') }}: <span class="u">{{ $order->uuid }}</span>
    · {{ __('Documento operativo interno — no es un sello ni una firma.') }}
</div>

<table class="hd"><tr>
    <td class="l">
        {{ __('Orden de transportación') }}<br>
        <span class="sub">{{ optional($production)->name ?? '' }} · {{ $longDate }}</span>
    </td>
    <td class="r">
        <div class="ver">v{{ $order->version }}</div>
        <div>{{ \Carbon\Carbon::parse($order->order_date)->format('d/m/Y') }}</div>
        <div class="frozen">{{ __('Congelada') }}@if($order->frozen_at) · {{ $order->frozen_at->format('d/m/Y H:i') }}@endif</div>
    </td>
</tr></table>
<hr class="hrule">

@if (($dsum['has_prev'] ?? false) && $totalCh > 0)
    <div class="band">
        <b>{{ __('Cambios vs. v') }}{{ $prevV }}:</b>
        @if($counts['nueva']) {{ $counts['nueva'] }} {{ __('nueva(s)') }}@endif
        @if($counts['modificada']) @if($counts['nueva']) · @endif{{ $counts['modificada'] }} {{ __('modificada(s)') }}@endif
        @if($counts['baja']) @if($counts['nueva'] || $counts['modificada']) · @endif{{ $counts['baja'] }} {{ __('baja(s)') }}@endif
    </div>
@endif

{{-- Figuras por puesto (encabezado). --}}
@if (! empty($roster['groups']))
    <div class="sec">{{ __('Crew del día por puesto') }}</div>
    <table class="roster"><tr>
        @foreach ($roster['groups'] as $i => $g)
            <td>
                <span class="grp">{{ $g['label'] }}</span><br>
                <span class="ppl">{{ collect($g['people'])->map(fn ($p) => $p['name'] . ($p['cargo'] ? ' (' . $p['cargo'] . ')' : ''))->implode(', ') }}</span>
            </td>
            @if (($i % 3) === 2)</tr><tr>@endif
        @endforeach
    </tr></table>
@endif

{{-- Corridas. --}}
<div class="sec">{{ __('Corridas') }}</div>
@forelse ($runs as $r)
    @php
        $key    = $r['run_key'] ?? '';
        $d      = $byKey[$key] ?? ['status' => 'sin_cambio', 'changed' => []];
        $isNew  = ($d['status'] ?? '') === 'nueva';
    @endphp
    <div class="run {{ $isNew ? 'new' : '' }}">
        <div class="top">
            <span class="type {{ $chg($key, 'type_label') }}">{{ $r['type_label'] ?? '' }}</span>
            <span class="veh {{ $chg($key, 'vehicle_label') }}">
                @if (! empty($r['vehicle_label'])){{ $r['vehicle_label'] }}@elseif (($r['run_type'] ?? '') === 'aplicacion')<span class="empty">{{ __('Sin vehículo (aplicación)') }}</span>@else<span class="empty">—</span>@endif
            </span>
            @if ($isNew) <span class="pill pill-new">{{ __('NUEVA') }}</span>@endif
        </div>
        @if (! empty($r['is_evento']))
            {{-- Evento de vehículo: ventana de horario + descripción, sin ocupantes. --}}
            <table class="grid"><tr>
                <td><span class="k">{{ __('Conductor') }}</span><br><span class="{{ $chg($key, 'driver_label') }}">{{ $r['driver_label'] ?: '—' }}</span></td>
                <td><span class="k">{{ __('Horario') }}</span><br><span class="{{ $chg($key, 'pickup') }} {{ $chg($key, 'pickup') ? 'chg-pre' : '' }}">{{ $r['pickup'] ?: '—' }}</span></td>
                <td colspan="2"><span class="k">{{ __('Descripción') }}</span><br><span class="{{ $chg($key, 'dest') }} {{ $chg($key, 'dest') ? 'chg-pre' : '' }}">{{ $r['dest'] ?: '—' }}</span></td>
            </tr></table>
        @else
            <table class="grid"><tr>
                <td><span class="k">{{ __('Conductor') }}</span><br><span class="{{ $chg($key, 'driver_label') }}">{{ $r['driver_label'] ?: '—' }}</span></td>
                <td><span class="k">{{ __('Pick up') }}</span><br><span class="{{ $chg($key, 'pickup') }} {{ $chg($key, 'pickup') ? 'chg-pre' : '' }}">{{ $r['pickup'] ?: '—' }}</span></td>
                <td><span class="k">{{ __('Destino') }}</span><br><span class="{{ $chg($key, 'dest') }} {{ $chg($key, 'dest') ? 'chg-pre' : '' }}">{{ $r['dest'] ?: '—' }}</span></td>
                <td><span class="k">{{ __('Equipo') }}</span><br><span class="{{ $chg($key, 'equipment_label') }}">{{ $r['equipment_label'] ?: '—' }}</span></td>
            </tr></table>
        @endif
        @if (! empty($r['notes']))<div class="notes {{ $chg($key, 'notes') }}">{{ $r['notes'] }}</div>@endif
        @if (! empty($r['occupants']))
            <div class="occ">
                <span class="k {{ $chg($key, 'occupants_label') }}">{{ __('Ocupantes') }}</span>
                <ul>@foreach ($r['occupants'] as $o)<li>{{ $o }}</li>@endforeach</ul>
            </div>
        @endif
    </div>
@empty
    <p class="empty">{{ __('Sin corridas.') }}</p>
@endforelse

{{-- Bajas contra la versión anterior. --}}
@if (! empty($dsum['dropped']))
    <div class="drop">
        <span class="k">{{ __('Bajas vs. v') }}{{ $prevV }}</span>
        <ul>
            @foreach ($dsum['dropped'] as $dr)
                <li>{{ $dr['type_label'] ?? '' }} · {{ $dr['vehicle_label'] ?: __('sin vehículo') }}@if(!empty($dr['pickup'])) · {{ $dr['pickup'] }}@endif</li>
            @endforeach
        </ul>
    </div>
@endif

{{-- Notas generales (§1: hitos sin vehículo). --}}
@if (trim((string) ($snap['notes_general'] ?? '')) !== '')
    <div class="sec">{{ __('Notas generales') }}</div>
    <div class="notesgen">{{ $snap['notes_general'] }}</div>
@endif

{{-- Leyenda (solo claves usadas ese día). --}}
@php $lgEq = $legend['equipment'] ?? []; $lgTy = $legend['types'] ?? []; @endphp
@if (! empty($lgEq) || ! empty($lgTy['aeropuerto']))
    <div class="leg">
        <b>{{ __('Leyenda') }}:</b>
        @foreach ($lgEq as $code => $nm)<span class="it">{{ $nm }}</span>@endforeach
        @if (! empty($lgTy['aeropuerto']))<span class="it">{{ __('Aeropuerto') }}</span>@endif
    </div>
@endif

</body></html>
