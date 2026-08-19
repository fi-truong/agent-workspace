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
    <div class="filters">
      <button class="filter-btn active">All Templates</button>
      <button class="filter-btn">📚 Teaching</button>
      <button class="filter-btn">📝 Assessment</button>
      <button class="filter-btn">📧 Communication</button>
      <button class="filter-btn">📊 Admin</button>
      <button class="filter-btn">🔬 Research</button>
    </div>
  </div>
</section>

<main class="content">
  <div class="wrap">
    <div class="template-grid">
      @foreach($templates as $template)
      <div class="template-card">
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
            <span class="template-uses">Used {{ $template['uses'] }} times</span>
            <button class="use-btn">Use Template</button>
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
  .page-header{background: var(--navy);color:#fff;padding: 48px 0 56px;}
  .page-header h1{font-family:'Fraunces', serif;font-weight:600;font-size: clamp(32px, 4vw, 44px);margin-bottom:12px;}
  .page-header p{color:#C7D3E2;font-size:16px;max-width:600px;}

  .filters-section{background: var(--card-bg);border-bottom:1px solid var(--line);padding:20px 0;position:sticky;top:0;z-index:10;}
  .filters{display:flex;gap:12px;flex-wrap:wrap;align-items:center;}
  .filter-btn{padding:8px 14px;background: var(--paper);border:1px solid var(--line);border-radius:6px;font-size:13px;color: var(--ink);cursor:pointer;transition: all 0.15s;}
  .filter-btn:hover{background: #fff;border-color:var(--navy);}
  .filter-btn.active{background: var(--navy);color:#fff;border-color:var(--navy);}

  .content{padding:32px 0 48px;}

  .template-grid{display:grid;grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));gap:24px;}
  .template-card{background: var(--card-bg);border:1px solid var(--line);border-radius:14px;overflow:hidden;transition: transform 0.18s, box-shadow 0.18s;}
  .template-card:hover{transform:translateY(-3px);box-shadow: 0 16px 32px -12px rgba(31,56,100,0.25);}
  .template-preview{height:140px;background: linear-gradient(135deg, var(--navy) 0%, #2A4A73 100%);display:flex;align-items:center;justify-content:center;position:relative;}
  .template-preview.math{background: linear-gradient(135deg, #1E4D6B 0%, #2E6D8B 100%);}
  .template-preview.english{background: linear-gradient(135deg, #6B4D1E 0%, #8B6D2E 100%);}
  .template-preview.science{background: linear-gradient(135deg, #1E6B4D 0%, #2E8B6D 100%);}
  .template-preview.admin{background: linear-gradient(135deg, #4D1E6B 0%, #6D2E8B 100%);}
  .template-icon{font-size:48px;opacity:0.9;}
  .template-badge{position:absolute;top:12px;right:12px;background: rgba(255,255,255,0.2);backdrop-filter:blur(8px);padding:4px 10px;border-radius:6px;font-size:11px;font-family:'IBM Plex Mono', monospace;color:#fff;}
  .template-body{padding:20px;}
  .template-title{font-family:'Fraunces', serif;font-size:18px;font-weight:600;color: var(--ink);margin-bottom:8px;}
  .template-desc{font-size:14px;color: var(--ink-soft);margin-bottom:16px;line-height:1.5;}
  .template-features{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:16px;}
  .feature-tag{font-size:11px;padding:4px 8px;background: var(--paper);border-radius:4px;color: var(--ink-soft);}
  .template-footer{display:flex;justify-content:space-between;align-items:center;padding-top:16px;border-top:1px solid var(--line);}
  .template-uses{font-size:12px;color: var(--ink-soft);}
  .use-btn{padding:8px 18px;background: var(--navy);color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:500;cursor:pointer;transition: background 0.15s;}
  .use-btn:hover{background: var(--navy-deep);}
</style>
@endpush
