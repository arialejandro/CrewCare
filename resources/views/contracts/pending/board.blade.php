@extends('layouts.app')
@section('content')
{{-- FIRMAS PENDIENTES · tablero por FIGURA (admin, solo lectura). Cuántos contratos esperan a cada
     figura interna AHORA (su turno en la ruta) → se ve de un vistazo quién frena la cola. --}}
@php
    $total = $groups->reduce(fn ($carry, $g) => $carry + $g->count(), 0);
    $fdate = fn ($d) => $d ? \Illuminate\Support\Carbon::parse($d)->format('d/m/Y H:i') : '—';
@endphp
@push('styles')
<style>
    .fb-wrap { max-width: 960px; }
    .fb-summary { display: flex; gap: 14px; flex-wrap: wrap; margin-bottom: 18px; }
    .fb-stat { flex: 1 1 160px; background: var(--surface-2); border: 1px solid var(--border); border-radius: 12px; padding: 12px 16px; }
    .fb-stat b { display: block; font-size: 26px; font-weight: 800; line-height: 1; color: var(--text); font-variant-numeric: tabular-nums; }
    .fb-stat span { font-size: 12px; color: var(--text-muted); }
    .fb-fig { border: 1px solid var(--border); border-radius: 14px; background: var(--surface-2); overflow: hidden; }
    .fb-fig + .fb-fig { margin-top: 14px; }
    .fb-fighead { display: flex; align-items: center; gap: 10px; padding: 12px 16px; border-bottom: 1px solid var(--border); }
    .fb-figname { font-weight: 700; font-size: 15px; color: var(--text); }
    .fb-figcount { margin-left: auto; font-size: 12.5px; font-weight: 700; color: var(--brand-primary-dark);
        background: color-mix(in srgb, var(--brand-primary) 13%, var(--surface-2)); border: 1px solid color-mix(in srgb, var(--brand-primary) 30%, var(--border)); border-radius: 999px; padding: 2px 11px; }
    .fb-row { display: flex; align-items: center; gap: 12px; padding: 10px 16px; }
    .fb-row + .fb-row { border-top: 1px solid var(--border); }
    .fb-who { font-weight: 600; color: var(--text); font-size: 13.5px; }
    .fb-sub { font-size: 12px; color: var(--text-muted); }
    .fb-since { margin-left: auto; font-size: 12px; color: var(--text-muted); white-space: nowrap; }
    .fb-empty { text-align: center; padding: 48px 20px; color: var(--text-muted); }
    .fb-empty svg { width: 40px; height: 40px; color: var(--ok); margin-bottom: 10px; }
</style>
@endpush
<div class="crew-page">
    <div class="container-fluid px-3 px-md-4 py-4 fb-wrap">
        <div class="crew-header d-flex align-items-center gap-3 mb-3">
            <span class="crew-header-icon d-inline-flex align-items-center justify-content-center rounded-3">
                @include('componentes._icon', ['name' => 'users', 'class' => 'cc-ico', 'label' => null])
            </span>
            <div>
                <h1 class="crew-title mb-0">{{ __('Seguimiento de firmas') }}</h1>
                <p class="text-muted mb-0 small">{{ __('Cuántos contratos esperan la firma de cada persona ahora mismo — para ver quién detiene el avance.') }}</p>
            </div>
            <a href="{{ route('contracts.pending.index') }}" class="btn btn-sm btn-crew-soft ms-auto">{{ __('Contratos por firmar') }}</a>
        </div>

        @if($groups->isEmpty())
            <div class="fb-empty">
                @include('componentes._icon', ['name' => 'check-circle', 'class' => '', 'label' => null])
                <div class="fw-semibold">{{ __('No hay contratos por firmar en esta producción.') }}</div>
            </div>
        @else
            <div class="fb-summary">
                <div class="fb-stat"><b>{{ $total }}</b><span>{{ __('contratos por firmar') }}</span></div>
                <div class="fb-stat"><b>{{ $groups->count() }}</b><span>{{ __('firmantes pendientes') }}</span></div>
            </div>

            @foreach($groups as $figure => $envelopes)
                <div class="fb-fig">
                    <div class="fb-fighead">
                        @include('componentes._icon', ['name' => 'pencil', 'class' => 'cc-item__ico', 'label' => null])
                        <span class="fb-figname">{{ $figure ?: __('Sin asignar') }}</span>
                        <span class="fb-figcount">{{ trans_choice('{1}:count contrato|[2,*]:count contratos', $envelopes->count(), ['count' => $envelopes->count()]) }}</span>
                    </div>
                    @foreach($envelopes as $env)
                        @php $rec = $env->currentRecipient; $payee = optional(optional($env->contract)->payee); @endphp
                        <div class="fb-row">
                            <div style="min-width:0">
                                <div class="fb-who">{{ $payee->name ?: optional($env->contract)->id }}</div>
                                <div class="fb-sub">{{ __('Espera la firma de') }}: {{ optional($rec)->name ?: '—' }}@if(optional($rec)->email) · {{ $rec->email }}@endif</div>
                            </div>
                            <a href="{{ route('contracts.envelope.show', $env->id) }}" class="btn btn-sm btn-crew-soft">{{ __('Ver sobre') }}</a>
                            <span class="fb-since">{{ __('desde') }} {{ $fdate(optional($rec)->sent_at ?: $env->sent_at) }}</span>
                        </div>
                    @endforeach
                </div>
            @endforeach
        @endif
    </div>
</div>
@endsection
