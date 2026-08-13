{{-- Una fila de documento del payee con enlace al serve GATEADO (payees.document). Recibe $doc y $payee. --}}
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
        </div>
    </div>
    @if(trim((string) $doc->photo_path) !== '')
        <a href="{{ route('payees.document', ['payee' => $payee->id, 'doc' => $doc->id]) }}"
           target="_blank" rel="noopener" class="btn btn-sm btn-crew-soft d-inline-flex align-items-center gap-1">
            @include('componentes._icon', ['name' => 'file-text', 'label' => null]) {{ __('Ver PDF') }}
        </a>
    @endif
</li>
