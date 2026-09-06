{{-- Una fila de documento del payee con enlace al serve GATEADO (payees.document). Recibe $doc y $payee.
     🔴 Los enlaces del SAT solo se ARMAN aquí; el servidor NUNCA consulta al SAT — los abre un humano
     (que resuelve el captcha). --}}
@php
    $satUrl = $doc->is32d() ? $doc->sat32dUrl() : $doc->satFacturaUrl();
    $isInvoice = optional($doc->documentType)->expects_cfdi_xml;
@endphp
<li class="list-group-item d-flex flex-wrap align-items-center justify-content-between gap-2 px-0">
    <div>
        <span class="fw-semibold">{{ optional($doc->documentType)->name ?: $doc->document_type }}</span>
        @if($doc->issued_at)<span class="text-muted small ms-2">{{ __('Emisión') }}: {{ \Carbon\Carbon::parse($doc->issued_at)->format('d/m/Y') }}</span>@endif
        <div class="small">
            @if($doc->isValidated())
                <span class="text-success">{{ $doc->validationLabel() }}</span>
            @else
                <span class="text-warning">{{ __('Recibido · pendiente de validar') }}</span>
            @endif
            @if($isInvoice && ! $doc->hasCfdi())
                <span class="text-muted">· {{ __('sin XML: no hay enlace de verificación') }}</span>
            @endif
        </div>
    </div>

    <div class="d-flex flex-wrap align-items-center gap-2">
        @if(trim((string) $doc->photo_path) !== '')
            <a href="{{ route('payees.document', ['payee' => $payee->id, 'doc' => $doc->id]) }}"
               target="_blank" rel="noopener" class="btn btn-sm btn-crew-soft d-inline-flex align-items-center gap-1">
                @include('componentes._icon', ['name' => 'file-text', 'label' => null]) {{ __('Ver PDF') }}
            </a>
        @endif

        @if($satUrl)
            {{-- El enlace lo abre una persona; el servidor no toca al SAT. --}}
            <a href="{{ $satUrl }}" target="_blank" rel="noopener nofollow"
               class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-1">
                @include('componentes._icon', ['name' => 'external-link', 'label' => null]) {{ __('Verificar en SAT') }}
            </a>
        @elseif($doc->is32d())
            {{-- 32-D sin folio: contabilidad lo captura desde aquí (el tablero). --}}
            @can('periods.manage')
                <form method="POST" action="{{ route('payees.document.satfolio', ['payee' => $payee->id, 'doc' => $doc->id]) }}"
                      class="d-inline-flex align-items-center gap-1">
                    @csrf
                    <input type="text" name="sat_folio" value="{{ $doc->sat_folio }}" maxlength="60"
                           class="form-control form-control-sm" style="max-width:150px" placeholder="{{ __('Folio 32-D') }}">
                    <button class="btn btn-sm btn-crew-soft">{{ __('Guardar folio') }}</button>
                </form>
            @endcan
        @endif
    </div>
</li>
