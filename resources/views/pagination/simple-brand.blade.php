{{-- Paginación simple de marca (prev/next) — usada por simplePaginate(). Ver pagination/brand. --}}
@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('Pagination Navigation') }}" class="cc-pager">
        <ul class="cc-pager__list">
            @if ($paginator->onFirstPage())
                <li class="cc-pager__item cc-pager__item--disabled" aria-disabled="true">
                    <span class="cc-pager__link"><i class="fa-solid fa-chevron-left me-1"></i> {{ __('Anterior') }}</span>
                </li>
            @else
                <li class="cc-pager__item">
                    <a class="cc-pager__link" href="{{ $paginator->previousPageUrl() }}" rel="prev"><i class="fa-solid fa-chevron-left me-1"></i> {{ __('Anterior') }}</a>
                </li>
            @endif

            @if ($paginator->hasMorePages())
                <li class="cc-pager__item">
                    <a class="cc-pager__link" href="{{ $paginator->nextPageUrl() }}" rel="next">{{ __('Siguiente') }} <i class="fa-solid fa-chevron-right ms-1"></i></a>
                </li>
            @else
                <li class="cc-pager__item cc-pager__item--disabled" aria-disabled="true">
                    <span class="cc-pager__link">{{ __('Siguiente') }} <i class="fa-solid fa-chevron-right ms-1"></i></span>
                </li>
            @endif
        </ul>
    </nav>
@endif
