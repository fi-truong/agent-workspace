{{--
  Reusable filter toolbar for admin tables
  Usage:
  @include('admin.partials.filters', [
    'searchPlaceholder' => 'Search prompts...',
    'searchValue' => request('search'),
    'filters' => [
      ['key' => 'subject', 'label' => 'Subject', 'options' => $subjects, 'selected' => request('subject')],
      ['key' => 'status', 'label' => 'Status', 'options' => ['draft' => 'Draft', 'published' => 'Published'], 'selected' => request('status')],
    ],
    'sortOptions' => ['newest' => 'Newest', 'oldest' => 'Oldest', 'alpha' => 'A–Z'],
    'sortValue' => request('sort', 'newest'),
    'createUrl' => route('admin.prompts.create'),
    'createLabel' => 'Add Prompt',
  ])
--}}

@php
  $searchPlaceholder = $searchPlaceholder ?? 'Search...';
  $searchValue = $searchValue ?? request('search');
  $filters = $filters ?? [];
  $sortOptions = $sortOptions ?? ['newest' => 'Newest', 'alpha' => 'A–Z'];
  $sortValue = $sortValue ?? request('sort', 'newest');
  $createUrl = $createUrl ?? '#';
  $createLabel = $createLabel ?? 'Add New';
  $extraActions = $extraActions ?? '';
@endphp

<div class="table-toolbar">
  <div class="search-box">
    <svg class="search-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
      <circle cx="11" cy="11" r="8"></circle>
      <path d="M21 21l-4.35-4.35"></path>
    </svg>
    <input type="text" id="adminSearch" name="search" placeholder="{{ $searchPlaceholder }}" value="{{ $searchValue }}" autocomplete="off">
  </div>

  @foreach($filters as $filter)
  <select name="{{ $filter['key'] }}" class="filter-select" data-filter="{{ $filter['key'] }}">
    <option value="">All {{ $filter['label'] }}</option>
    @foreach($filter['options'] as $value => $label)
      <option value="{{ $value }}" {{ ($filter['selected'] ?? '') == $value ? 'selected' : '' }}>
        {{ $label }}
      </option>
    @endforeach
  </select>
  @endforeach

  <select name="sort" id="adminSort" class="filter-select" style="min-width:140px;">
    @foreach($sortOptions as $value => $label)
      <option value="{{ $value }}" {{ $sortValue == $value ? 'selected' : '' }}>{{ $label }}</option>
    @endforeach
  </select>

  {{ $extraActions }}

  <a href="{{ $createUrl }}" class="btn-primary">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
      <line x1="12" y1="5" x2="12" y2="19"></line>
      <line x1="5" y1="12" x2="19" y2="12"></line>
    </svg>
    {{ $createLabel }}
  </a>
</div>

@pushOnce('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
  // Debounced search
  const searchInput = document.getElementById('adminSearch');
  const sortSelect = document.getElementById('adminSort');
  const filterSelects = document.querySelectorAll('.filter-select[data-filter]');
  let searchTimer = null;

  function buildUrl() {
    const params = new URLSearchParams(window.location.search);
    if (searchInput) params.set('search', searchInput.value);
    filterSelects.forEach(sel => {
      const key = sel.dataset.filter;
      if (sel.value) params.set(key, sel.value);
      else params.delete(key);
    });
    if (sortSelect) params.set('sort', sortSelect.value);
    return window.location.pathname + '?' + params.toString();
  }

  function reload() {
    window.location.href = buildUrl();
  }

  if (searchInput) {
    searchInput.addEventListener('input', () => {
      clearTimeout(searchTimer);
      searchTimer = setTimeout(reload, 300);
    });
    searchInput.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') {
        e.preventDefault();
        clearTimeout(searchTimer);
        reload();
      }
    });
  }

  filterSelects.forEach(sel => {
    sel.addEventListener('change', reload);
  });

  if (sortSelect) {
    sortSelect.addEventListener('change', reload);
  }
});
</script>
@endPushOnce