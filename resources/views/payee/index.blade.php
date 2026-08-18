@extends('layouts.app')
@section('content')
@push('styles')
    @include('componentes._crew-list-styles')
@endpush
{{-- PASO 4 · VISIBILIDAD — listado de "quien cobra" ACOTADO por Payee::scopeVisibleTo
     ("quien contrata es quien ve"). Solo lectura. El buscador es server-side (?q=). --}}
<div class="crew-page">
    <div class="container-fluid px-3 px-md-4 py-4">

        <div class="crew-header d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3 mb-4">
            <div class="d-flex align-items-center gap-3">
                <span class="crew-header-icon d-inline-flex align-items-center justify-content-center rounded-3">
                    @include('componentes._icon', ['name' => 'wallet', 'class' => 'cc-ico', 'label' => null])
                </span>
                <div>
                    <h1 class="crew-title mb-0">{{ __('Quién cobra') }}</h1>
                    <p class="text-muted mb-0 small">{{ __('Identidades y contratos que ves según tu departamento.') }}</p>
                </div>
            </div>

            <form method="GET" action="{{ route('payees.index') }}" class="d-flex gap-2" role="search">
                <input type="search" name="q" value="{{ $q }}" class="form-control"
                       placeholder="{{ __('Buscar por nombre o RFC…') }}" aria-label="{{ __('Buscar') }}">
                <button class="btn btn-crew-soft" type="submit">
                    @include('componentes._icon', ['name' => 'search', 'label' => null])
                </button>
            </form>
        </div>

        @if($payees->isEmpty())
            <div class="text-center text-muted py-5">
                @include('componentes._icon', ['name' => 'inbox', 'class' => 'cc-ico', 'label' => null])
                <p class="mb-0 mt-2">{{ $q !== '' ? __('Nada coincide con tu búsqueda.') : __('Aún no hay identidades en tu alcance.') }}</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="table cc-stack align-middle">
                    <thead>
                        <tr>
                            <th>{{ __('Nombre') }}</th>
                            <th>{{ __('Naturaleza') }}</th>
                            <th>{{ __('RFC') }}</th>
                            <th class="text-center">{{ __('Contratos') }}</th>
                            <th class="text-center">{{ __('Documentos') }}</th>
                            <th>{{ __('Registro') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($payees as $p)
                            <tr>
                                <td data-label="{{ __('Nombre') }}">
                                    <a href="{{ route('payees.show', $p) }}" class="fw-semibold text-decoration-none">{{ $p->name ?: '—' }}</a>
                                </td>
                                <td data-label="{{ __('Naturaleza') }}">
                                    <span class="badge {{ $p->isMoral() ? 'text-bg-primary' : 'text-bg-secondary' }}">
                                        {{ $p->isMoral() ? __('Moral') : __('Física') }}
                                    </span>
                                </td>
                                <td data-label="{{ __('RFC') }}"><span class="font-monospace">{{ $p->rfc ?: '—' }}</span></td>
                                <td data-label="{{ __('Contratos') }}" class="text-center">{{ $p->contracts_count }}</td>
                                <td data-label="{{ __('Documentos') }}" class="text-center">{{ $p->documents_count }}</td>
                                <td data-label="{{ __('Registro') }}">
                                    @if($p->intake_submitted_at)
                                        <span class="badge text-bg-success">{{ __('Recibido') }}</span>
                                    @else
                                        <span class="badge text-bg-warning">{{ __('Pendiente') }}</span>
                                    @endif
                                </td>
                                <td data-label="" class="text-end">
                                    <a href="{{ route('payees.show', $p) }}" class="btn btn-sm btn-crew-soft">{{ __('Ver') }}</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-3">{{ $payees->links() }}</div>
        @endif

    </div>
</div>
@endsection
