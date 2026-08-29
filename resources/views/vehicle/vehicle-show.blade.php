@extends('layouts.app')
@section('content')
@php
    use App\Support\VehicleVerdict;
    $attrs = $vehicle->resolvedAttributes();
    $ptLabels = ['combustion' => 'Combustión', 'electric' => 'Eléctrico', 'hybrid' => 'Híbrido'];
    $docsByCode = $vehicle->documents->groupBy(fn ($d) => optional($d->documentType)->code);
    $active = $inspections->firstWhere('is_active', true);
@endphp

<div class="crew-page insp-page">
    <div class="container-fluid px-3 px-md-4 py-4" style="max-width:1040px">

        <div class="crew-header d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-4">
            <div class="d-flex align-items-center gap-3">
                <span class="crew-header-icon d-inline-flex align-items-center justify-content-center rounded-3">
                    @include('componentes._icon', ['name' => 'truck', 'class' => 'cc-ico', 'label' => null])
                </span>
                <div>
                    <h1 class="crew-title mb-0">{{ trim(($vehicle->make ?: '') . ' ' . ($vehicle->model ?: '')) ?: ($vehicle->type->name_es ?? '—') }}</h1>
                    <p class="text-muted mb-0 small">{{ $vehicle->plate ?: __('Sin placas') }} · {{ $vehicle->type->name_es ?? '—' }}</p>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('transport.inspect.form', ['vehicle_id' => $vehicle->id]) }}" class="btn btn-crew-accent d-inline-flex align-items-center gap-1">
                    @include('componentes._icon', ['name' => 'shield-check', 'label' => null]) {{ __('Verificar') }}
                </a>
                <a href="{{ route('transport.vehicle.edit', $vehicle) }}" class="btn btn-crew-soft d-inline-flex align-items-center gap-1">
                    @include('componentes._icon', ['name' => 'pencil', 'label' => null]) {{ __('Editar') }}
                </a>
                <a href="{{ route('transport.index') }}" class="btn btn-crew-soft">{{ __('Volver') }}</a>
            </div>
        </div>

        @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
        @if (session('warn'))<div class="alert alert-warning">{{ session('warn') }}</div>@endif
        @if (session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

        <div class="mb-4">@include('vehicle._marks', ['vehicle' => $vehicle])</div>

        <div class="row g-4">
            {{-- Datos --}}
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm rounded-3 p-3 p-md-4 h-100">
                    <h5 class="mb-3">{{ __('Datos') }}</h5>
                    <dl class="row mb-0 small">
                        <dt class="col-5 text-muted">{{ __('Tipo') }}</dt><dd class="col-7">{{ $vehicle->type->name_es ?? '—' }}</dd>
                        <dt class="col-5 text-muted">{{ __('Año / color') }}</dt><dd class="col-7">{{ $vehicle->year ?: '—' }} · {{ $vehicle->color ?: '—' }}</dd>
                        <dt class="col-5 text-muted">{{ __('VIN') }}</dt><dd class="col-7">{{ $vehicle->vin ?: '—' }}</dd>
                        <dt class="col-5 text-muted">{{ __('Powertrain') }}</dt><dd class="col-7">{{ $ptLabels[$attrs['powertrain'] ?? ''] ?? '—' }}</dd>
                        <dt class="col-5 text-muted">{{ __('Propietario') }}</dt><dd class="col-7">{{ $vehicle->ownerLabel() ?: '—' }}</dd>
                        <dt class="col-5 text-muted">{{ __('Conductor') }}</dt><dd class="col-7">{{ $vehicle->driverLabel() ?: '—' }}</dd>
                        <dt class="col-5 text-muted">{{ __('Km inicial') }}</dt><dd class="col-7">{{ $vehicle->initial_km !== null ? number_format($vehicle->initial_km) . ' km' : '—' }}</dd>
                    </dl>
                </div>
            </div>

            {{-- Documentos --}}
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm rounded-3 p-3 p-md-4 h-100">
                    <h5 class="mb-3">{{ __('Documentos') }}</h5>
                    @foreach ($docTypes as $dt)
                        @php
                            $validated = $docState[$dt->code] ?? null;
                            $latest    = optional($docsByCode->get($dt->code))->sortByDesc('id')->first();
                        @endphp
                        <div class="d-flex align-items-center justify-content-between gap-2 py-2 border-bottom">
                            <div>
                                <div class="fw-semibold small">{{ $dt->name }}</div>
                                @if ($validated)
                                    <span class="text-success small">@include('componentes._icon', ['name' => 'file-check', 'label' => null]) {{ __('Revisado') }}@if($validated->effectiveValidUntil()) · {{ __('vence') }} {{ $validated->effectiveValidUntil()->format('d/m/Y') }}@endif</span>
                                @elseif ($latest && $latest->isValidated())
                                    <span class="text-warning small">{{ __('Validado pero VENCIDO — se vuelve a pedir') }}</span>
                                @elseif ($latest)
                                    <span class="text-muted small">{{ __('Capturado, pendiente de validar') }}</span>
                                @else
                                    <span class="text-muted small">{{ __('Falta') }}</span>
                                @endif
                            </div>
                            @if ($latest && ! $validated && $latest->isPending())
                                <form method="post" action="{{ route('transport.document.validate', $latest->id) }}" onsubmit="return confirm('{{ __('¿Validar el documento? Queda a tu nombre.') }}');" class="m-0">
                                    @csrf
                                    <input type="hidden" name="attestation" value="1">
                                    <button type="submit" class="btn btn-sm btn-outline-success">{{ __('Validar') }}</button>
                                </form>
                            @endif
                        </div>
                    @endforeach

                    {{-- Captura --}}
                    <form method="post" action="{{ route('transport.document.store', $vehicle) }}" enctype="multipart/form-data" class="mt-3">
                        @csrf
                        <div class="row g-2">
                            <div class="col-12">
                                <select name="document_type_id" class="form-select form-select-sm" required>
                                    <option value="">{{ __('— Tipo de documento —') }}</option>
                                    @foreach ($docTypes as $dt)
                                        <option value="{{ $dt->id }}">{{ $dt->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-6"><input type="text" name="folio" class="form-control form-control-sm" placeholder="{{ __('Folio') }}"></div>
                            <div class="col-6"><input type="date" name="valid_until" class="form-control form-control-sm" title="{{ __('Vencimiento') }}"></div>
                            <div class="col-12"><input type="file" name="photo" class="form-control form-control-sm" accept="image/*,.heic,.heif"></div>
                            <div class="col-12"><button type="submit" class="btn btn-sm btn-crew-soft">{{ __('Capturar documento') }}</button></div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- Licencia del conductor (§2): vive en el paquete del driver (contabilidad); aquí se MUESTRA. --}}
        <div class="card border-0 shadow-sm rounded-3 p-3 p-md-4 mt-4">
            <h5 class="mb-3">{{ __('Licencia del conductor') }}</h5>
            @if (! $vehicle->driver_user_id)
                <p class="text-muted mb-0">{{ __('Asigna un conductor para registrar y ver su licencia.') }}</p>
            @else
                <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap">
                    <div>
                        <div class="fw-semibold">{{ $vehicle->driverLabel() }}</div>
                        @if ($driverLicense)
                            @if ($driverLicense->isValidated())
                                <span class="text-success small">@include('componentes._icon', ['name' => 'file-check', 'label' => null]) {{ __('Licencia validada') }}@if($driverLicense->effectiveValidUntil()) · {{ __('vence') }} {{ $driverLicense->effectiveValidUntil()->format('d/m/Y') }}@endif</span>
                            @else
                                <span class="text-muted small">{{ __('Licencia capturada, pendiente de validar') }}@if($driverLicense->effectiveValidUntil()) · {{ __('vence') }} {{ $driverLicense->effectiveValidUntil()->format('d/m/Y') }}@endif</span>
                            @endif
                            <div class="text-muted small">{{ __('En el paquete documental del conductor (para contabilidad). No se copia.') }}</div>
                        @else
                            <span class="text-muted small">{{ __('Sin licencia registrada en el paquete del conductor.') }}</span>
                        @endif
                    </div>
                    @if ($driverLicense && $driverLicense->isPending())
                        <form method="post" action="{{ route('transport.document.validate', $driverLicense->id) }}" onsubmit="return confirm('{{ __('¿Validar la licencia? Queda a tu nombre.') }}');" class="m-0">
                            @csrf
                            <input type="hidden" name="attestation" value="1">
                            <button type="submit" class="btn btn-sm btn-outline-success">{{ __('Validar') }}</button>
                        </form>
                    @endif
                </div>
                <form method="post" action="{{ route('transport.driver.license.store', $vehicle) }}" enctype="multipart/form-data" class="mt-3">
                    @csrf
                    <div class="row g-2 align-items-end">
                        <div class="col-6 col-md-3"><input type="text" name="folio" class="form-control form-control-sm" placeholder="{{ __('Folio de licencia') }}"></div>
                        <div class="col-6 col-md-3"><input type="date" name="valid_until" class="form-control form-control-sm" title="{{ __('Vencimiento') }}"></div>
                        <div class="col-8 col-md-4"><input type="file" name="photo" class="form-control form-control-sm" accept="image/*,.heic,.heif"></div>
                        <div class="col-4 col-md-2"><button type="submit" class="btn btn-sm btn-crew-soft w-100">{{ __('Capturar') }}</button></div>
                    </div>
                </form>
            @endif
        </div>

        {{-- Actas / historial --}}
        <div class="card border-0 shadow-sm rounded-3 p-3 p-md-4 mt-4">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h5 class="mb-0">{{ __('Actas de verificación') }}</h5>
                @if ($active)
                    <a href="{{ route('transport.inspect.form', ['vehicle_id' => $vehicle->id, 'reeval' => $active->id]) }}" class="btn btn-sm btn-crew-soft d-inline-flex align-items-center gap-1">
                        @include('componentes._icon', ['name' => 'refresh-cw', 'label' => null]) {{ __('Reevaluar') }}
                    </a>
                @endif
            </div>
            @if ($inspections->count())
                <div class="list-group">
                    @foreach ($inspections as $acta)
                        @php $apto = $acta->isApto(); @endphp
                        <div class="list-group-item d-flex align-items-center justify-content-between gap-2">
                            <div>
                                <a href="{{ route('transport.acta', $acta->uuid) }}" class="fw-semibold text-decoration-none">{{ $acta->folio() }}</a>
                                <span class="text-muted small ms-2">
                                    {{ optional($acta->created_at)->format('d/m/Y H:i') }}
                                    @if ($acta->is_reevaluation) · {{ __('reevaluación') }}@endif
                                    @if (! $acta->is_active) · {{ __('retirada') }}@endif
                                </span>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <span class="insp-tag" style="background:{{ $apto ? '#dcfce7' : '#fee2e2' }};color:{{ $apto ? '#166534' : '#991b1b' }};">{{ $apto ? __('Apto') : __('No apto') }} · {{ VehicleVerdict::levelLabel($acta->level) }}</span>
                                @if (! $apto)
                                    <a href="{{ route('transport.acta.rejection', $acta->uuid) }}" class="btn btn-sm btn-outline-danger">{{ __('Rechazo PDF') }}</a>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-muted mb-0">{{ __('Aún no se ha verificado este vehículo.') }}</p>
            @endif
        </div>

    </div>
</div>

@push('styles')
    @include('componentes._crew-list-styles')
    @include('componentes._inspection-styles')
@endpush
@endsection
