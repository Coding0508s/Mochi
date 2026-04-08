@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('Pagination Navigation') }}" class="mochi-pagination-nav flex gap-2 items-center justify-between">

        @if ($paginator->onFirstPage())
            <span class="mochi-pagination-mobile-btn mochi-pagination-mobile-btn--disabled">
                {!! __('pagination.previous') !!}
            </span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="mochi-pagination-mobile-btn">
                {!! __('pagination.previous') !!}
            </a>
        @endif

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="mochi-pagination-mobile-btn">
                {!! __('pagination.next') !!}
            </a>
        @else
            <span class="mochi-pagination-mobile-btn mochi-pagination-mobile-btn--disabled">
                {!! __('pagination.next') !!}
            </span>
        @endif

    </nav>
@endif
