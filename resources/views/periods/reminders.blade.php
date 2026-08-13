@extends('layouts.app')
@section('content')
{{-- RECORDATORIO MANUAL a quienes faltan (§a). Contabilidad revisa (sabe quién está de vacaciones)
     y manda UNO POR UNO por WhatsApp. Nunca a quien ya entregó. El mensaje nombra la producción y
     dice qué le falta a cada quien. Solo hay autoservicio para payees ligados a un usuario. --}}
<div class="crew-page">
    <div class="container-fluid px-3 px-md-4 py-4" style="max-width:920px">

        <div class="mb-3">
            <a href="{{ route('periods.show', $period) }}" class="btn btn-sm btn-crew-soft d-inline-flex align-items-center gap-1">
                @include('componentes._icon', ['name' => 'arrow-left', 'label' => null]) {{ __('Volver al tablero') }}
            </a>
        </div>

        <div class="crew-header d-flex align-items-center gap-3 mb-3">
            <span class="crew-header-icon d-inline-flex align-items-center justify-content-center rounded-3">
                @include('componentes._icon', ['name' => 'clock', 'class' => 'cc-ico', 'label' => null])
            </span>
            <div>
                <h1 class="crew-title mb-0">{{ __('Recordatorios') }}</h1>
                <p class="text-muted mb-0 small">{{ $period->displayLabel() }}</p>
            </div>
        </div>

        <div class="alert alert-info small">
            {{ __('Revisa antes de enviar: contabilidad decide a quién (fulano dijo que lo manda mañana, mengano está de vacaciones). Se manda UNO POR UNO; nunca a quien ya entregó. El mensaje nombra la producción y dice qué le falta.') }}
        </div>

        @if(empty($rows))
            <div class="card"><div class="card-body">
                <p class="text-success mb-0">{{ __('Nadie falta en este periodo.') }}</p>
            </div></div>
        @else
            <div class="card"><div class="card-body p-0">
                <ul class="list-group list-group-flush">
                    @foreach($rows as $r)
                        <li class="list-group-item d-flex flex-wrap justify-content-between align-items-center gap-2">
                            <div>
                                <span class="fw-semibold">{{ $r['payee']->name }}</span>
                                <div class="small text-muted">
                                    {{ __('Le falta') }}: {{ !empty($r['missing']) ? implode(', ', $r['missing']) : '—' }}
                                </div>
                            </div>
                            <div>
                                @if($r['self_serve'])
                                    @feature('magic_links')
                                        <a href="{{ $r['wa'] }}" target="_blank" rel="noopener"
                                           class="btn btn-sm btn-outline-success d-inline-flex align-items-center gap-1">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2 22l5.25-1.38a9.9 9.9 0 0 0 4.79 1.22h.004c5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.82 9.82 0 0 0 12.04 2Zm5.8 14.13c-.24.68-1.4 1.3-1.94 1.35-.5.05-.98.24-3.3-.69-2.79-1.1-4.56-3.95-4.7-4.13-.14-.18-1.12-1.49-1.12-2.84 0-1.35.71-2.02.96-2.29.25-.27.55-.34.73-.34.18 0 .37.002.53.01.17.008.4-.064.62.48.24.55.81 1.9.88 2.04.07.14.12.3.02.48-.09.18-.14.29-.27.45-.14.16-.29.36-.41.48-.14.14-.28.29-.12.57.16.27.71 1.17 1.53 1.9 1.05.94 1.94 1.23 2.22 1.37.27.14.43.12.59-.07.16-.18.68-.79.86-1.06.18-.27.36-.23.61-.14.24.09 1.55.73 1.82.86.27.14.45.2.52.32.07.11.07.64-.17 1.32Z"/></svg>
                                            {{ __('WhatsApp') }}
                                        </a>
                                    @else
                                        <span class="text-muted small">{{ __('Magic Links desactivados') }}</span>
                                    @endfeature
                                @else
                                    <span class="badge text-bg-light border text-muted">{{ __('Sin autoservicio (lo captura producción)') }}</span>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div></div>
        @endif

    </div>
</div>
@endsection
