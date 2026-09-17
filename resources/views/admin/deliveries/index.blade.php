@extends('layouts.app')
@section('content')

<div class="container py-4">
    <div class="adm-header d-flex align-items-center gap-2">
        <span class="adm-icon">@include('componentes._icon', ['name' => 'send', 'class' => 'cc-ico', 'label' => null])</span>
        <div class="flex-grow-1">
            <h1 class="adm-title">Distribución</h1>
            <p class="adm-subtitle">Envía documentos a todo el equipo con marca de agua por persona (nombre en créditos).</p>
        </div>
        @if(empty($unavailable))
        <a href="{{ route('deliveries.create') }}" class="btn btn-primary">
            @include('componentes._icon', ['name' => 'send', 'class' => 'cc-ico me-1', 'label' => null]) Nuevo envío
        </a>
        @endif
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif
    @if(session('warning'))
        <div class="alert alert-warning alert-dismissible fade show">{{ session('warning') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif

    @if(!empty($unavailable))
        <div class="card cc-card"><div class="card-body text-center text-muted py-5">
            El módulo de distribución aún no está disponible en esta instancia.<br>
            Falta aplicar la actualización de base de datos <code>2026-08-24-file-deliveries.sql</code>.
        </div></div>
    @elseif($deliveries->isEmpty())
        <div class="card cc-card"><div class="card-body text-center text-muted py-5">
            Aún no has hecho envíos. <a href="{{ route('deliveries.create') }}">Crea el primero</a>.
        </div></div>
    @else
        <div class="card cc-card"><div class="table-responsive">
            <table class="table align-middle mb-0 cc-stack">
                <thead><tr>
                    <th>Documento</th><th>Cuándo</th><th class="text-center">Enviados</th>
                    <th class="text-center">En cola</th><th class="text-center">Error</th><th></th>
                </tr></thead>
                <tbody>
                @foreach($deliveries as $d)
                    <tr>
                        <td data-label="Documento"><b>{{ $d->title }}</b></td>
                        <td data-label="Cuándo">{{ optional($d->created_at)->isoFormat('D MMM HH:mm') }}</td>
                        <td data-label="Enviados" class="text-center">{{ $d->sent }}/{{ $d->total }}</td>
                        <td data-label="En cola" class="text-center">{{ $d->pending }}</td>
                        <td data-label="Error" class="text-center">{{ $d->failed ?: '—' }}</td>
                        <td data-label="" class="text-end"><a class="btn btn-sm btn-outline-secondary" href="{{ route('deliveries.show', $d->id) }}">Ver</a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div></div>
        <div class="mt-3">{{ $deliveries->links() }}</div>
    @endif
</div>
@endsection
