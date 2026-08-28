@extends('layouts.app')

@section('title', 'AI+ - LSTS Staff Portal')

@section('content')

@push('scripts')
<!-- Theme Switcher (homepage only) -->
<div class="theme-switcher" id="themeSwitcher">
  <span class="theme-switcher-label">Theme</span>
  <button type="button" class="theme-swatch" data-theme-value="mint" title="Mint (school color)" style="background:#B0EDE1;"></button>
  <button type="button" class="theme-swatch" data-theme-value="teal" title="Teal (dark)" style="background:#1F5147;"></button>
</div>

<style>
.theme-switcher{
  position:fixed; bottom:20px; right:20px; z-index:500;
  display:flex; align-items:center; gap:8px;
  background:#fff; border:1px solid var(--line);
  border-radius:999px; padding:8px 14px; box-shadow:0 8px 24px -8px rgba(0,0,0,0.18);
  font-family:'Inter', sans-serif;
}
.theme-switcher-label{ font-size:12px; color:var(--ink-soft); }
.theme-swatch{
  width:22px; height:22px; border-radius:50%; cursor:pointer;
  border:2px solid transparent; padding:0; transition: border-color .15s, transform .15s;
}
.theme-swatch:hover{ transform: scale(1.1); }
.theme-swatch.active{ border-color: var(--ink); }
@media (max-width:640px){
  .theme-switcher{ bottom:12px; right:12px; padding:6px 10px; }
  .theme-switcher-label{ display:none; }
}
</style>

<script>
(function(){
  var current = localStorage.getItem('aiplus-theme') || 'mint';
  function applyActiveState(){
    document.querySelectorAll('.theme-swatch').forEach(function(btn){
      btn.classList.toggle('active', btn.dataset.themeValue === current);
    });
  }
  document.querySelectorAll('.theme-swatch').forEach(function(btn){
    btn.addEventListener('click', function(){
      current = btn.dataset.themeValue;
      localStorage.setItem('aiplus-theme', current);
      if (current === 'teal') {
        document.documentElement.setAttribute('data-theme', 'teal');
      } else {
        document.documentElement.removeAttribute('data-theme');
      }
      applyActiveState();
    });
  });
  applyActiveState();
})();
</script>
@endpush

  <header class="hero">
    <div class="wrap">
      <span class="eyebrow">LSTS · AI+</span>
      <h1 class="title">Everything AI,<br><em>in one door.</em></h1>
      <p class="sub">Build agents, share what works, and find help — all from one place, built for how LSTS teachers and staff actually work.</p>
    </div>
  </header>

  <section id="create">
    <div class="wrap">
      <div class="section-head">
        <h2>Create</h2>
        <span class="tag">01 — BUILD YOUR OWN</span>
      </div>
      <p class="section-desc">Everything you need to build an agent from scratch — or start from something already made.</p>
      <div class="grid cols-3">
        @foreach($createCards as $card)
        <div class="card">
          <div class="icon">{{ $card['icon'] }}</div>
          <h3>{{ $card['title'] }}</h3>
          <p>{{ $card['description'] }}</p>
          <a href="{{ $card['url'] }}" class="cta" style="text-decoration:none;">{{ $card['ctaLabel'] }} <span class="arrow">→</span></a>
        </div>
        @endforeach
      </div>
    </div>
  </section>

  <section id="community">
    <div class="wrap">
      <div class="section-head">
        <h2>Community</h2>
        <span class="tag">02 — LEARN FROM EACH OTHER</span>
      </div>
      <div class="grid cols-2">
        @foreach($communityCards as $card)
        <div class="card">
          <div class="icon">{{ $card['icon'] }}</div>
          <h3>{{ $card['title'] }}</h3>
          <p>{{ $card['description'] }}</p>
          @if(!empty($card['strip']))
          <div class="strip">
            @foreach($card['strip'] as $tag)
              <span>{{ $tag }}</span>
            @endforeach
          </div>
          @endif
          <a href="{{ $card['url'] }}" class="cta" style="text-decoration:none;">{{ $card['ctaLabel'] }} <span class="arrow">→</span></a>
        </div>
        @endforeach
      </div>
    </div>
  </section>

  <section id="guidance">
    <div class="wrap">
      <div class="section-head">
        <h2>Guidance &amp; Account</h2>
        <span class="tag">03 — STAY INFORMED</span>
      </div>
      <div class="grid cols-2">
        @foreach($guidanceCards as $card)
        <div class="card">
          <div class="icon">{{ $card['icon'] }}</div>
          <h3>{{ $card['title'] }}</h3>
          <p>{{ $card['description'] }}</p>
          <a href="{{ $card['url'] }}" class="cta" style="text-decoration:none;">{{ $card['ctaLabel'] }} <span class="arrow">→</span></a>
        </div>
        @endforeach
      </div>
    </div>
  </section>

@endsection
