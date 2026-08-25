{{-- ============================================================================================
     PDF DE RECHAZO (§7). Solo los puntos REPROBADOS, su clase, su foto y el nivel. Se envía al
     proveedor o dueño; NO entra a ningún panel de producción. Visible solo para safety/transpo.
     Mismo chrome sellado que el acta, pero sin checklist completo ni sello (es un extracto).
============================================================================================ --}}
@php
    use App\Support\Branding;
    use App\Support\VehicleVerdict;
    use Illuminate\Support\Facades\Storage;

    $en        = app()->getLocale() === 'en';
    $brand     = isset($branding) && is_array($branding) ? $branding : [];
    $brandName = ($brand['brand_name'] ?? Branding::get('brand_name', 'CrewCare')) ?: 'CrewCare';
    $primary   = $brand['primary_color'] ?? (Branding::get('primary_color', '#ff9900') ?: '#ff9900');

    $failed = $inspection->failedPoints();
    $classTag = ['critical' => ['Crítico', 'crit'], 'major' => ['Mayor', 'maj'], 'minor' => ['Menor', 'min']];
    $moduleNames = [
        'nucleo' => 'Núcleo', 'carga' => 'Carga', 'ocupacion' => 'Alta ocupación',
        'habitables' => 'Instalaciones habitables', 'energia' => 'Energía y aparatos',
        'agua' => 'Agua a bordo', 'remolque' => 'Remolque', 'electrica' => 'Tracción eléctrica',
    ];
    $heroModule = 'Rechazo de verificación de vehículo';
@endphp
<!DOCTYPE html>
<html lang="{{ $en ? 'en' : 'es' }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $inspection->folio() }} · Rechazo — {{ $brandName }}</title>
@include('componentes._report-v2-head')
<style>
  .report-wrap td.acell{ padding:0 var(--pad); }
  .report-wrap td.acell-top{ padding-top:var(--pad); }
  @media print{ .report-wrap td.acell{ padding:0 12mm; } .report-wrap td.acell-top{ padding-top:10mm; } }
  .rej-banner{ display:flex; align-items:center; gap:14px; margin:0 0 12px; padding:15px 18px;
    border:1px solid var(--stroke); border-left:5px solid var(--danger); border-radius:var(--radius-sm);
    background:color-mix(in srgb, var(--danger) 9%, var(--panel)); break-inside:avoid;
    -webkit-print-color-adjust:exact; print-color-adjust:exact; }
  .rej-banner .vic{ flex:none; width:34px; height:34px; color:var(--danger); }
  .rej-banner .vic svg{ width:34px; height:34px; }
  .rej-banner h2{ margin:0; font-family:var(--poster); font-weight:900; font-style:italic; text-transform:uppercase;
    font-size:1.25rem; color:var(--danger); line-height:1.05; }
  .rej-banner p{ margin:4px 0 0; font-size:.85rem; color:var(--text); }
  .rej-row{ display:grid; grid-template-columns:1fr 110px 48px; gap:10px; align-items:start;
    padding:9px 0; border-bottom:1px solid var(--stroke); font-size:.82rem; }
  .rej-mod-h{ font-size:.62rem; text-transform:uppercase; letter-spacing:.09em; color:var(--muted); font-weight:800; margin:8px 0 2px; }
  .veh-tag{ display:inline-block; font-size:.56rem; font-weight:700; text-transform:uppercase; letter-spacing:.04em;
    border:1px solid var(--stroke); border-radius:20px; padding:2px 8px; margin:2px 4px 0 0; color:var(--muted); }
  .veh-tag.crit{ color:var(--danger); border-color:color-mix(in srgb,var(--danger) 40%,transparent); }
  .veh-tag.maj{ color:var(--warn); border-color:color-mix(in srgb,var(--warn) 40%,transparent); }
  .veh-thumb{ width:44px; height:44px; border-radius:5px; object-fit:cover; border:1px solid var(--stroke); }
</style>
</head>
<body>
<div class="stage">
  <article class="sheet">
    <table class="report-wrap">
    <thead><tr><td>
      @include('componentes._doc-hero', ['heroImage' => $inspection->unitPhotoUrl(), 'heroProject' => $brandName, 'heroHideCallbox' => true, 'heroModule' => $heroModule])
    </td></tr></thead>
    <tbody>
    <tr><td class="acell acell-top">
      <div class="rej-banner">
        <span class="vic">@include('componentes._icon', ['name' => 'octagon-alert', 'label' => null])</span>
        <div>
          <h2>Vehículo NO APTO</h2>
          <p>{{ trim(($inspection->make ?: '') . ' ' . ($inspection->model ?: '')) ?: $inspection->type_name }} · Placas {{ $inspection->plate ?: '—' }} · {{ $inspection->folio() }}</p>
          <p style="color:var(--muted)">Nivel interno: {{ VehicleVerdict::levelLabel($inspection->level) }} · Se requiere corrección y reevaluación aprobatoria.</p>
        </div>
      </div>
    </td></tr>
    <tr><td class="acell">
      <div class="sec-h"><span class="bar"></span>@include('componentes._icon', ['name' => 'clipboard-list'])<h2>Puntos reprobados</h2><span class="line"></span></div>
    </td></tr>
    @php $groups = []; foreach ($failed as $s) { $groups[$s['module'] ?? 'nucleo'][] = $s; } @endphp
    @forelse ($groups as $mod => $rows)
      <tr><td class="acell"><div class="rej-mod-h">{{ $moduleNames[$mod] ?? ucfirst($mod) }}</div></td></tr>
      @foreach ($rows as $s)
        @php $ct = $classTag[$s['class'] ?? 'minor'] ?? ['Menor','min']; $thumb = ! empty($s['photo_path']) ? Storage::url($s['photo_path']) : null; @endphp
        <tr><td class="acell">
          <div class="rej-row">
            <div>{{ $s['text'] ?? '' }} <span class="veh-tag {{ $ct[1] }}">{{ $ct[0] }}</span></div>
            <div class="mono" style="font-size:.74rem">{{ $s['code'] ?? '' }}</div>
            <div style="text-align:right">@if ($thumb)<a href="{{ $thumb }}" target="_blank" rel="noopener"><img class="veh-thumb" src="{{ $thumb }}" alt="{{ $s['code'] ?? '' }}"></a>@endif</div>
          </div>
        </td></tr>
      @endforeach
    @empty
      <tr><td class="acell"><p class="desc">Sin puntos reprobados registrados.</p></td></tr>
    @endforelse
    <tr><td class="acell" style="padding-top:16px"><p class="desc" style="font-size:.76rem;color:var(--muted)">Documento de rechazo — {{ $inspection->folio() }} · {{ optional($inspection->created_at)->format('d/m/Y') }}. Corrija los puntos y solicite una reevaluación.</p></td></tr>
    </tbody>
    <tfoot><tr><td><div class="footer-spacer"></div></td></tr></tfoot>
    </table>
</body>
</html>
