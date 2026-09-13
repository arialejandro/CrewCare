@extends('layouts.app')

@section('content')
@include('componentes._form-kit')
@include('componentes._confirm-submit')
@include('componentes._typeahead')

@php
    use Carbon\Carbon;
    $prev = Carbon::parse($day)->subDay()->toDateString();
    $next = Carbon::parse($day)->addDay()->toDateString();
@endphp

<div class="container-fluid py-4" style="max-width: 1000px;">
    @include('componentes._form-feedback')

    {{-- Encabezado + navegación por día --}}
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div class="d-flex align-items-center gap-3">
            <span class="cc-form-ico">
                @include('componentes._icon', ['name' => 'log-out', 'class' => 'cc-ico-20', 'label' => null])
            </span>
            <div>
                <h1 class="h4 fw-bold mb-0">{{ __('Salidas') }}</h1>
                <div class="cc-muted small">{{ __('A qué hora cada departamento abandona la locación.') }}</div>
            </div>
        </div>
        <a href="{{ route('outs.turnaround', ['date' => $day]) }}" class="btn btn-outline-secondary btn-sm">
            @include('componentes._icon', ['name' => 'clock', 'class' => 'cc-ico-16', 'label' => null])
            {{ __('Turnaround') }}
        </a>
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
                <span class="badge bg-success">{{ $reported }} {{ __('reportaron') }}</span>
                <span class="badge bg-warning text-dark">{{ $pending }} {{ __('faltan') }}</span>
                <div class="cc-muted small">{{ $totalDepts }} {{ __('deptos con llamado') }}</div>
            </div>
        </div>
    </div>

    {{-- DEPARTAMENTOS con llamado ese día --}}
    @if (empty($deptRows))
        <div class="alert alert-info">{{ __('Ningún departamento con llamado registrado este día (o fuera de tu alcance).') }}</div>
    @else
        <div class="cc-form-card mb-4">
            <div class="cc-form-card__body">
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('Departamento') }}</th>
                                <th class="text-center">{{ __('Llamado') }}</th>
                                <th style="width: 260px;">{{ __('Salida') }}</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($deptRows as $d)
                                @php $out = $deptOuts[$d['id']] ?? null; @endphp
                                <tr>
                                    <td>
                                        <span class="fw-semibold">{{ $d['label'] }}</span>
                                        @if ($out)
                                            <span class="badge bg-success ms-1">{{ __('Reportado') }}</span>
                                        @else
                                            <span class="badge bg-warning text-dark ms-1">{{ __('Pendiente') }}</span>
                                        @endif
                                    </td>
                                    <td class="text-center cc-muted">{{ $d['called'] }}</td>
                                    <td>
                                        {{-- Dos toques: escribe/ajusta la hora y guarda. Corregir = mismo formulario. --}}
                                        <form method="POST" action="{{ route('outs.dept.store') }}" class="d-flex align-items-center gap-2">
                                            @csrf
                                            <input type="hidden" name="department_id" value="{{ $d['id'] }}">
                                            <input type="hidden" name="shoot_date" value="{{ $day }}">
                                            <input type="time" name="time" class="form-control form-control-sm" style="max-width: 120px;"
                                                   value="{{ $out ? \Carbon\Carbon::parse($out->out_at)->format('H:i') : '' }}" required>
                                            <button type="submit" class="btn btn-primary btn-sm">
                                                {{ $out ? __('Corregir') : __('Guardar') }}
                                            </button>
                                        </form>
                                    </td>
                                    <td class="text-end">
                                        @if ($out)
                                            <form method="POST" action="{{ route('outs.dept.destroy', $out->id) }}"
                                                  data-confirm="{{ __('¿Quitar la salida de') }} {{ $d['label'] }}?">
                                                @csrf
                                                <button type="submit" class="btn btn-outline-danger btn-sm" title="{{ __('Quitar') }}">
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
    @endif

    {{-- SALIDAS INDIVIDUALES (alguien que salió a otra hora que su equipo) --}}
    <div class="cc-form-card mb-4">
        <div class="cc-form-card__body">
            <div class="fw-semibold mb-1">{{ __('Salidas individuales') }}</div>
            <div class="cc-muted small mb-3">{{ __('Alguien que salió a distinta hora que su departamento. Gana sobre la del depto.') }}</div>

            @if ($individualOuts->isNotEmpty())
                <div class="table-responsive mb-3">
                    <table class="table table-sm align-middle mb-0">
                        <tbody>
                            @foreach ($individualOuts as $io)
                                <tr>
                                    <td>{{ $indNames[$io->user_id] ?? ('#' . $io->user_id) }}</td>
                                    <td class="mono">{{ \Carbon\Carbon::parse($io->out_at)->format('H:i') }}</td>
                                    <td class="text-end">
                                        <form method="POST" action="{{ route('outs.ind.destroy', $io->id) }}"
                                              data-confirm="{{ __('¿Quitar esta salida individual?') }}">
                                            @csrf
                                            <button type="submit" class="btn btn-outline-danger btn-sm">
                                                @include('componentes._icon', ['name' => 'trash-2', 'class' => 'cc-ico-16', 'label' => __('Quitar')])
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            <form method="POST" action="{{ route('outs.ind.store') }}" class="row g-2 align-items-end">
                @csrf
                <input type="hidden" name="shoot_date" value="{{ $day }}">
                <div class="col-12 col-md-6">
                    <label class="form-label small mb-1">{{ __('Persona') }}</label>
                    <select name="user_id" class="form-select form-select-sm js-typeahead" required>
                        <option value="">{{ __('Buscar persona…') }}</option>
                        @foreach ($rosterPeople as $p)
                            <option value="{{ $p['user_id'] }}">{{ $p['name'] }} — {{ $p['dept'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label small mb-1">{{ __('Hora') }}</label>
                    <input type="time" name="time" class="form-control form-control-sm" required>
                </div>
                <div class="col-6 col-md-3">
                    <button type="submit" class="btn btn-primary btn-sm w-100">{{ __('Registrar salida') }}</button>
                </div>
            </form>
        </div>
    </div>

    {{-- PEGAR MENSAJE de grupo (mismo servicio que usará el bot). Solo producción/coordinación. --}}
    @if ($canPasteAll)
        <div class="cc-form-card">
            <div class="cc-form-card__body">
                <div class="fw-semibold mb-1">{{ __('Pegar mensaje del grupo') }}</div>
                <div class="cc-muted small mb-2">{{ __('Ej.: «Cámara 18:30 Juan Pérez 21:00». Confirma lo que entendió; ante la duda, avisa qué no entendió.') }}</div>
                <form method="POST" action="{{ route('outs.ingest') }}">
                    @csrf
                    <input type="hidden" name="shoot_date" value="{{ $day }}">
                    <div class="d-flex gap-2">
                        <input type="text" name="message" class="form-control form-control-sm" placeholder="Cámara 18:30 …" maxlength="1000" required>
                        <button type="submit" class="btn btn-outline-primary btn-sm">{{ __('Registrar') }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
@endsection
