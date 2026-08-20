@extends('layouts.ai-plus')

@section('title', 'Agent Templates — AI+ LSTS')

@section('breadcrumb', 'Agent Templates')

@section('content')
<header class="page-header">
  <div class="wrap">
    <h1>Agent Templates</h1>
    <p>Pre-built agents you can clone and customize, instead of starting from a blank page.</p>
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
        <input type="text" id="searchInput" placeholder="Search templates..." value="{{ request('search') }}" autocomplete="off">
        <button id="clearSearch" class="clear-btn" aria-label="Clear search" style="display: none;">×</button>
      </div>

      <div class="filter-group">
        <span class="filter-label">Filter:</span>
        <div class="filter-chips" id="categoryFilters">
          <button class="filter-chip active" data-category="">All Categories</button>
          @foreach($categories as $category)
          <button class="filter-chip" data-category="{{ $category }}">{{ $categoryLabels[$category] ?? ucfirst($category) }}</button>
          @endforeach
        </div>
      </div>

      <div class="sort-group">
        <label for="sortSelect" class="sort-label">Sort:</label>
        <select id="sortSelect" class="sort-select">
          <option value="new" {{ request('sort') === 'new' ? 'selected' : '' }}>Newest</option>
          <option value="alpha" {{ request('sort') === 'alpha' ? 'selected' : '' }}>A–Z</option>
        </select>
      </div>
    </div>
  </div>
</section>

<main class="content">
  <div class="wrap">
    <div class="results-header">
      <span class="results-count" id="resultsCount">
        {{ $templates->count() }} of {{ $totalTemplates }} templates
      </span>
    </div>

    <div class="template-grid" id="templateGrid">
      @if($templates->isEmpty())
      <div class="empty-state" id="emptyState">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
          <path d="M9.172 16.172a4 4 0 0 1 5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z"></path>
        </svg>
        <p>No templates match your search.</p>
        <button class="clear-filters-btn" id="clearFiltersBtn">Clear all filters</button>
      </div>
      @else
        @foreach($templates as $template)
        <div class="template-card" data-category="{{ $template['category'] ?? '' }}">
          <div class="template-preview {{ $template['preview_class'] ?? '' }}">
            <div class="template-icon">{{ $template['icon'] }}</div>
            @if($template['badge'])
            <span class="template-badge">{{ $template['badge'] }}</span>
            @endif
          </div>
          <div class="template-body">
            <h3 class="template-title">{{ $template['title'] }}</h3>
            <p class="template-desc">{{ $template['description'] }}</p>
            <div class="template-features">
              @foreach($template['features'] as $feature)
              <span class="feature-tag">{{ $feature }}</span>
              @endforeach
            </div>
            <div class="template-footer">
              <button class="use-btn" data-template-id="{{ $template['id'] }}">Use Template</button>
            </div>
          </div>
        </div>
        @endforeach
      @endif
    </div>
  </div>
</main>
@endsection

@push('styles')
<style>
  .page-header {
    background: var(--navy);
    color: #fff;
    padding: 48px 0 56px;
  }
  .page-header h1 {
    font-family: 'Fraunces', serif;
    font-weight: 600;
    font-size: clamp(32px, 4vw, 44px);
    margin-bottom: 12px;
  }
  .page-header p {
    color: #C7D3E2;
    font-size: 16px;
    max-width: 600px;
  }

  .filters-section {
    background: var(--card-bg);
    border-bottom: 1px solid var(--line);
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
    color: var(--ink-soft);
    pointer-events: none;
  }
  .search-box input {
    width: 100%;
    padding: 10px 44px 10px 44px;
    background: var(--paper);
    border: 1px solid var(--line);
    border-radius: 8px;
    font-size: 14px;
    color: var(--ink);
    transition: border-color 0.15s, box-shadow 0.15s;
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
    color: var(--ink-soft);
    cursor: pointer;
    padding: 0;
    line-height: 1;
  }
  .search-box .clear-btn:hover {
    color: var(--ink);
  }

  .filter-group {
    display: flex;
    align-items: center;
    gap: 8px;
  }
  .filter-label {
    font-size: 13px;
    color: var(--ink-soft);
    white-space: nowrap;
  }
  .filter-chips {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
  }
  .filter-chip {
    padding: 6px 12px;
    background: var(--paper);
    border: 1px solid var(--line);
    border-radius: 20px;
    font-size: 12px;
    color: var(--ink);
    cursor: pointer;
    transition: all 0.15s;
    white-space: nowrap;
  }
  .filter-chip:hover {
    background: #fff;
    border-color: var(--navy);
  }
  .filter-chip.active {
    background: var(--navy);
    color: #fff;
    border-color: var(--navy);
  }

  .sort-group {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-left: auto;
  }
  .sort-label {
    font-size: 13px;
    color: var(--ink-soft);
    white-space: nowrap;
  }
  .sort-select {
    padding: 6px 32px 6px 12px;
    background: var(--paper);
    border: 1px solid var(--line);
    border-radius: 6px;
    font-size: 13px;
    color: var(--ink);
    cursor: pointer;
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%234A5A7A' stroke-width='2'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 8px center;
  }
  .sort-select:focus {
    outline: none;
    border-color: var(--navy);
    box-shadow: 0 0 0 3px rgba(31, 56, 100, 0.15);
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
    color: var(--ink-soft);
  }

  .template-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 24px;
  }

  .template-card {
    background: var(--card-bg);
    border: 1px solid var(--line);
    border-radius: 14px;
    overflow: hidden;
    transition: transform 0.18s, box-shadow 0.18s;
    display: flex;
    flex-direction: column;
  }
  .template-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 16px 32px -12px rgba(31, 56, 100, 0.25);
  }
  .template-card.hidden {
    display: none;
  }

  .template-preview {
    height: 140px;
    background: linear-gradient(135deg, var(--navy) 0%, #2A4A73 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
  }
  .template-preview.math { background: linear-gradient(135deg, #1E4D6B 0%, #2E6D8B 100%); }
  .template-preview.english { background: linear-gradient(135deg, #6B4D1E 0%, #8B6D2E 100%); }
  .template-preview.science { background: linear-gradient(135deg, #1E6B4D 0%, #2E8B6D 100%); }
  .template-preview.admin { background: linear-gradient(135deg, #4D1E6B 0%, #6D2E8B 100%); }
  .template-icon {
    font-size: 48px;
    opacity: 0.9;
  }
  .template-badge {
    position: absolute;
    top: 12px;
    right: 12px;
    background: rgba(255, 255, 255, 0.2);
    backdrop-filter: blur(8px);
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 11px;
    font-family: 'IBM Plex Mono', monospace;
    color: #fff;
  }

  .template-body {
    padding: 20px;
    display: flex;
    flex-direction: column;
    flex: 1;
  }
  .template-title {
    font-family: 'Fraunces', serif;
    font-size: 18px;
    font-weight: 600;
    color: var(--ink);
    margin-bottom: 8px;
  }
  .template-desc {
    font-size: 14px;
    color: var(--ink-soft);
    margin-bottom: 16px;
    line-height: 1.5;
    flex: 1;
  }
  .template-features {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-bottom: 16px;
  }
  .feature-tag {
    font-size: 11px;
    padding: 4px 8px;
    background: var(--paper);
    border-radius: 4px;
    color: var(--ink-soft);
  }
  .template-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 16px;
    border-top: 1px solid var(--line);
  }
  .use-btn {
    padding: 8px 18px;
    background: var(--navy);
    color: #fff;
    border: none;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    transition: background 0.15s;
  }
  .use-btn:hover {
    background: var(--navy-deep);
  }

  .empty-state {
    grid-column: 1 / -1;
    text-align: center;
    padding: 60px 20px;
    color: var(--ink-soft);
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

  @media (max-width: 768px) {
    .filters-toolbar {
      flex-direction: column;
      align-items: stretch;
    }
    .search-box {
      max-width: none;
    }
    .sort-group {
      margin-left: 0;
      justify-content: space-between;
    }
    .filter-chips {
      overflow-x: auto;
      flex-wrap: nowrap;
      padding-bottom: 4px;
    }
  }
</style>
@endpush

@push('scripts')
<script>
(function() {
  'use strict';

  const searchInput = document.getElementById('searchInput');
  const clearSearchBtn = document.getElementById('clearSearch');
  const categoryFilters = document.getElementById('categoryFilters');
  const sortSelect = document.getElementById('sortSelect');
  const templateGrid = document.getElementById('templateGrid');
  const resultsCount = document.getElementById('resultsCount');
  const emptyState = document.getElementById('emptyState');
  const clearFiltersBtn = document.getElementById('clearFiltersBtn');

  let searchDebounceTimer = null;
  const DEBOUNCE_MS = 300;

  // State
  let currentSearch = searchInput.value;
  let currentCategory = '';
  let currentSort = sortSelect.value;

  // Get all template cards
  const templateCards = Array.from(templateGrid.querySelectorAll('.template-card'));

  function updateResultsCount(visibleCount) {
    resultsCount.textContent = `${visibleCount} of {{ $totalTemplates }} templates`;
  }

  function showEmptyState(show) {
    if (show) {
      emptyState.style.display = 'block';
      templateGrid.querySelectorAll('.template-card').forEach(c => c.style.display = 'none');
    } else {
      emptyState.style.display = 'none';
    }
  }

  function filterAndRender() {
    let visibleCount = 0;

    templateCards.forEach(card => {
      const title = card.querySelector('.template-title').textContent.toLowerCase();
      const desc = card.querySelector('.template-desc').textContent.toLowerCase();
      const cardCategory = card.dataset.category || '';

      // Search match
      const searchMatch = !currentSearch ||
        title.includes(currentSearch.toLowerCase()) ||
        desc.includes(currentSearch.toLowerCase());

      // Category match
      const categoryMatch = !currentCategory || cardCategory === currentCategory;

      if (searchMatch && categoryMatch) {
        card.classList.remove('hidden');
        card.style.display = '';
        visibleCount++;
      } else {
        card.classList.add('hidden');
        card.style.display = 'none';
      }
    });

    updateResultsCount(visibleCount);
    showEmptyState(visibleCount === 0);

    // Re-sort visible cards
    sortVisibleCards();
  }

  function sortVisibleCards() {
    const visibleCards = templateCards.filter(c => !c.classList.contains('hidden'));

    visibleCards.sort((a, b) => {
      if (currentSort === 'new') {
        // Newest = higher ID first (since we use latest())
        return parseInt(b.dataset.id || b.id) - parseInt(a.dataset.id || a.id);
      } else if (currentSort === 'alpha') {
        const titleA = a.querySelector('.template-title').textContent.toLowerCase();
        const titleB = b.querySelector('.template-title').textContent.toLowerCase();
        return titleA.localeCompare(titleB);
      }
      return 0;
    });

    // Re-append in sorted order
    visibleCards.forEach(card => templateGrid.appendChild(card));
  }

  function debouncedSearch() {
    clearTimeout(searchDebounceTimer);
    searchDebounceTimer = setTimeout(() => {
      currentSearch = searchInput.value.trim();
      clearSearchBtn.style.display = currentSearch ? 'block' : 'none';
      filterAndRender();
    }, DEBOUNCE_MS);
  }

  function clearAllFilters() {
    currentSearch = '';
    currentCategory = '';
    currentSort = 'new';

    searchInput.value = '';
    clearSearchBtn.style.display = 'none';
    categoryFilters.querySelectorAll('.filter-chip').forEach(btn => {
      btn.classList.toggle('active', btn.dataset.category === '');
    });
    sortSelect.value = 'new';

    filterAndRender();
  }

  // Event listeners
  searchInput.addEventListener('input', debouncedSearch);

  clearSearchBtn.addEventListener('click', () => {
    searchInput.value = '';
    currentSearch = '';
    clearSearchBtn.style.display = 'none';
    filterAndRender();
    searchInput.focus();
  });

  categoryFilters.addEventListener('click', (e) => {
    const chip = e.target.closest('.filter-chip');
    if (!chip) return;

    currentCategory = chip.dataset.category || '';
    categoryFilters.querySelectorAll('.filter-chip').forEach(btn => {
      btn.classList.toggle('active', btn.dataset.category === currentCategory);
    });
    filterAndRender();
  });

  sortSelect.addEventListener('change', () => {
    currentSort = sortSelect.value;
    sortVisibleCards();
  });

  clearFiltersBtn.addEventListener('click', clearAllFilters);

  // Keyboard: Escape to clear search
  searchInput.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      searchInput.blur();
      if (currentSearch) {
        searchInput.value = '';
        currentSearch = '';
        clearSearchBtn.style.display = 'none';
        filterAndRender();
      }
    }
  });

  // Initial render
  filterAndRender();
})();
</script>
@endpush