{{-- Resultado de búsqueda AJAX: reemplaza #toolgrid. Misma card que el render inicial. --}}
@if ($tools->count() === 0)
    @include('inspection._wildcard-cta', ['wildcard' => $wildcard ?? null])
@else
    <div class="tool-cards">
        @foreach ($tools as $tool)
            @include('inspection._tool-card', ['tool' => $tool, 'launch' => $launch ?? []])
        @endforeach
    </div>
    @if ($tools->hasPages())
        <div class="mt-3">{!! $tools->links() !!}</div>
    @endif
@endif
