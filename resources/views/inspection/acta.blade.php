{{-- ============================================================================================
     ACTA DE INSPECCIÓN DE HERRAMIENTA — DOCUMENTO SELLADO STANDALONE (delta #42/#47).
     HOMOLOGADA al "chrome v2" que ya usan DSR / PAE / MEDEVAC: _report-v2-head/-toolbar/-foot +
     _doc-hero + banda + secciones .sec, MISMOS tokens. Cinematográfico en pantalla, blanco
     imprimible, "Imprimir / PDF" = window.print().

     PRESERVA TODO lo del acta anterior: veredicto DERIVADO (paro/no-ejecutable/apta + re-redacción
     PRE-USO), datos CONGELADOS de la herramienta y de la unidad física, foto real, checklist
     congelado con normas, observaciones, inspector + cédula, estado RETIRADO, PARO + desbloqueo,
     action item (cerrar/levantar) y el sello CFDI. Los controles operativos van en .no-print (no
     salen al PDF); el resto es el documento. El sello se calcula sobre el DATO, no sobre el render.
============================================================================================ --}}
@php
    use App\Support\Branding;

    $en        = app()->getLocale() === 'en';
    $brand     = isset($branding) && is_array($branding) ? $branding : [];
    $brandName = ($brand['brand_name'] ?? Branding::get('brand_name', 'CrewCare')) ?: 'CrewCare';
    $primary   = $brand['primary_color'] ?? (Branding::get('primary_color', '#ff9900') ?: '#ff9900');

    $snap = is_array($inspection->checklist_snapshot) ? $inspection->checklist_snapshot : [];

    // Veredicto DERIVADO del dato (no se captura): los 3 titulares de la calculadora de
    // inoperatividad. A2: un PARO en PRE-USO se re-redacta como "equipo no autorizado" (hay ventana).
    $verdictMap = [
        'paro' => [
            'var' => 'var(--danger)', 'band' => 'warn', 'icon' => 'octagon-alert',
            'title' => $en ? 'IMMEDIATE STOP' : 'PARO INMEDIATO',
            'short' => $en ? 'Stop' : 'Paro',
            'sub'   => $en ? 'The tool is not to be used.' : 'La herramienta no se usa.',
        ],
        'actividad_no_ejecutable' => [
            'var' => 'var(--warn)', 'band' => '', 'icon' => 'alert-triangle',
            'title' => $en ? 'ACTIVITY NOT EXECUTABLE' : 'ACTIVIDAD NO EJECUTABLE',
            'short' => $en ? 'Not executable' : 'No ejecutable',
            'sub'   => $en ? 'The tool may be fine; the activity does not proceed.' : 'La herramienta puede estar bien; la actividad no procede.',
        ],
        'apta' => [
            'var' => 'var(--ok)', 'band' => 'ok', 'icon' => 'shield-check',
            'title' => $en ? 'FIT FOR USE' : 'APTA',
            'short' => $en ? 'Fit' : 'Apta',
            'sub'   => $en ? 'Remains in operation.' : 'Sigue en operación.',
        ],
    ];
    $v = $verdictMap[$inspection->verdict] ?? $verdictMap['apta'];
    if ($inspection->verdict === 'paro' && $inspection->isPreUse()) {
        $v = [
            'var' => 'var(--warn)', 'band' => '', 'icon' => 'alert-triangle',
            'title' => $en ? 'EQUIPMENT NOT AUTHORIZED' : 'EQUIPO NO AUTORIZADO',
            'short' => $en ? 'Not authorized' : 'No autorizado',
            'sub'   => $en ? 'Nothing is running: there is a window to fix or replace before use.' : 'Nada está corriendo: hay ventana para corregir o sustituir antes del uso.',
        ];
    }
    $pathLabel = [
        'reemplazo'            => $en ? 'Out of service: removed and replaced.' : 'Fuera de servicio: sale y se reemplaza.',
        'correccion_mismo_dia' => $en ? 'Same-day correction; returns today if fixed and re-inspected.' : 'Corrección el mismo día; vuelve hoy si se corrige y se reinspecciona.',
    ];
    $momentLabels = [
        'llegada_equipo' => $en ? 'Equipment arrival' : 'Llegada del equipo',
        'previo_al_uso'  => $en ? 'Before use' : 'Previo al uso',
        'en_uso'         => $en ? 'In use' : 'En uso',
        'por_hallazgo'   => $en ? 'From a finding' : 'Por hallazgo',
    ];
    $scopeLabels = [
        'universal'            => 'Universal',
        'universal_energizada' => $en ? 'Energized' : 'Energizada',
        'familia'              => $en ? 'Family' : 'Familia',
        'tipo'                 => $en ? 'Type' : 'Tipo',
        'actividad'            => $en ? 'Activity' : 'Actividad',
    ];

    // Estado para la banda (vistazo rápido): retirada / bloqueada / vigente.
    if ($inspection->isRetired()) {
        $statusLabel = $en ? 'Retired' : 'Retirada'; $statusBand = 'warn';
    } elseif ($inspection->isBlocked()) {
        $statusLabel = $en ? 'Blocked' : 'Bloqueada'; $statusBand = 'warn';
    } else {
        $statusLabel = $en ? 'In force' : 'Vigente'; $statusBand = 'ok';
    }

    // Marca / modelo (congelados) y pie.
    $brandModel = trim(($inspection->tool_brand ? $inspection->tool_brand . ' ' : '') . ($inspection->tool_model ?? '')) ?: '—';
    $footBits = array_filter([
        trim((string) ($inspection->inspector_role ?? '')),
        $inspection->created_at ? $inspection->created_at->format('d/m/Y H:i') : '',
    ]);
    $footMeta = implode(' · ', $footBits);

    // Textos de confirmación (sin apóstrofos → seguros dentro del onsubmit).
    $cfUnblock = $en ? 'Lift the stop? It is logged with your name and time, and the record is re-sealed.' : '¿Levantar el paro? Queda registrado con tu nombre y hora, y el acta se re-sella.';
    $cfClose   = $en ? 'Close the action? If it is a STOP, it is lifted and the record is re-sealed.' : '¿Cerrar la acción? Si es un PARO, se levanta y el acta se re-sella.';
    $cfRetire  = $en ? 'Retire the record? It stops being in force. The seal remains valid.' : '¿Retirar el acta? Deja de estar vigente. El sello sigue siendo válido.';
@endphp
<!DOCTYPE html>
<html lang="{{ $en ? 'en' : 'es' }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $inspection->folio() }} — {{ $brandName }}</title>
@include('componentes._report-v2-head')
<style>
    /* Contenido propio del acta (scoped .insp-*). Hereda los tokens del chrome (claro/oscuro/print). */

    /* Banner de estado RETIRADO — parte del documento (SÍ se imprime). */
    .insp-retired{display:flex;gap:11px;align-items:flex-start;padding:12px 15px;border-radius:var(--radius-sm);
        border:1px solid var(--stroke-2);background:var(--panel);margin:0 0 16px}
    .insp-retired svg{width:20px;height:20px;color:var(--muted);flex:none}
    .insp-retired b{color:var(--text)}
    .insp-retired .meta{font-size:.76rem;color:var(--muted);margin-top:3px}
    .insp-retired a{color:var(--brand)}

    /* Veredicto (derivado del dato): filo + tinte por severidad vía --vc. */
    .insp-verdict{display:flex;gap:14px;align-items:flex-start;padding:16px 18px;border-radius:var(--radius-sm);
        border:1px solid var(--stroke);border-left:5px solid var(--vc,var(--muted));
        background:color-mix(in srgb,var(--vc,var(--muted)) 8%,var(--panel))}
    .insp-verdict .vic{flex:none;width:34px;height:34px;color:var(--vc,var(--muted))}
    .insp-verdict .vic svg{width:34px;height:34px}
    .insp-verdict h2{margin:0;font-family:var(--poster);font-weight:900;font-style:italic;text-transform:uppercase;
        font-size:1.45rem;letter-spacing:.02em;line-height:1;color:var(--vc,var(--muted))}
    .insp-verdict p{margin:6px 0 0;font-size:.86rem;color:var(--text)}

    /* Nota de control operativo (no-print) + botón de paro. */
    .op-note{font-size:.78rem;color:var(--muted);margin-bottom:9px}
    .btn.stop{background:var(--danger);border:0;color:#fff}
    .btn.retire{border-color:color-mix(in srgb,var(--danger) 45%,transparent);color:var(--danger)}

    /* Observaciones. */
    .insp-obs{margin-top:16px;border-top:1px solid var(--stroke);padding-top:12px}
    .insp-obs .k{font-size:.58rem;letter-spacing:.1em;text-transform:uppercase;color:var(--muted);margin-bottom:5px}
    .insp-obs .txt{white-space:pre-line;font-size:.88rem;color:var(--text)}

    /* Checklist congelado (tabla): cabecera de alcance + código/normas por punto. */
    .tbl .scope-row td{font-size:.56rem;text-transform:uppercase;letter-spacing:.12em;color:var(--brand);
        font-weight:800;padding-top:15px;padding-bottom:5px}
    .insp-pt{font-weight:600;color:var(--text)}
    .insp-tags{display:flex;flex-wrap:wrap;gap:5px;margin-top:6px}
    .insp-tags .t{font-family:var(--mono);font-size:.6rem;border:1px solid var(--stroke);border-radius:6px;
        padding:2px 6px;color:var(--muted);white-space:nowrap}
    .insp-tags .t.gate{color:var(--warn);border-color:color-mix(in srgb,var(--warn) 40%,transparent)}

    /* Foto real de la unidad: una sola pieza, faithful al .photo del chrome. */
    .insp-photos{grid-template-columns:minmax(0,340px)}
    .insp-photos .photo{aspect-ratio:4/3;text-decoration:none}

    /* Panel del action item (documento) con el filo por estado. */
    .insp-ai{border-left:4px solid var(--warn)}
    .insp-ai.closed{border-left-color:var(--ok)}

    /* Campos operativos (no-print). */
    .insp-field-lbl{font-size:.72rem;color:var(--muted);display:block}
    .insp-field-lbl .field{width:100%;margin-top:4px}
    details.insp-retire summary{cursor:pointer;color:var(--muted);font-size:.8rem;list-style:none}
    details.insp-retire summary::-webkit-details-marker{display:none}
</style>
</head>
<body>

@include('componentes._report-v2-toolbar', [
    'backRoute'   => route('tools.index'),
    'backLabel'   => $en ? 'Back' : 'Volver',
    'exportLabel' => $en ? 'Export PDF' : 'Imprimir / PDF',
])

<div class="stage">
  <article class="sheet">
    <table class="report-wrap">
    <thead><tr><td>
      @include('componentes._doc-hero', [
        'heroImage'    => $inspection->toolPhotoUrl(),
        'heroProject'  => $brandName,
        'heroLocation' => ($inspection->tool_name ?: '—'),
        'heroDate'     => optional($inspection->created_at)->format('d M Y'),
        'heroTime'     => optional($inspection->created_at)->format('H:i'),
        'heroModule'   => $en ? 'CrewCare · Tool inspection' : 'CrewCare · Inspección de herramienta',
      ])
    </td></tr></thead>
    <tbody><tr><td>

    {{-- BANDA (como el DSR): identidad del documento + un vistazo rápido. --}}
    <div class="band">
      <div class="lead">
        <span class="ic">@include('componentes._icon', ['name' => 'wrench'])</span>
        <span class="who">
          <span class="lbl">{{ $en ? 'Tool inspection record' : 'Acta de inspección' }}</span>
          <span class="val">{{ $inspection->tool_name ?: '—' }}</span>
          <span class="sub">{{ $inspection->folio() }}@if($inspection->tool_code) · {{ $inspection->tool_code }}@endif</span>
        </span>
      </div>
      <div class="stats">
        <div class="cell">
          <span class="lbl">{{ $en ? 'Verdict' : 'Veredicto' }}</span>
          <span class="v {{ $v['band'] }}">{{ $v['short'] }}</span>
        </div>
        <div class="cell">
          <span class="lbl">{{ $en ? 'Status' : 'Estado' }}</span>
          <span class="v {{ $statusBand }}">{{ $statusLabel }}</span>
        </div>
      </div>
    </div>

    <div class="body">
      <h1 class="restricted" style="position:absolute;left:-9999px">{{ $brandName }} — {{ $en ? 'Tool inspection record' : 'Acta de inspección de herramienta' }} — {{ $inspection->folio() }}</h1>

      {{-- Flash operativo (no-print). --}}
      @if(session('success'))<div class="alert ok no-print">{{ session('success') }}</div>@endif
      @if(session('error'))<div class="alert bad no-print">{{ session('error') }}</div>@endif

      {{-- Estado RETIRADO (Parte B): el sello sigue válido; el documento ya no está vigente. --}}
      @if($inspection->isRetired())
      <div class="insp-retired">
        @include('componentes._icon', ['name' => 'lock'])
        <div>
          <b>{{ $en ? 'RETIRED record' : 'Acta RETIRADA' }}</b> — {{ $en ? 'the seal is still valid; only its state changed.' : 'el sello sigue siendo válido; solo cambió de estado.' }}
          @if($inspection->retired_at)<div class="meta">{{ $en ? 'Retired on' : 'Retirada el' }} {{ optional($inspection->retired_at)->format('d/m/Y H:i') }}@if($inspection->retired_reason) · {{ $inspection->retired_reason }}@endif</div>@endif
          @if($inspection->supersededBy)<div class="meta">{{ $en ? 'Superseded by' : 'Sustituida por' }} <a href="{{ route('tools.inspection.show', $inspection->supersededBy->uuid) }}">{{ $inspection->supersededBy->folio() }}</a></div>@endif
        </div>
      </div>
      @endif

      {{-- ============ VEREDICTO (derivado) + DESBLOQUEO DEL PARO ============ --}}
      <section class="sec">
        <div class="insp-verdict" style="--vc:{{ $v['var'] }}">
          <span class="vic">@include('componentes._icon', ['name' => $v['icon']])</span>
          <div>
            <h2>{{ $v['title'] }}</h2>
            <p>{{ $v['sub'] }}@if($inspection->verdict === 'paro' && $inspection->resolution_path) — {{ $pathLabel[$inspection->resolution_path] ?? $inspection->resolution_path }}@endif</p>
          </div>
        </div>

        {{-- Desbloqueo del PARO (acto con autor). El estado se imprime; el botón es operativo. --}}
        @if($inspection->isParo())
          @if($inspection->unblocked_at)
          <div style="display:flex;align-items:center;gap:7px;margin-top:11px">
            <span class="chip ok">@include('componentes._icon', ['name' => 'circle-check']) {{ $en ? 'Stop lifted by' : 'Paro levantado por' }} {{ $inspection->unblocked_by_name }} · {{ optional($inspection->unblocked_at)->format('d/m/Y H:i') }}</span>
          </div>
          @else
          <div class="no-print" style="margin-top:12px">
            <div class="op-note">{{ $en ? 'A stop lasts minutes: lift it once the exit path is met.' : 'El paro dura minutos: levántalo cuando la vía de salida esté cumplida.' }}</div>
            <form method="post" action="{{ route('tools.inspection.unblock', $inspection->uuid) }}" onsubmit="return confirm('{{ $cfUnblock }}');">
              @csrf
              <button class="btn stop">@include('componentes._icon', ['name' => 'lock']) {{ $en ? 'Lift the stop' : 'Levantar el paro' }}</button>
            </form>
          </div>
          @endif
        @endif
      </section>

      {{-- ============ ACCIÓN CORRECTIVA (action item ligado al acta, flujo PDCA) ============ --}}
      @if($actionItem)
        @php $aiClosed = $actionItem->status === \App\Models\ActionItem::STATUS_CLOSED; @endphp
        <section class="sec">
          <div class="sec-h"><span class="bar"></span>@include('componentes._icon', ['name' => $aiClosed ? 'clipboard-check' : 'clipboard-list'])<h2>{{ $en ? 'Corrective action' : 'Acción correctiva' }}</h2><span class="line"></span></div>
          <div class="panel insp-ai {{ $aiClosed ? 'closed' : '' }}">
            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
              <strong>{{ $en ? 'Corrective action' : 'Acción correctiva' }}</strong>
              <span class="chip {{ $aiClosed ? 'ok' : 'warn' }}">{{ $aiClosed ? ($en ? 'Closed' : 'Cerrada') : ($en ? 'Open' : 'Abierta') }}</span>
            </div>
            <div style="font-size:.86rem;margin-top:8px">{{ $actionItem->description }}</div>
            @if($actionItem->due_date)<div style="font-size:.76rem;color:var(--muted);margin-top:4px">{{ $en ? 'Commitment' : 'Compromiso' }}: {{ optional($actionItem->due_date)->format('d/m/Y H:i') }}</div>@endif
            @if(! $aiClosed)
              @can('hazards.manage')
              <div class="no-print" style="margin-top:12px">
                <form method="post" action="{{ url('/action-items/'.$actionItem->id.'/close') }}" onsubmit="return confirm('{{ $cfClose }}');">
                  @csrf
                  <button class="btn ok">@include('componentes._icon', ['name' => 'circle-check']) {{ $en ? 'Close and lift stop' : 'Cerrar y levantar paro' }}</button>
                </form>
              </div>
              @endcan
            @endif
          </div>
        </section>
      @endif

      {{-- ============ DATOS CONGELADOS DE LA HERRAMIENTA / UNIDAD ============ --}}
      <section class="sec">
        <div class="sec-h"><span class="bar"></span>@include('componentes._icon', ['name' => 'wrench'])<h2>{{ $en ? 'Tool data' : 'Datos de la herramienta' }}</h2><span class="line"></span></div>
        <div class="panel">
          <div class="facts">
            <div class="fact"><div class="k">{{ $en ? 'Tool' : 'Herramienta' }}</div><div class="v">{{ $inspection->tool_name ?: '—' }}@if($inspection->tool_code) <span style="color:var(--muted);font-weight:400">({{ $inspection->tool_code }})</span>@endif</div></div>
            <div class="fact"><div class="k">{{ $en ? 'Brand / model' : 'Marca / modelo' }}</div><div class="v">{{ $brandModel }}</div></div>
            <div class="fact"><div class="k">{{ $en ? 'Serial no.' : 'N.º de serie' }}</div><div class="v mono">{{ $inspection->tool_serial ?: '—' }}</div></div>
            <div class="fact"><div class="k">{{ $en ? 'Owner' : 'Dueño' }}</div><div class="v">{{ $inspection->ownerLabel() ?: '—' }}</div></div>
            <div class="fact"><div class="k">{{ $en ? 'Department' : 'Departamento' }}</div><div class="v">{{ $inspection->department_name ?: '—' }}</div></div>
            <div class="fact"><div class="k">{{ $en ? 'Checklist' : 'Checklist' }}</div><div class="v">{{ $inspection->checklist_mode === 'operator' ? ($en ? 'Operator (pre-shift)' : 'Operador (pre-turno)') : ($en ? 'Safety (on detection)' : 'Safety (al detectar)') }}</div></div>
            <div class="fact"><div class="k">{{ $en ? 'Moment' : 'Momento' }}</div><div class="v">{{ $momentLabels[$inspection->inspection_moment] ?? '—' }}</div></div>
            @if($inspection->origin_type && $inspection->origin_id)
            <div class="fact"><div class="k">{{ $en ? 'Origin' : 'Origen' }}</div><div class="v">{{ $en ? 'Linked to a report' : 'Ligada a un reporte' }} #{{ $inspection->origin_id }}</div></div>
            @endif
            <div class="fact"><div class="k">{{ $en ? 'Inspector' : 'Inspector' }}</div><div class="v">{{ $inspection->inspector_name ?: '—' }}@if($inspection->inspector_cedula) <span style="color:var(--muted);font-weight:400">· {{ $en ? 'Lic.' : 'Cédula' }} {{ $inspection->inspector_cedula }}</span>@endif</div></div>
            <div class="fact"><div class="k">{{ $en ? 'Date' : 'Fecha' }}</div><div class="v">{{ optional($inspection->created_at)->format('d/m/Y H:i') }}</div></div>
          </div>

          @if($inspection->observations)
          <div class="insp-obs">
            <div class="k">{{ $en ? 'Observations' : 'Observaciones' }}</div>
            <div class="txt">{{ $inspection->observations }}</div>
          </div>
          @endif
        </div>
      </section>

      {{-- ============ FOTO REAL DE LA UNIDAD (sellada con el acta) ============ --}}
      @if($inspection->toolPhotoUrl())
      <section class="sec">
        <div class="sec-h"><span class="bar"></span>@include('componentes._icon', ['name' => 'camera'])<h2>{{ $en ? 'Tool photo' : 'Foto de la herramienta' }}</h2><span class="line"></span></div>
        <div class="photos insp-photos">
          <a class="photo" href="{{ $inspection->toolPhotoUrl() }}" target="_blank" rel="noopener">
            <img src="{{ $inspection->toolPhotoUrl() }}" alt="{{ $inspection->tool_name }}">
            <span class="cap">{{ $inspection->tool_name ?: '—' }}@if($inspection->tool_serial) · {{ $inspection->tool_serial }}@endif</span>
          </a>
        </div>
      </section>
      @endif

      {{-- ============ CHECKLIST EJECUTADO (congelado: texto, norma y respuesta) ============ --}}
      <section class="sec">
        <div class="sec-h"><span class="bar"></span>@include('componentes._icon', ['name' => 'clipboard-list'])<h2>{{ $en ? 'Executed checklist' : 'Checklist ejecutado' }}</h2><span class="line"></span></div>
        @if(count($snap))
        <table class="tbl">
          <tbody>
            @php $lastScope = null; @endphp
            @foreach($snap as $s)
              @php $scope = $s['scope'] ?? null; @endphp
              @if($scope !== $lastScope)
                <tr class="scope-row"><td colspan="2">{{ $scopeLabels[$scope] ?? $scope }}</td></tr>
                @php $lastScope = $scope; @endphp
              @endif
              <tr>
                <td>
                  <div class="insp-pt">{{ $s['text'] ?? '' }}</div>
                  <div class="insp-tags">
                    @if(! empty($s['code']))<span class="t">{{ $s['code'] }}</span>@endif
                    @foreach(($s['standards'] ?? []) as $sc)<span class="t">{{ $sc }}</span>@endforeach
                    @if(! empty($s['is_gate']))<span class="t gate">{{ $en ? 'Gate' : 'Compuerta' }}</span>@endif
                  </div>
                </td>
                <td style="text-align:right;white-space:nowrap;vertical-align:top">
                  @if(($s['answer'] ?? '') === 'fail')
                    <span class="chip warn">{{ $en ? 'FAIL' : 'FALLA' }}</span>
                  @else
                    <span class="chip ok">{{ $en ? 'Pass' : 'Cumple' }}</span>
                  @endif
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
        @else
        <p class="restricted">{{ $en ? 'No checklist snapshot was stored.' : 'No se guardó un checklist.' }}</p>
        @endif
      </section>

      {{-- ============ SELLO SHA + QR + CADENA CFDI (verificable públicamente) ============ --}}
      <section class="sec">
        <div class="sec-h"><span class="bar"></span>@include('componentes._icon', ['name' => 'shield'])<h2>{{ $en ? 'Digital seal' : 'Sello digital' }}</h2><span class="line"></span></div>
        @include('componentes._seal-cfdi', ['doc' => $inspection, 'folio' => $inspection->folio(), 'prefix' => 'CREWCARE-INSP'])
      </section>

      {{-- ============ RETIRAR EL ACTA (operativo, no-print) — NO re-sella; cambia el estado ============ --}}
      @if(! $inspection->isRetired())
      <div class="no-print" style="margin-top:6px">
        <details class="insp-retire">
          <summary>{{ $en ? 'Retire this record' : 'Retirar esta acta' }}</summary>
          <div class="panel" style="margin-top:10px">
            <form method="post" action="{{ route('tools.inspection.retire', $inspection->uuid) }}" onsubmit="return confirm('{{ $cfRetire }}');" style="display:flex;flex-direction:column;gap:11px">
              @csrf
              <label class="insp-field-lbl">{{ $en ? 'Reason (optional)' : 'Motivo (opcional)' }}
                <input type="text" name="retired_reason" class="field" maxlength="255" placeholder="{{ $en ? 'e.g. equipment replaced / re-inspected' : 'p. ej. equipo sustituido / reinspeccionado' }}">
              </label>
              <label class="insp-field-lbl">{{ $en ? 'Superseded by (another record UUID, optional)' : 'Sustituida por (UUID de otra acta, opcional)' }}
                <input type="text" name="superseded_by" class="field" placeholder="{{ $en ? 'optional' : 'opcional' }}">
              </label>
              <div><button class="btn retire">{{ $en ? 'Retire record' : 'Retirar acta' }}</button></div>
            </form>
          </div>
        </details>
      </div>
      @endif

    </div>{{-- .body --}}

    </td></tr></tbody>
    </table>
@include('componentes._report-v2-foot', [
    'footPreparedName' => ($inspection->inspector_name ?: '—'),
    'footPreparedMeta' => $footMeta,
    'footUuid'         => $inspection->uuid,
])
</body>
</html>
