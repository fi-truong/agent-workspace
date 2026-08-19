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
    <div class="filters">
      <div class="filter-left">
        <button class="filter-btn active">All</button>
        <button class="filter-btn">🔥 Trending</button>
        <button class="filter-btn">✨ New</button>
        <button class="filter-btn">⭐ Most Used</button>
      </div>
      <button class="share-btn">+ Share Your Agent</button>
    </div>
  </div>
</section>

<main class="content">
  <div class="wrap">
    <div class="showcase-grid">
      @foreach($showcases as $showcase)
      <div class="showcase-card">
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
        </div>
      </div>
      @endforeach
    </div>
  </div>
</main>
@endsection

@push('styles')
<style>
  .page-header{background: linear-gradient(135deg, var(--navy) 0%, #274B7F 100%);color:#fff;padding: 48px 0 56px;position:relative;overflow:hidden;}
  .page-header h1{font-family:'Fraunces', serif;font-weight:600;font-size: clamp(32px, 4vw, 44px);margin-bottom:12px;}
  .page-header p{color:#C7D3E2;font-size:16px;max-width:600px;}
  .header-stats{display:flex;gap:24px;margin-top:24px;}
  .header-stat{display:flex;align-items:center;gap:8px;font-size:14px;color:#CBD6E4;}
  .header-stat strong{color: var(--gold-light);font-family:'IBM Plex Mono', monospace;}

  .filters-section{background: var(--card-bg);border-bottom:1px solid var(--line);padding:20px 0;position:sticky;top:0;z-index:10;}
  .filters{display:flex;gap:12px;flex-wrap:wrap;align-items:center;justify-content:space-between;}
  .filter-left{display:flex;gap:12px;flex-wrap:wrap;}
  .filter-btn{padding:8px 14px;background: var(--paper);border:1px solid var(--line);border-radius:6px;font-size:13px;color: var(--ink);cursor:pointer;transition: all 0.15s;}
  .filter-btn:hover{background: #fff;border-color:var(--navy);}
  .filter-btn.active{background: var(--navy);color:#fff;border-color:var(--navy);}
  .share-btn{padding:10px 20px;background: var(--gold);color: var(--navy-deep);border:none;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;transition: background 0.15s;}
  .share-btn:hover{background: #E5AB45;}

  .content{padding:32px 0 48px;}

  .showcase-grid{display:grid;grid-template-columns: repeat(auto-fill, minmax(360px, 1fr));gap:24px;}
  .showcase-card{background: var(--card-bg);border:1px solid var(--line);border-radius:14px;overflow:hidden;transition: transform 0.18s, box-shadow 0.18s;}
  .showcase-card:hover{transform:translateY(-3px);box-shadow: 0 16px 32px -12px rgba(31,56,100,0.25);}
  .showcase-header{padding:20px;display:flex;justify-content:space-between;align-items:flex-start;border-bottom:1px solid var(--line);}
  .showcase-author{display:flex;align-items:center;gap:12px;}
  .author-avatar{width:44px;height:44px;border-radius:50%;background: var(--navy);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:600;font-size:16px;}
  .author-info h3{font-size:15px;font-weight:600;color: var(--ink);margin:0;}
  .author-info span{font-size:12px;color: var(--ink-soft);}
  .showcase-badge{padding:4px 10px;background: #E8F5F0;color: var(--sage);border-radius:6px;font-size:11px;font-weight:500;}
  .showcase-body{padding:20px;}
  .showcase-title{font-family:'Fraunces', serif;font-size:20px;font-weight:600;color: var(--navy);margin-bottom:8px;}
  .showcase-desc{font-size:14px;color: var(--ink-soft);line-height:1.6;margin-bottom:16px;}
  .showcase-tags{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:16px;}
  .tag{padding:4px 10px;background: var(--paper);border-radius:4px;font-size:12px;color: var(--ink-soft);}
  .showcase-stats{display:flex;gap:20px;padding-top:16px;border-top:1px solid var(--line);}
  .stat-item{display:flex;align-items:center;gap:6px;font-size:13px;color: var(--ink-soft);}
  .stat-item .icon{font-size:16px;}

  @media (max-width: 800px){.showcase-grid{grid-template-columns:1fr;}}
</style>
@endpush
