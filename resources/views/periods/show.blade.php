@extends('layouts.app')
@section('content')
{{-- EL TABLERO DE QUIÉN FALTA — por periodo: cuántos entregaron, cuántos faltan y QUIÉNES.
     Estados derivados por PayeePackage (RECIBIDO/FALTA, nunca "vigente"/"cumple"). Acotado por
     "quien contrata es quien ve". Una 32-D NO POSITIVA cuenta como faltante. --}}
@php
    $tally = $board['tally'];
    $renderColumns = $filters['document_type_id']
        ? $board['columns']->where('id', (int) $filters['document_type_id'])->values()
        : $board['columns'];
@endphp
<div class="crew-page">
    <div class="container-fluid px-3 px-md-4 py-4" style="max-width:1180px">

        <div class="mb-3">
            <a href="{{ route('periods.index') }}" class="btn btn-sm btn-crew-soft d-inline-flex align-items-center gap-1">
                @include('componentes._icon', ['name' => 'arrow-left', 'label' => null]) {{ __('Periodos') }}
            </a>
        </div>

        @if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif

        <div class="crew-header d-flex align-items-center gap-3 mb-3">
            <span class="crew-header-icon d-inline-flex align-items-center justify-content-center rounded-3">
                @include('componentes._icon', ['name' => 'clipboard-list', 'class' => 'cc-ico', 'label' => null])
            </span>
            <div>
                <h1 class="crew-title mb-0">{{ $period->displayLabel() }}</h1>
                <p class="text-muted mb-0 small">
                    <span class="badge text-bg-light border">{{ $period->frequencyLabel() }}</span>
                    <span class="ms-1">{{ optional($period->opens_on)->format('d/m/Y') }} → {{ optional($period->closes_on)->format('d/m/Y') }}</span>
                    @if($period->isOpen())
                        <span class="badge text-bg-success ms-1">{{ __('Abierto') }}</span>
                    @else
                        <span class="badge text-bg-secondary ms-1">{{ __('Cerrado') }}</span>
                    @endif
                    @if($period->isDayPlayer() && $period->payee)<span class="ms-1">· {{ $period->payee->name }}</span>@endif
                </p>
            </div>
            @can('periods.manage')
                <div class="ms-auto d-flex gap-2">
                    @if($tally['missing'] > 0)
                        <a href="{{ route('periods.reminders', $period) }}" class="btn btn-sm btn-outline-success">{{ __('Recordar a quienes faltan') }}</a>
                    @endif
                    @if($period->isOpen())
                        <form method="POST" action="{{ route('periods.close', $period) }}" class="d-inline">
                            @csrf<button class="btn btn-sm btn-outline-secondary">{{ __('Cerrar recepción') }}</button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('periods.reopen', $period) }}" class="d-inline">
                            @csrf<button class="btn btn-sm btn-outline-secondary">{{ __('Reabrir') }}</button>
                        </form>
                    @endif
                </div>
            @endcan
        </div>

        @unless($period->isOpen())
            <div class="alert alert-secondary small">{{ __('Ventana cerrada: define qué se ESPERABA. La recepción sigue registrándose, marcada fuera de periodo — nunca se rechaza.') }}</div>
        @endunless

        {{-- ── Resumen: entregaron / faltan / quiénes ── --}}
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-3">
                <div class="card h-100"><div class="card-body text-center">
                    <div class="display-6 fw-bold">{{ $tally['expected'] }}</div>
                    <div class="text-muted small text-uppercase" style="letter-spacing:.06em">{{ __('Esperados') }}</div>
                </div></div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card h-100"><div class="card-body text-center">
                    <div class="display-6 fw-bold text-success">{{ $tally['delivered'] }}</div>
                    <div class="text-muted small text-uppercase" style="letter-spacing:.06em">{{ __('Entregaron') }}</div>
                </div></div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card h-100"><div class="card-body text-center">
                    <div class="display-6 fw-bold {{ $tally['missing'] > 0 ? 'text-danger' : 'text-muted' }}">{{ $tally['missing'] }}</div>
                    <div class="text-muted small text-uppercase" style="letter-spacing:.06em">{{ __('Faltan') }}</div>
                </div></div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card h-100"><div class="card-body">
                    <div class="text-muted small text-uppercase mb-1" style="letter-spacing:.06em">{{ __('Quiénes faltan') }}</div>
                    @if(empty($tally['who_missing']))
                        <span class="text-success small">{{ __('Nadie: todos entregaron.') }}</span>
                    @else
                        <div class="small" style="max-height:6rem;overflow:auto">{{ implode(', ', $tally['who_missing']) }}</div>
                    @endif
                </div></div>
            </div>
        </div>

        {{-- ── Filtros: departamento + tipo de documento ── --}}
        <form method="GET" action="{{ route('periods.show', $period) }}" class="row g-2 align-items-end mb-3">
            <div class="col-sm-5 col-md-4">
                <label class="form-label small text-muted mb-1">{{ __('Departamento') }}</label>
                <select name="department" class="form-select form-select-sm">
                    <option value="">{{ __('Todos') }}</option>
                    @foreach($departments as $d)
                        <option value="{{ $d->id }}" @selected((int) $filters['department_id'] === (int) $d->id)>{{ $d->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-sm-5 col-md-4">
                <label class="form-label small text-muted mb-1">{{ __('Tipo de documento') }}</label>
                <select name="document_type" class="form-select form-select-sm">
                    <option value="">{{ __('Todos') }}</option>
                    @foreach($board['columns'] as $t)
                        <option value="{{ $t->id }}" @selected((int) $filters['document_type_id'] === (int) $t->id)>{{ $t->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-sm-2 col-md-4 d-flex gap-2">
                <button class="btn btn-sm btn-crew">{{ __('Filtrar') }}</button>
                @if($filters['department_id'] || $filters['document_type_id'])
                    <a href="{{ route('periods.show', $period) }}" class="btn btn-sm btn-crew-soft">{{ __('Limpiar') }}</a>
                @endif
                {{-- Export CSV del tablero (recibido/falta, nunca "vigente"), conservando los filtros. --}}
                <a href="{{ route('periods.export', ['period' => $period, 'department' => $filters['department_id'], 'document_type' => $filters['document_type_id']]) }}"
                   class="btn btn-sm btn-outline-secondary ms-auto d-inline-flex align-items-center gap-1">
                    @include('componentes._icon', ['name' => 'download', 'label' => null]) {{ __('Exportar CSV') }}
                </a>
            </div>
        </form>

        {{-- ── El tablero ── --}}
        <div class="card">
            <div class="card-body p-0">
                @if(empty($board['rows']))
                    <p class="text-muted mb-0 p-3">{{ __('Nadie con este periodo. Asigna la frecuencia a los contratos (en el Padrón de pago) para que aparezcan aquí.') }}</p>
                @else
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead><tr>
                                <th>{{ __('Beneficiario') }}</th>
                                @foreach($renderColumns as $t)
                                    <th class="small">{{ $t->name }}</th>
                                @endforeach
                                <th class="text-end">{{ __('Entrega') }}</th>
                            </tr></thead>
                            <tbody>
                                @foreach($board['rows'] as $row)
                                    <tr>
                                        <td>
                                            <span class="fw-semibold">{{ $row['payee']->name }}</span>
                                            @if($row['out_of_window'])<span class="badge text-bg-warning ms-1" title="{{ __('Algún documento llegó fuera de la ventana') }}">{{ __('fuera de ventana') }}</span>@endif
                                        </td>
                                        @foreach($renderColumns as $t)
                                            <td>
                                                @if(array_key_exists($t->id, $row['cells']))
                                                    @include('periods._state-chip', ['state' => $row['cells'][$t->id]])
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                        @endforeach
                                        <td class="text-end">
                                            @if($row['no_reqs'])
                                                <span class="text-muted small">{{ __('Sin requisitos') }}</span>
                                            @elseif($row['delivered'])
                                                <span class="badge text-bg-success">{{ __('Entregó') }}</span>
                                            @else
                                                <span class="badge text-bg-danger">{{ __('Falta') }} {{ $row['missing_count'] }}/{{ $row['total'] }}</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

    </div>
</div>
@endsection
