@extends('layouts.ai-plus')

@section('title', 'Prompt Library — AI+ LSTS')

@section('breadcrumb', 'Prompt Library')

@section('content')
<header class="page-header">
  <div class="wrap">
    <h1>Prompt Library</h1>
    <p>Ready-to-use prompts for common tasks, organized by role and subject. Copy, tweak, and go.</p>
    <div class="stats-bar">
      <div class="stat">
        <div class="stat-num">{{ $totalPrompts }}</div>
        <div class="stat-label">Prompts</div>
      </div>
      <div class="stat">
        <div class="stat-num">{{ $totalSubjects }}</div>
        <div class="stat-label">Subjects</div>
      </div>
    </div>
  </div>
</header>

<section class="filters-section">
  <div class="wrap">
    <div class="filters">
      <div class="search-box">
        <span class="icon">🔍</span>
        <input type="text" id="prompt-search" placeholder="Search prompts..." value="{{ request('search') }}">
      </div>
      <div class="filter-group" id="subject-filters">
        <button class="filter-btn {{ !request('subject') ? 'active' : '' }}" data-subject="">All</button>
        @foreach($subjects as $subject)
        <button class="filter-btn {{ request('subject') == $subject->name ? 'active' : '' }}" data-subject="{{ $subject->name }}">{{ $subject->name }}</button>
        @endforeach
      </div>
      <div class="filter-group sort-group">
        <span class="sort-label">Sort by:</span>
        <button class="filter-btn {{ request('sort') == 'popular' ? 'active' : '' }}" data-sort="popular">⭐ Popular</button>
        <button class="filter-btn {{ request('sort') == 'new' ? 'active' : '' }}" data-sort="new">✨ New</button>
      </div>
    </div>
  </div>
</section>

<main class="content">
  <div class="wrap">
    <h2 class="section-title">Prompts <span class="count">{{ $promptsPaginator->total() }} prompts</span></h2>

    <div class="prompt-grid" id="prompt-grid">
      @foreach($prompts as $prompt)
      <div class="prompt-card" data-subject="{{ $prompt['badges'][0] ?? '' }}">
        <div class="prompt-header">
          <div class="prompt-title">{{ $prompt['title'] }}</div>
          <div class="prompt-badges">
            @foreach($prompt['badges'] as $badge)
            <span class="badge {{ strtolower($badge) == 'new' ? 'new' : (strtolower($badge) == 'admin' || strtolower($badge) == 'counseling' || strtolower($badge) == 'ciec' ? 'role' : 'subject') }}">{{ $badge }}</span>
            @endforeach
          </div>
        </div>
        <p class="prompt-desc">{{ $prompt['description'] }}</p>
        <div class="prompt-preview">{{ $prompt['preview'] }}</div>
        <div class="prompt-footer">
          <button class="copy-btn" data-prompt="{{ htmlspecialchars($prompt['preview'], ENT_QUOTES, 'UTF-8') }}">Copy Prompt</button>
        </div>
      </div>
      @endforeach
    </div>

    @if($prompts->isEmpty())
    <div class="empty-state">
      <div class="empty-icon">🔍</div>
      <h3>No prompts found</h3>
      <p>Try adjusting your search or filters.</p>
    </div>
    @else
    @if($promptsPaginator->hasPages())
    <div class="pagination">
      {{ $promptsPaginator->links() }}
    </div>
    @endif
    @endif
  </div>
</main>
@endsection

@push('styles')
<style>
  .page-header{background: var(--page-header-bg);color:var(--page-header-text);padding: 48px 0 56px;}
  .page-header h1{font-family:'Fraunces', serif;font-weight:600;font-size: clamp(32px, 4vw, 44px);margin-bottom:12px;}
  .page-header p{color:var(--page-header-muted);font-size:16px;max-width:600px;}
  .stats-bar{display:flex;gap:32px;margin-top:24px;}
  .stat{text-align:center;}
  .stat-num{font-family:'Fraunces', serif;font-size:28px;font-weight:600;color: var(--page-header-stat);}
  .stat-label{font-size:12px;color:var(--text-soft);text-transform:uppercase;letter-spacing:0.05em;}

  .filters-section{background: var(--surface);border-bottom:1px solid var(--surface-border);padding:20px 0;position:sticky;top:0;z-index:10;}
  .filters{display:flex;gap:12px;flex-wrap:wrap;align-items:center;}
  .search-box{flex:1;min-width:200px;max-width:400px;position:relative;}
  .search-box input{width:100%;padding:10px 16px 10px 40px;border:1px solid var(--input-border);border-radius:8px;font-size:14px;background: var(--input-bg);color: var(--input-text);transition: border-color .15s, background .3s, color .3s;}
  .search-box input:focus{outline:none;border-color:var(--navy);}
  .search-box .icon{position:absolute;left:14px;top:50%;transform:translateY(-50%);color: var(--text-soft);}
  .filter-group{display:flex;gap:8px;}
  .filter-group.sort-group{align-items:center;}
  .sort-label{font-size:13px;color: var(--text-soft);white-space:nowrap;}
  .filter-btn{padding:8px 14px;background: var(--chip-bg);border:1px solid var(--chip-border);border-radius:6px;font-size:13px;color: var(--chip-text);cursor:pointer;transition: all 0.15s;}
  .filter-btn:hover{background: var(--surface);border-color:var(--navy);}
  .filter-btn.active{background: var(--chip-active-bg);color:var(--chip-active-text);border-color:var(--chip-active-bg);}

  .content{padding:32px 0 48px;}
  .section-title{font-family:'Fraunces', serif;font-size:22px;font-weight:600;color: var(--section-title);margin-bottom:20px;display:flex;align-items:center;gap:10px;}
  .section-title .count{font-family:'IBM Plex Mono', monospace;font-size:13px;color: var(--text-soft);font-weight:400;}

  .prompt-grid{display:grid;grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));gap:20px;}
  .prompt-card{background: var(--surface);border:1px solid var(--surface-border);border-radius:12px;padding:20px;transition: transform 0.18s, box-shadow 0.18s, border-color 0.18s;}
  .prompt-card:hover{transform:translateY(-2px);box-shadow: 0 12px 24px -12px rgba(31,56,100,0.2);border-color: var(--surface-border);}
  .prompt-header{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:12px;}
  .prompt-title{font-weight:600;font-size:16px;color: var(--text-main);}
  .prompt-badges{display:flex;gap:6px;}
  .badge{font-family:'IBM Plex Mono', monospace;font-size:10px;padding:3px 8px;border-radius:4px;text-transform:uppercase;letter-spacing:0.03em;}
  .badge.subject{background:var(--brand-badge-bg);color:var(--brand-badge-text);}
  .badge.role{background:var(--brand-badge-bg);color:var(--brand-badge-text);}
  .badge.new{background:var(--sage-badge-bg);color:var(--sage-badge-text);}
  .prompt-desc{font-size:14px;color: var(--text-soft);margin-bottom:16px;line-height:1.5;}
  .prompt-preview{background: var(--input-bg);border:1px solid var(--input-border);border-radius:8px;padding:12px;font-family:'IBM Plex Mono', monospace;font-size:12px;color: var(--text-soft);margin-bottom:16px;max-height:80px;overflow:hidden;position:relative;}
  .prompt-preview::after{content:"";position:absolute;bottom:0;left:0;right:0;height:30px;background: linear-gradient(transparent, var(--input-bg));}
  .prompt-footer{display:flex;justify-content:space-between;align-items:center;}
  .copy-btn{padding:8px 16px;background: var(--navy);color:#fff;border:none;border-radius:6px;font-size:13px;font-weight:500;cursor:pointer;transition: background 0.15s;}
  .copy-btn:hover{background: var(--navy-deep);}

  .empty-state{grid-column:1/-1;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:60px 20px;text-align:center;background: var(--surface);border:1px solid var(--surface-border);border-radius:14px;}
  .empty-icon{font-size:48px;margin-bottom:16px;}
  .empty-state h3{font-family:'Fraunces', serif;font-size:22px;color:var(--text-main);margin-bottom:8px;}
  .empty-state p{color:var(--text-soft);margin-bottom:24px;}

  @media (max-width: 700px){
    .prompt-grid{grid-template-columns:1fr;}
    .filters{flex-direction:column;align-items:stretch;}
    .search-box{max-width:none;}
    .filter-group{justify-content:center;}
  }

  .pagination{display:flex;justify-content:center;align-items:center;gap:4px;margin-top:16px;padding-top:12px;border-top:1px solid var(--surface-border);}
  .pagination-link,.pagination-current,.pagination-ellipsis{min-width:30px;height:30px;padding:0 6px;border:1px solid var(--surface-border);border-radius:4px;font-size:11px;color: var(--text-main);text-decoration:none;background: var(--surface);transition: all 0.15s;line-height:1;display:flex;align-items:center;justify-content:center;}
  .pagination-link:hover{background: var(--input-bg);border-color:var(--navy);color:var(--navy);}
  .pagination-current{background: var(--navy);color:#fff;border-color:var(--navy);}
  .pagination-ellipsis{color: var(--text-soft);border-color:transparent;background:transparent;cursor:default;}
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
  const searchInput = document.getElementById('prompt-search');
  const subjectButtons = document.querySelectorAll('#subject-filters .filter-btn[data-subject]');
  const sortButtons = document.querySelectorAll('.filter-btn[data-sort]');
  const promptGrid = document.getElementById('prompt-grid');
  const promptCards = document.querySelectorAll('.prompt-card');
  const sectionTitleCount = document.querySelector('.section-title .count');

  let currentSearch = searchInput.value;
  let currentSubject = '{{ request('subject') }}';
  let currentSort = '{{ request('sort') }}';
  let currentPage = 1;

  // Debounce helper
  function debounce(fn, delay) {
    let timer;
    return (...args) => {
      clearTimeout(timer);
      timer = setTimeout(() => fn(...args), delay);
    };
  }

  // Build URL with current filters
  function buildUrl(page = currentPage) {
    const params = new URLSearchParams();
    if (currentSearch) params.set('search', currentSearch);
    if (currentSubject) params.set('subject', currentSubject);
    if (currentSort) params.set('sort', currentSort);
    if (page > 1) params.set('page', page);
    return '{{ route('ai-plus.prompt-library.index') }}?' + params.toString();
  }

  // Fetch and update grid
  async function fetchPrompts() {
    const url = buildUrl();
    const res = await fetch(url, { headers: { 'Accept': 'text/html' } });
    const html = await res.text();
    // Extract new grid content
    const parser = new DOMParser();
    const doc = parser.parseFromString(html, 'text/html');
    const newGrid = doc.getElementById('prompt-grid');
    const newCount = doc.querySelector('.section-title .count');
    const newPagination = doc.querySelector('.pagination');
    if (newGrid) {
      promptGrid.innerHTML = newGrid.innerHTML;
      // Re-attach copy buttons
      attachCopyButtons();
    }
    if (newCount) {
      sectionTitleCount.textContent = newCount.textContent;
    }
    // Replace pagination
    const paginationContainer = document.querySelector('.pagination');
    if (newPagination && paginationContainer) {
      paginationContainer.innerHTML = newPagination.innerHTML;
      attachPaginationLinks();
    } else if (newPagination) {
      const parent = promptGrid.parentElement;
      parent.insertAdjacentHTML('beforeend', newPagination.outerHTML);
      attachPaginationLinks();
    } else if (paginationContainer) {
      paginationContainer.remove();
    }
    // Update URL without reload
    window.history.replaceState({}, '', url);
  }

  // Debounced search
  const debouncedSearch = debounce(() => {
    currentSearch = searchInput.value;
    // Reset to page 1 when searching
    currentPage = 1;
    fetchPrompts();
  }, 300);

  searchInput.addEventListener('input', debouncedSearch);

  // Subject filter buttons
  subjectButtons.forEach(btn => {
    btn.addEventListener('click', () => {
      subjectButtons.forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      currentSubject = btn.dataset.subject;
      // Reset to page 1 when changing subject
      currentPage = 1;
      fetchPrompts();
    });
  });

  // Sort filter buttons
  sortButtons.forEach(btn => {
    btn.addEventListener('click', () => {
      sortButtons.forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      currentSort = btn.dataset.sort;
      // Reset to page 1 when changing sort
      currentPage = 1;
      fetchPrompts();
    });
  });

  // Copy button functionality
  function attachCopyButtons() {
    document.querySelectorAll('.copy-btn').forEach(btn => {
      btn.addEventListener('click', async () => {
        const promptText = btn.dataset.prompt;
        try {
          // Try modern clipboard API first (requires HTTPS)
          if (navigator.clipboard && window.isSecureContext) {
            await navigator.clipboard.writeText(promptText);
          } else {
            // Fallback for HTTP/localhost
            const textarea = document.createElement('textarea');
            textarea.value = promptText;
            textarea.style.position = 'fixed';
            textarea.style.opacity = '0';
            document.body.appendChild(textarea);
            textarea.select();
            document.execCommand('copy');
            document.body.removeChild(textarea);
          }
          const original = btn.textContent;
          btn.textContent = 'Copied!';
          btn.style.background = 'var(--sage)';
          setTimeout(() => {
            btn.textContent = original;
            btn.style.background = '';
          }, 1500);
        } catch (e) {
          console.error('Copy failed', e);
          // Last resort fallback
          const textarea = document.createElement('textarea');
          textarea.value = promptText;
          textarea.style.position = 'fixed';
          textarea.style.opacity = '0';
          document.body.appendChild(textarea);
          textarea.select();
          document.execCommand('copy');
          document.body.removeChild(textarea);

          const original = btn.textContent;
          btn.textContent = 'Copied!';
          btn.style.background = 'var(--sage)';
          setTimeout(() => {
            btn.textContent = original;
            btn.style.background = '';
          }, 1500);
        }
      });
    });
  }

  // Handle pagination links via AJAX
  function attachPaginationLinks() {
    document.querySelectorAll('.pagination a').forEach(link => {
      link.addEventListener('click', async (e) => {
        e.preventDefault();
        const url = link.href;
        // Extract page number from URL
        const urlObj = new URL(url, window.location.origin);
        const pageParam = urlObj.searchParams.get('page');
        if (pageParam) currentPage = parseInt(pageParam, 10);

        const res = await fetch(url, { headers: { 'Accept': 'text/html' } });
        const html = await res.text();
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');
        const newGrid = doc.getElementById('prompt-grid');
        const newCount = doc.querySelector('.section-title .count');
        const newPagination = doc.querySelector('.pagination');
        if (newGrid) {
          promptGrid.innerHTML = newGrid.innerHTML;
          attachCopyButtons();
        }
        if (newCount) {
          sectionTitleCount.textContent = newCount.textContent;
        }
        // Replace pagination
        const paginationContainer = document.querySelector('.pagination');
        if (newPagination && paginationContainer) {
          paginationContainer.innerHTML = newPagination.innerHTML;
          attachPaginationLinks();
        } else if (newPagination) {
          // Insert new pagination
          const parent = promptGrid.parentElement;
          parent.insertAdjacentHTML('beforeend', newPagination.outerHTML);
          attachPaginationLinks();
        } else if (paginationContainer) {
          paginationContainer.remove();
        }
        window.history.replaceState({}, '', url);
      });
    });
  }

  attachCopyButtons();
  attachPaginationLinks();
});
</script>
@endpush