@extends('layouts.app')
@section('content')

{{-- Formulario LEAN de Scouting / Location Risk Assessment (modo CREAR).
     El cuerpo del formulario vive en el parcial _form.blade.php, compartido
     con edit.blade.php ($report = null → crear). --}}
<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-0 fw-bold">Nuevo Scouting H&amp;S</h2>
            <p class="text-muted mb-0 small">Evaluación de riesgos de locación (versión ligera)</p>
        </div>
        <a href="{{ route('scoutings.index') }}" class="btn btn-outline-secondary">&larr; Volver</a>
    </div>

    @include('admin.scoutings._form', ['report' => null])

</div>
@endsection
