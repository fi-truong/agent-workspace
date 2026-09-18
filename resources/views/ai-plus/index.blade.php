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
        <h2>Guidance & Account</h2>
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

  @if($guideEnabled)
  <aside class="ai-guide" aria-label="AI Plus Guide">
    <section class="ai-guide-panel" id="aiGuidePanel" hidden>
      <div class="ai-guide-head">
        <div class="ai-guide-title">
          <img src="{{ asset('images/ai-plus-guide-bot.png') }}" alt="" class="ai-guide-avatar">
          <div>
          <strong>AI Plus Guide</strong>
          <span>Find the right place to start</span>
          </div>
        </div>
        <button type="button" class="ai-guide-close" id="aiGuideClose" aria-label="Close AI Plus Guide">×</button>
      </div>
      <div class="ai-guide-messages" id="aiGuideMessages" aria-live="polite">
        <div class="ai-guide-message assistant">Hi! I can help you find the right place in AI Plus. What would you like to do?</div>
      </div>
      @auth
      <form class="ai-guide-form" id="aiGuideForm">
        <label class="sr-only" for="aiGuideInput">Ask AI Plus Guide</label>
        <textarea id="aiGuideInput" rows="2" maxlength="800" placeholder="For example: I want to create an agent…" required></textarea>
        <button type="submit" id="aiGuideSend">Send</button>
      </form>
      @else
      <a class="ai-guide-login" href="{{ route('login.local.form') }}">Sign in to ask the guide →</a>
      @endauth
    </section>
    <button type="button" class="ai-guide-trigger" id="aiGuideTrigger" aria-label="Ask AI Plus Guide" aria-expanded="false" aria-controls="aiGuidePanel">
      <img src="{{ asset('images/ai-plus-guide-bot.png') }}" alt="">
    </button>
  </aside>
  @endif

@endsection

@push('styles')
<style>
  .ai-guide{position:fixed;right:20px;bottom:76px;z-index:80;font-family:inherit;}
  .ai-guide-trigger{width:78px;height:78px;border:0;border-radius:50%;background:#f4fbf9;padding:3px;box-shadow:0 12px 30px rgba(22,59,52,.28);cursor:pointer;overflow:hidden;transition:transform .16s,box-shadow .16s;}
  .ai-guide-trigger:hover{transform:translateY(-3px) scale(1.04);box-shadow:0 16px 34px rgba(22,59,52,.34);}.ai-guide-trigger img{width:100%;height:100%;object-fit:contain;display:block;}
  .ai-guide-panel{width:min(360px,calc(100vw - 32px));margin-bottom:10px;background:#fff;border:1px solid #d9e8e3;border-radius:16px;box-shadow:0 18px 50px rgba(22,59,52,.22);overflow:hidden;}
  .ai-guide-head{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;padding:15px 16px;background:#163b34;color:#fff;}
  .ai-guide-title{display:flex;align-items:center;gap:9px;}.ai-guide-avatar{width:35px;height:35px;object-fit:contain;}.ai-guide-head strong,.ai-guide-head span{display:block;}.ai-guide-head span{font-size:12px;color:#cce3de;margin-top:2px;}
  .ai-guide-close{border:0;background:transparent;color:#fff;font-size:25px;line-height:1;cursor:pointer;}
  .ai-guide-messages{max-height:310px;overflow-y:auto;padding:14px;background:#f4fbf9;display:flex;flex-direction:column;gap:10px;}
  .ai-guide-message{max-width:90%;padding:10px 12px;border-radius:12px;font-size:13px;line-height:1.45;white-space:pre-wrap;}
  .ai-guide-message.assistant{align-self:flex-start;background:#fff;color:#1b2e2a;border:1px solid #d9e8e3;}.ai-guide-message.user{align-self:flex-end;background:#1f5147;color:#fff;}.ai-guide-message.error{color:#9d2e23;}
  .ai-guide-form{display:flex;gap:8px;padding:12px;border-top:1px solid #d9e8e3;}.ai-guide-form textarea{flex:1;resize:none;border:1px solid #c6d9d3;border-radius:9px;padding:8px;font:13px inherit;}.ai-guide-form button{border:0;border-radius:9px;background:#d89b34;color:#1b2e2a;padding:0 12px;font:600 13px inherit;cursor:pointer;}.ai-guide-form button:disabled{opacity:.6;cursor:wait;}
  .ai-guide-login{display:block;padding:15px 16px;color:#1f5147;font-size:13px;font-weight:600;text-decoration:none;}.sr-only{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0;}
  @media (max-width:640px){.ai-guide{right:12px;bottom:60px;}.ai-guide-panel{width:calc(100vw - 24px);}}
</style>
@endpush

@if($guideEnabled)
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
  const panel = document.getElementById('aiGuidePanel');
  const trigger = document.getElementById('aiGuideTrigger');
  const close = document.getElementById('aiGuideClose');
  const form = document.getElementById('aiGuideForm');
  const input = document.getElementById('aiGuideInput');
  const messages = document.getElementById('aiGuideMessages');
  const history = [];

  const setOpen = (open) => {
    panel.hidden = !open;
    trigger.setAttribute('aria-expanded', String(open));
    if (open && input) input.focus();
  };
  trigger.addEventListener('click', () => setOpen(panel.hidden));
  if (close) close.addEventListener('click', () => setOpen(false));

  if (!form) return;
  const append = (role, text, extraClass = '') => {
    const node = document.createElement('div');
    node.className = `ai-guide-message ${role} ${extraClass}`;
    node.textContent = text;
    messages.appendChild(node);
    messages.scrollTop = messages.scrollHeight;
    return node;
  };

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    const message = input.value.trim();
    if (!message) return;

    append('user', message);
    input.value = '';
    const send = document.getElementById('aiGuideSend');
    send.disabled = true;
    const pending = append('assistant', 'Đang tìm hướng dẫn phù hợp…');

    try {
      const response = await fetch('{{ route('ai-plus.guide.reply') }}', {
        method: 'POST',
        headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content},
        body: JSON.stringify({message, history}),
      });
      const payload = await response.json();
      if (!response.ok) throw new Error(payload.error || 'Không thể nhận phản hồi. Vui lòng thử lại.');
      pending.textContent = payload.reply;
      history.push({role: 'user', content: message}, {role: 'assistant', content: payload.reply});
      if (history.length > 8) history.splice(0, history.length - 8);
    } catch (error) {
      pending.textContent = error.message || 'Có lỗi xảy ra. Vui lòng thử lại.';
      pending.classList.add('error');
    } finally {
      send.disabled = false;
      input.focus();
    }
  });
});
</script>
@endpush
@endif
