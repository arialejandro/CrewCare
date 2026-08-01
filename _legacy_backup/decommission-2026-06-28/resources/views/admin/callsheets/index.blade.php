@extends('layouts.app')
@section('title', 'Llamados - CrewCare')

@section('content')
<div class="container-fluid mt-4 mb-5">

    <div class="row mb-4 align-items-center">
        <div class="col-sm-8 col-12 mb-3 mb-sm-0">
            <h1 class="h3 mb-0 text-gray-800 fw-bold">
                <i class="fas fa-bullhorn text-primary me-2"></i>Llamados (Call Sheets)
            </h1>
            <p class="text-muted small mb-0">El "llamado" del día con boletines de seguridad adjuntos automáticamente.</p>
        </div>
        <div class="col-sm-4 col-12 text-sm-end">
            @can('call_sheets.create')
            <a href="{{ route('call_sheets.create') }}" class="btn btn-primary fw-bold shadow-sm w-100 w-sm-auto">
                <i class="fas fa-plus me-1"></i> Nuevo Llamado
            </a>
            @endcan
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i>{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th scope="col" class="text-secondary text-uppercase" style="font-size: 0.75rem;">Fecha</th>
                            <th scope="col" class="text-secondary text-uppercase" style="font-size: 0.75rem;">Título / Locación</th>
                            <th scope="col" class="text-secondary text-uppercase text-center" style="font-size: 0.75rem;">Riesgos</th>
                            <th scope="col" class="text-secondary text-uppercase text-center" style="font-size: 0.75rem;">Estatus</th>
                            <th scope="col" class="text-secondary text-uppercase text-end" style="font-size: 0.75rem;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($callSheets as $sheet)
                        <tr>
                            <td>
                                <div class="fw-bold text-dark">{{ \Carbon\Carbon::parse($sheet->sheet_date)->format('d/m/Y') }}</div>
                                @if($sheet->shoot_day)
                                    <span class="badge bg-dark">Día {{ $sheet->shoot_day }}</span>
                                @endif
                            </td>
                            <td>
                                <div class="fw-bold text-dark">{{ $sheet->title ?: 'Llamado sin título' }}</div>
                                @if($sheet->location_name)
                                    <div class="text-muted small"><i class="fas fa-map-marker-alt text-danger me-1"></i>{{ $sheet->location_name }}</div>
                                @endif
                            </td>
                            <td class="text-center">
                                @php $riskCount = is_array($sheet->identified_risks) ? count($sheet->identified_risks) : 0; @endphp
                                @if($riskCount > 0)
                                    <span class="badge bg-danger rounded-pill px-3">{{ $riskCount }} boletines</span>
                                @else
                                    <span class="badge bg-light text-muted border rounded-pill px-3">Sin riesgos</span>
                                @endif
                            </td>
                            <td class="text-center">
                                @if($sheet->status === 'sent')
                                    <span class="badge bg-success text-white"><i class="fas fa-paper-plane me-1"></i> Enviado</span>
                                @else
                                    <span class="badge bg-secondary text-white"><i class="fas fa-pencil-alt me-1"></i> Borrador</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <a href="{{ route('call_sheets.show', $sheet->id) }}" class="btn btn-sm btn-outline-dark fw-bold">
                                    <i class="fas fa-eye me-1"></i> Ver
                                </a>
                                <a href="{{ route('call_sheets.pdf', $sheet->id) }}" class="btn btn-sm btn-outline-danger fw-bold">
                                    <i class="fas fa-file-pdf me-1"></i> PDF
                                </a>
                                @can('call_sheets.create')
                                @if($sheet->status !== 'sent')
                                <form action="{{ route('call_sheets.send', $sheet->id) }}" method="POST" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-success fw-bold">
                                        <i class="fas fa-paper-plane me-1"></i> Enviar
                                    </button>
                                </form>
                                @endif
                                @endcan
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="text-center py-5 text-muted">
                                <div class="mb-3"><i class="fas fa-bullhorn fa-3x opacity-50"></i></div>
                                <h5 class="fw-bold">No hay llamados aún</h5>
                                <p class="small">Crea el llamado del día y adjunta sus boletines de seguridad.</p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="mt-4 d-flex justify-content-center">
        {{ $callSheets->links('pagination::bootstrap-4') }}
    </div>

</div>
@endsection
