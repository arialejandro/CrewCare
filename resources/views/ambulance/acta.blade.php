{{-- ============================================================================================
     ACTA DE VERIFICACIÓN DE RECURSO DE EMERGENCIA EN SITIO (ambulancia). Documento SELLADO,
     hermano del PAE / DSR / MEDEVAC: MISMO chrome v2 (_report-v2-head/-toolbar/-foot + _doc-hero
     + banda + .sec) y los MISMOS tokens. Lo único propio son las secciones .amb-*.

     ENCUADRE: es CONSTANCIA DE VERIFICACIÓN EN SITIO, NO inspección sanitaria (eso lo hace la
     autoridad). El sello SHA se calcula sobre el DATO, no sobre este render.

     Se PRESERVA todo el contenido y la lógica de la versión anterior:
       veredicto (paro/actividad_no_ejecutable/apta) con su color · datos congelados
       (tipo/rama/nivel/capacidad/proveedor/placas/N.º económico/inspector/cédula/fecha) ·
       correspondencia tipo↔riesgo (day_risk_level) · tripulación con folio CONOCER + estado
       "cotejado/sin cotejar" + glosa TAMP · foto de la unidad + galería de evidencia · checklist
       congelado · observaciones · estado RETIRADO · desbloqueo del PARO (form) · action item
       (form de cierre) · sello CFDI.
     Los controles operativos (desbloqueo, cerrar action item) NO se imprimen; el resto sí.
============================================================================================ --}}
@php
    use App\Support\Branding;

    $en        = app()->getLocale() === 'en';
    $brand     = isset($branding) && is_array($branding) ? $branding : [];
    $brandName = ($brand['brand_name'] ?? Branding::get('brand_name', 'CrewCare')) ?: 'CrewCare';
    $primary   = $brand['primary_color'] ?? (Branding::get('primary_color', '#ff9900') ?: '#ff9900');

    $snap     = is_array($inspection->checklist_snapshot) ? $inspection->checklist_snapshot : [];
    $crewSnap = is_array($inspection->crew_snapshot) ? $inspection->crew_snapshot : [];
    $evidence = $inspection->evidencePhotoUrls();

    // Veredicto DERIVADO del dato. 'ck' = clase de color de la banda; 'band' = color de la
    // celda de vistazo (rojo/verde; la banda de la celda no tiene ámbar → no_exec queda neutro).
    $verdictMap = [
        'paro'                    => ['ck' => 'paro',   'band' => 'warn', 'icon' => 'octagon-alert',  'title' => 'PARO INMEDIATO',          'sub' => 'La unidad no se usa.'],
        'actividad_no_ejecutable' => ['ck' => 'noexec', 'band' => '',     'icon' => 'alert-triangle', 'title' => 'ACTIVIDAD NO EJECUTABLE', 'sub' => 'El recurso no alcanza para la actividad prevista.'],
        'apta'                    => ['ck' => 'apta',   'band' => 'ok',   'icon' => 'shield-check',   'title' => 'APTA',                    'sub' => 'El recurso queda disponible.'],
    ];
    $v = $verdictMap[$inspection->verdict] ?? $verdictMap['apta'];

    $pathLabel = [
        'reemplazo'            => 'Fuera de servicio: la unidad sale y se reemplaza.',
        'correccion_mismo_dia' => 'Corrección el mismo día; vuelve si se corrige y se reverifica.',
    ];
    $triggerLabels = [
        'completa'  => 'Verificación completa',
        'identidad' => 'Identidad',
        'persona'   => 'Persona / tripulación',
        'unidad'    => 'Unidad',
        'consumo'   => 'Consumo / botiquín',
        'riesgo'    => 'Riesgo del día',
    ];
    $riskLabels = [1 => 'Muy bajo', 2 => 'Bajo', 3 => 'Medio', 4 => 'Alto', 5 => 'Muy alto'];

    // Display del tipo (nombre + código) y de rama/nivel.
    $ramaNivel = ($inspection->rama ?: '—') . ($inspection->type_level ? ' · Nivel ' . $inspection->type_level : '');

    // Hero + pie. El pie del hero ya antepone "CrewCare"; el módulo NO lo repite.
    $heroModule = 'Verificación de ambulancia';
    $footMeta   = implode(' · ', array_filter([
        $inspection->inspector_role ?: null,
        optional($inspection->created_at)->format('d/m/Y H:i'),
    ]));
    $appVersion = config('crewcare.doc_version');
    $footUuid   = 'UUID: ' . ($inspection->uuid ?: '—') . ($appVersion ? ' | ' . $appVersion : '');
@endphp
<!DOCTYPE html>
<html lang="{{ $en ? 'en' : 'es' }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $inspection->folio() }} — {{ $brandName }}</title>
@include('componentes._report-v2-head')
<style>
  /* Contenido propio del acta (scoped .amb-*). Hereda los tokens del chrome (claro/oscuro/print). */
  .amb-note{ font-size:.74rem; color:var(--muted); margin:0 0 16px; line-height:1.5; }
  .amb-note.tight{ margin:9px 0 0; }

  /* Veredicto — banda de color DERIVADA del dato (paro/no-exec/apta). Imprimible. */
  .amb-verdict{ display:flex; align-items:center; gap:14px; margin:0 0 8px; padding:15px 18px;
    border:1px solid var(--stroke); border-left:5px solid var(--vc); border-radius:var(--radius-sm);
    background:color-mix(in srgb, var(--vc) 9%, var(--panel)); break-inside:avoid;
    -webkit-print-color-adjust:exact; print-color-adjust:exact; }
  .amb-verdict.paro{ --vc:var(--danger); }
  .amb-verdict.noexec{ --vc:var(--warn); }
  .amb-verdict.apta{ --vc:var(--ok); }
  .amb-verdict .vic{ flex:none; width:34px; height:34px; color:var(--vc); }
  .amb-verdict .vic svg{ width:34px; height:34px; }
  .amb-verdict h2{ margin:0; font-family:var(--poster); font-weight:900; font-style:italic;
    text-transform:uppercase; font-size:1.3rem; letter-spacing:.01em; color:var(--vc); line-height:1.05; }
  .amb-verdict p{ margin:4px 0 0; font-size:.85rem; color:var(--text); }

  .amb-lifted{ display:flex; align-items:center; gap:8px; margin:0 0 18px; font-size:.8rem; color:var(--ok); }
  .amb-lifted svg{ width:16px; height:16px; flex:none; }

  /* Estado RETIRADO — imprimible (el sello sigue válido; solo cambió el estado). */
  .amb-retired{ display:flex; align-items:flex-start; gap:10px; margin:0 0 16px; padding:12px 15px;
    border:1px solid var(--stroke-2); border-left:4px solid var(--muted); border-radius:var(--radius-sm);
    background:var(--panel); font-size:.82rem; color:var(--text); break-inside:avoid; }
  .amb-retired svg{ flex:none; width:20px; height:20px; color:var(--muted); }
  .amb-retired a{ color:var(--brand); }

  /* Acción correctiva (obligación PDCA): panel IMPRIMIBLE; el botón de cierre NO se imprime. */
  .amb-ai{ border:1px solid var(--stroke); border-left:4px solid var(--warn); border-radius:var(--radius-sm);
    padding:13px 15px; background:var(--panel); break-inside:avoid; }
  .amb-ai.closed{ border-left-color:var(--ok); }
  .amb-ai .ai-h{ display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
  .amb-ai .ai-h svg{ width:16px; height:16px; color:var(--brand); }
  .amb-ai .ai-h strong{ font-size:.92rem; }
  .amb-ai .ai-desc{ font-size:.84rem; margin-top:6px; color:var(--text); }
  .amb-ai .ai-due{ font-size:.74rem; color:var(--muted); margin-top:3px; }
  .amb-ai .ai-close{ margin-top:10px; }

  .amb-state{ display:inline-block; font-size:.58rem; font-weight:800; text-transform:uppercase; letter-spacing:.05em;
    padding:2px 9px; border-radius:20px; border:1px solid var(--stroke); }
  .amb-state.open{ color:var(--warn); border-color:color-mix(in srgb, var(--warn) 40%, transparent); }
  .amb-state.closed{ color:var(--ok); border-color:color-mix(in srgb, var(--ok) 40%, transparent); }

  /* Checklist congelado — tabla con las COMPUERTAS destacadas. Es LARGO (todos los puntos del
     tipo), así que DEBE fluir entre hojas: el chrome pone .sec{break-inside:avoid} y eso lo
     empujaría entero a la 2ª hoja, dejando la 1ª cortada con un hueco en blanco. Aquí se libera
     el corte de la sección, se protege cada fila y se repite el encabezado por hoja. */
  .amb-chk{ break-inside:auto; }
  .amb-chk .tbl tr{ break-inside:avoid; }
  .amb-chk .tbl thead{ display:table-header-group; }
  .amb-chk .tbl td,.amb-chk .tbl th{ vertical-align:top; }
  .amb-chk .tbl tr.gate td{ background:color-mix(in srgb, var(--brand) 6%, transparent);
    -webkit-print-color-adjust:exact; print-color-adjust:exact; }
  .amb-tag{ display:inline-block; font-size:.58rem; font-weight:700; text-transform:uppercase; letter-spacing:.04em;
    color:var(--muted); border:1px solid var(--stroke); border-radius:20px; padding:2px 8px; margin:2px 4px 0 0; }
  .amb-tag.gate{ color:var(--brand); border-color:color-mix(in srgb, var(--brand) 40%, transparent); }
  .amb-res{ display:inline-block; font-weight:800; font-size:.6rem; text-transform:uppercase; letter-spacing:.04em;
    padding:3px 9px; border-radius:5px; white-space:nowrap; -webkit-print-color-adjust:exact; print-color-adjust:exact; }
  .amb-res.ok{ background:color-mix(in srgb, var(--ok) 15%, transparent); color:var(--ok); }
  .amb-res.bad{ background:color-mix(in srgb, var(--danger) 15%, transparent); color:var(--danger); }

  .amb-photos .photo a{ display:block; width:100%; height:100%; }

  @media (max-width:720px){
    .amb-verdict h2{ font-size:1.1rem; }
  }
</style>
</head>
<body>

@include('componentes._report-v2-toolbar', [
    'backRoute'   => route('ambulance.index'),
    'backLabel'   => 'Recursos',
    'exportLabel' => $en ? 'Export PDF' : 'Imprimir / PDF',
])

<div class="stage">
  <article class="sheet">
    <table class="report-wrap">
    <thead><tr><td>
      @include('componentes._doc-hero', [
        'heroImage'       => $inspection->unitPhotoUrl(),
        'heroProject'     => ($inspection->provider_name ?: $brandName),
        'heroHideCallbox' => true,
        'heroModule'      => $heroModule,
      ])
    </td></tr></thead>
    <tbody><tr><td>

    {{-- BANDA: identidad del documento + vistazo rápido (veredicto + fecha). --}}
    <div class="band">
      <div class="lead">
        <span class="ic">@include('componentes._icon', ['name' => 'ambulance'])</span>
        <span class="who">
          <span class="lbl">Verificación de recurso de emergencia</span>
          <span class="val">{{ $brandName }}</span>
          <span class="sub">{{ $inspection->folio() }} · {{ $inspection->type_name }}</span>
        </span>
      </div>
      <div class="stats">
        <div class="cell"><span class="lbl">Veredicto</span><span class="v {{ $v['band'] }}">{{ $v['title'] }}</span></div>
        <div class="cell"><span class="lbl">Fecha</span><span class="v">{{ optional($inspection->created_at)->format('d/m/Y') ?: '—' }}</span></div>
      </div>
    </div>

    <div class="body">
      <h1 class="restricted" style="position:absolute;left:-9999px">{{ $brandName }} — Constancia de verificación de recurso de emergencia en sitio — {{ $inspection->folio() }}</h1>

      {{-- Mensajes de sesión (no se imprimen). --}}
      @if (session('success'))<div class="alert ok no-print">{{ session('success') }}</div>@endif
      @if (session('error'))<div class="alert bad no-print">{{ session('error') }}</div>@endif

      {{-- Estado RETIRADO: el sello sigue válido; el documento ya no está vigente (imprimible). --}}
      @if ($inspection->isRetired())
      <div class="amb-retired">
        @include('componentes._icon', ['name' => 'lock', 'label' => null])
        <div>
          <strong>Acta RETIRADA</strong> — el sello sigue siendo válido; solo cambió de estado.
          @if ($inspection->retired_at)<br><span style="color:var(--muted)">Retirada el {{ optional($inspection->retired_at)->format('d/m/Y H:i') }}@if($inspection->retired_reason) · {{ $inspection->retired_reason }}@endif</span>@endif
          @if ($inspection->supersededBy)<br>Sustituida por <a href="{{ route('ambulance.acta', $inspection->supersededBy->uuid) }}">{{ $inspection->supersededBy->folio() }}</a>@endif
        </div>
      </div>
      @endif

      {{-- Encuadre: constancia de verificación en sitio, NO inspección sanitaria. --}}
      <p class="amb-note">Registra que el recurso de emergencia se verificó en sitio. No sustituye el dictamen de la autoridad sanitaria.</p>

      {{-- ============ VEREDICTO (derivado del dato) ============ --}}
      <section class="sec">
        <div class="sec-h"><span class="bar"></span>@include('componentes._icon', ['name' => $v['icon']])<h2>Veredicto</h2><span class="line"></span></div>
        <div class="amb-verdict {{ $v['ck'] }}">
          <span class="vic">@include('componentes._icon', ['name' => $v['icon'], 'label' => null])</span>
          <div>
            <h2>{{ $v['title'] }}</h2>
            <p>{{ $v['sub'] }}
              @if ($inspection->verdict === 'paro' && $inspection->resolution_path)
                — {{ $pathLabel[$inspection->resolution_path] ?? $inspection->resolution_path }}
              @endif
            </p>
          </div>
        </div>

        {{-- Paro ya levantado: acto con autor (unblocked_* SÍ entra al hash). Imprimible. --}}
        @if ($inspection->isParo() && $inspection->unblocked_at)
        <div class="amb-lifted">
          @include('componentes._icon', ['name' => 'circle-check', 'label' => null])
          <span>Paro levantado por <strong>{{ $inspection->unblocked_by_name }}</strong> · {{ optional($inspection->unblocked_at)->format('d/m/Y H:i') }}</span>
        </div>
        @endif

        {{-- Desbloqueo del PARO (control operativo: NO se imprime). --}}
        @if ($inspection->isParo() && ! $inspection->unblocked_at)
        <div class="ops no-print">
          <span class="ops-note">El paro dura minutos: levántalo cuando la vía de salida esté cumplida.</span>
          <form method="post" action="{{ route('ambulance.unblock', $inspection->uuid) }}"
                onsubmit="return confirm('¿Levantar el paro? Queda registrado con tu nombre y hora, y el acta se re-sella.');">
            @csrf
            <button class="btn brand" type="submit">
              @include('componentes._icon', ['name' => 'lock', 'label' => null])
              Levantar el paro
            </button>
          </form>
        </div>
        @endif
      </section>

      {{-- ============ ACCIÓN CORRECTIVA (obligación PDCA ligada al acta) ============ --}}
      @if (! empty($actionItem))
        @php $aiClosed = $actionItem->status === \App\Models\ActionItem::STATUS_CLOSED; @endphp
        <section class="sec">
          <div class="sec-h"><span class="bar"></span>@include('componentes._icon', ['name' => $aiClosed ? 'clipboard-check' : 'clipboard-list'])<h2>Acción correctiva</h2><span class="line"></span></div>
          <div class="amb-ai {{ $aiClosed ? 'closed' : '' }}">
            <div class="ai-h">
              @include('componentes._icon', ['name' => $aiClosed ? 'clipboard-check' : 'clipboard-list', 'label' => null])
              <strong>Acción correctiva</strong>
              <span class="amb-state {{ $aiClosed ? 'closed' : 'open' }}">{{ $aiClosed ? 'Cerrada' : 'Abierta' }}</span>
            </div>
            <div class="ai-desc">{{ $actionItem->description }}</div>
            @if ($actionItem->due_date)<div class="ai-due">Compromiso: {{ optional($actionItem->due_date)->format('d/m/Y H:i') }}</div>@endif
            @if (! $aiClosed)
              @can('hazards.manage')
                <div class="ai-close no-print">
                  <form method="post" action="{{ url('/action-items/'.$actionItem->id.'/close') }}"
                        onsubmit="return confirm('¿Cerrar la acción? Si es un PARO, se levanta y el acta se re-sella.');">
                    @csrf
                    <button class="btn ok sm" type="submit">
                      @include('componentes._icon', ['name' => 'circle-check', 'label' => null])
                      Cerrar y levantar paro
                    </button>
                  </form>
                </div>
              @endcan
            @endif
          </div>
        </section>
      @endif

      {{-- ============ DATOS CONGELADOS ============ --}}
      <section class="sec">
        <div class="sec-h"><span class="bar"></span>@include('componentes._icon', ['name' => 'file-check'])<h2>Datos de la verificación</h2><span class="line"></span></div>
        <div class="facts">
          <div class="fact"><div class="k">Tipo</div><div class="v">{{ $inspection->type_name }}@if($inspection->type_code) <span style="font-weight:400;color:var(--muted)">({{ $inspection->type_code }})</span>@endif</div></div>
          <div class="fact"><div class="k">Rama / nivel</div><div class="v">{{ $ramaNivel }}</div></div>
          @if ($inspection->capacity_level)
            <div class="fact"><div class="k">Capacidad</div><div class="v">{{ $inspection->capacity_level }}</div></div>
          @endif
          <div class="fact"><div class="k">Proveedor</div><div class="v">{{ $inspection->provider_name ?: '—' }}</div></div>
          <div class="fact"><div class="k">Verificación</div><div class="v">{{ $triggerLabels[$inspection->trigger_scope] ?? ($inspection->trigger_scope ?: '—') }}</div></div>
          <div class="fact"><div class="k">Placas</div><div class="v">{{ $inspection->plates ?: '—' }}</div></div>
          <div class="fact"><div class="k">N.º económico</div><div class="v">{{ $inspection->economic_number ?: '—' }}</div></div>
          @if ($inspection->day_risk_level !== null)
            <div class="fact"><div class="k">Riesgo del día</div><div class="v">{{ $inspection->day_risk_level }} · {{ $riskLabels[$inspection->day_risk_level] ?? '' }}</div></div>
            <div class="fact"><div class="k">¿Corresponde al riesgo?</div><div class="v" style="color:{{ $inspection->correspondence_ok ? 'var(--ok)' : 'var(--danger)' }}">{{ $inspection->correspondence_ok ? 'Sí' : 'No' }}</div></div>
          @endif
          <div class="fact"><div class="k">Inspector</div><div class="v">{{ $inspection->inspector_name ?: '—' }}@if($inspection->inspector_role)<br><span style="font-weight:400;color:var(--muted);font-size:.78rem">{{ $inspection->inspector_role }}</span>@endif</div></div>
          @if ($inspection->inspector_cedula)
            <div class="fact"><div class="k">Cédula</div><div class="v mono">{{ $inspection->inspector_cedula }}</div></div>
          @endif
          <div class="fact"><div class="k">Fecha</div><div class="v">{{ optional($inspection->created_at)->format('d/m/Y H:i') ?: '—' }}</div></div>
        </div>
      </section>

      {{-- ============ TRIPULACIÓN CONGELADA ============ --}}
      @if (count($crewSnap))
      <section class="sec">
        <div class="sec-h"><span class="bar"></span>@include('componentes._icon', ['name' => 'heart-pulse'])<h2>Tripulación</h2><span class="line"></span></div>
        <div class="chips">
          @foreach ($crewSnap as $m)
            @php
              $ver   = ! empty($m['verified']);
              $r     = $m['role'] ?? ($m['crew_role'] ?? null);
              $folio = $m['conocer_folio'] ?? null;
              $nm    = $m['name'] ?? ($m['full_name'] ?? '—');
              // Texto del chip armado en PHP: evita encadenar @endif@if inline (rompe Blade).
              $chipParts = [$nm];
              if ($r) { $chipParts[] = $r; }
              if ($folio) { $chipParts[] = 'CONOCER ' . $folio; }
              if ($ver) { $chipParts[] = 'cotejado'; }
              elseif ($folio) { $chipParts[] = 'sin cotejar'; }
            @endphp
            <span class="chip {{ $ver ? 'ok' : '' }}">
              @if ($ver)@include('componentes._icon', ['name' => 'circle-check', 'label' => null])@endif
              {{ implode(' · ', $chipParts) }}
            </span>
          @endforeach
        </div>
        <p class="amb-note tight">TAMP = Técnico en Atención Médica Prehospitalaria. «Cotejado» = folio CONOCER con foto del certificado y de la persona.</p>
      </section>
      @endif

      {{-- ============ CHECKLIST EJECUTADO (congelado) ============ --}}
      @if (count($snap))
      <section class="sec amb-chk">
        <div class="sec-h"><span class="bar"></span>@include('componentes._icon', ['name' => 'clipboard-check'])<h2>Checklist ejecutado</h2><span class="line"></span></div>
        <table class="tbl">
          <thead>
            <tr><th>Punto</th><th>Norma</th><th style="text-align:right">Resultado</th></tr>
          </thead>
          <tbody>
            @foreach ($snap as $s)
              @php $isGate = ! empty($s['is_gate']); $fail = ($s['answer'] ?? '') === 'fail'; @endphp
              <tr class="{{ $isGate ? 'gate' : '' }}">
                <td>
                  {{ $s['text'] ?? '' }}
                  @if ($isGate)<span class="amb-tag gate">Compuerta</span>@endif
                  @if (! empty($s['requires_document']))<span class="amb-tag">Documento</span>@endif
                </td>
                <td class="mono">
                  {{ $s['code'] ?? '' }}
                  @if (! empty($s['norm']))<span class="amb-tag">{{ $s['norm'] }}</span>@endif
                </td>
                <td style="text-align:right">
                  @if ($fail)<span class="amb-res bad">FALLA</span>@else<span class="amb-res ok">Cumple</span>@endif
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </section>
      @endif

      {{-- ============ FOTO DE LA UNIDAD + EVIDENCIA (sellada) ============ --}}
      @if ($inspection->unitPhotoUrl() || count($evidence))
      <section class="sec amb-photos">
        <div class="sec-h"><span class="bar"></span>@include('componentes._icon', ['name' => 'camera'])<h2>Foto de la unidad y evidencia</h2><span class="line"></span></div>
        <div class="photos">
          @if ($inspection->unitPhotoUrl())
            <div class="photo">
              <a href="{{ $inspection->unitPhotoUrl() }}" target="_blank" rel="noopener">
                <img src="{{ $inspection->unitPhotoUrl() }}" alt="{{ $inspection->type_name }}">
              </a>
            </div>
          @endif
          @foreach ($evidence as $i => $url)
            <div class="photo">
              <a href="{{ $url }}" target="_blank" rel="noopener">
                <img src="{{ $url }}" alt="Evidencia {{ $i + 1 }}">
              </a>
            </div>
          @endforeach
        </div>
      </section>
      @endif

      {{-- ============ OBSERVACIONES ============ --}}
      @if ($inspection->observations)
      <section class="sec">
        <div class="sec-h"><span class="bar"></span>@include('componentes._icon', ['name' => 'info'])<h2>Observaciones</h2><span class="line"></span></div>
        <p class="desc" style="white-space:pre-line">{{ $inspection->observations }}</p>
      </section>
      @endif

      {{-- ============ SELLO SHA + QR + CADENA CFDI ============ --}}
      <section class="sec">
        <div class="sec-h"><span class="bar"></span>@include('componentes._icon', ['name' => 'shield-check'])<h2>Sello digital</h2><span class="line"></span></div>
        @include('componentes._seal-cfdi', ['doc' => $inspection, 'folio' => $inspection->folio(), 'prefix' => 'CREWCARE-AMBU'])
      </section>

    </div>{{-- .body --}}

    </td></tr></tbody>
    <tfoot><tr><td><div class="footer-spacer"></div></td></tr></tfoot>
    </table>
@include('componentes._report-v2-foot', [
    'footPreparedName' => ($inspection->inspector_name ?: '—'),
    'footPreparedMeta' => $footMeta,
    'footUuid'         => $footUuid,
])
</body>
</html>
