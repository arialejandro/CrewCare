@extends('layouts.app')
@section('content')
<style>
    .report-body { background: #fff; border: 1px solid #000; padding: 30px; font-family: 'Arial', sans-serif; }
    .status-box { padding: 10px; border: 2px solid #000; font-weight: bold; text-align: center; }
    .risk-alert { color: #d32f2f; font-weight: bold; text-transform: uppercase; }
    .mitigation-text { background: #fdf2f2; border-left: 3px solid #d32f2f; padding-left: 10px; font-style: italic; }
</style>

<div class="container mt-4 mb-5">
    <div class="report-body shadow">
        <div class="row mb-4">
            <div class="col-8">
                <h2 class="text-uppercase fw-bold">Reporte de Seguridad en Locación</h2>
                <p class="mb-0"><strong>PROYECTO:</strong> {{ $report->production_name }}</p>
                <p><strong>LOCACIÓN:</strong> {{ $report->name_loc }}</p>
            </div>
            <div class="col-4 text-end">
                <div class="status-box">FECHA: {{ \Carbon\Carbon::parse($report->make_date)->format('d/m/Y') }}</div>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-12">
                <img src="{{ $report->main_image_path }}" class="img-fluid rounded border w-100" style="max-height: 400px; object-fit: cover;">
            </div>
        </div>

        <h4 class="bg-dark text-white p-2">RESUMEN DE RIESGOS Y MITIGACIÓN</h4>
        <table class="table table-sm">
            <thead>
                <tr class="table-secondary">
                    <th>Categoría</th>
                    <th>Estado</th>
                    <th>Acción Requerida</th>
                </tr>
            </thead>
            <tbody>
                {{-- Ejemplo Estructural --}}
                <tr>
                    <td><strong>Estructura y Cargas</strong></td>
                    <td>
                        @if($report->owner_4 == 1) <span class="risk-alert">Riesgo Detectado</span> @else <span class="text-success">Seguro</span> @endif
                    </td>
                    <td class="mitigation-text">{{ $report->owner_4_details ?? 'N/A' }}</td>
                </tr>
                {{-- Ejemplo Eléctrico --}}
                <tr>
                    <td><strong>Instalación Eléctrica</strong></td>
                    <td>
                        @if($report->owner_11 == 1) <span class="risk-alert">Riesgo Crítico</span> @else <span class="text-success">Seguro</span> @endif
                    </td>
                    <td class="mitigation-text">{{ $report->owner_11_details ?? 'N/A' }}</td>
                </tr>
            </tbody>
        </table>

        <div class="mt-5 text-center">
            <hr>
            <p><strong>Auditoría realizada por:</strong> {{ $report->make_by }}</p>
            <small>Este documento es una guía de seguridad. La producción es responsable de implementar las mitigaciones descritas.</small>
        </div>
    </div>

    <div class="mt-4 no-print">
        <button onclick="window.print()" class="btn btn-dark">Imprimir Reporte PDF</button>
        <a href="{{ route('locationreport2.create') }}" class="btn btn-outline-secondary">Nueva Auditoría</a>
    </div>
</div>
@endsection