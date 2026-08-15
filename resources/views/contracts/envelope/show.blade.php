@extends('layouts.app')
@section('content')
@include('contracts._route-styles')
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
                    {{-- FASE 1c — contrato armado con la plantilla activa + las firmas reales (solo si existe). --}}
                    @if(!empty($hasTemplate))
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span>{{ __('Contrato (plantilla)') }} <span class="badge text-bg-light border">{{ __('estampado') }}</span></span>
                            <a href="{{ route('contracts.envelope.template', $envelope) }}" target="_blank" rel="noopener" class="btn btn-sm btn-crew-soft">{{ __('Ver') }}</a>
                        </li>
                    @endif
                </ul>
            </div>
        </div>

        {{-- Ruta / destinatarios (certificado) — cadena secuencial con estados vivos --}}
        @php
            $svg = fn ($inner) => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $inner . '</svg>';
            $icoCheck = '<polyline points="20 6 9 17 4 12"/>';
            $icoUser  = '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>';
            $icoEye   = '<path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/>';
            $icoClock = '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>';
            $icoSend  = '<line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>';
            $curId  = (int) $envelope->current_recipient_id;
            $isSent = $envelope->isSent();
        @endphp
        <div class="card">
            <div class="card-header fw-semibold">{{ __('Ruta de firma') }}</div>
            <div class="card-body">
                <div class="cc-route">
                    <div class="cc-route__list">
                        @foreach($envelope->orderedRecipients()->get() as $r)
                            @php
                                $isContracted = $r->role === \App\Models\ContractEnvelopeRecipient::ROLE_CONTRACTED;
                                $isNow  = $isSent && $curId === (int) $r->id && $r->status !== 'signed';
                                $stepClass = $r->status === 'signed' ? 'cc-step--done' : ($isNow ? 'cc-step--now' : '');
                                switch ($r->status) {
                                    case 'signed': $chip = ['done', __('Firmado'), $icoCheck]; break;
                                    case 'viewed': $chip = [$isNow ? 'now' : 'view', $isNow ? __('En turno') : __('Visto'), $icoEye]; break;
                                    case 'sent':   $chip = [$isNow ? 'now' : '', $isNow ? __('En turno') : __('Enviado'), $isNow ? $icoSend : $icoSend]; break;
                                    default:       $chip = [$isNow ? 'now' : '', $isNow ? __('En turno') : __('Pendiente'), $icoClock];
                                }
                            @endphp
                            <div class="cc-step {{ $stepClass }}">
                                <div class="cc-step__rail"><span class="cc-step__num">{!! $r->status === 'signed' ? $svg($icoCheck) : ($r->sort_order + 1) !!}</span></div>
                                <div class="cc-step__card">
                                    <div class="cc-step__main">
                                        <span class="cc-step__pill {{ $isContracted ? 'cc-step__pill--lead' : 'cc-step__pill--sign' }}">{{ $r->roleLabel() }}</span>
                                        <div class="cc-step__title">{{ $r->name ?: '—' }}</div>
                                        <div class="cc-step__who">{!! $svg($icoUser) !!}<span>{{ $r->email ?: '—' }}@if($r->cargo) · {{ $r->cargo }}@endif @if($r->empresa) · {{ $r->empresa }}@endif</span></div>
                                        <div class="cc-step__times">
                                            <span>{{ __('Enviado') }}: <b>{{ $fmt($r->sent_at) }}</b>@if($r->resent_at) · {{ __('reenv.') }} {{ $fmt($r->resent_at) }}@endif</span>
                                            <span>{{ __('Visto') }}: <b>{{ $fmt($r->viewed_at) }}</b></span>
                                            <span>{{ __('Firmado') }}: <b>{{ $fmt($r->signed_at) }}</b></span>
                                            @if($r->ip_address)<span>{{ __('IP') }}: <b>{{ $r->ip_address }}</b>@if($r->sign_method) · {{ $r->sign_method }}@endif</span>@endif
                                        </div>
                                        @if($r->signature_image)
                                            @php $sig = $r->signatures()->latest('id')->first(); @endphp
                                            <div class="mt-2">
                                                @include('componentes._signature-block', [
                                                    'image'    => $r->signature_image,
                                                    'signer'   => $r->name,
                                                    'role'     => $r->cargo ?: $r->roleLabel(),
                                                    'date'     => $r->signed_at,
                                                    'hash'     => optional($sig)->document_hash,
                                                    'verified' => $r->verifyLatestSignature(),
                                                ])
                                            </div>
                                        @endif
                                    </div>
                                    <div class="cc-step__side">
                                        <span class="cc-step__state cc-step__state--{{ $chip[0] }}">{!! $svg($chip[2]) !!}{{ $chip[1] }}</span>
                                        @if($isNow)
                                            <a href="{{ \App\Http\Controllers\ContractSignController::signUrl($r) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-success">{{ __('Abrir firma') }}</a>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
