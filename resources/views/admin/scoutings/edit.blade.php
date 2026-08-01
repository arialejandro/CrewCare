@extends('layouts.app')
@section('content')

{{-- Formulario LEAN de Scouting / Location Risk Assessment (modo EDITAR).
     Reutiliza el parcial _form.blade.php precargado con el reporte: existe
     porque hay información (hospital, acuerdos, fotos) que no se obtiene en
     la primera visita a la locación. La autofirma original NO cambia. --}}
<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-0 fw-bold">Editar Scouting H&amp;S</h2>
            <p class="text-muted mb-0 small">{{ $report->location_name }} — completa o corrige la información de visitas posteriores</p>
        </div>
        <a href="{{ route('scoutings.show', $report->id) }}" class="btn btn-outline-secondary">&larr; Volver</a>
    </div>

    @include('admin.scoutings._form', ['report' => $report])

</div>
@endsection
