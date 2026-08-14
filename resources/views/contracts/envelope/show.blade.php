@extends('layouts.app')
@section('content')
{{-- EL SOBRE (Paso C) — paquete carátula+clausulado+anexos, ruta secuencial. El certificado de cada
     destinatario: nombre/correo/cargo/empresa congelados + 4 marcas de tiempo + IP + método. --}}
@php
    $statusBadge = [
        \App\Models\ContractEnvelope::STATUS_DRAFT     => ['text-bg-secondary', __('Borrador')],
        \App\Models\ContractEnvelope::STATUS_SENT      => ['text-bg-primary',   __('Enviado')],
        \App\Models\ContractEnvelope::STATUS_COMPLETED => ['text-bg-success',   __('Completado')],
        \App\Models\ContractEnvelope::STATUS_CANCELLED => ['text-bg-danger',    __('Cancelado')],
    ][$envelope->status] ?? ['text-bg-light', $envelope->status];
    $fmt = fn ($d) => $d ? $d->format('d/m/Y H:i') : '—';
@endphp
<div class="crew-page">
    <div class="container-fluid px-3 px-md-4 py-4" style="max-width:1080px">

        @if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
        @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

        <div class="crew-header d-flex align-items-center gap-3 mb-3">
            <span class="crew-header-icon d-inline-flex align-items-center justify-content-center rounded-3">
                @include('componentes._icon', ['name' => 'mail', 'class' => 'cc-ico', 'label' => null])
            </span>
            <div>
                <h1 class="crew-title mb-0">{{ __('Sobre de firma') }} #{{ $envelope->id }}</h1>
                <p class="text-muted mb-0 small">
                    {{ optional($envelope->contract->payee)->name }}
                    <span class="badge {{ $statusBadge[0] }} ms-1">{{ $statusBadge[1] }}</span>
                </p>
            </div>
            <div class="ms-auto d-flex gap-2">
                @if($envelope->isDraft())
                    <form method="POST" action="{{ route('contracts.envelope.send', $envelope) }}">@csrf<button class="btn btn-sm btn-crew">{{ __('Enviar a firma') }}</button></form>
                @endif
                @unless($envelope->isCompleted() || $envelope->isCancelled())
                    <form method="POST" action="{{ route('contracts.envelope.cancel', $envelope) }}">@csrf<button class="btn btn-sm btn-outline-danger">{{ __('Cancelar') }}</button></form>
                @endunless
            </div>
        </div>

        {{-- Documentos del paquete --}}
        <div class="card mb-4">
            <div class="card-header fw-semibold">{{ __('Documentos del paquete') }}</div>
            <div class="card-body">
                <ul class="list-group list-group-flush">
                    @foreach(($envelope->documents ?? []) as $i => $doc)
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span>{{ $doc['name'] ?? 'Documento' }} <span class="badge text-bg-light border">{{ $doc['kind'] ?? '' }}</span></span>
                            <a href="{{ route('contracts.envelope.document', ['envelope' => $envelope->id, 'index' => $i]) }}" target="_blank" rel="noopener" class="btn btn-sm btn-crew-soft">{{ __('Ver') }}</a>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>

        {{-- Ruta / destinatarios (certificado) --}}
        <div class="card">
            <div class="card-header fw-semibold">{{ __('Ruta de firma') }}</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead><tr>
                            <th>#</th><th>{{ __('Papel') }}</th><th>{{ __('Firmante') }}</th><th>{{ __('Estado') }}</th>
                            <th>{{ __('Enviado') }}</th><th>{{ __('Visto') }}</th><th>{{ __('Firmado') }}</th><th>{{ __('IP / método') }}</th>
                        </tr></thead>
                        <tbody>
                            @foreach($envelope->orderedRecipients()->get() as $r)
                                <tr @class(['table-active' => (int) $envelope->current_recipient_id === (int) $r->id])>
                                    <td>{{ $r->sort_order + 1 }}</td>
                                    <td>{{ $r->roleLabel() }}</td>
                                    <td>
                                        <div class="fw-semibold">{{ $r->name ?: '—' }}</div>
                                        <div class="small text-muted">{{ $r->email ?: '—' }} · {{ $r->cargo ?: '—' }}@if($r->empresa) · {{ $r->empresa }}@endif</div>
                                    </td>
                                    <td>
                                        @switch($r->status)
                                            @case('signed')<span class="badge text-bg-success">{{ __('Firmado') }}</span>@break
                                            @case('viewed')<span class="badge text-bg-info">{{ __('Visto') }}</span>@break
                                            @case('sent')<span class="badge text-bg-primary">{{ __('Enviado') }}</span>@break
                                            @default<span class="badge text-bg-secondary">{{ __('Pendiente') }}</span>
                                        @endswitch
                                    </td>
                                    <td class="small">{{ $fmt($r->sent_at) }}@if($r->resent_at)<div class="text-muted">{{ __('reenv.') }} {{ $fmt($r->resent_at) }}</div>@endif</td>
                                    <td class="small">{{ $fmt($r->viewed_at) }}</td>
                                    <td class="small">{{ $fmt($r->signed_at) }}</td>
                                    <td class="small">{{ $r->ip_address ?: '—' }}<div class="text-muted">{{ $r->sign_method ?: '' }}</div></td>
                                </tr>
                                @if($r->signature_image)
                                    @php $sig = $r->signatures()->latest('id')->first(); @endphp
                                    <tr><td colspan="8" class="bg-body-tertiary">
                                        @include('componentes._signature-block', [
                                            'image'    => $r->signature_image,
                                            'signer'   => $r->name,
                                            'role'     => $r->cargo ?: $r->roleLabel(),
                                            'date'     => $r->signed_at,
                                            'hash'     => optional($sig)->document_hash,
                                            'verified' => $r->verifyLatestSignature(),
                                        ])
                                    </td></tr>
                                @endif
                                @if($envelope->isSent() && (int) $envelope->current_recipient_id === (int) $r->id)
                                    <tr><td colspan="8" class="bg-body-tertiary">
                                        <span class="small text-muted">{{ __('Enlace de firma de este destinatario:') }}</span>
                                        <a href="{{ \App\Http\Controllers\ContractSignController::signUrl($r) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-success ms-2">{{ __('Abrir firma') }}</a>
                                    </td></tr>
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
