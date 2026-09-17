@extends('layouts.app')
@section('content')
@php
    $aliases = is_array($tool->aliases ?? null) ? $tool->aliases : [];
    $ppe = is_array($tool->min_ppe ?? null) ? $tool->min_ppe : [];
    $scopeLabels = ['universal'=>__('Universal'),'universal_energizada'=>__('Energizada'),'familia'=>__('Familia'),'tipo'=>__('Tipo'),'actividad'=>__('Actividad')];
@endphp

<div class="crew-page insp-page">
    <div class="container-fluid px-3 px-md-4 py-4" style="max-width: 820px;">

        <a href="{{ route('tools.index') }}" class="text-muted small d-inline-flex align-items-center gap-1 mb-3" style="text-decoration:none;">
            @include('componentes._icon', ['name' => 'chevron-left', 'label' => null]) {{ __('Volver al catálogo') }}
        </a>

        <div class="d-flex justify-content-between align-items-start gap-3 mb-3 flex-wrap">
            <div class="d-flex align-items-start gap-3">
                @if ($tool->imageUrl())
                    <img src="{{ $tool->imageUrl() }}" alt="{{ $tool->name }}"
                         class="rounded-3" style="width:56px;height:56px;object-fit:contain;background:var(--surface-2);padding:4px;">
                @else
                    <span class="crew-header-icon d-inline-flex align-items-center justify-content-center rounded-3">
                        @include('componentes._icon', ['name' => 'wrench', 'class' => 'cc-ico', 'label' => null])
                    </span>
                @endif
                <div>
                    <h1 class="crew-title mb-0">{{ $tool->name }}</h1>
                    <p class="text-muted mb-0 small">{{ $tool->code }} @if($tool->name_en) · {{ $tool->name_en }} @endif</p>
                    @if ($aliases)<p class="tool-card__alias mb-0">{{ implode(' · ', $aliases) }}</p>@endif
                </div>
            </div>
            <a href="{{ route('tools.inspect.form', $tool->id) }}" class="btn btn-crew-accent d-inline-flex align-items-center gap-2">
                @include('componentes._icon', ['name' => 'clipboard-check', 'label' => null]) {{ __('Inspeccionar') }}
            </a>
        </div>

        <div class="card border-0 shadow-sm rounded-3 p-3 p-md-4 mb-3">
            @if ($tool->quick_id)
                <div class="tool-card__look mb-3"><span><strong>{{ __('Qué mirar') }}:</strong> {{ $tool->quick_id }}</span></div>
            @endif
            @if ($tool->definition)<p>{{ $tool->definition }}</p>@endif
            @if ($tool->main_risk)<p class="mb-2"><strong>{{ __('Riesgo principal') }}:</strong> {{ $tool->main_risk }}</p>@endif
            <div class="d-flex flex-wrap gap-2 small">
                @if ($tool->requires_designated_operator)<span class="insp-tag insp-tag--gate">{{ __('Operador designado') }}</span>@endif
                @if ($tool->triggers_permit_name)<span class="insp-tag">{{ __('Permiso') }}: {{ $tool->triggers_permit_name }}</span>@endif
                @foreach ($ppe as $e)<span class="insp-tag">{{ $e }}</span>@endforeach
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-3 p-3 p-md-4">
            <h5 class="mb-3">{{ __('Checklist') }} <span class="text-muted small">({{ $points->count() }} {{ __('puntos') }})</span></h5>
            @php $lastScope = null; @endphp
            @foreach ($points as $p)
                @if ($p->scope !== $lastScope)
                    <div class="insp-scope-head">{{ $scopeLabels[$p->scope] ?? $p->scope }}</div>
                    @php $lastScope = $p->scope; @endphp
                @endif
                <div class="insp-point {{ $p->is_gate ? 'is-gate' : 'is-info' }}" style="margin-bottom:.5rem;">
                    <p class="insp-point__text mb-1">{{ $p->text_es }}</p>
                    <div class="insp-point__meta">
                        <span class="insp-tag">{{ $p->code }}</span>
                        @if ($p->is_gate)<span class="insp-tag insp-tag--gate">{{ __('Compuerta') }}</span>@endif
                        @foreach (($p->std_codes ?? []) as $sc)<span class="insp-tag">{{ $sc }}</span>@endforeach
                    </div>
                </div>
            @endforeach
        </div>

    </div>
</div>

@push('styles')
    @include('componentes._crew-list-styles')
    @include('componentes._inspection-styles')
@endpush

@endsection
