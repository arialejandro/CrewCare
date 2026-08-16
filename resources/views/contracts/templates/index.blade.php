@extends('layouts.app')
@section('content')
{{-- CONTRACT BUILDER · plantillas de contrato (documento generado con anclas de firma). --}}
<div class="crew-page">
    <div class="container-fluid px-3 px-md-4 py-4" style="max-width:920px">

        <div class="crew-header d-flex align-items-center gap-3 mb-4">
            <span class="crew-header-icon d-inline-flex align-items-center justify-content-center rounded-3">
                @include('componentes._icon', ['name' => 'file-text', 'class' => 'cc-ico', 'label' => null])
            </span>
            <div>
                <h1 class="crew-title mb-0">{{ __('Plantillas de contrato') }}</h1>
                <p class="text-muted mb-0 small">{{ __('El contrato como documento generado: campos que se llenan con el trato y firmas estampadas en su lugar.') }}</p>
            </div>
            <div class="ms-auto d-flex gap-2">
                <a href="{{ route('contracts.templates.create_pdf') }}" class="btn btn-crew-soft d-inline-flex align-items-center gap-1">
                    @include('componentes._icon', ['name' => 'file-up', 'label' => null]) {{ __('Subir PDF') }}
                </a>
                <a href="{{ route('contracts.templates.create') }}" class="btn btn-crew d-inline-flex align-items-center gap-1">
                    @include('componentes._icon', ['name' => 'plus', 'label' => null]) {{ __('Nueva plantilla') }}
                </a>
            </div>
        </div>

        {{-- Descargo legal — CrewCare no redacta ni asume responsabilidad legal (contract-builder-legal-boundary). --}}
        <div class="alert alert-warning small mb-3" role="note">
            <strong>{{ __('Responsabilidad legal de la productora.') }}</strong>
            {{ __('El contenido jurídico lo define y respalda la productora. CrewCare solo ensambla, numera y estampa firmas: no redacta contratos ni brinda asesoría legal.') }}
        </div>

        @if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif

        <div class="card">
            <div class="card-body p-0">
                @if($templates->isEmpty())
                    <p class="text-muted m-4">{{ __('Aún no hay plantillas. Crea la primera con “Nueva plantilla”.') }}</p>
                @else
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead><tr>
                                <th>{{ __('Nombre') }}</th><th>{{ __('Aplica a') }}</th><th>{{ __('Formato') }}</th><th>{{ __('Versión') }}</th><th>{{ __('Estado') }}</th><th></th>
                            </tr></thead>
                            <tbody>
                                @foreach($templates as $t)
                                    <tr>
                                        <td><a href="{{ route('contracts.templates.edit', $t) }}" class="fw-semibold text-decoration-none">{{ $t->name }}</a></td>
                                        <td class="small">
                                            @foreach(($t->applies_to ?? []) as $c)
                                                <span class="badge text-bg-light border">{{ $subtypes[$c] ?? $c }}</span>
                                            @endforeach
                                        </td>
                                        <td class="small">
                                            @if($t->isPdfSource())
                                                <span class="badge text-bg-light border">{{ __('PDF subido') }}</span>
                                            @else
                                                {{ \App\Support\ContractArchitectures::label($t->architecture) }}
                                            @endif
                                        </td>
                                        <td>v{{ $t->version }}</td>
                                        <td>
                                            @if($t->is_active)<span class="badge text-bg-success">{{ __('Activa') }}</span>
                                            @else<span class="badge text-bg-secondary">{{ __('Borrador') }}</span>@endif
                                        </td>
                                        <td class="text-end">
                                            <a href="{{ route('contracts.templates.edit', $t) }}" class="btn btn-sm btn-crew-soft">{{ __('Editar') }}</a>
                                            <form method="POST" action="{{ route('contracts.templates.toggle', $t) }}" class="d-inline">
                                                @csrf
                                                <button class="btn btn-sm btn-outline-secondary">{{ $t->is_active ? __('Desactivar') : __('Activar') }}</button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
