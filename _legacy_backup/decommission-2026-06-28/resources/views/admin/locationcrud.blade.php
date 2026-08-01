@extends('layouts.app')

@section('content')
<style>
    .location-card {
        position: relative;
        overflow: hidden;
        border: none;
        cursor: pointer;
        transition: transform 0.5s ease, box-shadow 0.5 ease;
        height: 100%;
        border-radius: 12px;
        background-color: transparent;
    }

    .location-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 12px 24px rgba(0, 0, 0, 0.1);
    }

    .location-img-wrapper {
        position: relative;
        height: 240px;
        overflow: hidden;
        border-top-left-radius: 12px;
        border-top-right-radius: 12px;
    }

    .location-img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }

    .location-overlay {
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        padding: 1rem 1.25rem;
        background: linear-gradient(to top, rgba(0, 0, 0, 0.85), rgba(0, 0, 0, 0));
        color: white;
    }

    .location-title {
        font-weight: 200;
        margin: 0;
        white-space: nowrap;
        overflow: hidden;
        display: block;
        text-align: left;

        /* Ajuste dinámico del tamaño de fuente */
        font-size: clamp(1rem, 4vw, 2rem);
    }

    .card-body-custom {
        padding: 1rem 1.25rem;
        background-color: #fff;
        border-radius: 0 0 12px 12px;
        margin-top: -4px;
    }

    .card-meta {
        font-size: 0.95rem;
        color: #444;
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-weight: 400;
    }

    .card-meta span {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
    }

    .card-link-wrapper {
        text-decoration: none;
        color: inherit;
    }
</style>

<div class="container py-5">
    <h1 class="mb-4">Reportes de Locación</h1>

    <!-- Buscador -->
    <div class="mb-4">
        <input type="text" id="searchInput" class="form-control" placeholder="Buscar por producción, locación o escena...">
    </div>

    <!-- Cards -->
    <div class="row" id="reportsContainer">
        @foreach($reports as $report)
        <div class="col-md-6 col-lg-4 mb-4 report-card"
             data-search="{{ strtolower($report->production_name . ' ' . $report->name_loc . ' ' . $report->scene . ' ' . $report->name_scene) }}">
            <a href="{{ route('locationreport.show', $report->id_loc) }}" class="card-link-wrapper">
                <div class="card location-card shadow-sm">
                    <div class="location-img-wrapper">
                        <img src="{{ asset($report->main_image_path) }}" class="location-img" alt="Imagen de locación">
                        <div class="location-overlay">
                            <h5 class="location-title">{{ $report->name_loc }}</h5>
                        </div>
                    </div>
                    <div class="card-body-custom">
                        <div class="card-meta">
                            <span><i class="bi bi-film"></i> Escena {{ $report->scene }}</span>
                            <span><i class="bi bi-calendar-event"></i> {{ \Carbon\Carbon::parse($report->date_shoot)->format('d M Y') }}</span>
                        </div>
                    </div>
                </div>
            </a>
        </div>
        @endforeach
    </div>

    @if($reports->isEmpty())
        <p class="text-muted">No hay reportes disponibles.</p>
    @endif

    {{-- PERF (2026-06-28): paginación añadida (controlador ahora usa paginate(15)). Patrón tomado de medicocrud.blade.php --}}
    <div>
        {!! $reports->links() !!}
    </div>
</div>

<!-- Script JS para el filtro -->
<script>
    document.getElementById('searchInput').addEventListener('input', function () {
        const search = this.value.toLowerCase();
        const cards = document.querySelectorAll('.report-card');

        cards.forEach(card => {
            const keywords = card.getAttribute('data-search');
            card.style.display = keywords.includes(search) ? 'block' : 'none';
        });
    });
</script>
@endsection
