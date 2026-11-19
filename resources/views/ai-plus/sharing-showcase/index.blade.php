@extends('layouts.ai-plus')

@section('title', 'Sharing & Showcase — LSTS AI+')

@section('breadcrumb', 'Sharing & Showcase')

@section('screen-class', 'lsts-ai-catalog')

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
    <form class="filters-toolbar" id="showcaseFilters" action="{{ route('ai-plus.sharing-showcase.index') }}" method="GET">
      <div class="showcase-search">
        <label for="searchInput">Search</label>
        <div class="showcase-search-input">
          <input type="search" id="searchInput" name="search" placeholder="Search showcases…" value="{{ request()->string('search') }}" autocomplete="off">
          <button type="submit" class="view-btn">Search</button>
        </div>
      </div>
      <div class="showcase-filter-controls">
        <label for="showcaseGroup">Group
          <select id="showcaseGroup" name="group">
            <option value="">All groups</option>
            @foreach($groups as $key => $label)
              <option value="{{ $key }}" @selected($selectedGroup === $key)>{{ $label }}</option>
            @endforeach
          </select>
        </label>
        <label for="showcaseCategory">Category
          <select id="showcaseCategory" name="category" @disabled($selectedGroup === '')>
            <option value="">{{ $selectedGroup === '' ? 'Select a group first' : 'All categories' }}</option>
            @foreach(($categories[$selectedGroup] ?? []) as $key => $label)
              <option value="{{ $key }}" @selected($selectedCategory === $key)>{{ $label }}</option>
            @endforeach
          </select>
        </label>
        <label for="showcaseDepartment">Department
          <select id="showcaseDepartment" name="department">
            <option value="">All departments</option>
            @foreach($departments as $dept)
              <option value="{{ $dept }}" @selected(request('department') === $dept)>{{ $dept }}</option>
            @endforeach
          </select>
        </label>
        <label for="showcaseView">View
          <select id="showcaseView" name="view">
            <option value="all" @selected($view === 'all')>All</option>
            <option value="trending" @selected($view === 'trending')>Trending</option>
            <option value="new" @selected($view === 'new')>New</option>
            <option value="mostused" @selected($view === 'mostused')>Most Used</option>
          </select>
        </label>
      </div>
    </form>
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
        <p>{{ $totalAgents === 0 ? 'No showcases shared yet. Be the first!' : 'No showcases match your filters.' }}</p>
        @if($totalAgents > 0)
          <a class="view-btn" href="{{ route('ai-plus.sharing-showcase.index') }}" data-showcase-reset>Clear all filters</a>
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
              @if($showcase['classification'])
              <span class="tag">{{ $showcase['classification'] }}</span>
              @endif
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
    font-family: var(--lsts-font);
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
    font-size: 16px;
    color: var(--page-header-muted);
  }
  .header-stat strong {
    color: var(--page-header-accent);
    font-family: var(--lsts-font);
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
    display: grid;
    gap: 12px;
  }
  .showcase-search { max-width: 480px; }
  .showcase-search-input { display: flex; gap: 8px; }
  .showcase-filter-controls {
    display: grid;
    grid-template-columns: minmax(140px, 1fr) minmax(220px, 1.6fr) minmax(140px, 1fr) minmax(110px, .8fr);
    gap: 12px;
  }
  .filters-toolbar label {
    display: block;
    color: var(--text-soft);
    font-size: 14px;
    font-weight: 600;
  }
  .filters-toolbar input,
  .filters-toolbar select {
    display: block;
    width: 100%;
    min-width: 0;
    box-sizing: border-box;
    margin-top: 4px;
    padding: 8px 10px;
    background: var(--input-bg);
    color: var(--text-main);
    border: 1px solid var(--surface-border);
    border-radius: 8px;
    font: inherit;
  }
  .showcase-search-input .view-btn { align-self: end; }
  .filters-toolbar select:disabled { opacity: .75; }

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
    font-size: 16px;
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
    font-size: 17px;
    font-weight: 600;
    color: var(--text-main);
    margin: 0;
  }
  .author-info span {
    font-size: 14px;
    color: var(--text-soft);
  }
  .showcase-badge {
    padding: 4px 10px;
    background: var(--sage-badge-bg);
    color: var(--sage-badge-text);
    border-radius: 6px;
    font-size: 13px;
    font-weight: 500;
  }

  .showcase-body {
    padding: 20px;
    display: flex;
    flex-direction: column;
    flex: 1;
  }
  .showcase-title {
    font-family: var(--lsts-font);
    font-size: 20px;
    font-weight: 600;
    color: var(--section-title);
    margin-bottom: 8px;
  }
  .showcase-desc {
    font-size: 16px;
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
    font-size: 14px;
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
    font-size: 15px;
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
    font-size: 15px;
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
  @media (max-width: 900px) {
    .showcase-filter-controls {
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 8px;
    }
    .filters-section { position: static; }
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
    font-size: 13px;
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
  const form = document.getElementById('showcaseFilters');
  const search = document.getElementById('searchInput');
  const group = document.getElementById('showcaseGroup');
  const category = document.getElementById('showcaseCategory');
  const content = document.querySelector('main.content');
  const categories = {{ Illuminate\Support\Js::from($categories) }};
  let currentRequest;
  let searchTimer;

  function setCategories() {
    const options = categories[group.value] || {};
    category.replaceChildren(new Option(group.value ? 'All categories' : 'Select a group first', ''));
    Object.entries(options).forEach(([value, label]) => category.add(new Option(label, value)));
    category.disabled = !group.value;
  }

  async function showResults(url) {
    clearTimeout(searchTimer);
    currentRequest?.abort();
    const request = new AbortController();
    currentRequest = request;
    content.setAttribute('aria-busy', 'true');
    try {
      const response = await fetch(url, { signal: request.signal });
      if (!response.ok) throw new Error();
      const page = new DOMParser().parseFromString(await response.text(), 'text/html');
      const results = page.querySelector('main.content');
      if (!results) throw new Error();
      if (request.signal.aborted) return;
      content.replaceChildren(...results.childNodes);
      window.history.replaceState({}, '', url);
    } catch (error) {
      if (!request.signal.aborted) window.location.assign(url);
    } finally {
      if (currentRequest === request) content.removeAttribute('aria-busy');
    }
  }

  function applyFilters() {
    const url = new URL(form.action);
    for (const [name, value] of new FormData(form)) {
      if (value && !(name === 'view' && value === 'all')) url.searchParams.set(name, value);
    }
    showResults(url);
  }

  form.addEventListener('submit', (event) => {
    event.preventDefault();
    applyFilters();
  });
  group.addEventListener('change', setCategories);
  form.querySelectorAll('select').forEach((select) => select.addEventListener('change', applyFilters));
  search.addEventListener('input', () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(applyFilters, 300);
  });
  content.addEventListener('click', (event) => {
    if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
    const link = event.target.closest('.pagination a, [data-showcase-reset]');
    if (!link) return;
    event.preventDefault();
    if (link.hasAttribute('data-showcase-reset')) {
      search.value = '';
      group.value = '';
      setCategories();
      document.getElementById('showcaseDepartment').value = '';
      document.getElementById('showcaseView').value = 'all';
    }
    showResults(link.href);
  });
});
</script>
@endpush
