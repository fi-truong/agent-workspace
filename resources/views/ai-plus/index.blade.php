@extends('layouts.app')

@section('title', 'AI+ - LSTS Staff Portal')

@section('content')

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
