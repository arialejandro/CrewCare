{{--
    _doc-row.blade.php — UNA fila de documento externo (empresa o persona).
    Recibe: $doc (App\Models\ExternalAuthorization).

    Muestra QUÉ documento es, su origen/estado, la foto (si existe) y el ESTADO DE
    VALIDACIÓN. Calca el patrón manual de la cédula profesional (admin/useredit):
    CAPTURAR y VALIDAR son actos distintos → validar es su propio <form> con una
    atestación obligatoria.

    Reglas que NO se colapsan:
      · «Documentos revisados» ≠ «Verificado contra registro» → se usa
        $doc->validationLabel() TAL CUAL (el modelo ya elige el verbo correcto).
      · «En trámite» SIN folio NI fecha compromiso = «No lo tiene»
        ($doc->effectiveStatus() lo degrada; aquí solo se pinta).
--}}
@php
    $eff = $doc->effectiveStatus();   // presentado | en_tramite | no_lo_tiene | no_aplica
    $origenLbl = [
        'normativo'   => __('Obligatorio por norma'),
        'contractual' => __('Exigido por contrato'),
        'recomendado' => __('Recomendado'),
    ];
@endphp
<div class="amb-doc">
    <div class="amb-doc__body">
        <div class="amb-doc__head">
            <h4 class="amb-doc__title">
                @include('componentes._icon', ['name' => 'file-check', 'class' => 'cc-ico-16', 'label' => null])
                {{ $doc->document_type }}
            </h4>
            <div class="amb-doc__flags">
                @if($doc->is_gate)
                    <span class="cc-chip cc-chip--danger">
                        @include('componentes._icon', ['name' => 'octagon-alert', 'class' => 'cc-ico-14', 'label' => null])
                        {{ __('Compuerta') }}
                    </span>
                @else
                    <span class="cc-chip amb-chip-muted">{{ __('Condicionante') }}</span>
                @endif
                <span class="cc-chip cc-chip--brand">{{ $origenLbl[$doc->origen] ?? $doc->origen }}</span>
            </div>
        </div>

        <div class="amb-doc__meta">
            @if($doc->authority)
                <span><strong>{{ __('Autoridad') }}:</strong> {{ $doc->authority }}</span>
            @endif
            @if($doc->folio)
                <span><strong>{{ __('Folio') }}:</strong> {{ $doc->folio }}</span>
            @endif
            @if($doc->valid_until)
                <span><strong>{{ __('Vigencia') }}:</strong> {{ $doc->valid_until->format('d/m/Y') }}</span>
            @endif
            @if($doc->origen !== 'normativo' && $doc->exigido_por)
                <span><strong>{{ __('Exigido por') }}:</strong> {{ $doc->exigido_por }}</span>
            @endif
        </div>

        {{-- Estado efectivo del documento. El «no lo tiene» se marca claramente. --}}
        @if($eff === 'no_lo_tiene')
            <div class="amb-doc__state amb-doc__state--bad">
                @include('componentes._icon', ['name' => 'octagon-alert', 'class' => 'cc-ico-16', 'label' => null])
                <span>
                    <strong>{{ __('No lo tiene.') }}</strong>
                    {{ __('Marcado «en trámite» pero sin folio ni fecha compromiso: no cuenta como presentado.') }}
                </span>
            </div>
        @elseif($eff === 'en_tramite')
            <div class="amb-doc__state amb-doc__state--warn">
                @include('componentes._icon', ['name' => 'clock', 'class' => 'cc-ico-16', 'label' => null])
                <span>
                    {{ __('En trámite') }}@if($doc->pending_commit_date) · {{ __('compromiso') }}: {{ $doc->pending_commit_date->format('d/m/Y') }}@endif
                </span>
            </div>
        @elseif($eff === 'no_aplica')
            <div class="amb-doc__state">{{ __('No aplica') }}</div>
        @endif

        {{-- CONOCER: clave + nombre IMPRESOS (lo que el contratante coteja). --}}
        @if($doc->standard_code || $doc->standard_name)
            <div class="amb-doc__std">
                @include('componentes._icon', ['name' => 'shield-check', 'class' => 'cc-ico-14', 'label' => null])
                <span>
                    @if($doc->standard_code)<strong>{{ $doc->standard_code }}</strong>@endif
                    @if($doc->standard_name) — {{ $doc->standard_name }}@endif
                </span>
            </div>
        @endif

        {{-- ESTADO DE VALIDACIÓN. validationLabel() ya distingue revisado / registro. --}}
        @if($doc->isValidated())
            <p class="amb-doc__validated mb-0">
                <span class="cc-chip cc-chip--ok">
                    @include('componentes._icon', ['name' => 'circle-check', 'class' => 'cc-ico-14', 'label' => null])
                    {{ $doc->validationLabel() }}
                </span>
            </p>
        @else
            <div class="amb-pending">
                @include('componentes._icon', ['name' => 'alert-triangle', 'class' => 'cc-ico', 'label' => __('Atención')])
                <div>
                    <strong>{{ __('Pendiente de validar') }}</strong>
                    <span>{{ __('Alguien capturó este documento, pero nadie lo ha cotejado todavía.') }}</span>
                </div>
            </div>
        @endif
    </div>

    {{-- Foto del documento (el riesgo real: un papel de OTRA persona). --}}
    @if($doc->photoUrl())
        <a href="{{ $doc->photoUrl() }}" target="_blank" rel="noopener" class="amb-doc__photo" title="{{ __('Ver documento') }}">
            <img src="{{ $doc->photoUrl() }}" alt="{{ __('Documento') }}: {{ $doc->document_type }}">
        </a>
    @endif

    {{-- FORMULARIO DE VALIDACIÓN — separado del de captura, solo si está pendiente.
         Calca la cédula profesional: casilla de atestación OBLIGATORIA. Solo quien GESTIONA
         (producción con ambulance.view solo ve el documento, no lo valida). --}}
    @if($doc->isPending() && auth()->check() && auth()->user()->can('ambulance.manage'))
        <form action="{{ route('ambulance.document.validate', ['doc' => $doc->id]) }}" method="POST" class="amb-doc__validate">
            @csrf
            <div class="cc-field">
                <label class="d-flex align-items-start gap-2" for="att-{{ $doc->id }}">
                    <input type="checkbox" id="att-{{ $doc->id }}" name="attestation" value="1" required class="mt-1">
                    <span class="cc-help">
                        <strong>{{ __('Declaro que revisé este documento bajo mi responsabilidad') }}</strong>
                        {{ __('y que corresponde a este titular. Esta validación queda registrada con mi nombre y la fecha.') }}
                    </span>
                </label>
            </div>
            <div class="d-grid d-md-flex justify-content-md-end">
                <button type="submit" class="btn btn-primary cc-cta w-100 w-md-auto">
                    @include('componentes._icon', ['name' => 'shield-check', 'class' => 'cc-ico-18', 'label' => null])
                    {{ __('Validar bajo mi responsabilidad') }}
                </button>
            </div>
        </form>
    @endif
</div>
