@extends('layouts.app')

@section('content')
@include('componentes._form-kit')
@include('componentes._confirm-submit')

@php
    use Carbon\Carbon;
    $prev = Carbon::parse($day)->subDay()->toDateString();
    $next = Carbon::parse($day)->addDay()->toDateString();
@endphp

<div class="container-fluid py-4" style="max-width: 1000px;">
    @include('componentes._form-feedback')

    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div class="d-flex align-items-center gap-3">
            <span class="cc-form-ico">
                @include('componentes._icon', ['name' => 'log-out', 'class' => 'cc-ico-20', 'label' => null])
            </span>
            <div>
                <h1 class="h4 fw-bold mb-0">{{ __('Salidas') }}</h1>
                <div class="cc-muted small">{{ __('Cada quien marca su salida desde su inicio. Aquí ves quién marcó y quién falta.') }}</div>
            </div>
        </div>
        <div class="d-flex gap-2">
            @if ($canDesignate)
                <a href="{{ route('outs.designations') }}" class="btn btn-outline-secondary btn-sm">
                    @include('componentes._icon', ['name' => 'users', 'class' => 'cc-ico-16', 'label' => null])
                    {{ __('Designados') }}
                </a>
            @endif
            <a href="{{ route('outs.turnaround', ['date' => $day]) }}" class="btn btn-outline-secondary btn-sm">
                @include('componentes._icon', ['name' => 'clock', 'class' => 'cc-ico-16', 'label' => null])
                {{ __('Turnaround') }}
            </a>
        </div>
    </div>

    <div class="cc-form-card mb-3">
        <div class="cc-form-card__body d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div class="d-flex align-items-center gap-2">
                <a href="{{ route('outs.index', ['date' => $prev]) }}" class="btn btn-sm btn-outline-secondary" title="{{ __('Día anterior') }}">‹</a>
                <div>
                    <div class="fw-semibold">{{ \Carbon\Carbon::parse($day)->isoFormat('ddd D MMM YYYY') }}</div>
                    <div class="cc-muted small">{{ $dayLabel }}@if($generalCall) · {{ __('Llamado gral.') }} {{ $generalCall }}@endif</div>
                </div>
                <a href="{{ route('outs.index', ['date' => $next]) }}" class="btn btn-sm btn-outline-secondary" title="{{ __('Día siguiente') }}">›</a>
            </div>
            <div class="text-end">
                <span class="badge bg-success">{{ $marked }} {{ __('marcaron') }}</span>
                <span class="badge bg-warning text-dark">{{ $pending }} {{ __('faltan') }}</span>
            </div>
        </div>
    </div>

    {{-- Reporte por DEPARTAMENTO (designado / producción): pegar el mensaje del grupo. --}}
    @if ($canDesignate)
        <div class="cc-form-card mb-3">
            <div class="cc-form-card__body">
                <div class="fw-semibold mb-1">{{ __('Reportar salida de un departamento') }}</div>
                <div class="cc-muted small mb-2">{{ __('Pega el mensaje del grupo. Ej.: «Cámara 18:30». Confirma lo que entendió; ante la duda, avisa qué no entendió.') }}</div>
                <form method="POST" action="{{ route('outs.ingest') }}" class="d-flex gap-2">
                    @csrf
                    <input type="text" name="message" class="form-control form-control-sm" placeholder="Cámara 18:30 …" maxlength="1000" required>
                    <button type="submit" class="btn btn-outline-primary btn-sm">{{ __('Registrar') }}</button>
                </form>
            </div>
        </div>
    @endif

    @if (empty($groups))
        <div class="alert alert-info">{{ __('Ningún departamento con llamado este día (o fuera de tu alcance).') }}</div>
    @else
        @foreach ($groups as $g)
            <div class="cc-form-card mb-3">
                <div class="cc-form-card__body">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                        <div class="fw-semibold">
                            {{ $g['label'] }}
                            @if ($g['dept_time'])
                                <span class="badge bg-info text-dark ms-1">{{ __('Depto salió') }} {{ $g['dept_time'] }}</span>
                            @endif
                        </div>
                        @if ($g['dept_out'])
                            <form method="POST" action="{{ route('outs.dept.destroy', $g['dept_out']->id) }}"
                                  data-confirm="{{ __('¿Quitar la salida de depto de') }} {{ $g['label'] }}?">
                                @csrf
                                <button type="submit" class="btn btn-outline-danger btn-sm">{{ __('Quitar salida de depto') }}</button>
                            </form>
                        @endif
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <tbody>
                                @foreach ($g['people'] as $p)
                                    <tr>
                                        <td>
                                            {{ $p['name'] }}
                                            @if ($p['cargo'])<span class="cc-muted small"> · {{ $p['cargo'] }}</span>@endif
                                        </td>
                                        <td style="width: 220px;">
                                            @if ($p['my_time'])
                                                <span class="badge bg-success">{{ __('marcó') }} {{ $p['my_time'] }}</span>
                                                @if ($p['discrepa'])
                                                    <span class="cc-muted small">· {{ __('depto') }} {{ $g['dept_time'] }}</span>
                                                @endif
                                            @elseif ($g['dept_time'])
                                                <span class="cc-muted small">{{ __('sin marca propia · depto') }} {{ $g['dept_time'] }}</span>
                                            @else
                                                <span class="badge bg-warning text-dark">{{ __('falta') }}</span>
                                            @endif
                                        </td>
                                        <td class="text-end">
                                            @if ($p['my_out_id'])
                                                <form method="POST" action="{{ route('outs.ind.destroy', $p['my_out_id']) }}"
                                                      data-confirm="{{ __('¿Quitar la salida de') }} {{ $p['name'] }}?">
                                                    @csrf
                                                    <button type="submit" class="btn btn-outline-danger btn-sm">
                                                        @include('componentes._icon', ['name' => 'trash-2', 'class' => 'cc-ico-16', 'label' => __('Quitar')])
                                                    </button>
                                                </form>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endforeach
    @endif
</div>
@endsection
