@extends('layouts.ai-plus')

@section('title', 'AI Policy & Guidelines — AI+ LSTS')

@section('breadcrumb', 'AI Policy & Guidelines')

@section('content')
<div class="policy-language-wrap">
  <div class="wrap">
    <div class="policy-language" role="group" aria-label="Language / Ngôn ngữ">
      <button type="button" class="is-active" data-policy-language="en" aria-pressed="true">English</button>
      <button type="button" data-policy-language="vi" aria-pressed="false">Tiếng Việt</button>
    </div>
  </div>
</div>

<header class="page-header">
  <div class="wrap">
    <div data-policy-copy="en" lang="en">
      <h1>AI Policy &amp; Guidelines</h1>
      <p>What's allowed, what isn't, and how to keep student and parent data safe when using AI at LSTS.</p>
      <div class="last-updated">Last updated: {{ $lastUpdated }} • Version {{ $version }}</div>
    </div>
    <div data-policy-copy="vi" lang="vi" hidden>
      <h1>Chính sách và Hướng dẫn sử dụng AI</h1>
      <p>Những nội dung được phép, không được phép và cách bảo vệ dữ liệu học sinh, phụ huynh khi sử dụng AI tại LSTS.</p>
      <div class="last-updated">Cập nhật lần cuối: 19 tháng 8, 2026 • Phiên bản {{ $version }}</div>
    </div>
  </div>
</header>

<nav class="toc-nav">
  <div class="wrap">
    <div class="toc-links" data-policy-copy="en" lang="en">
      <a href="#overview-en" class="toc-link">Overview</a>
      <a href="#data-protection-en" class="toc-link">Data Protection</a>
      <a href="#acceptable-use-en" class="toc-link">Acceptable Use</a>
      <a href="#assessment-en" class="toc-link">Assessment Policy</a>
      <a href="#prohibited-en" class="toc-link">Prohibited Uses</a>
    </div>
    <div class="toc-links" data-policy-copy="vi" lang="vi" hidden>
      <a href="#overview-vi" class="toc-link">Tổng quan</a>
      <a href="#data-protection-vi" class="toc-link">Bảo vệ dữ liệu</a>
      <a href="#acceptable-use-vi" class="toc-link">Sử dụng phù hợp</a>
      <a href="#assessment-vi" class="toc-link">Chính sách đánh giá</a>
      <a href="#prohibited-vi" class="toc-link">Hành vi bị cấm</a>
    </div>
  </div>
</nav>

<main class="content">
  <div class="wrap">
    <div data-policy-copy="en" lang="en">
      <section id="overview-en" class="section">
        <div class="section-header"><div class="section-number">1</div><h2 class="section-title">Overview</h2></div>
        <div class="section-content">
          <p>This policy establishes guidelines for the responsible use of artificial intelligence (AI) tools at Lawrence S. Ting School. All staff members using AI+ are expected to follow these guidelines to ensure ethical, secure, and effective use of AI in educational and administrative contexts.</p>
          <div class="info-card">
            <h4>Key Principles</h4>
            <ul>
              <li><strong>Transparency:</strong> Always disclose when AI has been used to assist with work</li>
              <li><strong>Human Oversight:</strong> AI is a tool to assist, not replace, human judgment</li>
              <li><strong>Data Protection:</strong> Never share student or parent PII with AI systems</li>
              <li><strong>Professional Judgment:</strong> Review and verify all AI-generated content before use</li>
            </ul>
          </div>
        </div>
      </section>

      <section id="data-protection-en" class="section">
        <div class="section-header"><div class="section-number">2</div><h2 class="section-title">Data Protection</h2></div>
        <div class="section-content">
          <div class="alert-box">
            <div class="alert-title">⚠️ Critical</div>
            <div class="alert-content">
              <strong>Never input the following into AI+ or any AI system:</strong>
              <ul>
                <li>Student names, ID numbers, or personal identifiers</li>
                <li>Parent/guardian names, phone numbers, or email addresses</li>
                <li>Grades, assessment scores, or academic records linked to individuals</li>
                <li>Medical information, learning support details, or disciplinary records</li>
                <li>Financial information (tuition, fees, payment details)</li>
              </ul>
            </div>
          </div>
          <p>AI+ includes a built-in PII (Personally Identifiable Information) filter that will attempt to detect and block sensitive data. However, <strong>you are responsible</strong> for ensuring no PII is submitted.</p>
          <div class="info-card">
            <h4>Safe Practices</h4>
            <ul>
              <li>Use generic placeholders: "Student A", "Grade 7 student", "Parent of student"</li>
              <li>Anonymize data before inputting: remove names, IDs, identifying details</li>
              <li>Aggregate data when possible: "5 students scored above 90%" instead of listing names</li>
              <li>Review AI output before sharing with others or storing in official records</li>
            </ul>
          </div>
        </div>
      </section>

      <section id="acceptable-use-en" class="section">
        <div class="section-header"><div class="section-number">3</div><h2 class="section-title">Acceptable Use</h2></div>
        <div class="section-content">
          <p>AI+ is provided to support your work at LSTS. Acceptable uses include:</p>
          <div class="info-card">
            <h4>✅ Recommended Uses</h4>
            <ul>
              <li>Drafting professional emails and communications</li>
              <li>Creating lesson plans, worksheets, and teaching materials</li>
              <li>Generating assessment questions and rubrics</li>
              <li>Summarizing documents and meeting notes</li>
              <li>Brainstorming ideas and problem-solving</li>
              <li>Creating templates for repetitive administrative tasks</li>
              <li>Research and professional development</li>
            </ul>
          </div>
        </div>
      </section>

      <section id="assessment-en" class="section">
        <div class="section-header"><div class="section-number">4</div><h2 class="section-title">AI in Assessment</h2></div>
        <div class="section-content">
          <p>LSTS follows a three-tier approach to AI use in student assessments:</p>
          <table class="tier-table">
            <thead><tr><th>Tier</th><th>AI Usage</th><th>Requirements</th></tr></thead>
            <tbody>
              <tr><td><span class="tier-name">Tier 1</span> <span class="tier-badge red">No AI</span></td><td>AI use is not permitted for this assessment</td><td>All work must be completed independently without AI assistance.</td></tr>
              <tr><td><span class="tier-name">Tier 2</span> <span class="tier-badge yellow">AI-Assisted</span></td><td>Limited AI use permitted with disclosure</td><td>Students may use AI for specific purposes but must disclose usage.</td></tr>
              <tr><td><span class="tier-name">Tier 3</span> <span class="tier-badge green">AI-Integrated</span></td><td>AI use is required or encouraged</td><td>Students use AI as a tool in the learning process.</td></tr>
            </tbody>
          </table>
        </div>
      </section>

      <section id="prohibited-en" class="section">
        <div class="section-header"><div class="section-number">5</div><h2 class="section-title">Prohibited Uses</h2></div>
        <div class="section-content">
          <div class="alert-box error">
            <div class="alert-title">🚫 The following uses are strictly prohibited</div>
            <div class="alert-content"><ul>
              <li>Generating content that violates school policies or codes of conduct</li>
              <li>Creating misleading, false, or deceptive content</li>
              <li>Attempting to bypass security features or access controls</li>
              <li>Sharing your account credentials with others</li>
              <li>Using AI to complete assessments marked as "No AI" (Tier 1)</li>
              <li>Creating deepfakes, manipulated media, or misleading content</li>
            </ul></div>
          </div>
        </div>
      </section>
    </div>

    <div data-policy-copy="vi" lang="vi" hidden>
      <section id="overview-vi" class="section">
        <div class="section-header"><div class="section-number">1</div><h2 class="section-title">Tổng quan</h2></div>
        <div class="section-content">
          <p>Chính sách này đưa ra hướng dẫn về việc sử dụng các công cụ trí tuệ nhân tạo (AI) một cách có trách nhiệm tại Trường Lawrence S. Ting. Nhân viên sử dụng AI+ cần tuân thủ các hướng dẫn này để bảo đảm AI được sử dụng có đạo đức, an toàn và hiệu quả trong công việc giáo dục và hành chính.</p>
          <div class="info-card">
            <h4>Nguyên tắc chính</h4>
            <ul>
              <li><strong>Minh bạch:</strong> Luôn thông báo khi có sử dụng AI để hỗ trợ công việc.</li>
              <li><strong>Giám sát của con người:</strong> AI là công cụ hỗ trợ, không thay thế khả năng phán đoán của con người.</li>
              <li><strong>Bảo vệ dữ liệu:</strong> Không chia sẻ thông tin cá nhân định danh (PII) của học sinh hoặc phụ huynh với hệ thống AI.</li>
              <li><strong>Phán đoán chuyên môn:</strong> Rà soát và xác minh mọi nội dung do AI tạo ra trước khi sử dụng.</li>
            </ul>
          </div>
        </div>
      </section>

      <section id="data-protection-vi" class="section">
        <div class="section-header"><div class="section-number">2</div><h2 class="section-title">Bảo vệ dữ liệu</h2></div>
        <div class="section-content">
          <div class="alert-box">
            <div class="alert-title">⚠️ Quan trọng</div>
            <div class="alert-content">
              <strong>Không nhập các thông tin sau vào AI+ hoặc bất kỳ hệ thống AI nào:</strong>
              <ul>
                <li>Họ tên, mã số học sinh hoặc thông tin định danh cá nhân</li>
                <li>Họ tên, số điện thoại hoặc địa chỉ email của cha mẹ/người giám hộ</li>
                <li>Điểm số, kết quả đánh giá hoặc hồ sơ học tập gắn với cá nhân</li>
                <li>Thông tin y tế, hỗ trợ học tập hoặc hồ sơ kỷ luật</li>
                <li>Thông tin tài chính (học phí, lệ phí, chi tiết thanh toán)</li>
              </ul>
            </div>
          </div>
          <p>AI+ có bộ lọc thông tin cá nhân định danh (PII) tích hợp để cố gắng phát hiện và chặn dữ liệu nhạy cảm. Tuy nhiên, <strong>bạn vẫn chịu trách nhiệm</strong> bảo đảm không gửi thông tin PII vào hệ thống.</p>
          <div class="info-card">
            <h4>Thực hành an toàn</h4>
            <ul>
              <li>Dùng tên thay thế chung: “Học sinh A”, “học sinh lớp 7”, “phụ huynh của học sinh”.</li>
              <li>Ẩn danh dữ liệu trước khi nhập: xóa tên, mã số và các chi tiết có thể nhận diện.</li>
              <li>Khi có thể, hãy tổng hợp dữ liệu: “5 học sinh đạt trên 90%” thay vì liệt kê tên.</li>
              <li>Rà soát nội dung AI trước khi chia sẻ hoặc lưu vào hồ sơ chính thức.</li>
            </ul>
          </div>
        </div>
      </section>

      <section id="acceptable-use-vi" class="section">
        <div class="section-header"><div class="section-number">3</div><h2 class="section-title">Sử dụng phù hợp</h2></div>
        <div class="section-content">
          <p>AI+ được cung cấp để hỗ trợ công việc tại LSTS. Các mục đích sử dụng phù hợp gồm:</p>
          <div class="info-card">
            <h4>✅ Mục đích được khuyến khích</h4>
            <ul>
              <li>Soạn email và nội dung giao tiếp chuyên môn</li>
              <li>Xây dựng kế hoạch bài dạy, phiếu bài tập và tài liệu giảng dạy</li>
              <li>Tạo câu hỏi đánh giá và tiêu chí chấm điểm</li>
              <li>Tóm tắt tài liệu và nội dung cuộc họp</li>
              <li>Phát triển ý tưởng và giải quyết vấn đề</li>
              <li>Tạo biểu mẫu cho các công việc hành chính lặp lại</li>
              <li>Nghiên cứu và phát triển chuyên môn</li>
            </ul>
          </div>
        </div>
      </section>

      <section id="assessment-vi" class="section">
        <div class="section-header"><div class="section-number">4</div><h2 class="section-title">AI trong đánh giá học sinh</h2></div>
        <div class="section-content">
          <p>LSTS áp dụng cách tiếp cận ba cấp độ đối với việc sử dụng AI trong các hoạt động đánh giá học sinh:</p>
          <table class="tier-table">
            <thead><tr><th>Cấp độ</th><th>Cách sử dụng AI</th><th>Yêu cầu</th></tr></thead>
            <tbody>
              <tr><td><span class="tier-name">Cấp độ 1</span> <span class="tier-badge red">Không dùng AI</span></td><td>Không được phép sử dụng AI trong bài đánh giá này.</td><td>Toàn bộ bài làm phải được hoàn thành độc lập, không có AI hỗ trợ.</td></tr>
              <tr><td><span class="tier-name">Cấp độ 2</span> <span class="tier-badge yellow">AI hỗ trợ</span></td><td>Được sử dụng AI ở mức giới hạn và phải công khai việc sử dụng.</td><td>Học sinh có thể dùng AI cho một số mục đích cụ thể nhưng phải thông báo việc sử dụng.</td></tr>
              <tr><td><span class="tier-name">Cấp độ 3</span> <span class="tier-badge green">Tích hợp AI</span></td><td>Việc sử dụng AI được yêu cầu hoặc khuyến khích.</td><td>Học sinh sử dụng AI như một công cụ trong quá trình học tập.</td></tr>
            </tbody>
          </table>
        </div>
      </section>

      <section id="prohibited-vi" class="section">
        <div class="section-header"><div class="section-number">5</div><h2 class="section-title">Hành vi bị cấm</h2></div>
        <div class="section-content">
          <div class="alert-box error">
            <div class="alert-title">🚫 Nghiêm cấm các mục đích sử dụng sau</div>
            <div class="alert-content"><ul>
              <li>Tạo nội dung vi phạm chính sách hoặc quy tắc ứng xử của nhà trường</li>
              <li>Tạo nội dung sai lệch, sai sự thật hoặc có tính lừa dối</li>
              <li>Tìm cách vượt qua tính năng bảo mật hoặc cơ chế kiểm soát truy cập</li>
              <li>Chia sẻ thông tin đăng nhập tài khoản với người khác</li>
              <li>Dùng AI để hoàn thành bài đánh giá được xếp Cấp độ 1 “Không dùng AI”</li>
              <li>Tạo deepfake, nội dung truyền thông bị thao túng hoặc nội dung gây hiểu lầm</li>
            </ul></div>
          </div>
        </div>
      </section>
    </div>
  </div>
</main>
@endsection

@push('scripts')
<script>
(() => {
  const copies = document.querySelectorAll('[data-policy-copy]');
  const languageButtons = document.querySelectorAll('[data-policy-language]');
  const breadcrumb = document.querySelector('.crumb-current');

  languageButtons.forEach(button => {
    button.addEventListener('click', () => {
      const language = button.dataset.policyLanguage;
      copies.forEach(copy => {
        copy.hidden = copy.dataset.policyCopy !== language;
      });
      languageButtons.forEach(languageButton => {
        const isActive = languageButton === button;
        languageButton.classList.toggle('is-active', isActive);
        languageButton.setAttribute('aria-pressed', String(isActive));
      });
      document.documentElement.lang = language;
      document.title = language === 'vi' ? 'Chính sách và Hướng dẫn sử dụng AI — AI+ LSTS' : 'AI Policy & Guidelines — AI+ LSTS';
      if (breadcrumb) {
        breadcrumb.textContent = language === 'vi' ? 'Chính sách và Hướng dẫn AI' : 'AI Policy & Guidelines';
      }
    });
  });
})();
</script>
@endpush

@push('styles')
<style>
  [data-policy-copy][hidden]{display:none!important;}
  .policy-language-wrap{background:var(--body-bg);padding:14px 0 0;}
  .policy-language{display:flex;justify-content:flex-end;gap:6px;}
  .policy-language button{border:1px solid var(--input-border);border-radius:999px;background:var(--surface);color:var(--text-soft);padding:7px 12px;font-family:inherit;font-size:12px;font-weight:600;cursor:pointer;}
  .policy-language button.is-active{background:var(--navy);border-color:var(--navy);color:#fff;}
  .page-header{background: var(--page-header-bg);color: var(--page-header-text);padding: 48px 0 56px;}
  .page-header h1{font-family:'Fraunces', serif;font-weight:600;font-size: clamp(32px, 4vw, 44px);margin-bottom:12px;}
  .page-header p{color: var(--page-header-muted);font-size:16px;max-width:600px;}
  .last-updated{margin-top:16px;font-size:12px;color: var(--text-soft);font-family:'IBM Plex Mono', monospace;}

  .toc-nav{background: var(--surface);border-bottom:1px solid var(--surface-border);padding:16px 0;position:sticky;top:0;z-index:10;}
  .toc-links{display:flex;gap:8px;flex-wrap:wrap;}
  .toc-link{padding:8px 14px;background: var(--input-bg);border:1px solid var(--input-border);border-radius:6px;font-size:13px;color: var(--input-text);text-decoration:none;transition: all 0.15s;}
  .toc-link:hover{background: var(--surface);border-color:var(--navy);color:var(--navy);}

  .content{padding:40px 0 60px;}
  .section{margin-bottom:48px;scroll-margin-top:80px;}
  .section-header{display:flex;align-items:center;gap:12px;margin-bottom:20px;}
  .section-number{width:36px;height:36px;border-radius:8px;background: var(--navy);color:#fff;display:flex;align-items:center;justify-content:center;font-family:'IBM Plex Mono', monospace;font-size:14px;font-weight:600;}
  .section-title{font-family:'Fraunces', serif;font-size:24px;font-weight:600;color: var(--section-title);}
  .section-content{padding-left:48px;}
  .section-content p{margin-bottom:16px;color: var(--text-main);line-height:1.7;}
  .section-content ul{margin-bottom:16px;padding-left:24px;}
  .section-content li{margin-bottom:8px;color: var(--text-main);line-height:1.6;}

  .alert-box{background: var(--chip-bg);border:1px solid var(--chip-border);border-left:4px solid var(--warning);border-radius:8px;padding:16px 20px;margin-bottom:20px;}
  .alert-box.error{background: var(--chip-bg);border-color: var(--chip-border);border-left-color: var(--error);}
  .alert-title{font-weight:600;color: var(--warning);margin-bottom:8px;}
  .alert-box.error .alert-title{color: var(--error);}
  .alert-content{font-size:14px;color: var(--text-main);}
  .alert-content ul{margin-top:8px;}

  .info-card{background: var(--surface);border:1px solid var(--surface-border);border-radius:12px;padding:20px;margin-bottom:20px;}
  .info-card h4{font-size:16px;font-weight:600;color: var(--section-title);margin-bottom:12px;}
  .info-card ul{margin-bottom:0;}

  .tier-table{width:100%;border-collapse:collapse;margin-bottom:20px;background: var(--surface);border-radius:12px;overflow:hidden;}
  .tier-table th{background: var(--navy);color:#fff;text-align:left;padding:14px 16px;font-size:13px;font-weight:500;}
  .tier-table td{padding:14px 16px;border-bottom:1px solid var(--surface-border);font-size:14px;vertical-align:top;}
  .tier-table tr:last-child td{border-bottom:none;}
  .tier-name{font-weight:600;color: var(--section-title);}
  .tier-badge{display:inline-block;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:500;margin-left:8px;}
  .tier-badge.green{background: var(--sage-badge-bg);color:var(--sage-badge-text);}
  .tier-badge.yellow{background: var(--chip-bg);color:var(--warning);}
  .tier-badge.red{background: var(--chip-bg);color:var(--error);}
  @media(max-width:720px){.section-content{padding-left:0;}.tier-table{display:block;overflow-x:auto;}}
</style>
@endpush
