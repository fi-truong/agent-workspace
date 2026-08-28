@extends('layouts.ai-plus')

@section('title', 'AI Policy & Guidelines — AI+ LSTS')

@section('breadcrumb', 'AI Policy & Guidelines')

@section('content')
<header class="page-header">
  <div class="wrap">
    <h1>AI Policy & Guidelines</h1>
    <p>What's allowed, what isn't, and how to keep student and parent data safe when using AI at LSTS.</p>
    <div class="last-updated">Last updated: {{ $lastUpdated }} • Version {{ $version }}</div>
  </div>
</header>

<nav class="toc-nav">
  <div class="wrap">
    <div class="toc-links">
      <a href="#overview" class="toc-link">Overview</a>
      <a href="#data-protection" class="toc-link">Data Protection</a>
      <a href="#acceptable-use" class="toc-link">Acceptable Use</a>
      <a href="#assessment" class="toc-link">Assessment Policy</a>
      <a href="#prohibited" class="toc-link">Prohibited Uses</a>
    </div>
  </div>
</nav>

<main class="content">
  <div class="wrap">
    <section id="overview" class="section">
      <div class="section-header">
        <div class="section-number">1</div>
        <h2 class="section-title">Overview</h2>
      </div>
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

    <section id="data-protection" class="section">
      <div class="section-header">
        <div class="section-number">2</div>
        <h2 class="section-title">Data Protection</h2>
      </div>
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

    <section id="acceptable-use" class="section">
      <div class="section-header">
        <div class="section-number">3</div>
        <h2 class="section-title">Acceptable Use</h2>
      </div>
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

    <section id="assessment" class="section">
      <div class="section-header">
        <div class="section-number">4</div>
        <h2 class="section-title">AI in Assessment</h2>
      </div>
      <div class="section-content">
        <p>LSTS follows a three-tier approach to AI use in student assessments:</p>
        <table class="tier-table">
          <thead>
            <tr>
              <th>Tier</th>
              <th>AI Usage</th>
              <th>Requirements</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td><span class="tier-name">Tier 1</span> <span class="tier-badge red">No AI</span></td>
              <td>AI use is not permitted for this assessment</td>
              <td>All work must be completed independently without AI assistance.</td>
            </tr>
            <tr>
              <td><span class="tier-name">Tier 2</span> <span class="tier-badge yellow">AI-Assisted</span></td>
              <td>Limited AI use permitted with disclosure</td>
              <td>Students may use AI for specific purposes but must disclose usage.</td>
            </tr>
            <tr>
              <td><span class="tier-name">Tier 3</span> <span class="tier-badge green">AI-Integrated</span></td>
              <td>AI use is required or encouraged</td>
              <td>Students use AI as a tool in the learning process.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <section id="prohibited" class="section">
      <div class="section-header">
        <div class="section-number">5</div>
        <h2 class="section-title">Prohibited Uses</h2>
      </div>
      <div class="section-content">
        <div class="alert-box error">
          <div class="alert-title">🚫 The following uses are strictly prohibited</div>
          <div class="alert-content">
            <ul>
              <li>Generating content that violates school policies or codes of conduct</li>
              <li>Creating misleading, false, or deceptive content</li>
              <li>Attempting to bypass security features or access controls</li>
              <li>Sharing your account credentials with others</li>
              <li>Using AI to complete assessments marked as "No AI" (Tier 1)</li>
              <li>Creating deepfakes, manipulated media, or misleading content</li>
            </ul>
          </div>
        </div>
      </div>
    </section>
  </div>
</main>
@endsection

@push('styles')
<style>
  .page-header{background: var(--navy);color:#fff;padding: 48px 0 56px;}
  .page-header h1{font-family:'Fraunces', serif;font-weight:600;font-size: clamp(32px, 4vw, 44px);margin-bottom:12px;}
  .page-header p{color:#C8E1DC;font-size:16px;max-width:600px;}
  .last-updated{margin-top:16px;font-size:12px;color:#7EA69E;font-family:'IBM Plex Mono', monospace;}

  .toc-nav{background: var(--card-bg);border-bottom:1px solid var(--line);padding:16px 0;position:sticky;top:0;z-index:10;}
  .toc-links{display:flex;gap:8px;flex-wrap:wrap;}
  .toc-link{padding:8px 14px;background: var(--paper);border:1px solid var(--line);border-radius:6px;font-size:13px;color: var(--ink);text-decoration:none;transition: all 0.15s;}
  .toc-link:hover{background: #fff;border-color:var(--navy);color:var(--navy);}

  .content{padding:40px 0 60px;}
  .section{margin-bottom:48px;scroll-margin-top:80px;}
  .section-header{display:flex;align-items:center;gap:12px;margin-bottom:20px;}
  .section-number{width:36px;height:36px;border-radius:8px;background: var(--navy);color:#fff;display:flex;align-items:center;justify-content:center;font-family:'IBM Plex Mono', monospace;font-size:14px;font-weight:600;}
  .section-title{font-family:'Fraunces', serif;font-size:24px;font-weight:600;color: var(--navy);}
  .section-content{padding-left:48px;}
  .section-content p{margin-bottom:16px;color: var(--ink);line-height:1.7;}
  .section-content ul{margin-bottom:16px;padding-left:24px;}
  .section-content li{margin-bottom:8px;color: var(--ink);line-height:1.6;}

  .alert-box{background: #FEF3E0;border:1px solid #E8C8A0;border-left:4px solid var(--warning);border-radius:8px;padding:16px 20px;margin-bottom:20px;}
  .alert-box.error{background: #FEE2E2;border-color: #F5A0A0;border-left-color: var(--error);}
  .alert-title{font-weight:600;color: var(--warning);margin-bottom:8px;}
  .alert-box.error .alert-title{color: var(--error);}
  .alert-content{font-size:14px;color: var(--ink);}
  .alert-content ul{margin-top:8px;}

  .info-card{background: var(--card-bg);border:1px solid var(--line);border-radius:12px;padding:20px;margin-bottom:20px;}
  .info-card h4{font-size:16px;font-weight:600;color: var(--navy);margin-bottom:12px;}
  .info-card ul{margin-bottom:0;}

  .tier-table{width:100%;border-collapse:collapse;margin-bottom:20px;background: var(--card-bg);border-radius:12px;overflow:hidden;}
  .tier-table th{background: var(--navy);color:#fff;text-align:left;padding:14px 16px;font-size:13px;font-weight:500;}
  .tier-table td{padding:14px 16px;border-bottom:1px solid var(--line);font-size:14px;vertical-align:top;}
  .tier-table tr:last-child td{border-bottom:none;}
  .tier-name{font-weight:600;color: var(--navy);}
  .tier-badge{display:inline-block;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:500;margin-left:8px;}
  .tier-badge.green{background:#E8F5F0;color:var(--sage);}
  .tier-badge.yellow{background:#FEF3E0;color:var(--warning);}
  .tier-badge.red{background:#FEE2E2;color:var(--error);}
</style>
@endpush
