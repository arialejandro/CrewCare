{{-- ============================================================================================
     ACTA DE VERIFICACIÓN DE VEHÍCULO. Documento SELLADO, hermano del acta de ambulancia:
     MISMO chrome v2 (_report-v2-head/-toolbar/-foot + _doc-hero + banda + .sec + _seal-cfdi) y los
     MISMOS tokens. Lo propio son las secciones .veh-*.

     El veredicto es GRADUADO: `verdict` apto/no_apto es la cara pública; `level` (alto_riesgo..
     excelente) es INTERNO — solo transpo/safety ven esta acta, así que aquí SÍ se muestra; el
     verificador público 'veh' NUNCA lo expone. El sello se calcula sobre el DATO, no sobre este render.
============================================================================================ --}}
@php
    use App\Support\Branding;
    use App\Support\VehicleVerdict;
    use Illuminate\Support\Facades\Storage;

    $en        = app()->getLocale() === 'en';
    $brand     = isset($branding) && is_array($branding) ? $branding : [];
    $brandName = ($brand['brand_name'] ?? Branding::get('brand_name', 'CrewCare')) ?: 'CrewCare';
    $primary   = $brand['primary_color'] ?? (Branding::get('primary_color', '#ff9900') ?: '#ff9900');

    $snap = is_array($inspection->checklist_snapshot) ? $inspection->checklist_snapshot : [];
    $attr = is_array($inspection->attributes_snapshot) ? $inspection->attributes_snapshot : [];

    // Veredicto/nivel → banda de color. apto+normal = "con observaciones" (ámbar); apto = verde;
    // no_apto = rojo (con la vía: reemplazo o taller según el nivel).
    $level = $inspection->level;
    $isApto = $inspection->isApto();
    $verdictMap = [
        'alto_riesgo' => ['ck' => 'noapto', 'icon' => 'octagon-alert',  'title' => 'NO APTO',                'sub' => 'Requiere reemplazo de unidad (uno o más puntos críticos).'],
        'pobre'       => ['ck' => 'noapto', 'icon' => 'octagon-alert',  'title' => 'NO APTO',                'sub' => 'Resoluble en taller (tres o más hallazgos mayores).'],
        'normal'      => ['ck' => 'obs',    'icon' => 'alert-triangle', 'title' => 'APTO CON OBSERVACIONES', 'sub' => 'Apto; los hallazgos quedan anotados, sin plazo de resolución.'],
        'bien'        => ['ck' => 'apto',   'icon' => 'shield-check',   'title' => 'APTO',                   'sub' => 'El vehículo queda apto para operar.'],
        'excelente'   => ['ck' => 'apto',   'icon' => 'shield-check',   'title' => 'APTO',                   'sub' => 'Sin hallazgos.'],
    ];
    $v = $verdictMap[$level] ?? ($isApto ? $verdictMap['bien'] : $verdictMap['pobre']);

    $classTag = ['critical' => ['Crítico', 'crit'], 'major' => ['Mayor', 'maj'], 'minor' => ['Menor', 'min']];
    $ptLabels = ['combustion' => 'Combustión', 'electric' => 'Eléctrico', 'hybrid' => 'Híbrido'];

    // Agrupar el checklist por módulo, en el orden en que se ejecutó.
    $groups = [];
    foreach ($snap as $s) { $groups[$s['module'] ?? 'nucleo'][] = $s; }
    $moduleNames = [
        'nucleo' => 'Núcleo', 'carga' => 'Carga', 'ocupacion' => 'Alta ocupación',
        'habitables' => 'Instalaciones habitables', 'energia' => 'Energía y aparatos',
        'agua' => 'Agua a bordo', 'remolque' => 'Remolque', 'electrica' => 'Tracción eléctrica',
    ];

    $heroModule = 'Verificación de vehículo';
    $footMeta   = implode(' · ', array_filter([$inspection->inspector_role ?: null]));
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
  .veh-note{ font-size:.74rem; color:var(--muted); margin:0 0 16px; line-height:1.5; }
  .veh-note.tight{ margin:9px 0 0; }
  .band .lead{ border-left:0; }

  .veh-verdict{ display:flex; align-items:center; gap:14px; margin:0 0 8px; padding:15px 18px;
    border:1px solid var(--stroke); border-left:5px solid var(--vc); border-radius:var(--radius-sm);
    background:color-mix(in srgb, var(--vc) 9%, var(--panel)); break-inside:avoid;
    -webkit-print-color-adjust:exact; print-color-adjust:exact; }
  .veh-verdict.apto{ --vc:var(--ok); }
  .veh-verdict.obs{ --vc:var(--warn); }
  .veh-verdict.noapto{ --vc:var(--danger); }
  .veh-verdict .vic{ flex:none; width:34px; height:34px; color:var(--vc); }
  .veh-verdict .vic svg{ width:34px; height:34px; }
  .veh-verdict h2{ margin:0; font-family:var(--poster); font-weight:900; font-style:italic;
    text-transform:uppercase; font-size:1.3rem; letter-spacing:.01em; color:var(--vc); line-height:1.05; }
  .veh-verdict p{ margin:4px 0 0; font-size:.85rem; color:var(--text); }

  /* Nivel interno + conteo (solo transpo/safety ven esta acta). */
  .veh-level{ display:flex; flex-wrap:wrap; gap:8px; align-items:center; margin:2px 0 0; }
  .veh-level .lvl{ font-size:.66rem; font-weight:800; text-transform:uppercase; letter-spacing:.04em;
    padding:3px 10px; border-radius:20px; border:1px solid var(--stroke); color:var(--muted); }
  .veh-count{ font-size:.72rem; color:var(--faint); font-family:var(--mono); }

  .veh-retired{ display:flex; align-items:flex-start; gap:10px; margin:0 0 16px; padding:12px 15px;
    border:1px solid var(--stroke-2); border-left:4px solid var(--muted); border-radius:var(--radius-sm);
    background:var(--panel); font-size:.82rem; color:var(--text); break-inside:avoid; }
  .veh-retired svg{ flex:none; width:20px; height:20px; color:var(--muted); }
  .veh-retired a{ color:var(--brand); }

  .report-wrap>tbody>tr{ break-inside:avoid; page-break-inside:avoid; }
  .report-wrap td.acell{ padding:0 var(--pad); }
  .report-wrap td.acell-top{ padding-top:var(--pad); }
  @media print{ .report-wrap td.acell{ padding:0 12mm; } .report-wrap td.acell-top{ padding-top:10mm; } }

  .veh-mod-h{ font-size:.62rem; text-transform:uppercase; letter-spacing:.09em; color:var(--muted);
    font-weight:800; margin:6px 0 2px; }
  .veh-chk-row{ display:grid; grid-template-columns:1fr 120px 78px 46px; gap:10px; align-items:start;
    padding:8px 0; border-bottom:1px solid var(--stroke); font-size:.8rem; color:var(--text); }
  .veh-chk-row.head{ font-size:.58rem; text-transform:uppercase; letter-spacing:.08em; color:var(--muted); font-weight:700; }
  .veh-chk-row .c2{ font-family:var(--mono); font-size:.74rem; }
  .veh-chk-row .c3{ text-align:right; }
  .veh-chk-row .c4{ text-align:right; }
  .report-wrap td.chkcell.fail{ background:color-mix(in srgb, var(--danger) 7%, transparent);
    -webkit-print-color-adjust:exact; print-color-adjust:exact; }
  .veh-tag{ display:inline-block; font-size:.56rem; font-weight:700; text-transform:uppercase; letter-spacing:.04em;
    color:var(--muted); border:1px solid var(--stroke); border-radius:20px; padding:2px 8px; margin:2px 4px 0 0; }
  .veh-tag.crit{ color:var(--danger); border-color:color-mix(in srgb,var(--danger) 40%,transparent); }
  .veh-tag.maj{ color:var(--warn); border-color:color-mix(in srgb,var(--warn) 40%,transparent); }
  .veh-tag.rep{ color:var(--ok); border-color:color-mix(in srgb,var(--ok) 40%,transparent); }
  .veh-res{ display:inline-block; font-weight:800; font-size:.6rem; text-transform:uppercase; letter-spacing:.04em;
    padding:3px 9px; border-radius:5px; white-space:nowrap; -webkit-print-color-adjust:exact; print-color-adjust:exact; }
  .veh-res.ok{ background:color-mix(in srgb, var(--ok) 15%, transparent); color:var(--ok); }
  .veh-res.bad{ background:color-mix(in srgb, var(--danger) 15%, transparent); color:var(--danger); }
  .veh-thumb{ width:40px; height:40px; border-radius:5px; object-fit:cover; border:1px solid var(--stroke); }
  @media (max-width:720px){ .veh-verdict h2{ font-size:1.1rem; } .veh-chk-row{ grid-template-columns:1fr 90px 66px 40px; } }
</style>
</head>
<body>

@include('componentes._report-v2-toolbar', [
    'backRoute'   => route('transport.index'),
    'backLabel'   => 'Transportación',
    'exportLabel' => $en ? 'Export PDF' : 'Imprimir / PDF',
])

<div class="stage">
  <article class="sheet">
    <table class="report-wrap">
    <thead><tr><td>
      @include('componentes._doc-hero', [
        'heroImage'       => $inspection->unitPhotoUrl(),
        'heroProject'     => $brandName,
        'heroHideCallbox' => true,
        'heroModule'      => $heroModule,
      ])
    </td></tr></thead>
    <tbody>

    <tr><td>
      <div class="band">
        <div class="lead">
          <span class="ic">@include('componentes._icon', ['name' => 'truck'])</span>
          <span class="who">
            <span class="lbl">Verificación de vehículo</span>
            <span class="val">{{ trim(($inspection->make ?: '') . ' ' . ($inspection->model ?: '')) ?: ($inspection->type_name ?: '—') }}</span>
            <span class="sub">{{ $inspection->folio() }}</span>
          </span>
        </div>
        <div class="stats">
          <div class="cell"><span class="lbl">Fecha y hora</span><span class="v">{{ optional($inspection->created_at)->format('d/m/Y | H:i') ?: '—' }}</span></div>
          <div class="cell"><span class="lbl">Placas</span><span class="v">{{ $inspection->plate ?: '—' }}</span></div>
        </div>
      </div>
    </td></tr>

    <tr><td class="acell acell-top">
      <h1 class="restricted" style="position:absolute;left:-9999px">{{ $brandName }} — Acta de verificación de vehículo — {{ $inspection->folio() }}</h1>
      @if (session('success'))<div class="alert ok no-print">{{ session('success') }}</div>@endif
      @if (session('error'))<div class="alert bad no-print">{{ session('error') }}</div>@endif

      @if ($inspection->isRetired())
      <div class="veh-retired">
        @include('componentes._icon', ['name' => 'lock', 'label' => null])
        <div>
          <strong>Acta RETIRADA</strong> — el sello sigue siendo válido; solo cambió de estado.
          @if ($inspection->retired_at)<br><span style="color:var(--muted)">Retirada el {{ optional($inspection->retired_at)->format('d/m/Y H:i') }}@if($inspection->retired_reason) · {{ $inspection->retired_reason }}@endif</span>@endif
          @if ($inspection->supersededBy)<br>Sustituida por <a href="{{ route('transport.acta', $inspection->supersededBy->uuid) }}">{{ $inspection->supersededBy->folio() }}</a>@endif
        </div>
      </div>
      @endif

      @if ($inspection->is_reevaluation && $inspection->originInspection)
      <p class="veh-note tight">Reevaluación de <a href="{{ route('transport.acta', $inspection->originInspection->uuid) }}" style="color:var(--brand)">{{ $inspection->originInspection->folio() }}</a>.</p>
      @endif

      <p class="veh-note" style="margin-bottom:0">Verificación de seguridad del vehículo en prep. El nivel de riesgo es de uso interno (transportación y safety).</p>
    </td></tr>

      {{-- ============ VEREDICTO (derivado del dato) ============ --}}
      <tr><td class="acell">
      <section class="sec">
        <div class="sec-h"><span class="bar"></span>@include('componentes._icon', ['name' => $v['icon']])<h2>Veredicto</h2><span class="line"></span></div>
        <div class="veh-verdict {{ $v['ck'] }}">
          <span class="vic">@include('componentes._icon', ['name' => $v['icon'], 'label' => null])</span>
          <div>
            <h2>{{ $v['title'] }}</h2>
            <p>{{ $v['sub'] }}</p>
          </div>
        </div>
        <div class="veh-level">
          <span class="lvl">Nivel interno: {{ VehicleVerdict::levelLabel($level) }}</span>
          <span class="veh-count">Críticos {{ $inspection->n_critical }} · Mayores {{ $inspection->n_major }} · Menores {{ $inspection->n_minor }}</span>
        </div>
      </section>
      </td></tr>

      {{-- ============ DATOS CONGELADOS ============ --}}
      <tr><td class="acell">
      <section class="sec">
        <div class="sec-h"><span class="bar"></span>@include('componentes._icon', ['name' => 'file-check'])<h2>Datos del vehículo</h2><span class="line"></span></div>
        <div class="facts">
          <div class="fact"><div class="k">Tipo</div><div class="v">{{ $inspection->type_name ?: '—' }}@if($inspection->type_code) <span style="font-weight:400;color:var(--muted)">({{ $inspection->type_code }})</span>@endif</div></div>
          <div class="fact"><div class="k">Marca / modelo</div><div class="v">{{ trim(($inspection->make ?: '') . ' ' . ($inspection->model ?: '')) ?: '—' }}@if($inspection->year) · {{ $inspection->year }}@endif</div></div>
          <div class="fact"><div class="k">Placas</div><div class="v">{{ $inspection->plate ?: '—' }}</div></div>
          <div class="fact"><div class="k">VIN</div><div class="v mono">{{ $inspection->vin ?: '—' }}</div></div>
          <div class="fact"><div class="k">Color</div><div class="v">{{ $inspection->color ?: '—' }}</div></div>
          <div class="fact"><div class="k">Powertrain</div><div class="v">{{ $ptLabels[$attr['powertrain'] ?? ''] ?? '—' }}</div></div>
          <div class="fact"><div class="k">Propietario</div><div class="v">{{ $inspection->owner_name ?: '—' }}</div></div>
          <div class="fact"><div class="k">Conductor</div><div class="v">{{ $inspection->driver_name ?: '—' }}</div></div>
          @if ($inspection->km !== null)
            <div class="fact"><div class="k">Kilometraje</div><div class="v">{{ number_format((int) $inspection->km) }} km</div></div>
          @endif
          <div class="fact"><div class="k">Levantó</div><div class="v">{{ $inspection->inspector_name ?: '—' }}@if($inspection->inspector_role)<br><span style="font-weight:400;color:var(--muted);font-size:.78rem">{{ $inspection->inspector_role }}</span>@endif</div></div>
          @if ($inspection->inspector_cedula)
            <div class="fact"><div class="k">Cédula</div><div class="v mono">{{ $inspection->inspector_cedula }}</div></div>
          @endif
        </div>
      </section>
      </td></tr>

      {{-- ============ CHECKLIST EJECUTADO (congelado), por módulo ============ --}}
      @if (count($snap))
      <tr><td class="acell">
        <div class="sec-h"><span class="bar"></span>@include('componentes._icon', ['name' => 'clipboard-check'])<h2>Checklist ejecutado</h2><span class="line"></span></div>
        <div class="veh-chk-row head">
          <div class="c1">Punto</div><div class="c2">Código</div><div class="c3">Resultado</div><div class="c4">Foto</div>
        </div>
      </td></tr>
      @foreach ($groups as $mod => $rows)
        <tr><td class="acell"><div class="veh-mod-h">{{ $moduleNames[$mod] ?? ucfirst($mod) }}</div></td></tr>
        @foreach ($rows as $s)
          @php
            $fail = ($s['answer'] ?? '') === 'fail';
            $ct   = $classTag[$s['class'] ?? 'minor'] ?? ['Menor','min'];
            $thumb = ! empty($s['photo_path']) ? Storage::url($s['photo_path']) : null;
          @endphp
          <tr><td class="acell chkcell {{ $fail ? 'fail' : '' }}">
            <div class="veh-chk-row">
              <div class="c1">
                {{ $s['text'] ?? '' }}
                <span class="veh-tag {{ $ct[1] }}">{{ $ct[0] }}</span>
                @if (! empty($s['reparado']))<span class="veh-tag rep">Reparado</span>@endif
              </div>
              <div class="c2">{{ $s['code'] ?? '' }}</div>
              <div class="c3">@if ($fail)<span class="veh-res bad">FALLA</span>@else<span class="veh-res ok">Cumple</span>@endif</div>
              <div class="c4">@if ($thumb)<a href="{{ $thumb }}" target="_blank" rel="noopener"><img class="veh-thumb" src="{{ $thumb }}" alt="{{ $s['code'] ?? '' }}"></a>@endif</div>
            </div>
          </td></tr>
        @endforeach
      @endforeach
      <tr><td class="acell" style="padding-top:14px"></td></tr>
      @endif

      {{-- ============ OBSERVACIONES ============ --}}
      @if ($inspection->observations)
      <tr><td class="acell">
      <section class="sec">
        <div class="sec-h"><span class="bar"></span>@include('componentes._icon', ['name' => 'info'])<h2>Resumen y recomendaciones</h2><span class="line"></span></div>
        <p class="desc" style="white-space:pre-line">{{ $inspection->observations }}</p>
      </section>
      </td></tr>
      @endif

      {{-- ============ SELLO SHA + QR + CADENA ============ --}}
      <tr><td class="acell">
      <section class="sec">
        <div class="sec-h"><span class="bar"></span>@include('componentes._icon', ['name' => 'shield-check'])<h2>Sello digital</h2><span class="line"></span></div>
        @include('componentes._seal-cfdi', ['doc' => $inspection, 'folio' => $inspection->folio(), 'prefix' => 'CREWCARE-VEHI'])
      </section>
      </td></tr>

    </tbody>
    <tfoot><tr><td><div class="footer-spacer"></div></td></tr></tfoot>
    </table>
@include('componentes._report-v2-foot', [
    'footPreparedName' => ($inspection->inspector_name ?: '—'),
    'footPreparedMeta' => $footMeta,
    'footUuid'         => $footUuid,
])
</body>
</html>
