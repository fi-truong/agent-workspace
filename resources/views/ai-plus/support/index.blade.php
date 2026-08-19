@extends('layouts.ai-plus')

@section('title', 'Support — AI+ LSTS')

@section('breadcrumb', 'Support')

@section('content')
<header class="page-header">
  <div class="wrap">
    <h1>Support</h1>
    <p>Something not working, or not sure where to start? Report an issue, ask a question, or request a new feature.</p>
  </div>
</header>

<main class="content">
  <div class="wrap">
    <div class="quick-help">
      <a href="#" class="help-card">
        <div class="help-icon">📖</div>
        <h3 class="help-title">Getting Started</h3>
        <p class="help-desc">Learn the basics of using AI+ effectively</p>
      </a>
      <a href="#" class="help-card">
        <div class="help-icon">🔧</div>
        <h3 class="help-title">Troubleshooting</h3>
        <p class="help-desc">Solve common issues quickly</p>
      </a>
      <a href="#" class="help-card">
        <div class="help-icon">💡</div>
        <h3 class="help-title">Request Feature</h3>
        <p class="help-desc">Suggest improvements or new features</p>
      </a>
    </div>

    <div class="faq-section">
      <h2 class="section-title">Frequently Asked Questions</h2>
      <div class="faq-list">
        @foreach($faqs as $index => $faq)
        <div class="faq-item {{ $loop->first ? 'open' : '' }}">
          <div class="faq-question">
            <span>{{ $faq['question'] }}</span>
            <span class="faq-arrow">▼</span>
          </div>
          <div class="faq-answer">
            <p>{{ $faq['answer'] }}</p>
          </div>
        </div>
        @endforeach
      </div>
    </div>

    <div class="contact-section">
      <h2 class="section-title">Submit a Request</h2>
      <form>
        <div class="form-grid">
          <div class="form-group">
            <label class="form-label">Your Name</label>
            <input type="text" class="form-input" placeholder="Enter your name">
          </div>
          <div class="form-group">
            <label class="form-label">Email</label>
            <input type="email" class="form-input" placeholder="your.email@lsts.edu.vn">
          </div>
          <div class="form-group full">
            <label class="form-label">Request Type</label>
            <select class="form-select">
              <option>— Select —</option>
              <option>Technical Issue / Bug Report</option>
              <option>Feature Request</option>
              <option>Question / How-To</option>
              <option>Access / Account Issue</option>
              <option>Training Request</option>
              <option>Other</option>
            </select>
          </div>
          <div class="form-group full">
            <label class="form-label">Subject</label>
            <input type="text" class="form-input" placeholder="Brief description of your request">
          </div>
          <div class="form-group full">
            <label class="form-label">Details</label>
            <textarea class="form-textarea" placeholder="Please describe your issue or request in detail."></textarea>
          </div>
        </div>
        <div class="form-row">
          <button type="submit" class="submit-btn">Submit Request</button>
          <span class="form-note">We typically respond within 1-2 business days</span>
        </div>
      </form>

      <div class="contact-info">
        <div class="contact-item">
          <div class="contact-icon">📧</div>
          <div>
            <div class="contact-label">Email</div>
            <div class="contact-value">ciec@lsts.edu.vn</div>
          </div>
        </div>
        <div class="contact-item">
          <div class="contact-icon">🏢</div>
          <div>
            <div class="contact-label">Office</div>
            <div class="contact-value">CIEC Room, Building A</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</main>
@endsection

@push('styles')
<style>
  .page-header{background: var(--navy);color:#fff;padding: 48px 0 56px;}
  .page-header h1{font-family:'Fraunces', serif;font-weight:600;font-size: clamp(32px, 4vw, 44px);margin-bottom:12px;}
  .page-header p{color:#C7D3E2;font-size:16px;max-width:600px;}

  .content{padding:40px 0 60px;}

  .quick-help{display:grid;grid-template-columns: repeat(3, 1fr);gap:20px;margin-bottom:48px;}
  .help-card{background: var(--card-bg);border:1px solid var(--line);border-radius:12px;padding:24px;text-align:center;transition: transform 0.18s, box-shadow 0.18s;cursor:pointer;text-decoration:none;color: inherit;}
  .help-card:hover{transform:translateY(-3px);box-shadow: 0 12px 24px -12px rgba(31,56,100,0.2);}
  .help-icon{width:56px;height:56px;margin:0 auto 16px;border-radius:12px;background: var(--paper);display:flex;align-items:center;justify-content:center;font-size:28px;}
  .help-card:nth-child(1) .help-icon{background: #E8F0F8;}
  .help-card:nth-child(2) .help-icon{background: #FEF3E0;}
  .help-card:nth-child(3) .help-icon{background: #E8F5F0;}
  .help-title{font-weight:600;font-size:16px;color: var(--navy);margin-bottom:8px;}
  .help-desc{font-size:13px;color: var(--ink-soft);}

  .faq-section{margin-bottom:48px;}
  .section-title{font-family:'Fraunces', serif;font-size:24px;font-weight:600;color: var(--navy);margin-bottom:24px;}
  .faq-list{display:flex;flex-direction:column;gap:12px;}
  .faq-item{background: var(--card-bg);border:1px solid var(--line);border-radius:12px;overflow:hidden;}
  .faq-question{padding:18px 20px;font-weight:500;font-size:15px;color: var(--ink);cursor:pointer;display:flex;justify-content:space-between;align-items:center;transition: background 0.15s;}
  .faq-question:hover{background: var(--paper);}
  .faq-arrow{color: var(--ink-soft);font-size:12px;transition: transform 0.2s;}
  .faq-item.open .faq-arrow{transform: rotate(180deg);}
  .faq-answer{padding:0 20px;max-height:0;overflow:hidden;transition: max-height 0.3s, padding 0.3s;font-size:14px;color: var(--ink-soft);line-height:1.7;}
  .faq-item.open .faq-answer{padding:0 20px 20px;max-height:300px;}

  .contact-section{background: var(--card-bg);border:1px solid var(--line);border-radius:14px;padding:32px;}
  .contact-section .section-title{margin-bottom:20px;}
  .form-grid{display:grid;grid-template-columns: 1fr 1fr;gap:16px;margin-bottom:16px;}
  .form-group{display:flex;flex-direction:column;gap:6px;}
  .form-group.full{grid-column: span 2;}
  .form-label{font-size:13px;font-weight:500;color: var(--ink);}
  .form-input, .form-select, .form-textarea{padding:12px 14px;border:1px solid var(--line);border-radius:8px;font-size:14px;font-family:inherit;background: var(--paper);transition: border-color 0.15s;}
  .form-input:focus, .form-select:focus, .form-textarea:focus{outline:none;border-color: var(--navy);}
  .form-textarea{min-height:120px;resize:vertical;}
  .form-row{display:flex;gap:12px;align-items:center;margin-top:8px;}
  .submit-btn{padding:12px 24px;background: var(--navy);color:#fff;border:none;border-radius:8px;font-size:14px;font-weight:500;cursor:pointer;transition: background 0.15s;}
  .submit-btn:hover{background: var(--navy-deep);}
  .form-note{font-size:12px;color: var(--ink-soft);}

  .contact-info{margin-top:32px;padding-top:24px;border-top:1px solid var(--line);display:flex;gap:32px;flex-wrap:wrap;}
  .contact-item{display:flex;align-items:center;gap:12px;}
  .contact-icon{width:40px;height:40px;border-radius:8px;background: var(--paper);display:flex;align-items:center;justify-content:center;font-size:18px;}
  .contact-label{font-size:12px;color: var(--ink-soft);margin-bottom:2px;}
  .contact-value{font-size:14px;font-weight:500;color: var(--ink);}

  @media (max-width: 700px){
    .quick-help{grid-template-columns:1fr;}
    .form-grid{grid-template-columns:1fr;}
    .form-group.full{grid-column: span 1;}
  }
</style>
@endpush

@push('scripts')
<script>
  document.querySelectorAll('.faq-question').forEach(q => {
    q.addEventListener('click', () => {
      const item = q.parentElement;
      item.classList.toggle('open');
    });
  });
</script>
@endpush
