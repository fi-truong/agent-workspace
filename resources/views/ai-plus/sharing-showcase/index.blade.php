@extends('layouts.ai-plus')

@section('title', 'Sharing & Showcase — AI+ LSTS')

@section('breadcrumb', 'Sharing & Showcase')

@section('content')
<header class="page-header">
  <div class="wrap">
    <h1>Sharing & Showcase</h1>
    <p>Browse agents and AI projects built by colleagues across LSTS. Leave a comment, ask how it works, or get inspired for your own.</p>
    <div class="header-stats">
      <div class="header-stat"><strong>{{ $totalAgents }}</strong> agents shared</div>
      <div class="header-stat"><strong>{{ $totalDepartments }}</strong> departments</div>
      <div class="header-stat"><strong>{{ $totalComments }}</strong> comments</div>
    </div>
  </div>
</header>

<section class="filters-section">
  <div class="wrap">
    <div class="filters-toolbar">
      <div class="search-box">
        <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <circle cx="11" cy="11" r="8"></circle>
          <path d="M21 21l-4.35-4.35"></path>
        </svg>
        <input type="text" id="searchInput" placeholder="Search showcases..." value="{{ request('search') }}" autocomplete="off">
        <button id="clearSearch" class="clear-btn" aria-label="Clear search" style="display: none;">×</button>
      </div>

      <div class="filter-group">
        <span class="filter-label">Department:</span>
        <div class="filter-chips" id="deptFilters">
          <button class="filter-chip {{ request('department') === '' ? 'active' : '' }}" data-department="">All Departments</button>
          @foreach($departments as $dept)
          <button class="filter-chip {{ request('department') == $dept ? 'active' : '' }}" data-department="{{ $dept }}">{{ $dept }}</button>
          @endforeach
        </div>
      </div>

      <div class="view-group">
        <span class="view-label">View:</span>
        <div class="view-tabs" id="viewTabs">
          <button class="view-tab {{ request('view', 'all') === 'all' ? 'active' : '' }}" data-view="all">All</button>
          <button class="view-tab {{ request('view') === 'trending' ? 'active' : '' }}" data-view="trending">🔥 Trending</button>
          <button class="view-tab {{ request('view') === 'new' ? 'active' : '' }}" data-view="new">✨ New</button>
          <button class="view-tab {{ request('view') === 'mostused' ? 'active' : '' }}" data-view="mostused">⭐ Most Used</button>
        </div>
      </div>

    </div>
  </div>
</section>

<main class="content">
  <div class="wrap">
    <div class="results-header">
      <span class="results-count" id="resultsCount">
        {{ $showcases->count() }} of {{ $paginator->total() }} showcases
      </span>
    </div>

    <div class="showcase-grid" id="showcaseGrid">
      @if($showcases->isEmpty())
      <div class="empty-state" id="emptyState">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
          <path d="M9.172 16.172a4 4 0 0 1 5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z"></path>
        </svg>
        <p>{{ $paginator->total() === 0 ? 'No showcases shared yet. Be the first!' : 'No showcases match your search.' }}</p>
        @if($paginator->total() !== 0)
          <button class="clear-filters-btn" id="clearFiltersBtn">Clear all filters</button>
        @endif
      </div>
      @else
        @foreach($showcases as $showcase)
        <div class="showcase-card" data-department="{{ $showcase['department'] }}" data-id="{{ $showcase['id'] }}">
          <div class="showcase-header">
            <div class="showcase-author">
              <div class="author-avatar">{{ $showcase['authorInitials'] }}</div>
              <div class="author-info">
                <h3>{{ $showcase['author'] }}</h3>
                <span>{{ $showcase['department'] }}</span>
              </div>
            </div>
            @if($showcase['badge'])
            <span class="showcase-badge">{{ $showcase['badge'] }}</span>
            @endif
          </div>
          <div class="showcase-body">
            <h2 class="showcase-title">{{ $showcase['title'] }}</h2>
            <p class="showcase-desc">{{ $showcase['description'] }}</p>
            <div class="showcase-tags">
              @foreach($showcase['tags'] as $tag)
              <span class="tag">{{ $tag }}</span>
              @endforeach
            </div>
            <div class="showcase-stats">
              <span class="stat-item"><span class="icon">👁</span> {{ $showcase['views'] }} views</span>
              <span class="stat-item"><span class="icon">💬</span> {{ $showcase['comments'] }} comments</span>
              <span class="stat-item"><span class="icon">⭐</span> {{ $showcase['uses'] }} uses</span>
            </div>
            <div class="showcase-footer">
              <a href="{{ $showcase['url'] }}" class="view-btn">View Details</a>
            </div>
          </div>
        </div>
        @endforeach
      @endif
    </div>

    @if($paginator->hasPages())
    <div class="pagination" id="paginationContainer">
      {{ $paginator->links() }}
    </div>
    @endif
  </div>
</main>

@endsection

@push('styles')
<style>
  .page-header {
    background: var(--page-header-bg);
    color: var(--page-header-text);
    padding: 48px 0 56px;
    position: relative;
    overflow: hidden;
  }
  .page-header h1 {
    font-family: 'Fraunces', serif;
    font-weight: 600;
    font-size: clamp(32px, 4vw, 44px);
    margin-bottom: 12px;
  }
  .page-header p {
    color: var(--page-header-muted);
    font-size: 16px;
    max-width: 600px;
  }
  .header-stats {
    display: flex;
    gap: 24px;
    flex-wrap: wrap;
  }
  .header-stat {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    color: var(--page-header-muted);
  }
  .header-stat strong {
    color: var(--page-header-accent);
    font-family: 'IBM Plex Mono', monospace;
  }

  .filters-section {
    background: var(--surface);
    border-bottom: 1px solid var(--surface-border);
    padding: 16px 0;
    position: sticky;
    top: 0;
    z-index: 10;
  }

  .filters-toolbar {
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
    align-items: center;
    justify-content: space-between;
  }

  .search-box {
    position: relative;
    flex: 1;
    min-width: 240px;
    max-width: 400px;
  }
  .search-icon {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    width: 20px;
    height: 20px;
    color: var(--text-soft);
    pointer-events: none;
  }
  .search-box input {
    width: 100%;
    padding: 10px 44px 10px 44px;
    background: var(--input-bg);
    border: 1px solid var(--input-border);
    border-radius: 8px;
    font-size: 14px;
    color: var(--input-text);
    transition: border-color 0.15s, box-shadow 0.15s, background 0.3s, color 0.3s;
  }
  .search-box input:focus {
    outline: none;
    border-color: var(--navy);
    box-shadow: 0 0 0 3px rgba(31, 56, 100, 0.15);
  }
  .search-box .clear-btn {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    font-size: 18px;
    color: var(--text-soft);
    cursor: pointer;
    padding: 0;
    line-height: 1;
  }
  .search-box .clear-btn:hover {
    color: var(--text-main);
  }

  .filter-group {
    display: flex;
    align-items: center;
    gap: 8px;
  }
  .filter-label {
    font-size: 13px;
    color: var(--text-soft);
    white-space: nowrap;
  }
  .filter-chips {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
  }
  .filter-chip {
    padding: 6px 12px;
    background: var(--chip-bg);
    border: 1px solid var(--chip-border);
    border-radius: 20px;
    font-size: 12px;
    color: var(--chip-text);
    cursor: pointer;
    transition: all 0.15s;
    white-space: nowrap;
  }
  .filter-chip:hover {
    background: var(--surface);
    border-color: var(--navy);
  }
  .filter-chip.active {
    background: var(--chip-active-bg);
    color: var(--chip-active-text);
    border-color: var(--chip-active-bg);
  }

  .view-group {
    display: flex;
    align-items: center;
    gap: 8px;
  }
  .view-label {
    font-size: 13px;
    color: var(--text-soft);
    white-space: nowrap;
  }
  .view-tabs {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
  }
  .view-tab {
    padding: 6px 12px;
    background: var(--chip-bg);
    border: 1px solid var(--chip-border);
    border-radius: 20px;
    font-size: 12px;
    color: var(--chip-text);
    cursor: pointer;
    transition: all 0.15s;
    white-space: nowrap;
  }
  .view-tab:hover {
    background: var(--surface);
    border-color: var(--navy);
  }
  .view-tab.active {
    background: var(--chip-active-bg);
    color: var(--chip-active-text);
    border-color: var(--chip-active-bg);
  }

  .content {
    padding: 24px 0 40px;
  }

  .results-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding: 0 4px;
  }
  .results-count {
    font-size: 14px;
    color: var(--text-soft);
  }

  .showcase-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(360px, 1fr));
    gap: 24px;
  }

  .showcase-card {
    background: var(--surface);
    border: 1px solid var(--surface-border);
    border-radius: 14px;
    overflow: hidden;
    transition: transform 0.18s, box-shadow 0.18s;
    display: flex;
    flex-direction: column;
  }
  .showcase-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 16px 32px -12px rgba(31, 56, 100, 0.25);
  }
  .showcase-card.hidden {
    display: none;
  }

  .showcase-header {
    padding: 16px 20px;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    border-bottom: 1px solid var(--surface-border);
  }
  .showcase-author {
    display: flex;
    align-items: center;
    gap: 12px;
  }
  .author-avatar {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: var(--navy);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 16px;
  }
  .author-info h3 {
    font-size: 15px;
    font-weight: 600;
    color: var(--text-main);
    margin: 0;
  }
  .author-info span {
    font-size: 12px;
    color: var(--text-soft);
  }
  .showcase-badge {
    padding: 4px 10px;
    background: var(--sage-badge-bg);
    color: var(--sage-badge-text);
    border-radius: 6px;
    font-size: 11px;
    font-weight: 500;
  }

  .showcase-body {
    padding: 20px;
    display: flex;
    flex-direction: column;
    flex: 1;
  }
  .showcase-title {
    font-family: 'Fraunces', serif;
    font-size: 20px;
    font-weight: 600;
    color: var(--section-title);
    margin-bottom: 8px;
  }
  .showcase-desc {
    font-size: 14px;
    color: var(--text-soft);
    line-height: 1.6;
    margin-bottom: 16px;
    flex: 1;
  }
  .showcase-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-bottom: 16px;
  }
  .tag {
    padding: 4px 10px;
    background: var(--input-bg);
    border-radius: 4px;
    font-size: 12px;
    color: var(--text-soft);
  }
  .showcase-stats {
    display: flex;
    gap: 20px;
    padding-bottom: 16px;
    border-bottom: 1px solid var(--surface-border);
    margin-bottom: 16px;
  }
  .stat-item {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 13px;
    color: var(--text-soft);
  }
  .stat-item .icon {
    font-size: 16px;
  }

  .showcase-footer {
    display: flex;
    justify-content: flex-end;
  }
  .view-btn {
    padding: 8px 18px;
    background: var(--navy);
    color: #fff;
    border: none;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    transition: background 0.15s;
    text-decoration: none;
  }
  .view-btn:hover {
    background: var(--navy-deep);
  }

  .empty-state {
    grid-column: 1 / -1;
    text-align: center;
    padding: 60px 20px;
    color: var(--text-soft);
  }
  .empty-state svg {
    width: 64px;
    height: 64px;
    margin-bottom: 16px;
    opacity: 0.5;
  }
  .empty-state p {
    font-size: 16px;
    margin-bottom: 16px;
  }
  .clear-filters-btn {
    padding: 10px 20px;
    background: var(--navy);
    color: #fff;
    border: none;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
  }
  .clear-filters-btn:hover {
    background: var(--navy-deep);
  }

  @media (max-width: 900px) {
    .filters-toolbar {
      flex-direction: column;
      align-items: stretch;
    }
    .search-box {
      max-width: none;
    }
    .filter-group {
      flex-wrap: wrap;
    }
    .view-group {
      flex-wrap: wrap;
    }
  }
  @media (max-width: 700px) {
    .showcase-grid {
      grid-template-columns: 1fr;
    }
    .header-stats {
      gap: 16px;
    }
  }

  .pagination {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 4px;
    margin-top: 16px;
    padding-top: 12px;
    border-top: 1px solid var(--surface-border);
  }
  .pagination-link,
  .pagination-current,
  .pagination-ellipsis {
    min-width: 30px;
    height: 30px;
    padding: 0 6px;
    border: 1px solid var(--surface-border);
    border-radius: 4px;
    font-size: 11px;
    color: var(--text-main);
    text-decoration: none;
    background: var(--surface);
    transition: all 0.15s;
    line-height: 1;
    display: flex;
    align-items: center;
    justify-content: center;
  }
  .pagination-link:hover {
    background: var(--input-bg);
    border-color: var(--navy);
    color: var(--navy);
  }
  .pagination-current {
    background: var(--navy);
    color: #fff;
    border-color: var(--navy);
  }
  .pagination-ellipsis {
    color: var(--text-soft);
    border-color: transparent;
    background: transparent;
    cursor: default;
  }

</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
  const searchInput = document.getElementById('searchInput');
  const clearSearchBtn = document.getElementById('clearSearch');
  const deptFilters = document.getElementById('deptFilters');
  const viewTabs = document.getElementById('viewTabs');
  const showcaseGrid = document.getElementById('showcaseGrid');
  const resultsCount = document.getElementById('resultsCount');
  const emptyState = document.getElementById('emptyState');
  const clearFiltersBtn = document.getElementById('clearFiltersBtn');

  // State
  let currentSearch = searchInput.value;
  let currentDepartment = '{{ request('department') }}';
  let currentView = '{{ request('view', 'all') }}';
  let currentPage = 1;

  function debounce(fn, delay) {
    let timer;
    return (...args) => {
      clearTimeout(timer);
      timer = setTimeout(() => fn(...args), delay);
    };
  }

  function buildUrl(page = currentPage) {
    const params = new URLSearchParams();
    if (currentSearch) params.set('search', currentSearch);
    if (currentDepartment) params.set('department', currentDepartment);
    if (currentView && currentView !== 'all') params.set('view', currentView);
    if (page > 1) params.set('page', page);
    return '{{ route('ai-plus.sharing-showcase.index') }}?' + params.toString();
  }

  async function fetchShowcases() {
    const url = buildUrl();
    const res = await fetch(url, { headers: { 'Accept': 'text/html' } });
    const html = await res.text();
    const parser = new DOMParser();
    const doc = parser.parseFromString(html, 'text/html');
    const newGrid = doc.getElementById('showcaseGrid');
    const newCount = doc.getElementById('resultsCount');
    const newEmptyState = doc.getElementById('emptyState');
    const newPagination = doc.querySelector('.pagination');
    if (newGrid) {
      showcaseGrid.innerHTML = newGrid.innerHTML;
    }
    if (newCount) {
      resultsCount.textContent = newCount.textContent;
    }
    if (newEmptyState) {
      emptyState.style.display = newEmptyState.style.display;
      emptyState.innerHTML = newEmptyState.innerHTML;
    }
    // Replace pagination
    const paginationContainer = document.querySelector('.pagination');
    if (newPagination && paginationContainer) {
      paginationContainer.innerHTML = newPagination.innerHTML;
      attachPaginationLinks();
    } else if (newPagination) {
      const parent = showcaseGrid.parentElement;
      parent.insertAdjacentHTML('beforeend', newPagination.outerHTML);
      attachPaginationLinks();
    } else if (paginationContainer) {
      paginationContainer.remove();
    }
    window.history.replaceState({}, '', url);
  }

  const debouncedSearch = debounce(() => {
    currentSearch = searchInput.value.trim();
    clearSearchBtn.style.display = currentSearch ? 'block' : 'none';
    currentPage = 1;
    fetchShowcases();
  }, 300);

  searchInput.addEventListener('input', debouncedSearch);

  clearSearchBtn.addEventListener('click', () => {
    searchInput.value = '';
    currentSearch = '';
    clearSearchBtn.style.display = 'none';
    currentPage = 1;
    fetchShowcases();
    searchInput.focus();
  });

  // Department filter
  deptFilters.addEventListener('click', (e) => {
    const chip = e.target.closest('.filter-chip');
    if (!chip) return;
    currentDepartment = chip.dataset.department || '';
    deptFilters.querySelectorAll('.filter-chip').forEach(btn => {
      btn.classList.toggle('active', btn.dataset.department === currentDepartment);
    });
    currentPage = 1;
    fetchShowcases();
  });

  // View tabs
  viewTabs.addEventListener('click', (e) => {
    const tab = e.target.closest('.view-tab');
    if (!tab) return;
    currentView = tab.dataset.view || 'all';
    viewTabs.querySelectorAll('.view-tab').forEach(btn => {
      btn.classList.toggle('active', btn.dataset.view === currentView);
    });
    currentPage = 1;
    fetchShowcases();
  });

  if (clearFiltersBtn) {
    clearFiltersBtn.addEventListener('click', () => {
      currentSearch = '';
      currentDepartment = '';
      currentView = 'all';
      currentPage = 1;

      searchInput.value = '';
      clearSearchBtn.style.display = 'none';
      deptFilters.querySelectorAll('.filter-chip').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.department === '');
      });
      viewTabs.querySelectorAll('.view-tab').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.view === 'all');
      });

      fetchShowcases();
    });
  }

  searchInput.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      searchInput.blur();
      if (currentSearch) {
        searchInput.value = '';
        currentSearch = '';
        clearSearchBtn.style.display = 'none';
        currentPage = 1;
        fetchShowcases();
      }
    }
  });

  function attachPaginationLinks() {
    document.querySelectorAll('.pagination a').forEach(link => {
      link.addEventListener('click', async (e) => {
        e.preventDefault();
        const url = link.href;
        const urlObj = new URL(url, window.location.origin);
        const pageParam = urlObj.searchParams.get('page');
        if (pageParam) currentPage = parseInt(pageParam, 10);

        const res = await fetch(url, { headers: { 'Accept': 'text/html' } });
        const html = await res.text();
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');
        const newGrid = doc.getElementById('showcaseGrid');
        const newCount = doc.getElementById('resultsCount');
        const newEmptyState = doc.getElementById('emptyState');
        const newPagination = doc.querySelector('.pagination');
        if (newGrid) {
          showcaseGrid.innerHTML = newGrid.innerHTML;
        }
        if (newCount) {
          resultsCount.textContent = newCount.textContent;
        }
        if (newEmptyState) {
          emptyState.style.display = newEmptyState.style.display;
          emptyState.innerHTML = newEmptyState.innerHTML;
        }
        const paginationContainer = document.querySelector('.pagination');
        if (newPagination && paginationContainer) {
          paginationContainer.innerHTML = newPagination.innerHTML;
          attachPaginationLinks();
        } else if (newPagination) {
          const parent = showcaseGrid.parentElement;
          parent.insertAdjacentHTML('beforeend', newPagination.outerHTML);
          attachPaginationLinks();
        } else if (paginationContainer) {
          paginationContainer.remove();
        }
        window.history.replaceState({}, '', url);
      });
    });
  }

  attachPaginationLinks();
});
</script>
@endpush
