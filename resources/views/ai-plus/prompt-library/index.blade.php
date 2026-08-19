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
      <div class="stat">
        <div class="stat-num">{{ $totalContributors }}</div>
        <div class="stat-label">Contributors</div>
      </div>
    </div>
  </div>
</header>

<section class="filters-section">
  <div class="wrap">
    <div class="filters">
      <div class="search-box">
        <span class="icon">🔍</span>
        <input type="text" placeholder="Search prompts...">
      </div>
      <div class="filter-group">
        <button class="filter-btn active">All</button>
        <button class="filter-btn">Math</button>
        <button class="filter-btn">English</button>
        <button class="filter-btn">Science</button>
        <button class="filter-btn">Admin</button>
        <button class="filter-btn">HR</button>
      </div>
      <div class="filter-group">
        <button class="filter-btn">⭐ Popular</button>
        <button class="filter-btn">✨ New</button>
      </div>
    </div>
  </div>
</section>

<main class="content">
  <div class="wrap">
    <h2 class="section-title">Popular Prompts <span class="count">{{ count($prompts) }} prompts</span></h2>

    <div class="prompt-grid">
      @foreach($prompts as $prompt)
      <div class="prompt-card">
        <div class="prompt-header">
          <div class="prompt-title">{{ $prompt['title'] }}</div>
          <div class="prompt-badges">
            @foreach($prompt['badges'] as $badge)
            <span class="badge {{ strtolower($badge) == 'new' ? 'new' : (strtolower($badge) == 'admin' ? 'role' : 'subject') }}">{{ $badge }}</span>
            @endforeach
          </div>
        </div>
        <p class="prompt-desc">{{ $prompt['description'] }}</p>
        <div class="prompt-preview">{{ $prompt['preview'] }}</div>
        <div class="prompt-footer">
          <button class="copy-btn">Copy Prompt</button>
        </div>
      </div>
      @endforeach
    </div>
  </div>
</main>
@endsection

@push('styles')
<style>
  .page-header{background: var(--navy);color:#fff;padding: 48px 0 56px;}
  .page-header h1{font-family:'Fraunces', serif;font-weight:600;font-size: clamp(32px, 4vw, 44px);margin-bottom:12px;}
  .page-header p{color:#C7D3E2;font-size:16px;max-width:600px;}
  .stats-bar{display:flex;gap:32px;margin-top:24px;}
  .stat{text-align:center;}
  .stat-num{font-family:'Fraunces', serif;font-size:28px;font-weight:600;color: var(--gold-light);}
  .stat-label{font-size:12px;color:#7C8FA8;text-transform:uppercase;letter-spacing:0.05em;}

  .filters-section{background: var(--card-bg);border-bottom:1px solid var(--line);padding:20px 0;position:sticky;top:0;z-index:10;}
  .filters{display:flex;gap:12px;flex-wrap:wrap;align-items:center;}
  .search-box{flex:1;min-width:200px;max-width:400px;position:relative;}
  .search-box input{width:100%;padding:10px 16px 10px 40px;border:1px solid var(--line);border-radius:8px;font-size:14px;background: var(--paper);}
  .search-box input:focus{outline:none;border-color:var(--navy);}
  .search-box .icon{position:absolute;left:14px;top:50%;transform:translateY(-50%);color: var(--ink-soft);}
  .filter-group{display:flex;gap:8px;}
  .filter-btn{padding:8px 14px;background: var(--paper);border:1px solid var(--line);border-radius:6px;font-size:13px;color: var(--ink);cursor:pointer;transition: all 0.15s;}
  .filter-btn:hover{background: #fff;border-color:var(--navy);}
  .filter-btn.active{background: var(--navy);color:#fff;border-color:var(--navy);}

  .content{padding:32px 0 48px;}
  .section-title{font-family:'Fraunces', serif;font-size:22px;font-weight:600;color: var(--navy);margin-bottom:20px;display:flex;align-items:center;gap:10px;}
  .section-title .count{font-family:'IBM Plex Mono', monospace;font-size:13px;color: var(--ink-soft);font-weight:400;}

  .prompt-grid{display:grid;grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));gap:20px;}
  .prompt-card{background: var(--card-bg);border:1px solid var(--line);border-radius:12px;padding:20px;transition: transform 0.18s, box-shadow 0.18s, border-color 0.18s;}
  .prompt-card:hover{transform:translateY(-2px);box-shadow: 0 12px 24px -12px rgba(31,56,100,0.2);border-color: rgba(31,56,100,0.2);}
  .prompt-header{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:12px;}
  .prompt-title{font-weight:600;font-size:16px;color: var(--ink);}
  .prompt-badges{display:flex;gap:6px;}
  .badge{font-family:'IBM Plex Mono', monospace;font-size:10px;padding:3px 8px;border-radius:4px;text-transform:uppercase;letter-spacing:0.03em;}
  .badge.subject{background:#E8F0F8;color:var(--navy);}
  .badge.role{background:#FDF3E0;color:#9A6B1F;}
  .badge.new{background:#E8F5F0;color:var(--sage);}
  .prompt-desc{font-size:14px;color: var(--ink-soft);margin-bottom:16px;line-height:1.5;}
  .prompt-preview{background: var(--paper);border:1px solid var(--line);border-radius:8px;padding:12px;font-family:'IBM Plex Mono', monospace;font-size:12px;color: var(--ink-soft);margin-bottom:16px;max-height:80px;overflow:hidden;position:relative;}
  .prompt-preview::after{content:"";position:absolute;bottom:0;left:0;right:0;height:30px;background: linear-gradient(transparent, var(--paper));}
  .prompt-footer{display:flex;justify-content:space-between;align-items:center;}
  .prompt-author{display:flex;align-items:center;gap:8px;font-size:13px;color: var(--ink-soft);}
  .author-avatar{width:24px;height:24px;border-radius:50%;background: var(--navy);color:#fff;display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:600;}
  .copy-btn{padding:8px 16px;background: var(--navy);color:#fff;border:none;border-radius:6px;font-size:13px;font-weight:500;cursor:pointer;transition: background 0.15s;}
  .copy-btn:hover{background: var(--navy-deep);}

  @media (max-width: 700px){.prompt-grid{grid-template-columns:1fr;}}
</style>
@endpush
