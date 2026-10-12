@extends('layouts.ai-plus')

@section('title', 'AI+ Acceptable Use — LSTS')
@section('breadcrumb', 'Acceptable Use')

@section('content')
<main class="policy-acceptance">
  <section class="policy-card" data-policy-page>
    <div class="policy-language" role="group" aria-label="Language / Ngôn ngữ">
      <button type="button" class="is-active" data-policy-language="en" aria-pressed="true">English</button>
      <button type="button" data-policy-language="vi" aria-pressed="false">Tiếng Việt</button>
    </div>
    <div class="policy-icon">AI+</div>
    <div data-policy-copy="en" lang="en">
      <p class="eyebrow">LSTS WORKPLACE ASSISTANT</p>
      <h1>Use AI+ responsibly</h1>
      <p class="lead">AI+ is provided for LSTS work only. Please confirm that you understand how it may be used before continuing.</p>
      <div class="policy-summary">
        <h2>AI+ may be used for</h2>
        <ul>
          <li>Teaching, learning materials, and student support</li>
          <li>Administration, reporting, professional communication, and school operations</li>
          <li>School-related research and approved professional work</li>
        </ul>
        <h2>Before you use AI+</h2>
        <ul>
          <li>Do not submit student, parent, financial, medical, or other sensitive personal information.</li>
          <li>Review AI-generated work before using or sharing it.</li>
          <li>Do not use AI+ for personal, commercial, unlawful, or unrelated activities.</li>
        </ul>
      </div>
    </div>
    <div data-policy-copy="vi" lang="vi" hidden>
      <p class="eyebrow">TRỢ LÝ CÔNG VIỆC LSTS</p>
      <h1>Sử dụng AI+ có trách nhiệm</h1>
      <p class="lead">AI+ chỉ phục vụ công việc của LSTS. Vui lòng xác nhận bạn hiểu các phạm vi sử dụng trước khi tiếp tục.</p>
      <div class="policy-summary">
        <h2>AI+ có thể được sử dụng cho</h2>
        <ul>
          <li>Giảng dạy, xây dựng tài liệu học tập và hỗ trợ học sinh</li>
          <li>Công việc hành chính, báo cáo, giao tiếp chuyên môn và vận hành nhà trường</li>
          <li>Nghiên cứu liên quan đến nhà trường và các công việc chuyên môn được phê duyệt</li>
        </ul>
        <h2>Trước khi sử dụng AI+</h2>
        <ul>
          <li>Không nhập thông tin nhạy cảm hoặc thông tin cá nhân của học sinh, phụ huynh; thông tin tài chính, y tế hoặc dữ liệu tương tự.</li>
          <li>Kiểm tra nội dung do AI tạo ra trước khi sử dụng hoặc chia sẻ.</li>
          <li>Không sử dụng AI+ cho mục đích cá nhân, thương mại, trái pháp luật hoặc không liên quan đến công việc của trường.</li>
        </ul>
      </div>
    </div>
    <form method="POST" action="{{ route('ai-plus.policy-acceptance.accept') }}">
      @csrf
      <label class="accept-check"><input type="checkbox" name="accept" value="1" required> <span data-policy-label data-en="I have read and agree to use AI+ in line with the LSTS AI Policy &amp; Guidelines." data-vi="Tôi đã đọc và đồng ý sử dụng AI+ phù hợp với Chính sách và Hướng dẫn AI của LSTS.">I have read and agree to use AI+ in line with the LSTS AI Policy &amp; Guidelines.</span></label>
      @error('accept')<p class="policy-error" data-policy-error data-en="You must accept the AI+ policy to continue." data-vi="Bạn cần đồng ý với chính sách AI+ để tiếp tục.">{{ $message }}</p>@enderror
      <button class="accept-button" type="submit" data-policy-label data-en="Accept and continue" data-vi="Đồng ý và tiếp tục">Accept and continue</button>
    </form>
    <p class="policy-link"><a href="{{ route('ai-plus.ai-policy.index') }}" data-policy-label data-en="Read the full AI Policy &amp; Guidelines" data-vi="Đọc toàn bộ Chính sách và Hướng dẫn AI">Read the full AI Policy &amp; Guidelines</a> · <span data-policy-label data-en="Version" data-vi="Phiên bản">Version</span> {{ $version }}</p>
  </section>
</main>
@endsection

@push('scripts')
<script>
(() => {
  const page = document.querySelector('[data-policy-page]');
  if (!page) return;

  const languageButtons = page.querySelectorAll('[data-policy-language]');
  const copyBlocks = page.querySelectorAll('[data-policy-copy]');
  const labels = page.querySelectorAll('[data-policy-label]');
  const errorMessage = page.querySelector('[data-policy-error]');
  const breadcrumb = document.querySelector('.crumb-current');

  languageButtons.forEach(button => {
    button.addEventListener('click', () => {
      const language = button.dataset.policyLanguage;
      copyBlocks.forEach(block => {
        block.hidden = block.dataset.policyCopy !== language;
      });
      labels.forEach(label => {
        label.textContent = label.dataset[language];
      });
      if (errorMessage) {
        errorMessage.textContent = errorMessage.dataset[language];
      }
      languageButtons.forEach(languageButton => {
        const isActive = languageButton === button;
        languageButton.classList.toggle('is-active', isActive);
        languageButton.setAttribute('aria-pressed', String(isActive));
      });
      document.documentElement.lang = language;
      document.title = language === 'vi' ? 'AI+ — Xác nhận chính sách sử dụng · LSTS' : 'AI+ Acceptable Use — LSTS';
      if (breadcrumb) {
        breadcrumb.textContent = language === 'vi' ? 'Xác nhận chính sách' : 'Acceptable Use';
      }
    });
  });
})();
</script>
@endpush

@push('styles')
<style>
  [data-policy-page] [hidden]{display:none!important;}
  .policy-language{display:flex;justify-content:flex-end;gap:6px;margin:-14px -14px 18px;}
  .policy-language button{border:1px solid var(--input-border);border-radius:999px;background:transparent;color:var(--text-soft);padding:7px 12px;font-family:inherit;font-size:12px;font-weight:600;cursor:pointer;}
  .policy-language button.is-active{background:var(--navy);border-color:var(--navy);color:#fff;}
  .policy-acceptance{min-height:calc(100vh - 150px);display:grid;place-items:center;padding:48px 24px;background:var(--body-bg);}
  .policy-card{width:min(100%,680px);background:var(--surface);border:1px solid var(--surface-border);border-radius:20px;padding:42px;box-shadow:0 20px 50px rgba(20,41,65,.08);}
  .policy-icon{width:58px;height:58px;display:grid;place-items:center;border-radius:16px;background:var(--navy);color:var(--gold-light);font:600 22px 'Fraunces',serif;margin-bottom:22px;}
  .eyebrow{font:600 11px 'IBM Plex Mono',monospace;letter-spacing:.12em;color:var(--sage-dark);margin:0 0 10px;}
  .policy-card h1{font:600 clamp(30px,5vw,42px) 'Fraunces',serif;color:var(--section-title);margin:0 0 12px;}.lead{line-height:1.7;color:var(--text-soft);margin:0 0 28px;}
  .policy-summary{padding:20px 22px;border-radius:12px;background:var(--input-bg);border:1px solid var(--input-border);}.policy-summary h2{font:600 15px 'Fraunces',serif;color:var(--section-title);margin:0 0 9px;}.policy-summary h2:not(:first-child){margin-top:20px;}.policy-summary ul{margin:0;padding-left:20px;color:var(--text-main);font-size:14px;line-height:1.65;}
  .accept-check{display:flex;gap:11px;align-items:flex-start;margin:26px 0 16px;color:var(--text-main);font-size:14px;line-height:1.55;cursor:pointer;}.accept-check input{width:18px;height:18px;margin-top:2px;accent-color:var(--navy);}.accept-button{width:100%;border:0;border-radius:10px;background:var(--navy);padding:13px 18px;color:#fff;font-weight:600;font-size:15px;cursor:pointer;}.accept-button:hover{background:var(--navy-light);}.policy-link{text-align:center;margin:18px 0 0;font-size:13px;color:var(--text-soft);}.policy-link a{color:var(--navy);font-weight:600;}.policy-error{color:var(--error);font-size:13px;margin:-8px 0 12px;}
  @media(max-width:560px){.policy-acceptance{padding:24px 14px;}.policy-card{padding:28px 22px;}}
</style>
@endpush
