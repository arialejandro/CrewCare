{{-- ============================================================================================
     DAILY SAFETY REPORT (DSR) — v2 "Cinematic Dark Glass". Documento STANDALONE (no layout).
     Ruta: GET /daily_reports/{id} → name daily_reports.show → DailyReportController@show.
     Chrome compartido: _report-v2-head (fuentes+CSS+motor impresión) · _report-v2-toolbar · _report-v2-foot.
     DOS CARAS: vidrio cinematográfico en pantalla / documento blanco firmable al Exportar PDF (data-view=print).
     PHP 7.4. Guards Schema::hasColumn/hasTable (columnas del delta módulos 6-14). i18n reports.* (?lang=en|es).
     El expediente clínico (DailyReport = "el llamado" de seguridad del día) NO es COVID: es registro vivo.
     El 5×5 es propio del Amazon MGM; aquí el heatmap de cumplimiento se muestra con chips de riesgo.
     Ghost del diseño anterior: admin/dailyreports/show-legacy.blade.php (revertir = renombrar).
============================================================================================ --}}
@php
    use Illuminate\Support\Facades\Schema;
    if (in_array(request('lang'), ['es', 'en'], true)) { app()->setLocale(request('lang')); }
    $brand     = isset($branding) && is_array($branding) ? $branding : [];
    $brandName = $brand['brand_name'] ?? 'CrewCare';
    $primary   = $brand['primary_color'] ?? '#ff9900';

    $r = $report;

    // ---- Hero: nombre de proyecto + "llamado" (setting · locación · time) + fecha/CALL ----
    $heroDate = $r->report_date ? \Carbon\Carbon::parse($r->report_date)->format('d M Y') : null;
    $callTime = $r->call_time ? \Carbon\Carbon::parse($r->call_time)->format('H:i') : null;
    $locParts = array_filter([$r->slug_setting, $r->location_name, $r->slug_time], function ($v) { return trim((string) $v) !== ''; });
    $heroLoc  = count($locParts) ? implode(' · ', $locParts) : (string) $r->location_name;

    // ---- Clima (emoji + rango) — el mapeo cubre las condiciones ampliadas del create ----
    $wxIcons = ['sunny' => '☀️', 'cloudy' => '☁️', 'rainy' => '🌧️', 'storm' => '⛈️', 'hail' => '🌨️', 'snow' => '❄️', 'windy' => '💨', 'extreme_heat' => '🥵'];
    $wxEmoji = isset($wxIcons[$r->weather_condition]) ? $wxIcons[$r->weather_condition] : '☁️';
    $hasTemp   = trim((string) $r->weather_min_temp) !== '' || trim((string) $r->weather_max_temp) !== '';
    $tempRange = $hasTemp ? ($r->weather_min_temp . '° / ' . $r->weather_max_temp . '°C') : '—';

    // ---- Cintillo: la banda oscura NO repite el nombre del proyecto (ya vive en el hero); solo stats. ----
    $titleLoc  = $r->location_name ?: ($heroDate ?: __('reports.dsr_module')); // solo para <title>/h1 a11y

    // ---- Junta de seguridad ----
    // (2026-07-21) TRES estados, no dos. `safety_meeting_held` es nullable a propósito:
    //   null  → reporte anterior a la columna: se comporta como siempre (hora o "—").
    //   false → declarado NO realizado: se dice, y se APAGA la hora. Esto termina con la
    //           contradicción "No hubo safety meeting · 14:00 HRS" que salía en el
    //           documento — que NO era un bug de la vista sino DATO: cuando los temas
    //           eran texto libre alguien escribió ahí que no hubo junta, y aparte llenó
    //           la hora. Los temas también se ocultan: si no hubo junta no hubo temas,
    //           y ese texto es justamente el que generaba la confusión (el original se
    //           conserva intacto en la BD: se oculta en el acta, no se borra del registro).
    //   true  → realizado: hora + temas + foto en gran angular del crew.
    $meetingTime  = $r->safety_meeting_time ? \Carbon\Carbon::parse($r->safety_meeting_time)->format('H:i') : null;
    // Banderas de esquema resueltas por el CONTROLADOR (Schema::hasTable/hasColumn no están
    // cacheados en Laravel 8: cada llamada pega a information_schema). Antes se evaluaban
    // DENTRO del @foreach de hallazgos: un DSR de 8 hallazgos gastaba 9 consultas sólo en
    // preguntar por `standardables` y otras 24 en preguntar por la columna de mitigación.
    // El `??` deja la vista a prueba de un render que no venga del controlador.
    $ccHasStdPivot    = isset($ccHasStdPivot)    ? $ccHasStdPivot    : Schema::hasTable('standardables');
    $ccHasActionItems = isset($ccHasActionItems) ? $ccHasActionItems : Schema::hasTable('action_items');
    $ccHasMitCol      = isset($ccHasMitCol)      ? $ccHasMitCol      : ($ccHasActionItems && Schema::hasColumn('action_items', 'mitigation_image_path'));
    $ccHasMeetHeld  = Schema::hasColumn('daily_reports', 'safety_meeting_held');
    $ccHasMeetPhoto = Schema::hasColumn('daily_reports', 'safety_meeting_photo_path');
    $meetHeld    = $ccHasMeetHeld ? $r->safety_meeting_held : null;   // true | false | null
    $meetDenied  = ($meetHeld === false);
    $meetPhoto   = $ccHasMeetPhoto ? $r->safety_meeting_photo_path : null;
    // TEMAS: en la BD se guardan las CLAVES del catálogo de eventos (stunts_vehicular,
    // pyro_sfx…), no las etiquetas, para no desbordar el varchar(255). Aquí se traducen al
    // idioma activo. Los reportes viejos guardaron TEXTO LIBRE ("Rutas de evacuación") y
    // los de la lista industrial anterior también: cualquier token que no sea una clave
    // conocida se pinta tal cual, así que el histórico se sigue leyendo igual que siempre.
    $meetTopics = '';
    if (!$meetDenied && trim((string) $r->safety_meeting_topics) !== '') {
        $catLabels  = \App\Models\HazardEvent::categoriesLocalized();
        $meetTopics = implode(' · ', array_map(function ($t) use ($catLabels) {
            $t = trim($t);
            return isset($catLabels[$t]) ? $catLabels[$t] : $t;
        }, array_filter(explode(',', (string) $r->safety_meeting_topics), function ($t) {
            return trim($t) !== '';
        })));
    }

    // ---- Soporte médico (sub de la tira de metadatos: ambulancia · médico) ----
    $medBits = array_filter([
        trim((string) $r->ambulance_company) !== '' ? __('reports.dsr_ambulance') . ': ' . $r->ambulance_company : null,
        trim((string) $r->medic_name)        !== '' ? __('reports.dsr_medic_abbr') . ': ' . $r->medic_name       : null,
    ]);
    $medSub = count($medBits) ? implode(' · ', $medBits) : null;

    // ---- Columnas del delta (módulos 6-9): defensivo por si prod no tiene el SQL ----
    $ccHasHumidity = Schema::hasColumn('daily_reports', 'humidity');
    $ccHasWind     = Schema::hasColumn('daily_reports', 'wind_speed');
    $ccHasHeat     = Schema::hasColumn('daily_reports', 'heat_index');
    $ccHasPpe      = Schema::hasColumn('daily_reports', 'required_ppe');
    $ccHasRisk     = Schema::hasColumn('daily_reports', 'day_risk_factors');

    $ccHumidity = $ccHasHumidity ? $r->humidity  : null;
    $ccWind     = $ccHasWind     ? $r->wind_speed : null;
    $ccHeat     = $ccHasHeat     ? $r->heat_index : null;
    // ---- EPP VIVO (2026-07-22) ----
    // El EPP del día ya no es la lista congelada del alta: es la UNIÓN de lo declarado al
    // abrir la jornada MÁS lo que fue exigiendo cada hallazgo a través de su evento del
    // catálogo. Así el acta refleja lo que el día realmente pidió, no lo que se supuso a
    // las 6 de la mañana. Se distinguen las dos procedencias para que se lea de dónde salió
    // cada pieza — si todo se mezclara, el documento afirmaría que el arnés se previó desde
    // el inicio cuando en realidad lo trajo una escena que entró a media jornada.
    $ccPpeAlta     = ($ccHasPpe && is_array($r->required_ppe)) ? array_values(array_filter($r->required_ppe)) : [];
    $ccHasLogPpe   = Schema::hasColumn('daily_logs', 'required_ppe');
    $ccPpeHallazgo = [];
    if ($ccHasLogPpe) {
        foreach ($r->logs as $l) {
            if (!is_array($l->required_ppe)) { continue; }
            foreach ($l->required_ppe as $pieza) {
                $pieza = trim((string) $pieza);
                if ($pieza !== '' && !in_array($pieza, $ccPpeAlta, true)) {
                    $ccPpeHallazgo[$pieza] = $pieza; // dedup por clave
                }
            }
        }
    }
    $ccPpeHallazgo = array_values($ccPpeHallazgo);
    $ccPpe = array_merge($ccPpeAlta, $ccPpeHallazgo);
    $ccRisk = ($ccHasRisk && is_array($r->day_risk_factors)) ? array_filter($r->day_risk_factors) : [];

    $ccShowEnv = $hasTemp || trim((string) $r->weather_condition) !== ''
        || $ccHumidity !== null || $ccWind !== null || $ccHeat !== null
        || count($ccPpe) > 0 || count($ccRisk) > 0;

    // ---- Firma digital / no-repudio ----
    // (2026-07-22) El estado de la firma ya NO se calcula aquí: lo resuelve por su cuenta el
    // parcial componentes/_seal-cfdi, que además imprime la cadena verificable. Calcularlo
    // también aquí significaba hacer dos veces la misma consulta y el mismo SHA-256 por render.

    // ---- URLs de boletín por norma (para los links no-print de cada log) ----
    $stdUrls = $standards->pluck('reference_url', 'regulation_code');

    // ---- Folio / UUID del pie ----
    $footUuid = 'UUID: ' . $brandName . '-DSR-' . (16210 + $r->id) . '-' . \Carbon\Carbon::parse($r->created_at)->format('dmY') . ' | ' . config('crewcare.doc_version');

    // ---- RISK HEATMAP: SOLO los iconos de los riesgos REALMENTE presentes (sin ausentes, sin radios).
    //      Un riesgo está presente si (a) un log del día lo disparó vía su boletín ($heatmap del ctrl),
    //      o (b) fue capturado en day_risk_factors del reporte ($ccRisk). Cada categoría: icono SVG del
    //      catálogo (_icon) + etiqueta corta i18n. Catálogo: [key, icono, clave i18n, agujas day_risk, heatmap key].
    $hzCatalog = [
        ['electrical', 'zap',           'dsr_hz_electrical', ['eléctric', 'electric'],                                     'electrical'],
        ['traffic',    'truck',         'dsr_hz_traffic',    ['tráfic', 'trafic', 'vialidad', 'tránsito', 'transito', 'traffic'], 'traffic'],
        ['heights',    'trending-up',   'dsr_hz_heights',    ['altura', 'height'],                                         'heights'],
        ['fire',       'flame',         'dsr_hz_fire',       ['fuego', 'calor', 'incendio', 'fire', 'heat'],               'fire'],
        ['confined',   'box',           'dsr_hz_confined',   ['confinad', 'confined'],                                     null],
        ['chemical',   'flask-conical', 'dsr_hz_chemical',   ['químic', 'quimic', 'chemical'],                             null],
        ['loads',      'weight',        'dsr_hz_loads',      ['carga', 'suspendid', 'load'],                               null],
    ];
    $riskLower = array_map(function ($f) { return \Illuminate\Support\Str::lower(trim((string) $f)); }, $ccRisk);
    $hzPresent = [];
    foreach ($hzCatalog as $hz) {
        $on = ($hz[4] !== null && !empty($heatmap[$hz[4]]));
        if (!$on) {
            foreach ($riskLower as $rf) {
                if ($rf === '') { continue; }
                foreach ($hz[3] as $needle) {
                    if (strpos($rf, $needle) !== false) { $on = true; break 2; }
                }
            }
        }
        if ($on) { $hzPresent[] = $hz; }
    }

    $hasFlash = session()->has('success') || session()->has('error') || (isset($errors) && $errors->any());
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $brandName }} · {{ __('reports.dsr_module') }} · {{ $titleLoc }}</title>
@include('componentes._report-v2-head')
<style>
  /* Rejilla de logs del día (bitácora): 2 col en pantalla/papel, 1 col en móvil. Cada tarjeta
     evita partirse entre hojas. NO re-glasea impresión (los tonos de papel salen de _report-v2-head). */
  .dsr-log-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:14px}
  .dsr-log-card{background:var(--panel);border:1px solid var(--stroke);border-radius:var(--radius-sm);overflow:hidden;display:flex;flex-direction:column;break-inside:avoid}
  .dsr-log-photo{position:relative;aspect-ratio:16/9;background:#141a26;overflow:hidden}
  .dsr-log-photo img{width:100%;height:100%;object-fit:cover;display:block}
  .dsr-log-time{position:absolute;top:8px;left:8px;font-family:var(--mono);font-size:.62rem;font-weight:600;color:#fff;background:rgba(0,0,0,.6);padding:3px 7px;border-radius:6px;letter-spacing:.03em}
  .dsr-log-auto{position:absolute;top:8px;right:8px;font-size:.58rem;font-weight:700;color:#fff;background:rgba(124,58,237,.85);padding:3px 7px;border-radius:6px}
  .dsr-log-b{padding:13px 14px;display:flex;flex-direction:column;gap:9px;flex:1}
  .dsr-log-top{display:flex;justify-content:space-between;gap:10px;align-items:flex-start}
  .dsr-log-desc{margin:0;font-size:.84rem;font-weight:700;color:var(--text);line-height:1.3}
  .dsr-log-code{font-family:var(--mono);font-size:.6rem;color:var(--faint);margin-top:3px;text-align:right}
  .dsr-log-action{font-size:.74rem;color:var(--ok);background:color-mix(in srgb,var(--ok) 10%,transparent);border-left:2px solid var(--ok);padding:6px 9px;border-radius:0 7px 7px 0}
  .dsr-pdca{margin-top:auto;padding-top:8px;border-top:1px solid var(--stroke);display:flex;flex-direction:column;gap:5px}
  .dsr-pdca-row{display:flex;align-items:center;justify-content:space-between;gap:8px;font-size:.68rem}
  .dsr-pdca-who{display:inline-flex;align-items:center;gap:5px;color:var(--muted);min-width:0;overflow:hidden}
  .dsr-pdca-who .who{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
  .dsr-pdca-tag{font-weight:700;letter-spacing:.08em;color:var(--faint)}
  /* Resolución del hallazgo: quién cerró y cuándo (SÍ se imprime: es parte del acta). */
  .dsr-pdca-done{font-size:.66rem;color:var(--ok);display:flex;align-items:baseline;gap:5px;flex-wrap:wrap}
  .dsr-pdca-pend{font-size:.66rem;color:var(--warn);font-style:italic}
  .dsr-meet-no{color:var(--warn)}
  /* CONTRASTE EN PAPEL. --ok (#34D399) y --warn (#FBBF24) están calibrados para el vidrio
     OSCURO de pantalla; sobre el blanco del documento firmable dan 1.9:1 y 1.7:1, o sea
     ilegibles. El chrome compartido re-tokeniza para papel --bg/--text/--muted… pero NO
     estos dos. Se corrigen aquí, en el DSR, para no alterar la impresión de los otros 4
     reportes; si algún día se homologa, el lugar correcto son los dos bloques de tokens de
     papel de _report-v2-head. Sin esto, las tres declaraciones de cumplimiento que este
     paso agrega al acta (resolución del hallazgo, hallazgo pendiente y junta no realizada)
     serían justo las que no se leen en la salida impresa.
     OJO: nada de citar aquí el TEXTO de la interfaz — este comentario viaja al navegador
     dentro del <style> y cualquier cadena que se escriba queda presente en el HTML pase lo
     que pase, lo que despista a quien busque ese texto para saber si la vista lo pintó. */
  :root[data-view="print"] .dsr-pdca-done{color:#15803d}
  :root[data-view="print"] .dsr-pdca-pend,
  :root[data-view="print"] .dsr-meet-no{color:#c2410c}
  @media print{
    .dsr-pdca-done{color:#15803d!important}
    .dsr-pdca-pend,.dsr-meet-no{color:#c2410c!important}
  }
  /* Evidencia de la solución (foto de mitigación). Miniatura para no inflar la hoja. */
  .dsr-pdca-eviden{margin-top:5px}
  .dsr-pdca-eviden img{max-width:120px;max-height:90px;object-fit:cover;border-radius:6px;border:1px solid var(--stroke);display:block}
  /* Controles de cierre: fuera del papel (el PDF firmable no lleva botones). */
  .dsr-pdca-ops{margin-top:6px;padding-top:6px;border-top:1px dashed var(--stroke);display:flex;flex-wrap:wrap;gap:8px;align-items:center}

  /* ===== NORMAS DEL HALLAZGO (N:M) — ver componentes/_standards-chips =====
     Estas reglas viven AQUÍ y no en el chrome compartido a propósito: hoy el parcial
     sólo lo usa el DSR y el chrome lo comparten los 5 reportes. Cuando los otros 4
     adopten el parcial, MOVER este bloque a _report-v2-head y borrarlo de aquí. */
  .std-row{display:flex;flex-wrap:wrap;gap:6px}
  .std-stack{display:flex;flex-direction:column;gap:5px;align-items:flex-end}
  .std-one{display:inline-flex;align-items:center;gap:6px;font-size:.62rem;line-height:1.25;
    border:1px solid var(--stroke);border-radius:7px;padding:3px 7px;max-width:100%}
  .std-code{font-family:var(--mono);color:var(--muted);white-space:nowrap}
  .std-cat{color:var(--faint);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
  .std-link{font-weight:700;color:var(--brand);text-decoration:none;white-space:nowrap}
  /* En la tarjeta basta marco + código: el nombre de la categoría satura la rejilla. */
  .std-row .std-cat{display:none}

  /* EPP heredado del evento, dentro de la tarjeta del hallazgo. Discreto: es contexto,
     no el titular — el titular es qué pasó. */
  .dsr-log-ppe{display:flex;flex-wrap:wrap;gap:4px 6px;align-items:baseline;font-size:.66rem}
  .dsr-log-ppe .k{color:var(--faint);text-transform:uppercase;letter-spacing:.06em;font-weight:700}
  .dsr-log-ppe .p{color:var(--muted);border:1px solid var(--stroke);border-radius:5px;padding:1px 6px}

  /* ===== SAFETY MEETING — foto en gran angular del crew reunido ===== */
  .dsr-meet-photo{margin-top:12px}
  .dsr-meet-photo img{width:100%;max-height:280px;object-fit:cover;border-radius:var(--radius-sm);
    border:1px solid var(--stroke);display:block}
  .dsr-meet-photo figcaption{font-size:.66rem;color:var(--faint);margin-top:5px;text-align:center}
  /* Formularios operativos (no-print): <details> nativo, sin JS/Bootstrap. */
  .dsr-form{margin-top:12px;display:flex;flex-direction:column;gap:10px;max-width:660px}
  .dsr-form label,.dsr-form .lbl{font-size:.72rem;color:var(--muted);display:block;margin-bottom:4px}
  .dsr-form .field{width:100%}
  .dsr-form textarea.field{height:auto;min-height:64px;padding:9px 12px;line-height:1.4;resize:vertical}
  .dsr-ops details>summary{cursor:pointer;list-style:none}
  .dsr-ops details>summary::-webkit-details-marker{display:none}

  /* ===== TIRA DE METADATOS (2ª fila): Safety Meeting · Risk Heatmap · Medical Support =====
     Edge-to-edge bajo la banda oscura; hereda los tokens del chrome (claro/oscuro/print). */
  .dsr-meta{display:flex;border-bottom:1px solid var(--stroke);background:rgba(0,0,0,.14)}
  :root[data-view="print"] .dsr-meta{background:#f4f7fb}
  .dsr-meta .mcell{flex:1;display:flex;gap:11px;padding:11px 16px;border-right:1px solid var(--stroke);min-width:0;align-items:flex-start}
  .dsr-meta .mcell:last-child{border-right:0}
  .dsr-meta .mcell.center{flex-direction:column;align-items:center;justify-content:center;text-align:center;gap:7px}
  .dsr-meta .mico{width:30px;height:30px;border-radius:8px;display:grid;place-items:center;flex:none;color:var(--brand);background:color-mix(in srgb,var(--brand) 14%,transparent)}
  .dsr-meta .mico.med{color:var(--danger);background:color-mix(in srgb,var(--danger) 14%,transparent)}
  .dsr-meta .mico svg{width:16px;height:16px}
  .dsr-meta .mbody{min-width:0}
  .dsr-meta .mlbl{font-size:.55rem;letter-spacing:.14em;text-transform:uppercase;color:var(--faint);font-weight:700;margin-bottom:3px}
  .dsr-meta .mv{font-size:.82rem;font-weight:700;color:var(--text);line-height:1.2;word-break:break-word}
  .dsr-meta .msub{font-size:.72rem;color:var(--muted);line-height:1.3;margin-top:2px;word-break:break-word}
  /* Iconos SOLO de los riesgos presentes (sin radios, sin ausentes). */
  .dsr-hz{display:flex;flex-wrap:wrap;gap:9px;justify-content:center}
  .dsr-hz .hz{display:flex;flex-direction:column;align-items:center;gap:3px;width:52px;text-align:center}
  .dsr-hz .hz .ic{width:30px;height:30px;border-radius:9px;display:grid;place-items:center;color:var(--danger);
    background:color-mix(in srgb,var(--danger) 12%,transparent);border:1px solid color-mix(in srgb,var(--danger) 34%,transparent)}
  .dsr-hz .hz .ic svg{width:16px;height:16px}
  .dsr-hz .hz .l{font-size:.5rem;line-height:1.1;color:var(--muted);text-transform:uppercase;letter-spacing:.02em}
  .dsr-hz .none{color:var(--faint);font-size:.9rem;font-weight:700}
  @media (max-width:720px){
    .dsr-log-grid{grid-template-columns:1fr}
    .dsr-meta{flex-direction:column}
    .dsr-meta .mcell{border-right:0;border-bottom:1px solid var(--stroke)}
    .dsr-meta .mcell:last-child{border-bottom:0}
  }
  @media print{.dsr-meta{background:#f4f7fb!important;-webkit-print-color-adjust:exact;print-color-adjust:exact}}
</style>
{{-- ============================================================================================
     FIX PAGINACIÓN DSR (scoped, SOLO papel) — 2026-07-16.
     SÍNTOMA (solo DSR): en export/print la HOJA 1 deja un hueco grande tras el Resumen Ejecutivo y
     el contenido salta a la hoja 2. CAUSA: la BITÁCORA (la <section class="sec"> que envuelve
     .dsr-log-grid) es el ÚNICO bloque alto y de altura VARIABLE del DSR (crece con el nº de logs).
     El chrome compartido marca TODA .sec con `break-inside:avoid`; cuando la bitácora no cabe en el
     espacio que queda tras la 1ª sección, `avoid` la empuja ENTERA a la hoja 2 → hueco en la hoja 1.
     (Los otros 4 reportes no lo sufren porque sus secciones son chicas y caben.)
     ARREGLO MÍNIMO: SOLO a la sección de la bitácora (y a su rejilla) se les permite FLUIR/partir
     entre hojas; cada TARJETA de log sigue ÍNTEGRA (.dsr-log-card ya es break-inside:avoid, se
     reafirma aquí). No toca el chrome, ni el hero/banda, ni las otras 3 secciones (siguen `avoid`).
     Puro comportamiento de paginación en @media print → el vidrio en pantalla queda intacto. ============================================================================================ --}}
<style>
  @media print{
    /* La bitácora es alta y variable → debe FLUIR entre hojas para llenar la hoja 1, no saltar entera. */
    .body .sec:has(.dsr-log-grid){break-inside:auto;page-break-inside:auto}
    .dsr-log-grid{break-inside:auto;page-break-inside:auto}
    /* Pero cada tarjeta de log se mantiene ÍNTEGRA (no se parte a la mitad entre hojas). */
    .dsr-log-card{break-inside:avoid;page-break-inside:avoid}
    /* El encabezado de la bitácora no queda huérfano al pie de la hoja. */
    .body .sec:has(.dsr-log-grid) .sec-h{break-after:avoid;page-break-after:avoid}
  }
</style>
</head>
<body>

<div class="ambient"><div class="b b1"></div><div class="b b2"></div></div>

@include('componentes._report-v2-toolbar', ['backRoute' => route('daily_reports.index')])

<div class="stage">
  <article class="sheet">
    <table class="report-wrap">
    <thead><tr><td>
      @include('componentes._doc-hero', [
        'heroImage'    => $r->hero_image_path,
        'heroProject'  => $brandName,
        'heroLocation' => $heroLoc,
        'heroDate'     => $heroDate,
        'heroTime'     => $callTime,
        'heroModule'   => __('reports.dsr_module'),
      ])
    </td></tr></thead>
    <tbody><tr><td>

    {{-- TIRA DE METADATOS · fila 1 (banda oscura): SHOOT DAY · CREW · LOGS · MIN|MAX.
         El nombre del proyecto NO se repite aquí (vive en el hero); las 4 celdas ocupan todo el ancho. --}}
    <div class="band">
      <div class="stats">
        <div class="cell"><span class="lbl">{{ __('reports.dsr_shoot_day') }}</span><span class="v">{{ \App\Support\ProductionCalendar::labelForReport($r) }}</span></div>
        <div class="cell"><span class="lbl">{{ __('reports.dsr_crew') }}</span><span class="v">{{ ($r->crew_count !== null && $r->crew_count !== '') ? $r->crew_count : '—' }}</span></div>
        <div class="cell"><span class="lbl">{{ __('reports.dsr_logs') }}</span><span class="v ok">{{ $r->logs->count() }}</span></div>
        <div class="cell"><span class="lbl">{{ __('reports.dsr_min_max') }}</span><span class="v">{{ $wxEmoji }} {{ $tempRange }}</span></div>
      </div>
    </div>

    {{-- TIRA DE METADATOS · fila 2: SAFETY MEETING · RISK HEATMAP (iconos de riesgos presentes) · MEDICAL SUPPORT. --}}
    <div class="dsr-meta">
      <div class="mcell">
        <span class="mico">@include('componentes._icon', ['name' => 'bell'])</span>
        <div class="mbody">
          <div class="mlbl">{{ __('reports.dsr_safety_meeting') }}</div>
          @if($meetDenied)
          <div class="mv dsr-meet-no">{{ __('reports.dsr_meeting_not_held') }}</div>
          @elseif($meetHeld === true)
          {{-- (2026-07-25) Realizada: la hora si la hay; si no, se AFIRMA "Sí, se realizó" en vez de
               dejar un "—" huérfano bajo la etiqueta SAFETY MEETING —que se leía como junta faltante
               cuando SÍ ocurrió—. Un DSR nuevo cae aquí o en el "No realizado" (el <select> manda 1|0). --}}
          <div class="mv">{{ $meetingTime ? $meetingTime . ' HRS' : __('reports.dsr_meeting_held_yes') }}</div>
          @else
          {{-- Legacy SIN DECLARAR (columna null, previa a safety_meeting_held): se conserva el "—"
               honesto. Eso sí es un hueco real —nadie declaró la junta— y no se afirma nada. --}}
          <div class="mv">{{ $meetingTime ? $meetingTime . ' HRS' : '—' }}</div>
          @endif
          @if($meetTopics !== '')<div class="msub">{{ $meetTopics }}</div>@endif
        </div>
      </div>
      <div class="mcell center">
        <div class="mlbl">{{ __('reports.dsr_risk_heatmap') }}</div>
        @if(count($hzPresent))
        <div class="dsr-hz">
          @foreach($hzPresent as $hz)
          <span class="hz"><span class="ic">@include('componentes._icon', ['name' => $hz[1], 'label' => __('reports.' . $hz[2])])</span><span class="l">{{ __('reports.' . $hz[2]) }}</span></span>
          @endforeach
        </div>
        @else
        {{-- (2026-07-25) Sin riesgos registrados hoy: se DECLARA explícito en vez de un "—" que, bajo
             la etiqueta RISK HEATMAP, se leía como dato faltante. El heatmap se deriva de los hallazgos
             del día; cero hallazgos es un enunciado honesto, no un hueco. --}}
        <div class="dsr-hz"><span class="none">{{ __('reports.dsr_no_risks') }}</span></div>
        @endif
      </div>
      <div class="mcell">
        <span class="mico med">@include('componentes._icon', ['name' => 'heart-pulse'])</span>
        <div class="mbody">
          <div class="mlbl">{{ __('reports.dsr_medical_support') }}</div>
          <div class="mv">{{ trim((string) $r->nearest_hospital) !== '' ? $r->nearest_hospital : '—' }}</div>
          @if($medSub)<div class="msub">{{ $medSub }}</div>@endif
        </div>
      </div>
    </div>

    <div class="body">
      <h1 class="restricted" style="position:absolute;left:-9999px">{{ $brandName }} — {{ __('reports.dsr_module') }} — {{ $titleLoc }}</h1>

      {{-- CONTROLES OPERATIVOS (no-print): flash + candado + captura de hallazgo / cierre de día.
           Standalone: sin Bootstrap → los formularios viven inline en <details> nativos. --}}
      @if($hasFlash || $isLocked || !$isLocked)
      <div class="no-print dsr-ops" style="margin-bottom:20px;display:flex;flex-direction:column;gap:10px">
        @if(session('success'))<div class="alert ok">{{ session('success') }}</div>@endif
        @if(session('error'))<div class="alert bad">{{ session('error') }}</div>@endif
        @if(isset($errors) && $errors->any())@foreach($errors->all() as $e)<div class="alert bad">{{ $e }}</div>@endforeach @endif

        @if($isLocked)
        <div class="alert bad">🔒 <strong>Reporte sellado.</strong> Por cumplimiento normativo no admite cambios después de 24 h de su creación.</div>
        @else
        <div style="display:flex;flex-direction:column;gap:10px">
          @can('tools.inspect')
          <a class="btn" href="{{ route('tools.index', ['origin' => 'dsr', 'origin_id' => $r->id, 'moment' => 'previo_al_uso']) }}"
             style="display:inline-flex;align-items:center;gap:6px;">
             @include('componentes._icon', ['name' => 'wrench']) Inspeccionar herramienta
          </a>
          @endcan
          {{-- + Hallazgo. Gateado por el MISMO permiso que exige la ruta (dsr.create).
               Sin esto, los roles que sólo tienen dsr.view —coordinator, hod y auditor—
               veían el formulario, capturaban el hallazgo, subían la foto y al enviar se
               comían un 403 con todo lo escrito perdido. Es la misma lección que ya se
               aplicó al botón de cierre unas líneas más abajo. --}}
          @can('dsr.create')
          <details>
            <summary class="btn brand">@include('componentes._icon', ['name' => 'camera']) + Hallazgo</summary>
            <form action="{{ route('daily_logs.store', $r->id) }}" method="POST" enctype="multipart/form-data" class="dsr-form">
              @csrf
              <div>
                <span class="lbl">Hora del evento *</span>
                <input type="time" name="log_time" class="field" value="{{ date('H:i') }}" required>
              </div>
              <div>
                <span class="lbl">Evento / peligro (catálogo) *</span>
                @include('componentes._event-picker', [
                    'hazardEvents' => $hazardEvents ?? collect(),
                    'name'         => 'hazard_event_id',
                    'selected'     => old('hazard_event_id'),
                    'required'     => true,
                ])
                <div style="font-size:.68rem;color:var(--faint);margin-top:4px">Al elegir el evento se etiqueta automáticamente su norma (CSATF/STPS/OSHA).</div>
              </div>
              <div>
                <span class="lbl">Descripción de la observación *</span>
                <textarea name="description" class="field" rows="2" placeholder="Ej: Personal cruzando cerca de la grúa en movimiento…" required></textarea>
              </div>
              <div>
                <span class="lbl">Acción correctiva (mitigación)</span>
                <input type="text" name="action_taken" class="field" placeholder="Ej: Se detiene maniobra y se reubica al personal.">
              </div>
              <div>
                <span class="lbl">Foto evidencia</span>
                <input type="file" name="photo" class="field" accept="image/*,.heic,.heif" capture="environment" data-cc-photo>
              </div>
              <button class="btn brand" type="submit" style="align-self:flex-start">@include('componentes._icon', ['name' => 'check-circle']) Guardar hallazgo</button>
            </form>
          </details>
          @endcan

          {{-- Cierre de Día (wrap). Mismo permiso que exige la ruta: dsr.update. --}}
          @can('dsr.update')
          <details>
            <summary class="btn">@include('componentes._icon', ['name' => 'pencil']) Cierre de día</summary>
            <form action="{{ route('daily_reports.update', $r->id) }}" method="POST" enctype="multipart/form-data" class="dsr-form">
              @csrf
              <div>
                <span class="lbl">Resumen ejecutivo de seguridad</span>
                <textarea name="executive_summary" class="field" rows="4" placeholder="Describe cómo se desarrolló el día, si hubo incidentes notables o si todo transcurrió con normalidad…">{{ $r->executive_summary }}</textarea>
              </div>
              <div>
                <span class="lbl">Hero image (foto de portada)</span>
                <input type="file" name="hero_image" class="field" accept="image/*,.heic,.heif" data-cc-photo>
              </div>
              {{-- La foto del safety meeting también se puede subir aquí: el DSR se crea al
                   arrancar la jornada y la junta ocurre al call time, así que muchas veces
                   la foto llega después del alta. --}}
              @if($ccHasMeetPhoto && !$meetDenied)
              <div>
                <span class="lbl">{{ __('reports.dsr_meeting_photo_label') }}</span>
                <input type="file" name="safety_meeting_photo" class="field" accept="image/*,.heic,.heif" capture="environment" data-cc-photo>
                <div style="font-size:.68rem;color:var(--faint);margin-top:4px">{{ __('reports.dsr_meeting_photo_hint') }}</div>
              </div>
              @endif
              <button class="btn brand" type="submit" style="align-self:flex-start">@include('componentes._icon', ['name' => 'upload']) Actualizar reporte</button>
            </form>
          </details>
          @endcan
        </div>
        @endif
      </div>
      @endif

      {{-- RESUMEN EJECUTIVO --}}
      <section class="sec">
        <div class="sec-h"><span class="bar"></span>@include('componentes._icon', ['name' => 'file-text'])<h2>{{ __('reports.dsr_section_executive_summary') }}</h2><span class="line"></span></div>
        <p class="desc">{{ $r->executive_summary ?: __('reports.dsr_empty_executive_summary') }}</p>
      </section>

      {{-- SAFETY MEETING — evidencia. Sólo si la junta se declaró REALIZADA y hay foto:
           es la prueba que piden los estudios internacionales. Si no hubo junta, el
           bloque no existe (y la tira de metadatos ya lo declaró sin rodeos). --}}
      @if($meetPhoto && !$meetDenied)
      <section class="sec">
        <div class="sec-h"><span class="bar"></span>@include('componentes._icon', ['name' => 'users'])<h2>{{ __('reports.dsr_safety_meeting') }}</h2><span class="line"></span></div>
        <figure class="dsr-meet-photo">
          <img src="{{ $meetPhoto }}" alt="{{ __('reports.dsr_meeting_photo') }}" loading="lazy">
          <figcaption>{{ __('reports.dsr_meeting_photo') }}@if($meetingTime) · {{ $meetingTime }} HRS @endif @if($r->crew_count)· {{ $r->crew_count }} {{ __('reports.dsr_crew') }}@endif</figcaption>
        </figure>
      </section>
      @endif

      {{-- (Safety Meeting, Risk Heatmap y Medical Support viven ahora en la TIRA DE METADATOS de arriba;
           aquí abajo van solo los bloques de detalle: ambientales/EPP, bitácora y sello de firma.) --}}

      {{-- BITÁCORA & CUMPLIMIENTO (logs del día) --}}
      <section class="sec">
        <div class="sec-h"><span class="bar"></span>@include('componentes._icon', ['name' => 'clipboard-list'])<h2>{{ __('reports.dsr_section_log_compliance') }}</h2><span class="line"></span></div>
        @if($r->logs->count())
        <div class="dsr-log-grid">
          @foreach($r->logs as $log)
          <div class="dsr-log-card">
            @if($log->photo_path)
            <div class="dsr-log-photo">
              <img src="{{ $log->photo_path }}" loading="lazy" alt="{{ $log->description }}">
              @if($log->log_time)<span class="dsr-log-time">{{ \Carbon\Carbon::parse($log->log_time)->format('H:i') }} HRS</span>@endif
              @if($log->is_injected)<span class="dsr-log-auto">⚙ {{ __('reports.label_auto_short') }}</span>@endif
            </div>
            @endif
            <div class="dsr-log-b">
              <div class="dsr-log-top">
                <p class="dsr-log-desc">{{ $log->description }}</p>
              </div>

              {{-- NORMAS DEL HALLAZGO. Con el N:M (standardables) se estampan TODAS las del
                   evento — el catálogo mapea de 2 a 4 marcos por evento, así que el snapshot
                   plano de una sola norma venía tirando la mayor parte. El parcial degrada
                   solo al snapshot para los hallazgos históricos (hazard_event_id NULL). --}}
              @include('componentes._standards-chips', [
                  'standards' => ($ccHasStdPivot && $log->relationLoaded('standards')) ? $log->standards : collect(),
                  'snapBadge' => $log->regulation_badge,
                  'snapCode'  => $log->regulation_code,
                  'snapUrl'   => $stdUrls[$log->regulation_code] ?? null,
                  'stdLayout' => 'row',
              ])

              {{-- EPP que exigió ESTE hallazgo, heredado de su evento del catálogo. --}}
              @if($ccHasLogPpe && is_array($log->required_ppe) && count($log->required_ppe))
              <div class="dsr-log-ppe">
                <span class="k">{{ __('reports.label_required_ppe') }}:</span>
                @foreach($log->required_ppe as $pieza)<span class="p">{{ $pieza }}</span>@endforeach
              </div>
              @endif

              @if($log->action_taken)
              <div class="dsr-log-action"><strong>{{ __('reports.dsr_log_action') }}:</strong> {{ $log->action_taken }}</div>
              @endif

              @if($ccHasActionItems && $log->actionItems->count())
              <div class="dsr-pdca">
                @foreach($log->actionItems as $ai)
                @php
                    $st     = $ai->status;
                    $ov     = method_exists($ai, 'isOverdue') && $ai->isOverdue();
                    $closed = $st === 'closed';
                @endphp
                <div class="dsr-pdca-row">
                  <span class="dsr-pdca-who">
                    <span class="dsr-pdca-tag">PDCA</span>
                    <span class="who">{{ $ai->owner ? $ai->owner->name : '—' }}</span>
                    @if($ai->due_date)<span style="{{ $ov ? 'color:var(--danger);font-weight:700' : 'color:var(--faint)' }}">· {{ \Carbon\Carbon::parse($ai->due_date)->format('d/m/Y') }}{{ $ov ? ' · ' . __('reports.label_overdue') : '' }}</span>@endif
                  </span>
                  <span class="chip {{ $closed ? 'ok' : ($st === 'in_progress' ? '' : 'warn') }}" style="flex:none;font-size:.6rem;padding:3px 8px">{{ $closed ? __('reports.status_closed') : ($st === 'in_progress' ? __('reports.status_in_progress') : __('reports.status_open')) }}</span>
                </div>

                {{-- LAS DOS VERDADES, cada una en su momento. El día se sella a las 24 h,
                     pero corregir un hallazgo toma más (el SLA por defecto son 3 días), así
                     que es NORMAL que el documento se cierre con hallazgos abiertos. En vez
                     de esconderlo, el acta lo DICE; y cuando el hallazgo se cierra después,
                     el mismo documento muestra la resolución. Ambas líneas SÍ se imprimen:
                     son parte del acta, no controles de la app. --}}
                @if($closed)
                <div class="dsr-pdca-done">
                  @include('componentes._icon', ['name' => 'check-circle'])
                  <span><strong>{{ __('reports.dsr_finding_resolved') }}</strong>
                    @if($ai->verified_by_id && $ai->verifiedBy) · {{ $ai->verifiedBy->name }}@endif
                    @if($ai->closed_at) · {{ \Carbon\Carbon::parse($ai->closed_at)->format('d/m/Y H:i') }}@endif
                  </span>
                </div>
                @else
                <div class="dsr-pdca-pend">{{ __('reports.dsr_finding_pending') }}</div>
                @endif

                {{-- Evidencia de la solución (opcional a propósito: muchos hallazgos se
                     resuelven al instante y no da tiempo de fotografiar). --}}
                @if($ccHasMitCol && !empty($ai->mitigation_image_path))
                <figure class="dsr-pdca-eviden">
                  <img src="{{ $ai->mitigation_image_path }}" alt="{{ __('reports.dsr_finding_evidence') }}" loading="lazy">
                </figure>
                @endif

                {{-- CONTROLES (nunca se imprimen). El permiso es hazards.manage porque es el
                     que exige la ruta action_items.close; sin este @can un usuario con
                     dsr.update vería el botón y se comería un 403. --}}
                @can('hazards.manage')
                <div class="dsr-pdca-ops no-print">
                  @if(in_array($st, ['open', 'in_progress'], true))
                  <form action="{{ route('action_items.close', $ai->id) }}" method="POST">@csrf<button class="btn ok sm" type="submit">{{ __('reports.label_mark_closed') }}</button></form>
                  @else
                  <form action="{{ route('action_items.reopen', $ai->id) }}" method="POST">@csrf<button class="btn sm" type="submit">{{ __('reports.label_reopen') }}</button></form>
                  @endif
                  {{-- Sólo si AÚN no llegó la evidencia: el parcial, cuando ya existe foto,
                       pinta su propia miniatura y duplicaría la que el acta ya muestra arriba. --}}
                  @if(!($ccHasMitCol && !empty($ai->mitigation_image_path)))
                  @include('componentes._wa-mitigation-link', ['item' => $ai])
                  @endif
                </div>
                @endcan
                @endforeach
              </div>
              @endif
            </div>
          </div>
          @endforeach
        </div>
        @else
        <p class="desc" style="font-style:italic;color:var(--muted)">{{ __('reports.dsr_empty_logs') }}</p>
        @endif
      </section>

      {{-- AMBIENTALES · EPP · FACTORES DE RIESGO --}}
      @if($ccShowEnv)
      <section class="sec">
        <div class="sec-h"><span class="bar"></span>@include('componentes._icon', ['name' => 'thermometer'])<h2>{{ __('reports.dsr_section_environmental_ppe') }}</h2><span class="line"></span></div>
        <div class="panel"><div class="facts">
          <div class="fact"><div class="k">{{ __('reports.dsr_min_max') }}</div><div class="v">{{ $wxEmoji }} {{ $tempRange }}</div></div>
          @if($ccHumidity !== null)<div class="fact"><div class="k">{{ __('reports.label_humidity') }}</div><div class="v">{{ $ccHumidity }}%</div></div>@endif
          @if($ccWind !== null)<div class="fact"><div class="k">{{ __('reports.label_wind') }}</div><div class="v">{{ $ccWind }} km/h</div></div>@endif
          @if($ccHeat !== null)<div class="fact"><div class="k">{{ __('reports.label_heat_index') }}</div><div class="v" style="{{ (float) $ccHeat >= 39 ? 'color:var(--danger)' : '' }}">{{ $ccHeat }}°C</div></div>@endif
        </div></div>

        @if(count($ccRisk) > 0)
        <div style="margin-top:14px">
          <div class="fact" style="margin-bottom:7px"><div class="k">{{ __('reports.label_day_risk_factors') }}</div></div>
          <div class="chips">
            @foreach($ccRisk as $factor)<span class="chip warn">@include('componentes._icon', ['name' => 'alert-triangle']) {{ $factor }}</span>@endforeach
          </div>
        </div>
        @endif

        @if(count($ccPpe) > 0)
        <div style="margin-top:14px">
          <div class="fact" style="margin-bottom:7px"><div class="k">{{ __('reports.label_required_ppe') }}</div></div>
          <div class="chips">
            @foreach($ccPpeAlta as $ppe)<span class="chip">@include('componentes._icon', ['name' => 'shield']) {{ $ppe }}</span>@endforeach
            {{-- Lo que NO se previó al alta y exigieron los hechos del día. Se marca aparte:
                 el acta no debe dar a entender que el arnés estaba contemplado desde las 6 de
                 la mañana si en realidad lo trajo una escena que entró a media jornada. --}}
            @foreach($ccPpeHallazgo as $ppe)<span class="chip warn">@include('componentes._icon', ['name' => 'shield']) {{ $ppe }}</span>@endforeach
          </div>
          @if(count($ccPpeHallazgo))
          <div style="font-size:.66rem;color:var(--muted);margin-top:6px;font-style:italic">{{ __('reports.dsr_ppe_from_findings') }}</div>
          @endif
        </div>
        @endif
      </section>
      @endif

      {{-- VALIDACIÓN / SELLO DE INTEGRIDAD --}}
      <section class="sec">
        <div class="sec-h"><span class="bar"></span>@include('componentes._icon', ['name' => 'shield'])<h2>{{ __('reports.label_prepared_by') }}</h2><span class="line"></span></div>
        <div class="sign">
          <div class="sig"><div class="who">{{ $r->author_name ?: '—' }}</div><div class="role">{{ __('reports.label_risk_assessment') }}</div></div>
          <div class="sig"><div class="who">{{ $heroDate ?: '—' }}</div><div class="role">{{ __('reports.label_date') }}</div></div>
        </div>
        {{-- Hueco de la firma autógrafa (ver la nota equivalente en el Injury): el espacio
             en blanco sobre la línea ya se imprime y ya se puede firmar a mano. --}}
        <div style="font-size:.66rem;color:var(--faint);margin-top:8px;font-style:italic">{{ __('reports.sign_space_hint') }}</div>
        {{-- (2026-07-22) CADENA VERIFICABLE ESTILO CFDI, la misma que el Injury. Antes el DSR
             sólo pintaba el banner de integridad: no imprimía el hash, ni el UUID, ni la hora
             del sellado, así que un tercero no tenía NADA que cotejar. Ahora los dos documentos
             hablan el mismo idioma de sello, que es lo que hará posible el verificador público.
             El bloque .sign de arriba es el hueco de la firma AUTÓGRAFA (otra cosa: ver el
             encabezado del parcial). --}}
        @include('componentes._seal-cfdi', [
            'doc'    => $r,
            'folio'  => 'DSR-' . str_pad((string) $r->id, 4, '0', STR_PAD_LEFT),
            'prefix' => 'CREWCARE-DSR',
        ])
      </section>
    </div>
    </td></tr></tbody>
    <tfoot><tr><td><div class="footer-spacer"></div></td></tr></tfoot>
    </table>
    @include('componentes._report-v2-foot', [
      'footPreparedName' => $r->author_name ?: '—',
      'footPreparedMeta' => __('reports.label_risk_assessment') . ($heroDate ? ' · ' . $heroDate : ''),
      'footUuid'         => $footUuid,
    ])

@push('scripts')
{{-- HEIC (iPhone): conversión a JPEG en el navegador antes de subir (el servidor no decodifica HEIC). --}}
<script src="/js/cc-photo.js"></script>
<script src="/js/cc-photo-auto.js"></script>
@endpush

{{-- Render del stack de scripts. Este documento es STANDALONE (su propio <!DOCTYPE>, sin
     @extends layouts.app) y por eso NO heredaba ningún @stack('scripts'). Sin él, todo lo
     que _typeahead y _event-picker empujan con @push('scripts') (estilos de la faceta +
     handlers del filtro + mejora del combobox + preview de normas) se descartaba en
     silencio: la fila de filtro por marco salía como botones nativos sin estilo ni acción
     sobre un documento firmable. El picker vive dentro del bloque .no-print (+ Hallazgo),
     así que estos estilos/scripts no afectan la impresión. --}}
@stack('scripts')

</body>
</html>
