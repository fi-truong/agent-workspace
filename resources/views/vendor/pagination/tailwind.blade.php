@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Pagination Navigation" class="pagination">
        {{-- Previous Page Link --}}
        @if ($paginator->onFirstPage())
            <span aria-disabled="true" aria-label="Previous" title="Previous">
                <span class="pagination-ellipsis" aria-hidden="true">«</span>
            </span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="pagination-link" aria-label="Previous" title="Previous">«</a>
        @endif

        {{-- Pagination Elements --}}
        @foreach ($elements as $element)
            {{-- "Three Dots" Separator --}}
            @if (is_string($element))
                <span aria-disabled="true">
                    <span class="pagination-ellipsis">{{ $element }}</span>
                </span>
            @endif

            {{-- Array Of Links --}}
            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <span aria-current="page" class="pagination-current">{{ $page }}</span>
                    @else
                        <a href="{{ $url }}" class="pagination-link" aria-label="Go to page {{ $page }}" title="Go to page {{ $page }}">{{ $page }}</a>
                    @endif
                @endforeach
            @endif
        @endforeach

        {{-- Next Page Link --}}
        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="pagination-link" aria-label="Next" title="Next">»</a>
        @else
            <span aria-disabled="true" aria-label="Next" title="Next">
                <span class="pagination-ellipsis" aria-hidden="true">»</span>
            </span>
        @endif
    </nav>
@endif