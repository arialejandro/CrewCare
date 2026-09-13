@extends('layouts.app')

@section('content')
@include('componentes._form-kit')

@php
    use App\Support\Turnaround;
    use Carbon\Carbon;
@endphp

<div class="container-fluid py-4" style="max-width: 1000px;">

    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div class="d-flex align-items-center gap-3">
            <span class="cc-form-ico">
                @include('componentes._icon', ['name' => 'clock', 'class' => 'cc-ico-20', 'label' => null])
            </span>
            <div>
                <h1 class="h4 fw-bold mb-0">{{ __('Turnaround') }}</h1>
                <div class="cc-muted small">{{ \Carbon\Carbon::parse($day)->isoFormat('ddd D MMM YYYY') }} · {{ $dayLabel }}</div>
            </div>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('outs.index', ['date' => $day]) }}" class="btn btn-outline-secondary btn-sm">← {{ __('Salidas') }}</a>
            <a href="{{ route('outs.turnaround.export', ['date' => $day]) }}" class="btn btn-outline-primary btn-sm">
                @include('componentes._icon', ['name' => 'printer', 'class' => 'cc-ico-16', 'label' => null])
                {{ __('Exportar CSV') }}
            </a>
        </div>
    </div>

    <div class="cc-muted small mb-3">
        {{ __('Descanso entre la salida y el siguiente llamado. Estadística: no bloquea ni alerta. Sin salida no hay turnaround.') }}
    </div>

    <div class="cc-form-card">
        <div class="cc-form-card__body">
            @if (empty($rows))
                <p class="cc-muted mb-0">{{ __('No hay salidas registradas este día.') }}</p>
            @else
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('Departamento') }}</th>
                                <th>{{ __('Persona') }}</th>
                                <th>{{ __('Salida') }}</th>
                                <th>{{ __('Siguiente llamado') }}</th>
                                <th>{{ __('Turnaround') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $r)
                                <tr>
                                    <td>
                                        {{ $r['department'] }}
                                        @if ($r['scope'] === 'individual')
                                            <span class="badge bg-secondary ms-1">{{ __('individual') }}</span>
                                        @endif
                                    </td>
                                    <td>{{ $r['name'] ?? '—' }}</td>
                                    <td class="mono">{{ $r['out_at'] ? $r['out_at']->format('d/m H:i') : '—' }}</td>
                                    <td class="mono">{{ $r['next_call_at'] ? $r['next_call_at']->format('d/m H:i') : '—' }}</td>
                                    <td class="fw-semibold">{{ Turnaround::humanize($r['minutes']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
