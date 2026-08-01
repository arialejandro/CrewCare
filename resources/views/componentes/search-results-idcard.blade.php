{{-- Fragmento AJAX de búsqueda de gafetes. Reemplaza el interior de #usertable en idcardscrud.
     MISMA estructura de tabla + fila compartida (_idcard-row) → el swap se ve idéntico a la
     lista completa (crew-table + cc-stack, 4 columnas). --}}
<table class="table table-hover align-middle mb-0 crew-table cc-stack">
    <thead>
        <tr>
            <th scope="col" class="ps-4">{{ __('listas.integrante') }}</th>
            <th scope="col">{{ __('listas.depto') }}</th>
            <th scope="col">{{ __('listas.estado') }}</th>
            <th scope="col" class="text-end pe-4">{{ __('listas.acciones') }}</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($usuarios as $user)
            @include('componentes._idcard-row', ['user' => $user])
        @empty
            <tr>
                <td colspan="4">
                    <div class="crew-empty text-center py-5">
                        <div class="crew-empty-icon mx-auto mb-3 d-inline-flex align-items-center justify-content-center rounded-circle">
                            @include('componentes._icon', ['name' => 'search', 'class' => 'cc-ico', 'label' => null])
                        </div>
                        <h5 class="mb-1">{{ __('listas.no_result') }}</h5>
                        <p class="text-muted mb-0">{{ __('listas.empty_sub') }}</p>
                    </div>
                </td>
            </tr>
        @endforelse
    </tbody>
</table>
@if ($usuarios->hasPages())
    <div class="card-footer border-0 py-3">{!! $usuarios->links() !!}</div>
@endif
