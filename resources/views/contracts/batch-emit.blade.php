@extends('layouts.app')
@section('content')
{{-- EL CONTRATO · PASO C · B2 — EMISIÓN MASIVA. Crear (y opcionalmente enviar) el sobre de varios
     contratos crew_work de una sola vez. Solo aparecen los EMITIDOS (con carátula), sin sobre en
     curso, y visibles para quien captura. Cada quien firma su propio contrato (N sobres, no uno). --}}
<div class="crew-page">
    <div class="container-fluid px-3 px-md-4 py-4" style="max-width:1080px">

        @if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
        @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

        <div class="crew-header d-flex align-items-center gap-3 mb-3">
            <span class="crew-header-icon d-inline-flex align-items-center justify-content-center rounded-3">
                @include('componentes._icon', ['name' => 'layers', 'class' => 'cc-ico', 'label' => null])
            </span>
            <div>
                <h1 class="crew-title mb-0">{{ __('Emisión masiva de sobres') }}</h1>
                <p class="text-muted mb-0 small">{{ __('Crea la ruta de firma de varios contratos de una vez. Cada persona firma el suyo.') }}</p>
            </div>
        </div>

        {{-- Resumen del último lote --}}
        @php $summary = session('batch_summary'); @endphp
        @if($summary)
            <div class="card mb-4">
                <div class="card-header fw-semibold">{{ __('Resultado del lote') }}</div>
                <div class="card-body">
                    @if(!empty($summary['created']))
                        <div class="fw-semibold text-success mb-1">{{ __('Sobres creados') }} ({{ count($summary['created']) }})</div>
                        <ul class="list-group list-group-flush mb-3">
                            @foreach($summary['created'] as $row)
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span>{{ $row['payee'] }} <span class="text-muted">· {{ $row['puesto'] }}</span></span>
                                    <a href="{{ route('contracts.envelope.show', $row['envelope']) }}" class="btn btn-sm btn-crew-soft">{{ __('Ver sobre') }}</a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                    @if(!empty($summary['skipped']))
                        <div class="fw-semibold text-warning mb-1">{{ __('Omitidos') }} ({{ count($summary['skipped']) }})</div>
                        <ul class="list-group list-group-flush">
                            @foreach($summary['skipped'] as $row)
                                <li class="list-group-item d-flex justify-content-between align-items-center gap-2">
                                    <span>{{ $row['payee'] }} <span class="text-muted">· {{ $row['puesto'] }}</span></span>
                                    <span class="badge text-bg-light border text-wrap text-end">{{ $row['reason'] }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        @endif

        {{-- Filtro por departamento --}}
        <form method="GET" action="{{ route('contracts.batch.form') }}" class="row g-2 align-items-end mb-3">
            <div class="col-12 col-md-6">
                <label class="form-label small mb-1">{{ __('Departamento') }}</label>
                <select name="department_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">{{ __('Todos los departamentos') }}</option>
                    @foreach($departments as $d)
                        <option value="{{ $d->id }}" @selected($deptId === $d->id)>{{ $d->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-3">
                <button class="btn btn-sm btn-crew-soft w-100">{{ __('Filtrar') }}</button>
            </div>
        </form>

        {{-- Lista elegible + acción por lote --}}
        <div class="card">
            <div class="card-header fw-semibold d-flex flex-wrap align-items-center justify-content-between gap-2">
                <span>{{ __('Contratos listos para emitir') }} ({{ $eligible->count() }})</span>
                <span class="text-muted small fw-normal">{{ __('Emitidos (con carátula), sin sobre en curso.') }}</span>
            </div>
            <div class="card-body">
                @if($eligible->isEmpty())
                    <p class="text-muted small mb-0">{{ __('No hay contratos elegibles. Deben estar emitidos (con carátula) y sin un sobre en curso.') }}</p>
                @else
                    <form method="POST" action="{{ route('contracts.batch.store') }}">
                        @csrf
                        <input type="hidden" name="department_id" value="{{ $deptId }}">
                        <div class="table-responsive">
                            <table class="table table-sm align-middle">
                                <thead>
                                    <tr>
                                        <th style="width:36px"><input type="checkbox" id="ccAll" class="form-check-input" aria-label="{{ __('Seleccionar todos') }}"></th>
                                        <th>{{ __('Contratado') }}</th>
                                        <th>{{ __('Puesto') }}</th>
                                        <th>{{ __('Departamento') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($eligible as $c)
                                        <tr>
                                            <td><input type="checkbox" name="contract_ids[]" value="{{ $c->id }}" class="form-check-input cc-pick" checked></td>
                                            <td class="fw-semibold">{{ optional($c->payee)->name }}</td>
                                            <td>{{ $c->title ?: '—' }}</td>
                                            <td>{{ optional($c->department)->name ?: '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="d-flex flex-wrap gap-2 mt-2">
                            <button type="submit" name="send" value="0" class="btn btn-sm btn-crew-soft">{{ __('Crear sobres (borrador)') }}</button>
                            <button type="submit" name="send" value="1" class="btn btn-sm btn-crew">{{ __('Crear y enviar a firma') }}</button>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    </div>
</div>

<script>
    (function () {
        var all = document.getElementById('ccAll');
        if (!all) return;
        all.checked = true;
        all.addEventListener('change', function () {
            document.querySelectorAll('.cc-pick').forEach(function (c) { c.checked = all.checked; });
        });
    })();
</script>
@endsection
