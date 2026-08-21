@extends('layouts.app')
@section('content')
{{-- CONSULTA DE CONTRATOS (solo lectura). Cada depto ve lo suyo; producción / oficina de producción /
     contabilidad ven todo. Sin acciones de administración: abrir el contrato armado y, si está
     completo, descargar la copia firmada + el certificado. --}}
@php
    $money = function ($amount, $cur) {
        if ($amount === null || $amount === '') return null;
        return '$' . number_format((float) $amount, 2) . ' ' . ($cur ?: 'MXN');
    };
    $fdate = fn ($d) => $d ? \Illuminate\Support\Carbon::parse($d)->format('d/m/Y') : null;
    $chips = [
        'draft'     => ['Borrador',   'muted'],
        'sent'      => ['En firma',   'warn'],
        'completed' => ['Completado', 'ok'],
        'cancelled' => ['Cancelado',  'bad'],
        'declined'  => ['Rechazado',  'bad'],
        'expired'   => ['Vencido',    'muted'],
    ];
@endphp
@push('styles')
<style>
    .cs-wrap { max-width: 1040px; }
    .cs-note { font-size: 12.5px; color: var(--text-muted); margin: 2px 0 16px; }
    .cs-card { border: 1px solid var(--border); border-radius: 14px; background: var(--surface-2); padding: 15px 18px; }
    .cs-card + .cs-card { margin-top: 12px; }
    .cs-top { display: flex; align-items: flex-start; gap: 12px; flex-wrap: wrap; }
    .cs-title { font-weight: 700; font-size: 15.5px; color: var(--text); line-height: 1.2; }
    .cs-sub { font-size: 12.5px; color: var(--text-muted); margin-top: 2px; }
    .cs-chip { font-size: 11px; font-weight: 700; padding: 2px 10px; border-radius: 999px; white-space: nowrap; border: 1px solid transparent; margin-left: auto; }
    .cs-chip--ok    { background: color-mix(in srgb, var(--ok) 14%, var(--surface-2)); color: var(--ok); border-color: color-mix(in srgb, var(--ok) 32%, var(--border)); }
    .cs-chip--warn  { background: color-mix(in srgb, #d99a00 15%, var(--surface-2)); color: #b7791f; border-color: color-mix(in srgb, #d99a00 34%, var(--border)); }
    .cs-chip--bad   { background: color-mix(in srgb, #c0392b 13%, var(--surface-2)); color: #c0392b; border-color: color-mix(in srgb, #c0392b 30%, var(--border)); }
    .cs-chip--muted { background: var(--surface); color: var(--text-muted); border-color: var(--border); }
    .cs-data { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 9px 18px; margin: 12px 0 2px; }
    .cs-k { font-size: 10px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: var(--text-muted); }
    .cs-v { font-size: 13px; color: var(--text); font-weight: 600; margin-top: 1px; font-variant-numeric: tabular-nums; }
    .cs-foot { display: flex; align-items: center; gap: 8px; margin-top: 13px; padding-top: 11px; border-top: 1px solid var(--border); flex-wrap: wrap; }
    .cs-btn { font-size: 12.5px; font-weight: 600; padding: 6px 12px; border-radius: 9px; border: 1px solid var(--border); background: var(--surface); color: var(--text); text-decoration: none; display: inline-flex; align-items: center; gap: 6px; }
    .cs-btn:hover { background: var(--surface-2); }
    .cs-btn--primary { border-color: color-mix(in srgb, var(--brand-primary) 40%, var(--border)); color: var(--brand-primary-dark); }
    .cs-empty { text-align: center; padding: 48px 20px; color: var(--text-muted); }
</style>
@endpush
<div class="crew-page">
    <div class="container-fluid px-3 px-md-4 py-4 cs-wrap">
        <div class="crew-header d-flex align-items-center gap-3 mb-1">
            <span class="crew-header-icon d-inline-flex align-items-center justify-content-center rounded-3">
                @include('componentes._icon', ['name' => 'file-text', 'class' => 'cc-ico', 'label' => null])
            </span>
            <div>
                <h1 class="crew-title mb-0">{{ __('Contratos') }}</h1>
                <div class="cs-note">
                    {{ $seesAll
                        ? __('Ves todos los contratos de la producción.')
                        : __('Ves los contratos de tu(s) departamento(s).') }}
                </div>
            </div>
        </div>

        @forelse($envelopes as $env)
            @php
                $c   = $env->contract;
                $st  = $chips[$env->status] ?? [$env->status, 'muted'];
                $dep = optional(optional($c)->department)->name;
                $fin = optional($c)->definitive_end_date ?: optional($c)->estimated_end_date;
            @endphp
            <div class="cs-card">
                <div class="cs-top">
                    <div>
                        <div class="cs-title">{{ optional(optional($c)->payee)->name ?: __('Contrato') }}</div>
                        <div class="cs-sub">
                            {{ $dep ? $dep . ' · ' : '' }}{{ optional($c)->crew_activity ?: optional($c)->title }}
                        </div>
                    </div>
                    <span class="cs-chip cs-chip--{{ $st[1] }}">{{ __($st[0]) }}</span>
                </div>

                <div class="cs-data">
                    <div><div class="cs-k">{{ __('Folio') }}</div><div class="cs-v">{{ $env->folio() }}</div></div>
                    <div><div class="cs-k">{{ __('Contraprestación') }}</div><div class="cs-v">{{ $money(optional($c)->fee_amount, optional($c)->fee_currency) ?: '—' }}</div></div>
                    <div><div class="cs-k">{{ __('Vigencia') }}</div><div class="cs-v">{{ $fdate($fin) ?: '—' }}</div></div>
                    @if($env->isSent() && $env->currentRecipient)
                        <div><div class="cs-k">{{ __('En turno') }}</div><div class="cs-v">{{ $env->currentRecipient->name }}</div></div>
                    @elseif($env->isCompleted() && $env->completed_at)
                        <div><div class="cs-k">{{ __('Completado') }}</div><div class="cs-v">{{ $fdate($env->completed_at) }}</div></div>
                    @endif
                </div>

                <div class="cs-foot">
                    <a class="cs-btn cs-btn--primary" href="{{ route('contracts.envelope.template', $env) }}" target="_blank" rel="noopener">
                        {{ __('Ver contrato') }}
                    </a>
                    @if($env->isCompleted() && $env->hasSignedDocument())
                        <a class="cs-btn" href="{{ route('contracts.envelope.signed', $env) }}" target="_blank" rel="noopener">{{ __('Copia firmada') }}</a>
                        <a class="cs-btn" href="{{ route('contracts.envelope.certificate', $env) }}?pdf=1" target="_blank" rel="noopener">{{ __('Certificado') }}</a>
                    @endif
                </div>
            </div>
        @empty
            <div class="cs-empty">{{ __('No hay contratos para mostrar todavía.') }}</div>
        @endforelse

        @if($envelopes->hasPages())
            <div class="mt-4">{{ $envelopes->links() }}</div>
        @endif
    </div>
</div>
@endsection
