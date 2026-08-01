{{--
    Paginación de marca (.cc-pager) — vista por DEFECTO de toda la app (registrada en
    AppServiceProvider con Paginator::defaultView). Markup propio para no depender de las
    clases Bootstrap ni chocar con estilos .page-link por-vista (usuarioscrud/dsr). Los
    estilos viven en layouts/_brand-theme (global) y usan la paleta de marca.
--}}
@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('Pagination Navigation') }}" class="cc-pager">
        <ul class="cc-pager__list">
            {{-- Anterior --}}
            @if ($paginator->onFirstPage())
                <li class="cc-pager__item cc-pager__item--disabled" aria-disabled="true" aria-label="{{ __('pagination.previous') }}">
                    <span class="cc-pager__link cc-pager__link--nav" aria-hidden="true"><i class="fa-solid fa-chevron-left"></i></span>
                </li>
            @else
                <li class="cc-pager__item">
                    <a class="cc-pager__link cc-pager__link--nav" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="{{ __('pagination.previous') }}"><i class="fa-solid fa-chevron-left"></i></a>
                </li>
            @endif

            {{-- Números / separadores --}}
            @foreach ($elements as $element)
                @if (is_string($element))
                    <li class="cc-pager__item cc-pager__item--gap" aria-disabled="true"><span class="cc-pager__link">{{ $element }}</span></li>
                @endif
                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <li class="cc-pager__item cc-pager__item--active" aria-current="page"><span class="cc-pager__link">{{ $page }}</span></li>
                        @else
                            <li class="cc-pager__item"><a class="cc-pager__link" href="{{ $url }}" aria-label="{{ __('Go to page :page', ['page' => $page]) }}">{{ $page }}</a></li>
                        @endif
                    @endforeach
                @endif
            @endforeach

            {{-- Siguiente --}}
            @if ($paginator->hasMorePages())
                <li class="cc-pager__item">
                    <a class="cc-pager__link cc-pager__link--nav" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="{{ __('pagination.next') }}"><i class="fa-solid fa-chevron-right"></i></a>
                </li>
            @else
                <li class="cc-pager__item cc-pager__item--disabled" aria-disabled="true" aria-label="{{ __('pagination.next') }}">
                    <span class="cc-pager__link cc-pager__link--nav" aria-hidden="true"><i class="fa-solid fa-chevron-right"></i></span>
                </li>
            @endif
        </ul>
    </nav>
@endif
