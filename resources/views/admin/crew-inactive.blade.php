@extends('layouts.app')
@section('content')
@push('styles')@include('componentes._crew-list-styles')@endpush
@push('styles')
<style>
    .inact-band { font-family:'Poppins',sans-serif; font-weight:600; letter-spacing:-.01em; color:var(--text);
        font-size:.95rem; margin:1.4rem 0 .5rem; padding-bottom:.3rem; border-bottom:1px solid var(--stroke); }
    .inact-row { display:grid; grid-template-columns: 1fr auto auto; gap:.75rem; align-items:center;
        border:1px solid var(--stroke); border-radius:10px; padding:.55rem .8rem; margin-bottom:.4rem; background:var(--glass); }
    .inact-name { font-weight:600; color:var(--text); }
    .inact-cargo { color:var(--text-muted); font-size:.82rem; }
    .inact-why { font-size:.7rem; text-transform:uppercase; letter-spacing:.04em; border-radius:6px; padding:.12rem .5rem; white-space:nowrap; }
    .inact-why.unit { color:#b45309; background:color-mix(in srgb,#f59e0b 16%,transparent); }
    .inact-why.indiv { color:var(--text-muted); background:var(--glass); border:1px solid var(--stroke); }
    @media (max-width:575px){ .inact-row { grid-template-columns:1fr; } }
</style>
@endpush

<div class="crew-page">
    <div class="container-fluid px-3 px-md-4 py-4" style="max-width:1040px">

        <div class="crew-header d-flex align-items-center gap-3 mb-2">
            <span class="crew-header-icon d-inline-flex align-items-center justify-content-center rounded-3">
                @include('componentes._icon', ['name' => 'x-circle', 'class' => 'cc-ico', 'label' => null])
            </span>
            <div>
                <h1 class="crew-title mb-0">{{ __('Dados de baja') }}</h1>
                <p class="text-muted mb-0 small">{{ __('Crew inactivo, por departamento. Reintegrar reactiva a la persona; el contrato y las condiciones nuevas se emiten aparte.') }}</p>
            </div>
            <a href="{{ route('usuarioscrud') }}" class="btn btn-sm btn-crew-soft ms-auto">{{ __('Crew activo') }}</a>
        </div>

        @if(session('reintegrated'))
            <div class="alert alert-warning d-flex align-items-start gap-2" role="alert">
                @include('componentes._icon', ['name' => 'alert-triangle', 'class' => 'cc-ico mt-1', 'label' => null])
                <div>
                    <strong>{{ __('Reintegraste a :n.', ['n' => session('reintegrated')]) }}</strong><br>
                    {{ __('Queda PENDIENTE emitir su contrato y condiciones nuevas — eso no se resuelve aquí. Hazlo desde el módulo de contratos.') }}
                </div>
            </div>
        @endif

        @php $groups = $roster['groups'] ?? []; @endphp

        @if(($roster['total'] ?? 0) === 0)
            <div class="card"><div class="card-body text-center text-muted py-5">
                @include('componentes._icon', ['name' => 'users', 'class' => 'cc-ico mb-2', 'label' => null])
                <p class="mb-0">{{ __('No hay personas dadas de baja en tu alcance.') }}</p>
            </div></div>
        @else
            <p class="text-muted small mb-3">{{ trans_choice('{1} :count persona dada de baja.|[2,*] :count personas dadas de baja.', $roster['total'], ['count' => $roster['total']]) }}</p>

            @foreach($groups as $g)
                <div class="inact-band">{{ $g['label'] }}</div>
                @foreach($g['people'] as $p)
                    <div class="inact-row">
                        <div>
                            <span class="inact-name">{{ $p['name'] }}</span>
                            @if($p['cargo'] !== '')<span class="inact-cargo"> · {{ $p['cargo'] }}</span>@endif
                            @isset($contractStatus[$p['id']])
                                <span class="d-block mt-1">@include('componentes._contract-badge', ['cs' => $contractStatus[$p['id']]])</span>
                            @endisset
                        </div>
                        @if($reasons->has($p['id']))
                            <span class="inact-why unit">{{ __('Apagado con «:u»', ['u' => $reasons[$p['id']]]) }}</span>
                        @else
                            <span class="inact-why indiv">{{ __('Baja individual') }}</span>
                        @endif
                        <form action="{{ route('crew.reintegrate', $p['id']) }}" method="POST" class="m-0"
                            data-confirm="{{ __('Reintegrar a :n lo reactiva. OJO: NO emite su contrato — quedará PENDIENTE emitir contrato y condiciones nuevas. ¿Continuar?', ['n' => $p['name']]) }}">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-crew">
                                @include('componentes._icon', ['name' => 'rotate-ccw', 'class' => 'cc-ico me-1', 'label' => null]) {{ __('Reintegrar') }}
                            </button>
                        </form>
                    </div>
                @endforeach
            @endforeach
        @endif

    </div>
</div>
@include('componentes._confirm-submit')
@endsection
