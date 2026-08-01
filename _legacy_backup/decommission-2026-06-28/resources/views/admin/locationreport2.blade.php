@extends('layouts.app')
@section('content')
<style>
    .audit-section { border-left: 5px solid #d32f2f; margin-bottom: 25px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
    .table-audit thead { background: #212529; color: white; font-size: 0.85rem; }
    .risk-label { font-weight: bold; color: #d32f2f; }
    .section-title { background: #f8f9fa; font-weight: bold; border-bottom: 2px solid #dee2e6; }
</style>

<div class="container pb-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Auditoría de Seguridad (V2)</h1>
        <span class="badge bg-danger">ESTÁNDAR OSHA / SAFETY COMPLIANCE</span>
    </div>

    <form action="{{ route('locationreport2.store') }}" method="POST" enctype="multipart/form-data">
        @csrf

        {{-- BLOQUE 0: INFO GENERAL --}}
        <div class="card mb-4 shadow-sm">
            <div class="card-body row">
                <div class="col-md-3">
                    <label class="form-label fw-bold">Proyecto</label>
                    <input type="text" name="production_name" class="form-control" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">Locación</label>
                    <input type="text" name="name_loc" class="form-control" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">Scout / Auditor</label>
                    <input type="text" name="make_by" class="form-control" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">Fecha</label>
                    <input type="date" name="make_date" class="form-control" required>
                </div>
            </div>
        </div>

        {{-- BLOQUE 1: ESTRUCTURA Y HAZMAT --}}
        <div class="card audit-section">
            <div class="card-header section-title">I. INTEGRIDAD ESTRUCTURAL Y MATERIALES PELIGROSOS</div>
            <div class="table-responsive">
                <table class="table table-hover table-audit mb-0">
                    <thead>
                        <tr>
                            <th width="45%">Punto de Control</th>
                            <th width="15%" class="text-center">Estado de Riesgo</th>
                            <th width="40%">Plan de Mitigación Requerido</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Planos, puntos de anclaje y carga estructural (Punto 4).</td>
                            <td class="text-center">
                                <input type="radio" name="owner_4" value="1" class="form-check-input"> Riesgo
                                <input type="radio" name="owner_4" value="0" class="form-check-input ms-2" checked> OK
                            </td>
                            <td><textarea name="owner_4_details" class="form-control" rows="1"></textarea></td>
                        </tr>
                        <tr>
                            <td>Materiales Peligrosos (Plomo, Asbesto, Moho) (Punto 6).</td>
                            <td class="text-center">
                                <input type="radio" name="owner_6" value="1" class="form-check-input"> Riesgo
                                <input type="radio" name="owner_6" value="0" class="form-check-input ms-2" checked> OK
                            </td>
                            <td><textarea name="owner_6_details" class="form-control" rows="1"></textarea></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- BLOQUE 2: ELECTRICIDAD --}}
        <div class="card audit-section">
            <div class="card-header section-title">II. SUMINISTRO ELÉCTRICO (UTILITIES)</div>
            <div class="table-responsive">
                <table class="table table-hover table-audit mb-0">
                    <tbody>
                        <tr>
                            <td width="45%">Puesta a tierra (Grounding) y cableado expuesto (Punto 10-11).</td>
                            <td width="15%" class="text-center">
                                <input type="radio" name="owner_11" value="1" class="form-check-input"> Riesgo
                                <input type="radio" name="owner_11" value="0" class="form-check-input ms-2" checked> OK
                            </td>
                            <td width="40%"><textarea name="owner_11_details" class="form-control" rows="1"></textarea></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- BLOQUE 3: ALTURAS Y CAIDAS --}}
        <div class="card audit-section">
            <div class="card-header section-title">III. TRABAJO EN ALTURAS Y PROTECCIÓN CONTRA CAÍDAS</div>
            <div class="table-responsive">
                <table class="table table-hover table-audit mb-0">
                    <tbody>
                        <tr>
                            <td width="45%">Bordes sin protección (>3m) o riesgo de caída de objetos (Punto 35).</td>
                            <td width="15%" class="text-center">
                                <input type="radio" name="height_35" value="1" class="form-check-input"> Riesgo
                                <input type="radio" name="height_35" value="0" class="form-check-input ms-2" checked> OK
                            </td>
                            <td width="40%"><textarea name="height_35_details" class="form-control" rows="1"></textarea></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- EVIDENCIA FOTOGRÁFICA --}}
        <div class="card mb-4">
            <div class="card-header bg-dark text-white">IV. EVIDENCIA FOTOGRÁFICA DE RIESGOS</div>
            <div class="card-body">
                <label class="form-label fw-bold">Imagen Principal (Header)</label>
                <input type="file" name="main_image" class="form-control mb-3">
                
                <label class="form-label fw-bold">Imágenes de Detalle / Riesgos Específicos</label>
                <input type="file" name="additional_images[]" class="form-control" multiple>
                <small class="text-muted">Puedes seleccionar varias imágenes a la vez.</small>
            </div>
        </div>

        <div class="d-grid gap-2">
            <button type="submit" class="btn btn-danger btn-lg shadow">GUARDAR Y GENERAR REPORTE TÉCNICO</button>
        </div>
    </form>
</div>
@endsection