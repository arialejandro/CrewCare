@extends('layouts.app')
@section('content')
@php
    $conceptLabels = [
        \App\Models\PayeeContract::CONCEPT_CREW    => __('Trabajo de crew'),
        \App\Models\PayeeContract::CONCEPT_RENTAL  => __('Renta de equipo'),
        \App\Models\PayeeContract::CONCEPT_SERVICE => __('Servicio'),
    ];
@endphp
{{-- PASO 4 · VISIBILIDAD — ficha de un payee (solo lectura). Datos fiscales sensibles:
     la pantalla llega solo a quien tiene alcance (PayeePolicy). Los PDF se abren por el
     serve GATEADO (payees.document), nunca por /storage. --}}
<div class="crew-page">
    <div class="container-fluid px-3 px-md-4 py-4" style="max-width:960px">

        <div class="mb-3">
            <a href="{{ route('payees.index') }}" class="btn btn-sm btn-crew-soft d-inline-flex align-items-center gap-1">
                @include('componentes._icon', ['name' => 'arrow-left', 'label' => null]) {{ __('Volver') }}
            </a>
        </div>

        @if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif

        <div class="crew-header d-flex align-items-center gap-3 mb-4">
            <span class="crew-header-icon d-inline-flex align-items-center justify-content-center rounded-3">
                @include('componentes._icon', ['name' => $payee->isMoral() ? 'building-2' : 'user', 'class' => 'cc-ico', 'label' => null])
            </span>
            <div>
                <h1 class="crew-title mb-0">{{ $payee->name ?: __('(sin nombre)') }}</h1>
                <p class="text-muted mb-0 small">
                    <span class="badge {{ $payee->isMoral() ? 'text-bg-primary' : 'text-bg-secondary' }}">{{ $payee->isMoral() ? __('Persona moral') : __('Persona física') }}</span>
                    @if($payee->nationality === 'extranjera')<span class="badge text-bg-info">{{ __('Extranjera') }}</span>@endif
                    @if($payee->intake_submitted_at)<span class="badge text-bg-success">{{ __('Registro recibido') }}</span>
                    @else<span class="badge text-bg-warning">{{ __('Registro pendiente') }}</span>@endif
                    @if($payee->ambulanceProvider)<span class="badge text-bg-danger">{{ __('Proveedor de ambulancias') }}</span>@endif
                </p>
            </div>
            @if($payee->ambulanceProvider)
                @can('ambulance.view')
                    <a href="{{ route('ambulance.provider.show', $payee->ambulanceProvider->id) }}"
                       class="btn btn-sm btn-crew-soft ms-auto d-inline-flex align-items-center gap-1" title="{{ __('Ver el lado OPERAR (ambulancia)') }}">
                        @include('componentes._icon', ['name' => 'heart-pulse', 'label' => null]) {{ __('Operar (ambulancia)') }}
                    </a>
                @endcan
            @endif
        </div>

        {{-- ── Datos fiscales de la identidad ── --}}
        <div class="card mb-4">
            <div class="card-header fw-semibold">{{ __('Datos fiscales') }}</div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-4">{{ __('RFC') }}</dt><dd class="col-sm-8 font-monospace">{{ $payee->rfc ?: '—' }}</dd>
                    <dt class="col-sm-4">{{ __('Residencia fiscal') }}</dt><dd class="col-sm-8">{{ $payee->tax_residence_country ?: '—' }}</dd>
                    <dt class="col-sm-4">{{ __('Regímenes') }}</dt>
                    <dd class="col-sm-8">
                        @forelse($payee->fiscalRegimes as $r)
                            <span class="badge text-bg-light border">{{ trim(($r->code ? $r->code.' · ' : '').$r->name) }}</span>
                        @empty — @endforelse
                    </dd>
                    <dt class="col-sm-4">{{ __('Banco') }}</dt><dd class="col-sm-8">{{ $payee->bank_name ?: '—' }}</dd>
                    <dt class="col-sm-4">{{ __('CLABE') }}</dt><dd class="col-sm-8 font-monospace">{{ $payee->bank_clabe ?: '—' }}</dd>
                    @if($payee->addr_cp || $payee->addr_street)
                        <dt class="col-sm-4">{{ __('Domicilio') }}</dt>
                        <dd class="col-sm-8">{{ trim(collect([$payee->addr_street, $payee->addr_ext_no, $payee->addr_colonia, $payee->addr_municipio, $payee->addr_state, $payee->addr_cp])->filter()->implode(', ')) ?: '—' }}</dd>
                    @endif
                </dl>
            </div>
        </div>

        {{-- ── Contratos (quién contrata) ── --}}
        <div class="card mb-4">
            <div class="card-header fw-semibold">{{ __('Contratos') }} <span class="text-muted">({{ $payee->contracts->count() }})</span></div>
            <div class="card-body">
                @if($payee->contracts->isEmpty())
                    <p class="text-muted mb-0">{{ __('Sin contratos registrados.') }}</p>
                @else
                    <div class="table-responsive">
                        <table class="table cc-stack align-middle mb-0">
                            <thead><tr>
                                <th>{{ __('Concepto') }}</th><th>{{ __('Contrata') }}</th>
                                <th>{{ __('Régimen') }}</th><th>{{ __('Frecuencia') }}</th><th>{{ __('REPSE') }}</th>
                            </tr></thead>
                            <tbody>
                                @foreach($payee->contracts as $c)
                                    <tr>
                                        <td data-label="{{ __('Concepto') }}">{{ $conceptLabels[$c->concept] ?? $c->concept }}</td>
                                        <td data-label="{{ __('Contrata') }}">{{ optional($c->contractedBy)->name ? trim($c->contractedBy->name.' '.$c->contractedBy->lname) : '—' }}</td>
                                        <td data-label="{{ __('Régimen') }}">{{ optional($c->fiscalRegime)->name ?: '—' }}</td>
                                        <td data-label="{{ __('Frecuencia') }}">
                                            @can('periods.manage')
                                                {{-- La frecuencia HEREDA el periodo de pago; definible caso por caso (day players/apoyos). --}}
                                                <form method="POST" action="{{ route('periods.contract.frequency', $c) }}" class="d-inline-flex gap-1 align-items-center">
                                                    @csrf
                                                    <select name="payment_frequency" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
                                                        <option value="">{{ __('—') }}</option>
                                                        @foreach(\App\Models\PayeeContract::frequencies() as $fv => $fl)
                                                            <option value="{{ $fv }}" @selected($c->payment_frequency === $fv)>{{ $fl }}</option>
                                                        @endforeach
                                                    </select>
                                                    <noscript><button class="btn btn-sm btn-crew-soft">{{ __('Guardar') }}</button></noscript>
                                                </form>
                                            @else
                                                {{ $c->frequencyLabel() ?: '—' }}
                                            @endcan
                                        </td>
                                        <td data-label="{{ __('REPSE') }}">@if($c->is_repse)<span class="badge text-bg-warning">{{ __('Sí') }}</span>@else<span class="text-muted">—</span>@endif</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        {{-- ── Documentos (serve GATEADO) — separados por familia: OPERAR vs COBRAR (Paso 5) ── --}}
        @php
            $operateDocs = $payee->documents->where('level', 'empresa')->values();
            $billingDocs = $payee->documents->where('level', '!=', 'empresa')->values();
        @endphp
        <div class="card mb-4">
            <div class="card-header fw-semibold">{{ __('Documentos') }} <span class="text-muted">({{ $payee->documents->count() }})</span></div>
            <div class="card-body">
                @if($payee->documents->isEmpty())
                    <p class="text-muted mb-0">{{ __('Sin documentos.') }}</p>
                @else
                    @if($operateDocs->isNotEmpty())
                        <div class="text-uppercase text-muted small fw-semibold mb-2" style="letter-spacing:.06em">{{ __('Para operar (ambulancia)') }}</div>
                        <ul class="list-group list-group-flush mb-3">
                            @foreach($operateDocs as $doc)
                                @include('payee._doc-line', ['doc' => $doc, 'payee' => $payee])
                            @endforeach
                        </ul>
                    @endif

                    <div class="text-uppercase text-muted small fw-semibold mb-2" style="letter-spacing:.06em">{{ __('Para cobrar') }}</div>
                    @if($billingDocs->isEmpty())
                        <p class="text-muted mb-0">{{ __('Sin documentos fiscales recibidos.') }}</p>
                    @else
                        <ul class="list-group list-group-flush">
                            @foreach($billingDocs as $doc)
                                @include('payee._doc-line', ['doc' => $doc, 'payee' => $payee])
                            @endforeach
                        </ul>
                    @endif
                @endif
            </div>
        </div>

        {{-- ── Beneficiarios + equipo declarado (resumen) ── --}}
        <div class="row g-4">
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header fw-semibold">{{ __('Beneficiarios') }}</div>
                    <div class="card-body">
                        @forelse($payee->beneficiaries as $b)
                            <div class="d-flex justify-content-between"><span>{{ $b->full_name }} <span class="text-muted small">({{ $b->relationship }})</span></span><span class="fw-semibold">{{ rtrim(rtrim(number_format($b->percentage, 2), '0'), '.') }}%</span></div>
                        @empty
                            <p class="text-muted mb-0">{{ __('Sin beneficiarios.') }}</p>
                        @endforelse
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header fw-semibold">{{ __('Equipo declarado') }}</div>
                    <div class="card-body">
                        @forelse($payee->declaredEquipment as $e)
                            <div class="d-flex justify-content-between">
                                <span>{{ $e->description }}</span>
                                <span class="text-muted">${{ number_format((float) $e->declared_value, 2) }} @if($e->isSigned())<span class="badge text-bg-success">{{ __('Firmado') }}</span>@endif</span>
                            </div>
                        @empty
                            <p class="text-muted mb-0">{{ __('Sin equipo declarado.') }}</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>
@endsection
