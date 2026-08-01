{{--
    ANEXO(S) MÉDICO(S) firmado(s) — Paso Injury (2026-07-20). Se incluye SÓLO en el
    expediente COMPLETO (admin/injuryreport.blade.php), gateado por viewMedical.

    Recibe: $injuryReport (con addendums cargados) y $folio (INJ-####).

    Item 8: cada addendum se pinta como ANEXO referenciado al reporte original, con:
      · la CÉDULA del médico tratante (vía createdBy → medic_credentials),
      · su SELLO SHA-256 PROPIO (firma polimórfica del addendum, independiente del
        sello del reporte base → la cadena de custodia del original NO cambia),
      · fecha/hora del sellado, y el HUECO preparado para el sello del firmante.
    El reporte original nunca se altera (append-only).

    Además, un formulario NO-PRINT para AGREGAR un anexo, gateado por la AddendumPolicy
    (@can('create', ...)) = sólo un médico. Estilo del documento (.field/.btn), no Bootstrap.

    Defensivo: feature flag + existencia de la tabla; si prod no los tiene, no truena.
--}}
@feature('medical_addendum')
@if(\Illuminate\Support\Facades\Schema::hasTable('addendums'))
@php
    $addenda        = $injuryReport->relationLoaded('addendums') ? $injuryReport->addendums : $injuryReport->addendums()->latest()->get();
    $canAddAddendum = auth()->check() && auth()->user()->can('create', [\App\Models\Addendum::class, $injuryReport]);
    $addTypeLabels  = \App\Models\Addendum::typeLabels();
    $addTreatLabels = [
        'first_aid'         => __('reports.treatment_first_aid'),
        'medical_treatment' => __('reports.treatment_medical'),
        'hospitalization'   => __('reports.treatment_hospitalization'),
        'fatality'          => __('reports.treatment_fatality'),
    ];
    $signaturesTable = \Illuminate\Support\Facades\Schema::hasTable('digital_signatures');
@endphp

@if($addenda->count() || $canAddAddendum)
<section class="sec">
  <div class="sec-h"><span class="bar"></span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M16 13H8M16 17H8M10 9H8"/></svg><h2>{{ __('reports.injury_section_addenda') }}</h2><span class="line"></span></div>

  @if($addenda->count())
  <div style="display:flex;flex-direction:column;gap:12px">
    @foreach($addenda as $addendum)
      @php
        // Cédula del médico tratante (autor del anexo), si aplica.
        $aCred = (\App\Models\MedicCredential::supportsCredentials() && $addendum->createdBy && $addendum->createdBy->isMedic())
            ? $addendum->createdBy->medicCredential : null;
        // Sello PROPIO del anexo (independiente del reporte base).
        $aSig       = $signaturesTable ? $addendum->verifyLatestSignature() : null;
        $aSigRecord = $signaturesTable ? $addendum->signatures()->latest('id')->first() : null;
        // ¿Cambió alguna estadística efectiva?
        $aHasChanges = ($addendum->new_treatment_level !== null && $addendum->new_treatment_level !== '')
            || $addendum->new_is_recordable !== null
            || $addendum->new_days_away !== null
            || $addendum->new_days_restricted !== null;
      @endphp
      <div class="panel" style="border-left:3px solid var(--brand)">
        {{-- Cabecera del anexo: tipo · referencia al reporte · fecha/hora · autor + cédula. --}}
        <div style="display:flex;flex-wrap:wrap;gap:6px 14px;align-items:baseline;justify-content:space-between">
          <span style="font-weight:800;font-size:.86rem">{{ isset($addTypeLabels[$addendum->type]) ? $addTypeLabels[$addendum->type] : $addendum->type }}</span>
          <span style="font-size:.68rem;color:var(--muted);font-family:var(--mono)">{{ __('reports.addendum_annex_of') }} {{ $folio }} · {{ optional($addendum->created_at)->format('d/m/Y H:i') }}</span>
        </div>
        @if($addendum->createdBy)
        <div style="font-size:.72rem;color:var(--muted);margin-top:3px">
          {{ __('reports.label_prepared_by') }}: <strong>{{ $addendum->createdBy->isMedic() && method_exists($addendum->createdBy, 'fullName') ? $addendum->createdBy->fullName() : $addendum->createdBy->name }}</strong>
          @if($aCred)
            · {{ __('Cédula prof.') }} {{ $aCred->cedula }}
            @if($aCred->isVerified())
              ({{ __('verificada') }}@if($aCred->verified_at) {{ $aCred->verified_at->format('d/m/Y') }}@endif)
            @else
              <span style="color:var(--warn,#c2410c);font-weight:700">({{ __('SIN VERIFICAR') }})</span>
            @endif
          @endif
        </div>
        @endif

        {{-- Cuerpo del anexo (evolución del diagnóstico/tratamiento). --}}
        <p class="desc" style="margin:10px 0 0;font-size:.86rem;white-space:pre-line">{{ $addendum->body }}</p>

        {{-- Cambios efectivos declarados por este anexo. --}}
        @if($aHasChanges)
        <div class="chips" style="margin-top:10px">
          @if($addendum->new_treatment_level !== null && $addendum->new_treatment_level !== '')
          <span class="chip">{{ __('reports.label_treatment_level') }}: {{ isset($addTreatLabels[$addendum->new_treatment_level]) ? $addTreatLabels[$addendum->new_treatment_level] : $addendum->new_treatment_level }}</span>
          @endif
          @if($addendum->new_is_recordable !== null)
          <span class="chip {{ $addendum->new_is_recordable ? 'warn' : 'ok' }}">OSHA: {{ $addendum->new_is_recordable ? __('reports.label_yes') : __('reports.label_no') }}</span>
          @endif
          @if($addendum->new_days_away !== null)
          <span class="chip">{{ __('reports.label_days_away') }}: {{ $addendum->new_days_away }}</span>
          @endif
          @if($addendum->new_days_restricted !== null)
          <span class="chip">{{ __('reports.label_days_restricted') }}: {{ $addendum->new_days_restricted }}</span>
          @endif
        </div>
        @endif

        {{-- SELLO PROPIO del anexo (SHA-256 + sellado) + hueco del firmante.
             (2026-07-24) Va en NEUTRO, no en verde: aquí el bloque no felicita al documento,
             carga el dato cotejable (el anexo no tiene recuadro CFDI propio donde ponerlo).
             El verde quedó reservado para nada y el rojo para lo alterado. --}}
        @if($aSig === true && $aSigRecord)
        <div class="seal none" style="margin-top:12px">@include('componentes._icon', ['name' => 'shield'])<div style="min-width:0">
          <b>{{ __('reports.addendum_seal_own') }}</b>
          <div class="h" style="word-break:break-all">SHA-256: {{ $aSigRecord->document_hash }}</div>
          <div class="h">{{ __('reports.seal_label_sealed_at') }}: {{ $aSigRecord->signed_at ? \Carbon\Carbon::parse($aSigRecord->signed_at)->format('d/m/Y H:i:s') : '—' }}</div>
        </div></div>
        @elseif($aSig === false)
        <div class="seal bad" style="margin-top:12px">@include('componentes._icon', ['name' => 'shield-alert'])<div><b>{{ __('reports.injury_altered') }}</b></div></div>
        @endif
        <div style="font-size:.64rem;color:var(--faint);margin-top:6px;font-style:italic">{{ __('reports.addendum_evolved_from_original') }}</div>
      </div>
    @endforeach
  </div>
  @endif

  {{-- FORMULARIO DE ALTA (no-print): sólo el MÉDICO TRATANTE. AddendumController firma el
       anexo al crearse. Estilo del documento; se oculta en el PDF. --}}
  @if($canAddAddendum)
  <div class="no-print" style="margin-top:16px">
    @if(session('success'))<div class="alert ok">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert bad">{{ session('error') }}</div>@endif
    @if(isset($errors) && $errors->any())<div class="alert bad">{{ $errors->first() }}</div>@endif
    <div class="panel">
      <div style="font-weight:800;font-size:.9rem;margin-bottom:4px">{{ __('reports.addendum_add_title') }}</div>
      <div style="font-size:.74rem;color:var(--muted);margin-bottom:12px">{{ __('reports.addendum_add_hint') }}</div>
      <form method="POST" action="{{ route('addendums.store', $injuryReport->id) }}" style="display:flex;flex-direction:column;gap:10px">
        @csrf
        <div style="display:flex;flex-wrap:wrap;gap:10px">
          <label style="flex:1;min-width:180px;font-size:.72rem;color:var(--muted)">{{ __('reports.addendum_field_type') }}
            <select name="type" class="field" style="width:100%;margin-top:4px" required>
              @foreach($addTypeLabels as $value => $label)
              <option value="{{ $value }}" {{ old('type') === $value ? 'selected' : '' }}>{{ $label }}</option>
              @endforeach
            </select>
          </label>
          <label style="flex:1;min-width:180px;font-size:.72rem;color:var(--muted)">{{ __('reports.addendum_field_new_level') }}
            <select name="new_treatment_level" class="field" style="width:100%;margin-top:4px">
              <option value="">{{ __('reports.addendum_no_change') }}</option>
              @foreach($addTreatLabels as $value => $label)
              <option value="{{ $value }}" {{ old('new_treatment_level') === $value ? 'selected' : '' }}>{{ $label }}</option>
              @endforeach
            </select>
          </label>
        </div>
        <label style="font-size:.72rem;color:var(--muted)">{{ __('reports.addendum_field_body') }}
          <textarea name="body" rows="3" maxlength="4000" required class="field" style="width:100%;height:auto;margin-top:4px;padding:8px 12px;font:400 .84rem/1.5 var(--font)">{{ old('body') }}</textarea>
        </label>
        <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:end">
          <label style="flex:1;min-width:140px;font-size:.72rem;color:var(--muted)">{{ __('reports.addendum_field_new_days_away') }}
            <input type="number" name="new_days_away" min="0" value="{{ old('new_days_away') }}" class="field" style="width:100%;margin-top:4px">
          </label>
          <label style="flex:1;min-width:140px;font-size:.72rem;color:var(--muted)">{{ __('reports.addendum_field_new_days_restricted') }}
            <input type="number" name="new_days_restricted" min="0" value="{{ old('new_days_restricted') }}" class="field" style="width:100%;margin-top:4px">
          </label>
          <label style="flex:1;min-width:180px;font-size:.76rem;color:var(--text);display:flex;align-items:center;gap:7px;padding-bottom:9px">
            <input type="checkbox" name="new_is_recordable" value="1" {{ old('new_is_recordable') ? 'checked' : '' }}>
            {{ __('reports.addendum_field_new_recordable') }}
          </label>
        </div>
        <div><button type="submit" class="btn brand">{{ __('reports.addendum_save') }}</button></div>
      </form>
    </div>
  </div>
  @endif
</section>
@endif
@endif
@endfeature
