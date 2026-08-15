@extends('layouts.app')
@section('content')
{{-- FIRMAS PENDIENTES · bandeja personal. Lo que le toca firmar AHORA al usuario, con los datos que
     sí mira antes de firmar (contraprestación, situación fiscal, vigencia). UNO A UNO por defecto;
     el lote aparece solo si el admin encendió el flag `contracts_batch_signing`. --}}
@php
    $money = function ($amount, $cur) {
        if ($amount === null || $amount === '') return null;
        return '$' . number_format((float) $amount, 2) . ' ' . ($cur ?: 'MXN');
    };
    $fdate = fn ($d) => $d ? \Illuminate\Support\Carbon::parse($d)->format('d/m/Y') : null;
@endphp
@push('styles')
<style>
    .fp-wrap { max-width: 920px; }
    .fp-card { border: 1px solid var(--border); border-radius: 14px; background: var(--surface-2); padding: 16px 18px; }
    .fp-card + .fp-card { margin-top: 14px; }
    .fp-top { display: flex; align-items: flex-start; gap: 12px; flex-wrap: wrap; }
    .fp-title { font-weight: 700; font-size: 16px; color: var(--text); line-height: 1.2; }
    .fp-role { font-size: 12.5px; color: var(--text-muted); margin-top: 2px; }
    .fp-badge { font-size: 11px; font-weight: 700; padding: 2px 9px; border-radius: 999px; background: color-mix(in srgb, var(--brand-primary) 13%, var(--surface-2)); color: var(--brand-primary-dark); border: 1px solid color-mix(in srgb, var(--brand-primary) 30%, var(--border)); white-space: nowrap; }
    .fp-data { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px 18px; margin: 14px 0 4px; }
    .fp-k { font-size: 10.5px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: var(--text-muted); }
    .fp-v { font-size: 13.5px; color: var(--text); font-weight: 600; margin-top: 1px; }
    .fp-v small { font-weight: 400; color: var(--text-muted); }
    .fp-foot { display: flex; align-items: center; gap: 12px; margin-top: 14px; padding-top: 12px; border-top: 1px solid var(--border); flex-wrap: wrap; }
    .fp-docs { font-size: 12.5px; color: var(--text-muted); }
    .fp-check { display: inline-flex; align-items: center; gap: 8px; font-size: 13px; color: var(--text-muted); user-select: none; }
    .fp-empty { text-align: center; padding: 48px 20px; color: var(--text-muted); }
    .fp-empty svg { width: 40px; height: 40px; color: var(--ok); margin-bottom: 10px; }
    .fp-batchbar { position: sticky; bottom: 12px; margin-top: 16px; display: flex; align-items: center; gap: 14px; flex-wrap: wrap;
        background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 12px 16px; box-shadow: 0 6px 20px rgba(15,23,42,.08); }
    .fp-batchhint { font-size: 12.5px; color: var(--text-muted); }
</style>
@endpush
<div class="crew-page">
    <div class="container-fluid px-3 px-md-4 py-4 fp-wrap">
        <div class="crew-header d-flex align-items-center gap-3 mb-3">
            <span class="crew-header-icon d-inline-flex align-items-center justify-content-center rounded-3">
                @include('componentes._icon', ['name' => 'pencil', 'class' => 'cc-ico', 'label' => null])
            </span>
            <div>
                <h1 class="crew-title mb-0">{{ __('Contratos por firmar') }}</h1>
                <p class="text-muted mb-0 small">{{ __('Los contratos que esperan tu firma. Revisa los datos y firma cada uno.') }}</p>
            </div>
            @can('settings.manage')
                <a href="{{ route('contracts.pending.board') }}" class="btn btn-sm btn-crew-soft ms-auto">{{ __('Seguimiento de firmas') }}</a>
            @endcan
        </div>

        @if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
        @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

        @if($pending->isEmpty())
            <div class="fp-empty">
                @include('componentes._icon', ['name' => 'check-circle', 'class' => '', 'label' => null])
                <div class="fw-semibold">{{ __('No tienes contratos por firmar.') }}</div>
                <div class="small">{{ __('Cuando un contrato llegue a tu turno en la ruta, aparecerá aquí.') }}</div>
            </div>
        @else
            <form method="POST" action="{{ route('contracts.pending.batch') }}" id="fpForm">
                @csrf
                @foreach($pending as $r)
                    @php
                        $env = $r->envelope; $c = optional($env)->contract; $d = \App\Support\PendingSignatures::keyData($c);
                        $docCount = is_array(optional($env)->documents) ? count($env->documents) : 0;
                    @endphp
                    <div class="fp-card">
                        <div class="fp-top">
                            @if($batchOn)
                                <label class="fp-check pt-1">
                                    <input class="form-check-input mt-0 fp-pick" type="checkbox" name="recipient_ids[]" value="{{ $r->id }}">
                                </label>
                            @endif
                            <div class="flex-grow-1" style="min-width:0">
                                <div class="fp-title">{{ optional(optional($c)->payee)->name ?: $r->name ?: '—' }}</div>
                                <div class="fp-role">{{ __('Firmas como') }}: <strong>{{ $r->cargo ?: $r->roleLabel() }}</strong></div>
                            </div>
                            <span class="fp-badge">{{ \App\Support\PendingSignatures::conceptLabel($d['concept'] ?? null) }}</span>
                        </div>

                        <div class="fp-data">
                            <div>
                                <div class="fp-k">{{ __('Contraprestación') }}</div>
                                <div class="fp-v">{{ $money($d['fee'] ?? null, $d['currency'] ?? null) ?? '—' }}</div>
                            </div>
                            <div>
                                <div class="fp-k">{{ __('Situación fiscal') }}</div>
                                <div class="fp-v">
                                    {{ $d['rfc'] ?? '—' }}
                                    @if(\App\Support\PendingSignatures::natureLabel($d['nature'] ?? null))
                                        <small>· {{ \App\Support\PendingSignatures::natureLabel($d['nature']) }}</small>
                                    @endif
                                    @if(!empty($d['regime']))<div><small>{{ $d['regime'] }}</small></div>@endif
                                </div>
                            </div>
                            <div>
                                <div class="fp-k">{{ __('Vigencia') }}</div>
                                <div class="fp-v">
                                    @if($fdate($d['start'] ?? null) || $fdate($d['end'] ?? null))
                                        {{ $fdate($d['start'] ?? null) ?: '—' }} <small>→</small> {{ $fdate($d['end'] ?? null) ?: '—' }}
                                    @else — @endif
                                </div>
                            </div>
                            @if(!empty($d['department']))
                                <div>
                                    <div class="fp-k">{{ __('Departamento') }}</div>
                                    <div class="fp-v">{{ $d['department'] }}</div>
                                </div>
                            @endif
                        </div>

                        <div class="fp-foot">
                            <span class="fp-docs">@include('componentes._icon', ['name' => 'files', 'class' => 'cc-ico', 'label' => null]) {{ trans_choice('{0}Sin documentos|{1}:count documento|[2,*]:count documentos', $docCount, ['count' => $docCount]) }}</span>
                            <a href="{{ \App\Http\Controllers\ContractSignController::signUrl($r) }}" class="btn btn-sm btn-crew ms-auto">{{ __('Revisar y firmar') }}</a>
                        </div>
                    </div>
                @endforeach

                @if($batchOn)
                    <div class="fp-batchbar">
                        <label class="fp-check"><input class="form-check-input mt-0" type="checkbox" id="fpAll"> {{ __('Seleccionar todos') }}</label>
                        <span class="fp-batchhint">
                            @if($hasAdopted)
                                {{ __('La firma en lote aplica tu firma adoptada a los contratos seleccionados; cada uno se sella por separado.') }}
                            @else
                                {{ __('Aún no tienes firma adoptada — firma uno de forma individual y guárdala; luego podrás firmar en lote.') }}
                            @endif
                        </span>
                        <button type="submit" class="btn btn-crew ms-auto" id="fpSubmit" @disabled(!$hasAdopted)>
                            {{ __('Firmar seleccionados') }} <span id="fpCount">(0)</span>
                        </button>
                    </div>
                @endif
            </form>
        @endif
    </div>
</div>
@endsection

@if($batchOn && !$pending->isEmpty())
@push('scripts')
<script>
(function () {
    var picks = Array.prototype.slice.call(document.querySelectorAll('.fp-pick'));
    var all   = document.getElementById('fpAll');
    var count = document.getElementById('fpCount');
    var submit = document.getElementById('fpSubmit');
    var hasAdopted = @json($hasAdopted);
    function refresh() {
        var n = picks.filter(function (c) { return c.checked; }).length;
        if (count) { count.textContent = '(' + n + ')'; }
        if (submit) { submit.disabled = !hasAdopted || n === 0; }
        if (all) { all.checked = n > 0 && n === picks.length; }
    }
    picks.forEach(function (c) { c.addEventListener('change', refresh); });
    if (all) { all.addEventListener('change', function () { picks.forEach(function (c) { c.checked = all.checked; }); refresh(); }); }
    refresh();
})();
</script>
@endpush
@endif
