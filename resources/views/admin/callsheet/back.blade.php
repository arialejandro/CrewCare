@php
    $en = $lang === 'en';
    $L = [
        'day'      => $en ? 'Day' : 'Día',
        'of'       => $en ? 'of'  : 'de',
        'sheet'    => $en ? 'Call Sheet' : 'Hoja de Llamado',
        'general'  => $en ? 'General call' : 'Llamado general',
        'wrap'     => $en ? 'Est. wrap' : 'Wrap estimado',
        'catering' => $en ? 'Catering' : 'Alimentación',
        'service'  => $en ? 'Service' : 'Servicio',
        'time'     => $en ? 'Time' : 'Hora',
        'place'    => $en ? 'Place' : 'Lugar',
        'ready'    => $en ? 'Ready' : 'Listo',
        'crew'     => 'Crew', 'cast' => 'Cast', 'bg' => 'BG', 'total' => 'Total',
        'radio'    => $en ? 'Radio channels' : 'Canales de radio',
        'legend'   => $en ? 'Key' : 'Nomenclatura',
        'notes'    => $en ? 'General notes' : 'Notas generales',
        'hotels'   => $en ? 'Housing' : 'Hoteles',
        'emerg'    => $en ? 'Emergency / Hospital' : 'Emergencia / Hospital',
        'nc'       => 'N/C',
        'approved' => $en ? 'Approved by' : 'Aprobado por',
    ];
    $dayHead = $dayNumber !== null && $dayNumber > 0
        ? ($L['day'] . ' ' . $dayNumber . ($plannedM ? ' ' . $L['of'] . ' ' . $plannedM : ''))
        : $dayLabel;
    $longDate = \Carbon\Carbon::parse($day)->locale($en ? 'en' : 'es')->isoFormat('dddd, D MMMM YYYY');
    $colspan  = count($tableCols);
    $logi     = ['hotel', 'pickup', 'place', 'call', 'out'];   // columnas con tinte crema
    $bandBg   = ($preset['bands'] ?? 'gray') === 'black' ? '#111' : '#c2c2c2';
    $bandFg   = ($preset['bands'] ?? 'gray') === 'black' ? '#fff' : '#000';
    $statusLegend = $en
        ? 'O/C=Own Call · D/C=Dept Call · N/C=No Call · N/A=N/A · SD=Self Drive · W/N=Will Notify'
        : 'O/C=Llamado propio · D/C=Llamado de depto · N/C=Sin llamado · N/A=No aplica · SD=Maneja · W/N=Se avisa';
    $mealTotal = (int) $mealCrew + (int) ($castCount ?? 0) + (int) ($bgCount ?? 0);
    $mealsOn   = collect($meals)->where('enabled', true)->values();
@endphp
<!DOCTYPE html>
<html><head><meta charset="utf-8">
<style>
    /* Formato universal tipo CASPER / Silver-Olson-Williams 201 (columnas/comidas/notas = preset). */
    @page { margin: 8mm 7mm; }
    * { box-sizing: border-box; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 5.3pt; color: #111; margin: 0; line-height: 1.0; }

    .hdtbl { width: 100%; border-collapse: collapse; margin-bottom: 2.5pt; }
    .hdtbl td { vertical-align: middle; padding: 0 2pt; }
    .hd-l { width: 34%; font-size: 12.5pt; font-weight: bold; letter-spacing: -.01em; }
    .hd-c { width: 32%; text-align: center; font-size: 6.6pt; color: #222; }
    .hd-c .hd-sheet { font-size: 8.5pt; font-weight: bold; letter-spacing: .04em; text-transform: uppercase; }
    .hd-r { width: 34%; text-align: right; font-size: 6.6pt; color: #222; }
    .hd-r .hd-date { font-size: 8pt; font-weight: bold; }
    .hd-r .hd-day  { font-size: 9.5pt; font-weight: bold; }
    .hd-c b, .hd-r b { color: #000; }
    .hrule { border: 0; border-top: 1.4pt solid #111; margin: 0 0 2.5pt; }
    .safebar { border: 0.7pt solid #7a1f1f; background: #fbeeee; color: #b3261e; text-align: center;
        font-weight: bold; font-size: 6pt; padding: 1.6pt 4pt; margin-bottom: 3pt; text-transform: uppercase; letter-spacing: .02em; }

    .cols { width: 100%; border-collapse: collapse; table-layout: fixed; }
    .cols > tbody > tr > td { vertical-align: top; width: 33.33%; padding: 0 2pt; }
    table.crew { width: 100%; border-collapse: collapse; table-layout: fixed; }
    /* Altura de renglón CONSISTENTE: las filas (incl. las vacías reservadas de Crew Adicional) miden
       lo mismo que una con texto, como las plantillas CASPER (celdas completas, no mini-celdas). */
    table.crew td { border: 0.4pt solid #9a9a9a; padding: 0.9pt 2pt; height: 7pt; vertical-align: middle; white-space: nowrap; overflow: hidden; }
    .crew .th td { background: #dadada; font-weight: bold; font-size: 5.2pt; text-transform: uppercase; color: #222; border-color: #7d7d7d; height: auto; padding: 1pt 2pt; }
    .crew .band td { font-weight: bold; font-size: 5.6pt; padding: 1.3pt 3pt; height: auto; text-transform: uppercase; letter-spacing: .03em; text-align: center; border-color: #7d7d7d; }
    .band-nc { font-weight: normal; font-style: italic; }
    .tint { background: #fbfbe6; }
    .nc { color: #8a8a8a; font-style: italic; font-weight: normal; }
    /* Horario cambiado tras aprobar → AZUL (como los llamados reales resaltan los cambios). */
    .crew .row td.chg { color: #1d4ed8; font-weight: bold; }

    /* Alimentación: va DENTRO de la última columna (como los llamados reales), no en un bloque suelto. */
    .mealhead { background: #c2c2c2; color: #000; font-weight: bold; font-size: 5.6pt; text-transform: uppercase;
        letter-spacing: .03em; text-align: center; border: 0.4pt solid #7d7d7d; padding: 1.3pt 3pt; margin-top: 3pt; }
    .mealtbl { width: 100%; border-collapse: collapse; table-layout: fixed; }
    .mealtbl td { border: 0.4pt solid #8f8f8f; padding: 1pt 2pt; font-size: 5.2pt; text-align: center; white-space: nowrap; overflow: hidden; }
    .mealtbl td.ml-svc { text-align: left; font-weight: bold; }
    .mealtbl .th td { background: #e6e6e6; font-weight: bold; text-transform: uppercase; }
    .mealtot { font-size: 5.4pt; font-weight: bold; text-align: center; border: 0.4pt solid #8f8f8f; border-top: 0; padding: 1.2pt 2pt; }

    /* Pie: fluye bajo la rejilla (rectangular tras padColumns). Sin position:fixed (rompía en carta). */
    .foot { margin-top: 6pt; }
    .foot__row { font-size: 6pt; margin-top: 2.5pt; color: #222; }
    .foot__row b { text-transform: uppercase; font-size: 5.8pt; color: #000; }
    .radiogrid { border-collapse: collapse; margin-top: 2pt; }
    .radiogrid td { border: 0.4pt solid #b5b5b5; padding: 1pt 4pt; font-size: 5.6pt; }
    .radiogrid td b { font-weight: bold; }
    /* Firma: 3 roles de aprobación (Productor en Línea · Gerente de Producción · 1er AD) con nombre. */
    .signtbl { width: 100%; border-collapse: collapse; margin-top: 10pt; }
    .signtbl td { width: 33.33%; text-align: center; padding: 0 8pt; vertical-align: bottom; }
    .sign-space { height: 16pt; }
    .sign-line { border-top: 0.7pt solid #111; padding-top: 2pt; }
    .sign-name { font-size: 6.4pt; font-weight: bold; min-height: 7pt; }
    .sign-role { font-size: 5.6pt; color: #444; text-transform: uppercase; letter-spacing: .02em; }
</style>
</head>
<body>

<table class="hdtbl"><tbody><tr>
    <td class="hd-l">{{ $production->name ?? 'Llamado' }}</td>
    <td class="hd-c">
        <span class="hd-sheet">{{ $L['sheet'] }}</span><br>
        <b>{{ $L['general'] }}:</b> {{ $general ?? '—' }}@if($wrap) &nbsp;·&nbsp; <b>{{ $L['wrap'] }}:</b> {{ $wrap }}@endif
    </td>
    <td class="hd-r">
        <span class="hd-date">{{ ucfirst($longDate) }}</span><br>
        <span class="hd-day">{{ $dayHead }}</span>
    </td>
</tr></tbody></table>
<hr class="hrule">

@if(! empty($safetyBar))
    <div class="safebar">{{ $safetyBar }}</div>
@endif

<table class="cols"><tbody><tr>
    @foreach($columns as $column)
        <td>
            <table class="crew">
                <tr class="th">
                    @foreach($tableCols as $c)
                        <td style="width:{{ $c['w'] }}%; text-align:{{ $c['align'] }}">{{ $c['label'] }}</td>
                    @endforeach
                </tr>
                @foreach($column as $blk)
                    @if(! empty($blk['filler']))
                        {{-- Renglones reservados (rellenables) para igualar la altura de las columnas. --}}
                        @foreach($blk['rows'] as $r)
                            <tr class="row">
                                @foreach($tableCols as $c)
                                    <td class="{{ in_array($c['key'], $logi) ? 'tint' : '' }}" style="text-align:{{ $c['align'] }}">&nbsp;</td>
                                @endforeach
                            </tr>
                        @endforeach
                    @elseif(count($blk['rows']))
                        <tr class="band"><td colspan="{{ $colspan }}" style="background:{{ $bandBg }}; color:{{ $bandFg }}">{{ $blk['label'] }}</td></tr>
                        @foreach($blk['rows'] as $r)
                            <tr class="row">
                                @foreach($tableCols as $c)
                                    @php
                                        $val  = $r[$c['key']] ?? '';
                                        $isNc = $c['key'] === 'call' && ($val === 'N/C' || $val === $L['nc']);
                                        $isChg = $c['key'] === 'call' && ! empty($r['uid']) && isset($changed[$r['uid']]);
                                        $cls  = trim((in_array($c['key'], $logi) ? 'tint ' : '') . ($isNc ? 'nc' : '') . ($isChg ? ' chg' : ''));
                                    @endphp
                                    <td class="{{ $cls }}" style="text-align:{{ $c['align'] }}{{ $c['bold'] && ! $isNc ? '; font-weight:bold' : '' }}">{!! $val === '' ? '&nbsp;' : e($val) !!}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    @else
                        {{-- Depto sin llamado: una sola fila (banda + N/C), para no gastar renglones. --}}
                        <tr class="band"><td colspan="{{ $colspan }}" style="background:{{ $bandBg }}; color:{{ $bandFg }}">{{ $blk['label'] }} <span class="band-nc">— N/C</span></td></tr>
                    @endif
                @endforeach
            </table>

            @if($loop->last)
                {{-- ===== Alimentación DENTRO de la última columna (estilo del preset) ===== --}}
                <div class="mealhead">{{ $L['catering'] }}</div>
                @switch($preset['meals'] ?? 'matrix')
                    @case('matrix')
                        <table class="mealtbl">
                            <tr class="th">
                                <td class="ml-svc" style="width:27%">{{ $L['service'] }}</td><td style="width:13%">{{ $L['place'] }}</td><td style="width:16%">{{ $L['time'] }}</td>
                                <td style="width:11%">{{ $L['crew'] }}</td><td style="width:11%">{{ $L['cast'] }}</td><td style="width:10%">{{ $L['bg'] }}</td><td style="width:12%">{{ $L['total'] }}</td>
                            </tr>
                            @forelse($mealsOn as $m)
                                @php $ca=(int)($m['cast']??($castCount??0)); $bgn=(int)($m['bg']??($bgCount??0)); $mc=(int)$mealCrew; @endphp
                                <tr>
                                    <td class="ml-svc">{{ $m['label'] }}</td><td>{{ $m['place'] ?? '' }}</td><td>{{ $m['time'] ?? '—' }}</td>
                                    <td>{{ $mc }}</td><td>{{ $ca }}</td><td>{{ $bgn }}</td><td>{{ $mc+$ca+$bgn }}</td>
                                </tr>
                            @empty
                                <tr><td class="ml-svc" colspan="7">&nbsp;</td></tr>
                            @endforelse
                        </table>
                        @break

                    @case('ready')
                        <table class="mealtbl">
                            <tr class="th"><td class="ml-svc" style="width:50%">{{ $L['service'] }}</td><td style="width:24%">{{ $L['place'] }}</td><td style="width:26%">{{ $L['ready'] }}</td></tr>
                            @forelse($mealsOn as $m)
                                <tr><td class="ml-svc">{{ $m['label'] }}</td><td>{{ $m['place'] ?? '' }}</td><td>{{ $m['time'] ?? '—' }}</td></tr>
                            @empty
                                <tr><td class="ml-svc" colspan="3">&nbsp;</td></tr>
                            @endforelse
                        </table>
                        <div class="mealtot">{{ $L['crew'] }} {{ (int) $mealCrew }} · {{ $L['cast'] }} {{ (int) ($castCount ?? 0) }} · {{ $L['bg'] }} {{ (int) ($bgCount ?? 0) }} · {{ $L['total'] }} {{ $mealTotal }}</div>
                        @break

                    @case('lunch')
                        <table class="mealtbl">
                            <tr class="th"><td>{{ $L['crew'] }}</td><td>{{ $L['cast'] }}</td><td>{{ $L['bg'] }}</td><td>{{ $L['total'] }}</td></tr>
                            <tr><td>{{ (int) $mealCrew }}</td><td>{{ (int) ($castCount ?? 0) }}</td><td>{{ (int) ($bgCount ?? 0) }}</td><td>{{ $mealTotal }}</td></tr>
                        </table>
                        @break

                    @case('list')
                        <table class="mealtbl">
                            <tr class="th"><td class="ml-svc" style="width:52%">{{ $L['service'] }}</td><td style="width:18%">#</td><td style="width:30%">{{ $L['time'] }}</td></tr>
                            @forelse($mealsOn as $m)
                                <tr><td class="ml-svc">{{ $m['label'] }}</td><td>{{ (int) $mealCrew }}</td><td>{{ $m['time'] ?? '—' }}</td></tr>
                            @empty
                                <tr><td class="ml-svc" colspan="3">&nbsp;</td></tr>
                            @endforelse
                        </table>
                        @break
                @endswitch
            @endif
        </td>
    @endforeach
</tr></tbody></table>

<div class="foot">
    {{-- ===== Bloques de notas según el preset (a lo ancho, bajo la rejilla) ===== --}}
    @foreach($preset['notes'] ?? [] as $blockKey)
        @switch($blockKey)
            @case('general')
                @if(! empty($globalNotes))
                    <div class="foot__row"><b>{{ $L['notes'] }}:</b> {!! nl2br(e($globalNotes)) !!}</div>
                @endif
                @break

            @case('nomenclature')
                @if(count($usedCodes))
                    <div class="foot__row"><b>{{ $L['legend'] }}:</b>
                        @foreach($usedCodes as $code => $name){{ $code }} = {{ $name }}@if(! $loop->last) &nbsp;·&nbsp; @endif @endforeach
                    </div>
                @endif
                <div class="foot__row">{{ $statusLegend }}</div>
                @break

            @case('radio')
                @if(count($radios))
                    <div class="foot__row"><b>{{ $L['radio'] }}:</b>
                        @foreach($radios as $r){{ $r['channel'] }} = {{ $r['depts'] }}@if(! $loop->last) &nbsp;·&nbsp; @endif @endforeach
                    </div>
                @endif
                @break

            @case('radio_grid')
                @if(count($radios))
                    <div class="foot__row"><b>{{ $L['radio'] }}</b></div>
                    <table class="radiogrid"><tr>
                        @foreach($radios as $r)
                            <td><b>{{ $r['channel'] }}</b> · {{ $r['depts'] }}</td>
                            @if($loop->iteration % 4 === 0 && ! $loop->last)</tr><tr>@endif
                        @endforeach
                    </tr></table>
                @endif
                @break

            @case('hotels')
                @if(! empty($hotelsNote))
                    <div class="foot__row"><b>{{ $L['hotels'] }}:</b> {!! nl2br(e($hotelsNote)) !!}</div>
                @endif
                @break

            @case('emergency')
                @if(! empty($emergNote))
                    <div class="foot__row"><b>{{ $L['emerg'] }}:</b> {!! nl2br(e($emergNote)) !!}</div>
                @endif
                @break
        @endswitch
    @endforeach

    @if($callDay && $callDay->sign_enabled && empty($hideSign))
        <table class="signtbl"><tbody><tr>
            @foreach($signers as $sg)
                <td>
                    <div class="sign-space"></div>
                    <div class="sign-line">
                        <div class="sign-name">{{ $sg['name'] ?: '' }}&nbsp;</div>
                        <div class="sign-role">{{ $sg['role'] }}</div>
                    </div>
                </td>
            @endforeach
        </tr></tbody></table>
    @endif
</div>

</body></html>
