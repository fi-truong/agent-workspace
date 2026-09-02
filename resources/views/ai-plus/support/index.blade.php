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
    <div class="faq-section" id="faq-section">
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

    <div class="contact-section" id="contact-section">
      <h2 class="section-title">Submit a Request</h2>
      <form id="supportForm" method="POST" action="{{ route('ai-plus.support.store') }}">
        @csrf
        <div class="form-grid">
          <div class="form-group">
            <label class="form-label">Your Name</label>
            <input type="text" name="name" class="form-input" placeholder="Enter your name" required>
          </div>
          <div class="form-group">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-input" placeholder="your.email@lsts.edu.vn" required>
          </div>
          <div class="form-group full">
            <label class="form-label">Request Type</label>
            <select name="type" class="form-select" required>
              <option value="">— Select —</option>
              <option value="Technical Issue / Bug Report">Technical Issue / Bug Report</option>
              <option value="Feature Request">Feature Request</option>
              <option value="Question / How-To">Question / How-To</option>
              <option value="Access / Account Issue">Access / Account Issue</option>
              <option value="Training Request">Training Request</option>
              <option value="Other">Other</option>
            </select>
          </div>
          <div class="form-group full">
            <label class="form-label">Subject</label>
            <input type="text" name="subject" class="form-input" placeholder="Brief description of your request" required>
          </div>
          <div class="form-group full">
            <label class="form-label">Details</label>
            <textarea name="details" class="form-textarea" placeholder="Please describe your issue or request in detail." required minlength="10"></textarea>
          </div>
        </div>
        <div class="form-row">
          <button type="submit" class="submit-btn">Submit Request</button>
          <span class="form-note">We typically respond within 1-2 business days</span>
        </div>
        <div id="formMessage" class="form-message" style="display: none;"></div>
      </form>

      <div class="contact-info">
        <div class="contact-item">
          <div class="contact-icon">📧</div>
          <div>
            <div class="contact-label">Email</div>
            <div class="contact-value">ciec.coordinator.04@lsts.edu.vn</div>
          </div>
        </div>
        <div class="contact-item">
          <div class="contact-icon">🏢</div>
          <div>
            <div class="contact-label">Office</div>
            <div class="contact-value">HSC, Open Office</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</main>
@endsection

@push('styles')
<style>
  .page-header{background: var(--page-header-bg);color: var(--page-header-text);padding: 48px 0 56px;}
  .page-header h1{font-family:'Fraunces', serif;font-weight:600;font-size: clamp(32px, 4vw, 44px);margin-bottom:12px;}
  .page-header p{color: var(--page-header-muted);font-size:16px;max-width:600px;}

  .content{padding:40px 0 60px;}

  .faq-section{margin-bottom:48px;}
  .section-title{font-family:'Fraunces', serif;font-size:24px;font-weight:600;color: var(--section-title);margin-bottom:24px;}
  .faq-list{display:flex;flex-direction:column;gap:12px;}
  .faq-item{background: var(--surface);border:1px solid var(--surface-border);border-radius:12px;overflow:hidden;}
  .faq-question{padding:18px 20px;font-weight:500;font-size:15px;color: var(--text-main);cursor:pointer;display:flex;justify-content:space-between;align-items:center;transition: background 0.15s;}
  .faq-question:hover{background: var(--input-bg);}
  .faq-arrow{color: var(--text-soft);font-size:12px;transition: transform 0.2s;}
  .faq-item.open .faq-arrow{transform: rotate(180deg);}
  .faq-answer{padding:0 20px;max-height:0;overflow:hidden;transition: max-height 0.3s, padding 0.3s;font-size:14px;color: var(--text-soft);line-height:1.7;}
  .faq-item.open .faq-answer{padding:0 20px 20px;max-height:300px;}

  .contact-section{background: var(--surface);border:1px solid var(--surface-border);border-radius:14px;padding:32px;}
  .contact-section .section-title{margin-bottom:20px;}
  .form-grid{display:grid;grid-template-columns: 1fr 1fr;gap:16px;margin-bottom:16px;}
  .form-group{display:flex;flex-direction:column;gap:6px;}
  .form-group.full{grid-column: span 2;}
  .form-label{font-size:13px;font-weight:500;color: var(--text-main);}
  .form-input, .form-select, .form-textarea{padding:12px 14px;border:1px solid var(--input-border);border-radius:8px;font-size:14px;font-family:inherit;background: var(--input-bg);color: var(--input-text);transition: border-color 0.15s, background 0.3s, color 0.3s;}
  .form-input:focus, .form-select:focus, .form-textarea:focus{outline:none;border-color: var(--navy);}
  .form-textarea{min-height:120px;resize:vertical;}
  .form-row{display:flex;gap:12px;align-items:center;margin-top:8px;}
  .submit-btn{padding:12px 24px;background: var(--navy);color:#fff;border:none;border-radius:8px;font-size:14px;font-weight:500;cursor:pointer;transition: background 0.15s;}
  .submit-btn:hover{background: var(--navy-deep);}
  .form-note{font-size:12px;color: var(--text-soft);}

  .form-message{padding:12px 16px;border-radius:8px;font-size:13px;font-weight:500;margin-top:12px;display:none;}
  .form-message.success{background:var(--sage-badge-bg);color:var(--sage-badge-text);border:1px solid var(--sage);}
  .form-message.error{background:#FEF0F0;color:#C0392B;border:1px solid #E74C3C;}

  .contact-info{margin-top:32px;padding-top:24px;border-top:1px solid var(--surface-border);display:flex;gap:32px;flex-wrap:wrap;}
  .contact-item{display:flex;align-items:center;gap:12px;}
  .contact-icon{width:40px;height:40px;border-radius:8px;background: var(--input-bg);display:flex;align-items:center;justify-content:center;font-size:18px;}
  .contact-label{font-size:12px;color: var(--text-soft);margin-bottom:2px;}
  .contact-value{font-size:14px;font-weight:500;color: var(--text-main);}

  @media (max-width: 700px){
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

  // Form submission via AJAX
  const supportForm = document.getElementById('supportForm');
  const formMessage = document.getElementById('formMessage');
  const submitBtn = supportForm?.querySelector('.submit-btn');

  supportForm?.addEventListener('submit', async (e) => {
    e.preventDefault();

    if (!supportForm.checkValidity()) {
      supportForm.reportValidity();
      return;
    }

    submitBtn.disabled = true;
    submitBtn.textContent = 'Submitting...';
    formMessage.style.display = 'none';
    formMessage.className = 'form-message';

    const formData = new FormData(supportForm);

    try {
      const response = await fetch('{{ route('ai-plus.support.store') }}', {
        method: 'POST',
        headers: {
          'Accept': 'application/json',
          'X-CSRF-TOKEN': '{{ csrf_token() }}',
        },
        body: formData,
      });

      const data = await response.json();

      if (response.ok && data.success) {
        formMessage.textContent = data.message;
        formMessage.className = 'form-message success';
        formMessage.style.display = 'block';
        supportForm.reset();
      } else {
        const msg = data.message || 'Something went wrong. Please try again.';
        formMessage.textContent = msg;
        formMessage.className = 'form-message error';
        formMessage.style.display = 'block';
      }
    } catch (err) {
      console.error(err);
      formMessage.textContent = 'Network error. Please check your connection and try again.';
      formMessage.className = 'form-message error';
      formMessage.style.display = 'block';
    } finally {
      submitBtn.disabled = false;
      submitBtn.textContent = 'Submit Request';
    }
  });
</script>
@endpush
