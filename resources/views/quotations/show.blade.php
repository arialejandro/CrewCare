@extends('layouts.app')
@section('content')
{{-- COTIZACIÓN · detalle. Versión en curso (PDF byte-intact o partidas) + estado. La aceptación
     sellada y el historial de versiones se agregan en sus fases. --}}
@php
    $v = $quotation->currentVersion;
    $badges = [
        'recibida' => 'text-bg-secondary', 'en_negociacion' => 'text-bg-warning',
        'aceptada' => 'text-bg-success', 'rechazada' => 'text-bg-danger',
    ];
    $labels = [
        'recibida' => __('Recibida'), 'en_negociacion' => __('En negociación'),
        'aceptada' => __('Aceptada'), 'rechazada' => __('Rechazada'),
    ];
    $money = fn ($n) => '$'.number_format((float) $n, 2);
@endphp
<div class="crew-page">
    <div class="container-fluid px-3 px-md-4 py-4" style="max-width:920px">
        <div class="crew-header d-flex align-items-center gap-3 mb-4">
            <span class="crew-header-icon d-inline-flex align-items-center justify-content-center rounded-3">
                @include('componentes._icon', ['name' => 'file-text', 'class' => 'cc-ico', 'label' => null])
            </span>
            <div>
                <h1 class="crew-title mb-0">{{ $quotation->emitter_name }}
                    <span class="badge {{ $badges[$quotation->status] ?? 'text-bg-light' }} align-middle">{{ $labels[$quotation->status] ?? $quotation->status }}</span></h1>
                <p class="text-muted mb-0 small">
                    {{ optional($v)->quotation_number ? __('Núm.').' '.$v->quotation_number.' · ' : '' }}{{ $quotation->emitter_email ?: '' }}
                </p>
            </div>
            <div class="ms-auto d-flex gap-2">
                @if(! $quotation->isAccepted())
                    @can('quotations.accept')<a href="{{ route('quotations.accept.show', $quotation) }}" class="btn btn-crew">{{ __('Aceptar') }}</a>@endcan
                    <a href="{{ route('quotations.new_version', $quotation) }}" class="btn btn-crew-soft">{{ __('Nueva versión') }}</a>
                    <a href="{{ route('quotations.edit', $quotation) }}" class="btn btn-crew-soft">{{ __('Editar') }}</a>
                @endif
                <a href="{{ route('quotations.index') }}" class="btn btn-crew-soft">{{ __('← Cotizaciones') }}</a>
            </div>
        </div>

        @if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif

        @if($quotation->isExpired() && ! $quotation->isAccepted())
            <div class="alert alert-warning small">{{ __('La vigencia de esta cotización ya venció. Se puede renegociar o aceptar igual.') }}</div>
        @endif

        @if($quotation->isAccepted())
            @php $sealOk = $quotation->verifyLatestSignature(); @endphp
            <div class="card mb-3 border-success"><div class="card-body">
                <h2 class="h6 mb-2">@include('componentes._icon', ['name' => 'check-circle', 'label' => null]) {{ __('Cotización aceptada') }}
                    @if($sealOk === true)<span class="badge text-bg-success">{{ __('Sello íntegro') }}</span>
                    @elseif($sealOk === false)<span class="badge text-bg-danger">{{ __('Alterada') }}</span>@endif
                </h2>
                <p class="small mb-2">{{ __('Aceptada por') }}
                    <strong>{{ trim(optional($quotation->acceptedBy)->name.' '.optional($quotation->acceptedBy)->lname) }}</strong>
                    {{ __('el') }} {{ optional($quotation->accepted_at)->format('d/m/Y H:i') }}.
                    @if($quotation->payee)· {{ __('Ligada a') }} <strong>{{ $quotation->payee->name }}</strong>@endif
                </p>
                <div class="d-flex gap-2 flex-wrap">
                    @if($quotation->acceptance_sheet_path)
                        <a href="{{ route('quotations.acceptance_sheet', $quotation) }}" target="_blank" class="btn btn-sm btn-crew-soft">{{ __('Hoja de aceptación (PDF)') }}</a>
                    @endif
                </div>
            </div></div>
        @endif

        {{-- Pedir la cotización al proveedor por enlace firmado (SE SOLICITA) --}}
        @if(! empty($requestUrl))
        <div class="card mb-3"><div class="card-body">
            <h2 class="h6 mb-2">{{ __('Pedir la cotización al proveedor') }}</h2>
            <p class="small text-muted mb-2">{{ __('Enlace para que la llene sin cuenta (vence en 14 días). El PDF o las partidas llegan directo.') }}</p>
            <div class="input-group input-group-sm mb-2">
                <input type="text" class="form-control" id="reqLink" value="{{ $requestUrl }}" readonly>
                <button class="btn btn-crew-soft" type="button" onclick="navigator.clipboard.writeText(document.getElementById('reqLink').value)">{{ __('Copiar') }}</button>
            </div>
            <a class="btn btn-sm btn-crew-soft" target="_blank" href="https://wa.me/?text={{ rawurlencode('Hola, ¿nos compartes tu cotización? '.$requestUrl) }}">{{ __('Enviar por WhatsApp') }}</a>
        </div></div>
        @endif

        {{-- Contenido de la versión en curso --}}
        <div class="card mb-3"><div class="card-body">
            @if($v && $v->isPdf())
                <h2 class="h6 mb-3">{{ __('Documento (PDF)') }}</h2>
                @if($v->pdf_path)
                    <p><a href="{{ route('quotations.version_pdf', [$quotation, $v]) }}" target="_blank" class="btn btn-crew-soft btn-sm">
                        @include('componentes._icon', ['name' => 'file-text', 'label' => null]) {{ $v->pdf_original_name ?: 'cotizacion.pdf' }}</a></p>
                    <p class="small text-muted mb-0">SHA-256: <code>{{ $v->pdf_sha256 }}</code></p>
                @else
                    <p class="text-muted">{{ __('Sin archivo adjunto.') }}</p>
                @endif
            @elseif($v)
                <h2 class="h6 mb-3">{{ __('Partidas') }}</h2>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-2">
                        <thead><tr><th>{{ __('Descripción') }}</th><th class="text-end">{{ __('Cant.') }}</th><th class="text-end">{{ __('Días') }}</th><th class="text-end">{{ __('P. unit.') }}</th><th class="text-end">{{ __('Importe') }}</th></tr></thead>
                        <tbody>
                            @foreach($v->items as $it)
                                <tr>
                                    <td>{{ $it->description }}@if($it->detail)<div class="small text-muted">{{ $it->detail }}</div>@endif</td>
                                    <td class="text-end">{{ rtrim(rtrim((string) $it->quantity, '0'), '.') }}</td>
                                    <td class="text-end">{{ $it->days !== null ? rtrim(rtrim((string) $it->days, '0'), '.') : '—' }}</td>
                                    <td class="text-end">{{ $money($it->unit_price) }}</td>
                                    <td class="text-end">{{ $money($it->line_total) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            @if($v)
                <div class="text-end small">
                    <div>{{ __('Subtotal') }}: {{ $money($v->subtotal) }}</div>
                    <div>{{ __('IVA') }} @if($v->iva_included)<span class="text-muted">({{ __('incluido') }})</span>@endif: {{ $money($v->iva_amount) }}</div>
                    <div class="fw-bold">{{ __('Total') }}: {{ $money($v->total) }}</div>
                </div>
            @endif
        </div></div>

        {{-- Datos --}}
        @if($v)
        <div class="card mb-3"><div class="card-body">
            <h2 class="h6 mb-3">{{ __('Datos') }}</h2>
            <dl class="row mb-0 small">
                @if($v->issued_at)<dt class="col-sm-3">{{ __('Emisión') }}</dt><dd class="col-sm-9">{{ $v->issued_at->format('d/m/Y') }}</dd>@endif
                @if($v->valid_until)<dt class="col-sm-3">{{ __('Vigencia') }}</dt><dd class="col-sm-9">{{ $v->valid_until->format('d/m/Y') }}</dd>@endif
                @if($v->payment_terms)<dt class="col-sm-3">{{ __('Pago') }}</dt><dd class="col-sm-9">{{ $v->payment_terms }}</dd>@endif
                @if($v->bank_details)<dt class="col-sm-3">{{ __('Banco') }}</dt><dd class="col-sm-9">{{ $v->bank_details }}</dd>@endif
                @if($quotation->department)<dt class="col-sm-3">{{ __('Departamento') }}</dt><dd class="col-sm-9">{{ $quotation->department->name }}</dd>@endif
                @if($quotation->location_name)<dt class="col-sm-3">{{ __('Locación') }}</dt><dd class="col-sm-9">{{ $quotation->location_name }}</dd>@endif
            </dl>
        </div></div>
        @endif

        {{-- Historial de versiones (negociar es versionar) --}}
        @if($quotation->versions->count() > 1)
        <div class="card mb-3"><div class="card-body">
            <h2 class="h6 mb-3">{{ __('Historial de versiones') }}</h2>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th>{{ __('Versión') }}</th><th class="text-end">{{ __('Total') }}</th><th class="text-end">{{ __('Cambio') }}</th><th>{{ __('Qué cambió') }}</th><th>{{ __('Fecha') }}</th></tr></thead>
                    <tbody>
                        @foreach($quotation->versions->sortByDesc('version_no') as $ver)
                            @php
                                $prevTotal = $ver->supersedes_id ? optional($quotation->versions->firstWhere('id', $ver->supersedes_id))->total : null;
                                $delta = $prevTotal !== null ? ((float) $ver->total - (float) $prevTotal) : null;
                            @endphp
                            <tr class="{{ $ver->id === $quotation->current_version_id ? 'table-active' : '' }}">
                                <td>v{{ $ver->version_no }}@if($ver->id === $quotation->current_version_id) <span class="badge text-bg-success">{{ __('actual') }}</span>@endif</td>
                                <td class="text-end">{{ $money($ver->total) }}</td>
                                <td class="text-end {{ $delta > 0 ? 'text-danger' : ($delta < 0 ? 'text-success' : 'text-muted') }}">
                                    {{ $delta === null ? '—' : ($delta > 0 ? '+' : '').number_format($delta, 2) }}
                                </td>
                                <td class="small">{{ $ver->change_note ?: '—' }}</td>
                                <td class="small text-muted">{{ optional($ver->created_at)->format('d/m/Y H:i') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div></div>
        @endif
    </div>
</div>
@endsection
