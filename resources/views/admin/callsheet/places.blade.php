@extends('layouts.app')
@section('content')
@push('styles')@include('admin.callsheet._styles')@endpush

<div class="container py-4 cs-wrap" style="max-width:680px">
    <div class="adm-header mb-4">
        <span class="adm-icon">@include('componentes._icon', ['name' => 'map-pin', 'class' => 'cc-ico', 'label' => null])</span>
        <div>
            <h1 class="adm-title">Catálogo de lugares</h1>
            <p class="adm-subtitle">Clave corta + nombre. Las claves alimentan pick up, comidas y la leyenda del back.</p>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">
            @include('componentes._icon', ['name' => 'check-circle', 'class' => 'cc-ico me-1', 'label' => null]) {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <form action="{{ route('callsheet.places.save') }}" method="POST">
        @csrf
        <div class="card cs-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Lugares</span>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="cs-add-place">+ Lugar</button>
            </div>
            <div class="card-body">
                <div class="cs-meal__head" style="grid-template-columns:0.6fr 1.6fr auto">
                    <span>Clave</span><span>Nombre</span><span></span>
                </div>
                <div id="cs-places">
                    @foreach($places as $i => $pl)
                        <div class="cs-meal" style="grid-template-columns:0.6fr 1.6fr auto">
                            <input type="hidden" name="places[{{ $i }}][id]" value="{{ $pl->id }}">
                            <input type="text" class="form-control" name="places[{{ $i }}][code]" value="{{ $pl->code }}" placeholder="HRP">
                            <input type="text" class="form-control" name="places[{{ $i }}][name]" value="{{ $pl->name }}" placeholder="Hotel Real de la Paz">
                            <button type="button" class="cs-icon-btn cs-del-place" aria-label="Quitar">@include('componentes._icon', ['name' => 'trash-2', 'class' => 'cc-ico', 'label' => null])</button>
                        </div>
                    @endforeach
                    @if(! count($places))
                        <div class="cs-meal" style="grid-template-columns:0.6fr 1.6fr auto">
                            <input type="hidden" name="places[0][id]" value="">
                            <input type="text" class="form-control" name="places[0][code]" placeholder="HRP">
                            <input type="text" class="form-control" name="places[0][name]" placeholder="Hotel Real de la Paz">
                            <button type="button" class="cs-icon-btn cs-del-place" aria-label="Quitar">@include('componentes._icon', ['name' => 'trash-2', 'class' => 'cc-ico', 'label' => null])</button>
                        </div>
                    @endif
                </div>
            </div>
        </div>
        <div class="d-flex justify-content-end mb-4">
            <button type="submit" class="btn btn-primary">
                @include('componentes._icon', ['name' => 'save', 'class' => 'cc-ico me-1', 'label' => null]) Guardar
            </button>
        </div>
    </form>
</div>

<template id="cs-place-tpl">
    <div class="cs-meal" style="grid-template-columns:0.6fr 1.6fr auto">
        <input type="hidden" name="places[__i__][id]" value="">
        <input type="text" class="form-control" name="places[__i__][code]" placeholder="HRP">
        <input type="text" class="form-control" name="places[__i__][name]" placeholder="Hotel Real de la Paz">
        <button type="button" class="cs-icon-btn cs-del-place" aria-label="Quitar">@include('componentes._icon', ['name' => 'trash-2', 'class' => 'cc-ico', 'label' => null])</button>
    </div>
</template>

@push('scripts')
<script>
(function () {
    var wrap = document.getElementById('cs-places'), tpl = document.getElementById('cs-place-tpl'),
        add = document.getElementById('cs-add-place'), n = {{ max(count($places), 1) }};
    add && add.addEventListener('click', function () {
        var div = document.createElement('div'); div.innerHTML = tpl.innerHTML.replace(/__i__/g, n++);
        wrap.appendChild(div.firstElementChild);
    });
    wrap && wrap.addEventListener('click', function (e) {
        var b = e.target.closest('.cs-del-place'); if (b) b.closest('.cs-meal').remove();
    });
})();
</script>
@endpush
@endsection
