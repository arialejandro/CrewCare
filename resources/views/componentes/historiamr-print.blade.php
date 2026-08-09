{{-- ============================================================================================
     EXPEDIENTE CLÍNICO — VISTA DE IMPRESIÓN (PDF "como los demás documentos"). 2026-08-09.

     El formato en pantalla (componentes/historiamr, dentro de layouts.app) YA está bien; lo que
     faltaba era una salida imprimible funcional. Ésta es la MISMA lectura read-only —mismos datos,
     misma fuente única (cmedicController@expedienteVigente), MISMO candado por departamento— con la
     PIEL de los reportes: chrome v2 (_report-v2-head/-toolbar/-foot + _doc-hero + banda + .sec) en
     papel CARTA, con el sello CFDI del expediente y de cada consulta.

     NO edita ni expone nada que la vista de pantalla no muestre ya. El expediente sigue siendo
     inmutable y sellado: el hash se recomputa sobre el documento original ($expedienteModelo), no
     sobre este render; los anexos lo acompañan, no lo alteran.

     Contrato de variables (idéntico a componentes.historiamr):
       $datos            fila identidad (users) + expediente (formularios), anexos aplicados.
       $usuario          colección con UNA fila (el expediente vigente, anexos aplicados).
       $target           User real del crew (para positionName()).
       $consultas        consultas visibles del paciente (modelos, para tabla + sello).
       $medicos          mapa id→User (fallback en vivo de "quién atendió").
       $intakeState      'ok' | 'missing' | 'incomplete'.
       $expedienteModelo modelo del expediente SIN anexos aplicados (sello + traza) o null.
       $anexosExpediente colección de anexos médicos del expediente.
============================================================================================ --}}
@php
    use App\Support\Branding;

    $en        = app()->getLocale() === 'en';
    $brand     = isset($branding) && is_array($branding) ? $branding : [];
    $brandName = ($brand['brand_name'] ?? Branding::get('brand_name', 'CrewCare')) ?: 'CrewCare';
    $primary   = $brand['primary_color'] ?? (Branding::get('primary_color', '#ff9900') ?: '#ff9900');

    // El expediente VIGENTE (una fila, anexos ya aplicados) o null si la persona no tiene.
    $exp     = $usuario->first();
    $hasExp  = $exp !== null;

    $fullName  = trim(($datos->name ?? '') . ' ' . ($datos->lname ?? '') . ' ' . ($datos->lname2 ?? ''));
    $folio     = $expedienteModelo ? $expedienteModelo->folio() : null;
    $filedDate = ($hasExp && ! empty($exp->created_at)) ? \Carbon\Carbon::parse($exp->created_at)->format('d/m/Y') : null;
    // Tipo de sangre al cintillo: es EL dato de vistazo en una urgencia. Del estado vigente.
    $bloodType = $hasExp ? ($exp->blod_type ?: null) : null;

    $heroModule = __('Historial Médico');

    $appVersion = config('crewcare.doc_version');
    $footMeta   = implode(' · ', array_filter([
        $en ? 'Clinical record' : 'Expediente clínico',
        $filedDate,
    ]));
    $footUuid   = 'UUID: ' . (($expedienteModelo && $expedienteModelo->uuid) ? $expedienteModelo->uuid : '—')
                . ($appVersion ? ' | ' . $appVersion : '');
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $folio ? $folio . ' · ' : '' }}{{ $fullName !== '' ? $fullName : $heroModule }} — {{ $brandName }}</title>
@include('componentes._report-v2-head')
@include('componentes._report-v2-blockflow')
<style>
  /* Contenido propio del expediente (scoped .hmp-*). Hereda los tokens del chrome (claro/oscuro/print). */
  .hmp-id{ display:flex; gap:16px; align-items:flex-start; }
  .hmp-photo{ width:88px; height:88px; border-radius:12px; overflow:hidden; flex:none;
    border:1px solid var(--stroke-2); background:var(--panel);
    -webkit-print-color-adjust:exact; print-color-adjust:exact; }
  .hmp-photo img{ width:100%; height:100%; object-fit:cover; display:block; }
  .hmp-id .facts{ flex:1; min-width:0; }

  .hmp-sub{ font-size:.6rem; font-weight:800; text-transform:uppercase; letter-spacing:.1em;
    color:var(--muted); margin:16px 0 9px; }

  /* Lista etiqueta→valor para texto libre (antecedentes, hospitalizaciones, consultas). */
  .hmp-dl{ display:grid; grid-template-columns:150px 1fr; gap:7px 16px; font-size:.84rem; }
  .hmp-dl .k{ color:var(--muted); font-weight:700; font-size:.58rem; text-transform:uppercase;
    letter-spacing:.06em; padding-top:2px; }
  .hmp-dl .v{ color:var(--text); word-break:break-word; white-space:pre-line; }

  .hmp-note{ font-size:.8rem; color:var(--text); border-left:3px solid var(--warn);
    padding:8px 12px; background:var(--panel); border-radius:0 var(--radius-sm) var(--radius-sm) 0;
    margin:0 0 16px; break-inside:avoid; }

  /* Tarjeta atómica (anexo / consulta): no se parte entre hojas. */
  .hmp-card{ border:1px solid var(--stroke); border-radius:var(--radius-sm); padding:12px 14px;
    margin-bottom:12px; background:var(--panel); break-inside:avoid; }
  .hmp-card .ch{ display:flex; flex-wrap:wrap; align-items:baseline; gap:8px; margin-bottom:7px; }
  .hmp-card .ch .fol{ font-family:var(--mono); font-weight:700; font-size:.82rem; color:var(--text); }
  .hmp-card .ch .dt{ font-size:.72rem; color:var(--muted); }
  .hmp-card .note{ font-style:italic; margin:7px 0 0; font-size:.84rem; color:var(--text); }
  .hmp-card .by{ font-size:.72rem; color:var(--muted); margin-top:6px; }

  .hmp-mgmt{ display:block; font-style:italic; color:var(--muted); font-size:.8rem; margin-top:2px; }

  .hmp-seal-item{ margin-bottom:14px; break-inside:avoid; }
  .hmp-seal-head{ font-family:var(--mono); font-size:.72rem; color:var(--muted); margin:0 0 4px; }
</style>
</head>
<body>

@include('componentes._report-v2-toolbar', [
    'backRoute'   => route('historialwr', $target->id),
    'backLabel'   => $en ? 'Back to record' : 'Volver al expediente',
    'exportLabel' => $en ? 'Export PDF' : 'Imprimir / PDF',
])

<div class="stage">
  <article class="sheet">
    @include('componentes._doc-hero', [
      'heroProject'     => $brandName,
      'heroModule'      => $heroModule,
      'heroHideCallbox' => true,
    ])

    {{-- BANDA (full-bleed): la PERSONA + folio a la izquierda; tipo de sangre y fecha de llenado
         como vistazo (el tipo de sangre es el dato de urgencia). --}}
    <div class="band">
      <div class="lead">
        <span class="ic">@include('componentes._icon', ['name' => 'clipboard-list'])</span>
        <span class="who">
          <span class="lbl">{{ $en ? 'Clinical record' : 'Expediente clínico' }}</span>
          <span class="val">{{ $fullName !== '' ? $fullName : '—' }}</span>
          <span class="sub">{{ $folio ?: ($en ? 'No record on file' : 'Sin expediente en archivo') }}</span>
        </span>
      </div>
      <div class="stats">
        <div class="cell"><span class="lbl">{{ __('Tipo de sangre') }}</span><span class="v">{{ $bloodType ?: '—' }}</span></div>
        <div class="cell"><span class="lbl">{{ $en ? 'Filed on' : 'Declarado el' }}</span><span class="v">{{ $filedDate ?: '—' }}</span></div>
      </div>
    </div>

    {{-- CUERPO EN FLUJO DE BLOQUES (no tabla): los saltos de página caen ENTRE secciones. --}}
    <div class="doc-body">

      <h1 class="restricted" style="position:absolute;left:-9999px">{{ $brandName }} — {{ $heroModule }} — {{ $fullName }}</h1>

      {{-- Aviso de expediente (imprimible: es contexto clínico, no un control de pantalla). --}}
      @if(($intakeState ?? 'ok') === 'missing')
        <p class="hmp-note">{{ __('Esta persona no ha llenado el cuestionario médico. Las consultas de abajo se registraron sin información de alergias ni antecedentes.') }}</p>
      @elseif(($intakeState ?? 'ok') === 'incomplete')
        <p class="hmp-note">{{ __('Hay expediente, pero el campo de alergias está vacío: no se capturaron.') }}</p>
      @endif

      @foreach ($usuario as $user)

      {{-- ============ DATOS PERSONALES E INFORMACIÓN GENERAL ============ --}}
      <section class="sec">
        <div class="sec-h"><span class="bar"></span>@include('componentes._icon', ['name' => 'user'])<h2>{{ __('Datos personales e información general') }}</h2><span class="line"></span></div>
        <div class="hmp-id">
          @if(!empty($datos) && \App\Support\Avatar::has($datos))
            <div class="hmp-photo"><img src="{{ \App\Support\Avatar::url($datos) }}" alt="{{ __('Foto de perfil') }}"></div>
          @endif
          <div class="facts">
            <div class="fact"><div class="k">{{ __('Nombre') }}</div><div class="v">{{ $fullName !== '' ? $fullName : '—' }}</div></div>
            <div class="fact"><div class="k">{{ __('Teléfono') }}</div><div class="v">{{ $datos->phone ?? '' ?: '—' }}</div></div>
            <div class="fact"><div class="k">{{ __('Email') }}</div><div class="v">{{ $datos->email ?? '' ?: '—' }}</div></div>
            <div class="fact"><div class="k">{{ __('Puesto') }}</div><div class="v">{{ $target->positionName() ?: '—' }}</div></div>
            <div class="fact"><div class="k">{{ __('Edad') }}</div><div class="v">{{ !empty($datos->borndate) ? \Carbon\Carbon::parse($datos->borndate)->age . ' ' . __('años') : '—' }}</div></div>
            <div class="fact"><div class="k">{{ __('Tipo de sangre') }}</div><div class="v">{{ $user->blod_type ?: '—' }}</div></div>
            <div class="fact"><div class="k">{{ __('Peso') }}</div><div class="v">{{ $user->height ?: '—' }}</div></div>
            <div class="fact"><div class="k">{{ __('Talla') }}</div><div class="v">{{ $user->size ?: '—' }}</div></div>
            <div class="fact"><div class="k">{{ __('IMC') }}</div><div class="v">{{ (empty($user->size) || empty($user->height)) ? 'N/D' : round($user->height / ($user->size * $user->size), 1) }}</div></div>
          </div>
        </div>

        <div class="hmp-sub">{{ __('Contacto de emergencia') }}</div>
        <div class="facts">
          <div class="fact"><div class="k">{{ __('Nombre') }}</div><div class="v">{{ $user->c_emer ?: '—' }}</div></div>
          <div class="fact"><div class="k">{{ $user->relation ?: __('Relación') }}</div><div class="v">{{ $user->p_emer ?: '—' }}</div></div>
        </div>
      </section>

      {{-- ============ ANTECEDENTES, NO PATOLÓGICOS Y VACUNACIÓN ============ --}}
      <section class="sec">
        <div class="sec-h"><span class="bar"></span>@include('componentes._icon', ['name' => 'file-text'])<h2>{{ __('Antecedentes, no patológicos y vacunación') }}</h2><span class="line"></span></div>

        <div class="hmp-sub">{{ __('Patológicos') }}</div>
        <div class="hmp-dl">
          <div class="k">{{ __('Cirugías') }}</div><div class="v">{{ $user->cirugy ?: '—' }}</div>
          <div class="k">{{ __('Alergias') }}</div><div class="v">{{ $user->alergy ?: '—' }}</div>
          <div class="k">{{ __('Patológicas') }}</div><div class="v">{{ $user->pathology ?: '—' }}</div>
          <div class="k">{{ __('Traumáticos') }}</div><div class="v">{{ $user->trauma ?: '—' }}</div>
        </div>

        @php
            $noPat = [
                __('Tabaquismo')   => (int) ($user->pers_nopat1 ?? 0) === 1,
                __('Alcoholismo')  => (int) ($user->pers_nopat2 ?? 0) === 1,
                __('Toxicomanías') => (int) ($user->pers_nopat3 ?? 0) === 1,
            ];
            $vac = [
                'COVID-19'        => (int) ($user->vacci1 ?? 0) === 1,
                __('Influenza')   => (int) ($user->vacci2 ?? 0) === 1,
                __('Tétanos')     => (int) ($user->vacci3 ?? 0) === 1,
                __('Neumococo')   => (int) ($user->vacci4 ?? 0) === 1,
                __('Hepatitis B') => (int) ($user->vacci5 ?? 0) === 1,
            ];
        @endphp

        <div class="hmp-sub">{{ __('No patológicos') }}</div>
        <div class="chips">
          @foreach($noPat as $lbl => $yes)
            <span class="chip {{ $yes ? 'warn' : '' }}">{{ $lbl }} — {{ $yes ? __('SÍ') : 'NO' }}</span>
          @endforeach
        </div>

        <div class="hmp-sub">{{ __('Vacunación') }}</div>
        <div class="chips">
          @foreach($vac as $lbl => $yes)
            <span class="chip {{ $yes ? 'warn' : '' }}">{{ $lbl }} — {{ $yes ? __('SÍ') : 'NO' }}</span>
          @endforeach
        </div>
      </section>

      {{-- ============ ANTECEDENTES HEREDO-FAMILIARES ============ --}}
      @php
          $heredoLabels = [
              __('Vivo/Sano'), __('Fallecido'), __('Diabetes'), __('Hipertensión'),
              __('Cardiopatías'), __('Nefropatías'), __('Neoplasias'),
          ];
      @endphp
      <section class="sec">
        <div class="sec-h"><span class="bar"></span>@include('componentes._icon', ['name' => 'users'])<h2>{{ __('Antecedentes heredo-familiares') }}</h2><span class="line"></span></div>

        <div class="hmp-sub">{{ __('Madre') }}</div>
        <div class="chips">
          @foreach($heredoLabels as $idx => $lbl)
            @php $yes = (int) ($user->{'momdat' . ($idx + 1)} ?? 0) === 1; @endphp
            <span class="chip {{ $yes ? 'warn' : '' }}">{{ $lbl }} — {{ $yes ? __('SÍ') : 'NO' }}</span>
          @endforeach
        </div>

        <div class="hmp-sub">{{ __('Padre') }}</div>
        <div class="chips">
          @foreach($heredoLabels as $idx => $lbl)
            @php $yes = (int) ($user->{'daddat' . ($idx + 1)} ?? 0) === 1; @endphp
            <span class="chip {{ $yes ? 'warn' : '' }}">{{ $lbl }} — {{ $yes ? __('SÍ') : 'NO' }}</span>
          @endforeach
        </div>
      </section>

      {{-- ============ HOSPITALIZACIONES ============ --}}
      <section class="sec">
        <div class="sec-h"><span class="bar"></span>@include('componentes._icon', ['name' => 'building-2'])<h2>{{ __('Hospitalizaciones') }}</h2><span class="line"></span></div>
        @if(($user->hospitals ?? 0) > 0)
          <div class="hmp-dl">
            @for ($i = 1; $i <= $user->hospitals; $i++)
              <div class="k">{{ __('¿Qué sucedió?') }}</div><div class="v">{{ $user->{'hsp' . $i} ?: '—' }}</div>
            @endfor
          </div>
        @else
          <p class="desc">{{ __('Sin hospitalizaciones registradas.') }}</p>
        @endif
      </section>

      {{-- ============ GINECO-OBSTÉTRICO (solo si aplica) ============ --}}
      @if(($datos->sex ?? '') === 'F')
      <section class="sec">
        <div class="sec-h"><span class="bar"></span>@include('componentes._icon', ['name' => 'heart-pulse'])<h2>{{ __('Gineco-obstétrico') }}</h2><span class="line"></span></div>
        <div class="hmp-dl">
          <div class="k">{{ __('Ritmo') }}</div><div class="v">{{ $user->rythm ?: '—' }}</div>
          <div class="k">{{ __('Embarazos') }}</div><div class="v">{{ $user->pregnant ?: '—' }}</div>
        </div>
        @php
            $gineco = [
                __('Papanicolaou') => (int) ($user->prevent1 ?? 0) === 1,
                __('Mastografía')  => (int) ($user->prevent2 ?? 0) === 1,
            ];
        @endphp
        <div class="hmp-sub">{{ __('Detección preventiva') }}</div>
        <div class="chips">
          @foreach($gineco as $lbl => $yes)
            <span class="chip {{ $yes ? 'warn' : '' }}">{{ $lbl }} — {{ $yes ? __('SÍ') : 'NO' }}</span>
          @endforeach
        </div>
      </section>
      @endif

      @endforeach

      {{-- ============ TRAZA DE ANEXOS + SELLO DEL EXPEDIENTE ============
           El expediente NO se edita, ni su titular. Lo de arriba es el estado VIGENTE; aquí está
           la traza de cómo llegó a serlo y el sello del documento original. --}}
      @if(isset($expedienteModelo) && $expedienteModelo)
      <section class="sec">
        <div class="sec-h"><span class="bar"></span>@include('componentes._icon', ['name' => 'files'])<h2>{{ __('health.trace_title') }}</h2><span class="line"></span></div>

        @forelse(($anexosExpediente ?? collect()) as $anexo)
          <div class="hmp-card">
            <div class="ch">
              <span class="fol">{{ $anexo->folio() }}</span>
              <span class="dt">{{ \Carbon\Carbon::parse($anexo->created_at)->format('d/m/Y H:i') }}</span>
              <span class="chip">{{ $anexo->motivoLabel() }}</span>
            </div>
            <div class="hmp-dl">
              @foreach($anexo->cambiosLegibles() as $cambio)
                <div class="k">{{ $cambio['campo'] }}</div><div class="v">{{ $cambio['valor'] }}</div>
              @endforeach
            </div>
            @if($anexo->notes)<p class="note">{{ $anexo->notes }}</p>@endif
            <div class="by">
              {{ __('health.trace_by') }}: {{ $anexo->medic_name ?: '—' }}
              @if($anexo->medic_cedula) · {{ __('health.trace_cedula') }} {{ $anexo->medic_cedula }}@if($anexo->medic_cedula_verified) ✓ @endif @endif
            </div>
            @include('componentes._seal-cfdi', [
                'doc' => $anexo, 'folio' => $anexo->folio(), 'prefix' => 'CREWCARE-EXPA',
            ])
          </div>
        @empty
          <p class="desc">{{ __('health.trace_empty') }}</p>
        @endforelse

        {{-- Sello del expediente ORIGINAL (recomputado sobre el documento declarado). --}}
        <p class="hmp-seal-head">{{ __('health.trace_seal_head', ['folio' => $expedienteModelo->folio()]) }}</p>
        @include('componentes._seal-cfdi', [
            'doc' => $expedienteModelo, 'folio' => $expedienteModelo->folio(), 'prefix' => 'CREWCARE-EXP',
        ])
      </section>
      @endif

      {{-- ============ CONSULTAS REGISTRADAS ============ --}}
      <section class="sec">
        <div class="sec-h"><span class="bar"></span>@include('componentes._icon', ['name' => 'stethoscope'])<h2>{{ __('Consultas') }}</h2><span class="line"></span></div>

        @forelse ($consultas as $consulta)
          @php
              $folioMed = 'MED-' . str_pad((string) $consulta->id_cmedic, 4, '0', STR_PAD_LEFT);
              $mgmtLabels = $consulta->managementLabels();
              $hasSnap = filled($consulta->medic_name) || filled($consulta->medic_cedula);
              $medFallback = $hasSnap ? null : $medicos->get($consulta->created_by_id);
              $mv = $consulta->medic_cedula_verified;
          @endphp
          <div class="hmp-card">
            <div class="ch">
              <span class="fol">{{ $folioMed }}</span>
              <span class="dt">{{ \Carbon\Carbon::parse($consulta->created_at)->format('d/m/Y H:i') }}</span>
              @if((int) ($consulta->without_record ?? 0) === 1)
                <span class="chip warn">{{ __('Sin expediente') }}</span>
              @endif
            </div>
            <div class="hmp-dl">
              <div class="k">{{ __('Atendió') }}</div>
              <div class="v">
                @if($hasSnap)
                  {{ $consulta->medic_name ?: '—' }}@if(filled($consulta->medic_cedula)) · <span class="mono">{{ $consulta->medic_cedula }}</span>@endif
                  @if($mv !== null && (int) $mv === 1)
                    <span class="chip ok">{{ __('Cédula verificada') }}</span>
                  @elseif($mv !== null)
                    <span class="chip warn">{{ __('Cédula sin verificar') }}</span>
                  @endif
                @else
                  {{ $medFallback ? $medFallback->fullName() : '—' }}
                @endif
              </div>

              <div class="k">{{ __('Diagnóstico') }}</div><div class="v">{{ $consulta->diagnosis ?: '—' }}</div>

              <div class="k">{{ __('Medicamento') }}</div>
              <div class="v">
                @if($consulta->medication !== '' && $consulta->medication !== null){{ $consulta->medication }}@elseif(empty($mgmtLabels)){{ __('Ninguno') }}@endif
                @if(! empty($mgmtLabels))<span class="hmp-mgmt">{{ implode(' · ', array_map('__', $mgmtLabels)) }}</span>@endif
              </div>

              <div class="k">{{ __('Observaciones') }}</div><div class="v">{{ $consulta->observations ?: __('Ninguna') }}</div>
              <div class="k">{{ __('Información adicional') }}</div><div class="v">{{ $consulta->aditional ?: __('Ninguna') }}</div>
            </div>
          </div>
        @empty
          <p class="desc">{{ __('No hay consultas registradas.') }}</p>
        @endforelse
      </section>

      {{-- ============ SELLOS DE INTEGRIDAD POR CONSULTA ============ --}}
      @if($consultas->count())
      <section class="sec">
        <div class="sec-h"><span class="bar"></span>@include('componentes._icon', ['name' => 'shield-check'])<h2>{{ __('Sellos de integridad') }}</h2><span class="line"></span></div>
        @foreach($consultas as $consulta)
          <div class="hmp-seal-item">
            <p class="hmp-seal-head">
              {{ __('Consulta') }} MED-{{ str_pad((string) $consulta->id_cmedic, 4, '0', STR_PAD_LEFT) }}
              · {{ \Carbon\Carbon::parse($consulta->created_at)->format('d/m/Y H:i') }}
            </p>
            @include('componentes._seal-cfdi', [
                'doc'    => $consulta,
                'folio'  => 'MED-' . str_pad((string) $consulta->id_cmedic, 4, '0', STR_PAD_LEFT),
                'prefix' => 'CREWCARE-MED',
            ])
          </div>
        @endforeach
      </section>
      @endif

    </div>{{-- .doc-body --}}
@include('componentes._report-v2-foot', [
    'footPreparedName' => $fullName !== '' ? $fullName : '—',
    'footPreparedMeta' => $footMeta,
    'footUuid'         => $footUuid,
])
</body>
</html>
