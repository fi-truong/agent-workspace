@if ($paginator->hasPages())
<ul class="pagination pagination-with-jump">
    @if ($paginator->onFirstPage())
        <li class="page-item disabled"><span class="page-link">‹ Previous</span></li>
    @else
        <li class="page-item"><a class="page-link" href="{{ $paginator->previousPageUrl() }}" rel="prev">‹ Previous</a></li>
    @endif

    <li class="page-item disabled page-jump-wrapper">
        <span class="page-link page-jump-form">
            Page
            <input
                type="number"
                class="page-jump-input"
                min="1"
                max="{{ $paginator->lastPage() }}"
                value="{{ $paginator->currentPage() }}"
                data-jump-input
                aria-label="Go to page"
            >
            of {{ $paginator->lastPage() }}
            <button type="button" class="page-jump-btn" data-jump-btn>Go</button>
        </span>
    </li>

    @if ($paginator->hasMorePages())
        <li class="page-item"><a class="page-link" href="{{ $paginator->nextPageUrl() }}" rel="next">Next ›</a></li>
    @else
        <li class="page-item disabled"><span class="page-link">Next ›</span></li>
    @endif
</ul>

@once
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[data-jump-btn]').forEach(btn => {
    const wrapper = btn.closest('.page-jump-form');
    const input = wrapper.querySelector('[data-jump-input]');

    function jump() {
      let page = parseInt(input.value, 10);
      const max = parseInt(input.max, 10);
      const min = parseInt(input.min, 10);
      if (isNaN(page) || page < min) page = min;
      if (page > max) page = max;
      const params = new URLSearchParams(window.location.search);
      params.set('page', page);
      window.location.href = window.location.pathname + '?' + params.toString();
    }

    btn.addEventListener('click', jump);
    input.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') {
        e.preventDefault();
        jump();
      }
    });
  });
});
</script>
@endpush
@endonce
@endif
