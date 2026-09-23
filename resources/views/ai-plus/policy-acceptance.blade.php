@extends('layouts.ai-plus')

@section('title', 'AI+ Acceptable Use — LSTS')
@section('breadcrumb', 'Acceptable Use')

@section('content')
<main class="policy-acceptance">
  <section class="policy-card">
    <div class="policy-icon">AI+</div>
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
    <form method="POST" action="{{ route('ai-plus.policy-acceptance.accept') }}">
      @csrf
      <label class="accept-check"><input type="checkbox" name="accept" value="1" required> <span>I have read and agree to use AI+ in line with the LSTS AI Policy &amp; Guidelines.</span></label>
      @error('accept')<p class="policy-error">{{ $message }}</p>@enderror
      <button class="accept-button" type="submit">Accept and continue</button>
    </form>
    <p class="policy-link"><a href="{{ route('ai-plus.ai-policy.index') }}">Read the full AI Policy &amp; Guidelines</a> · Version {{ $version }}</p>
  </section>
</main>
@endsection

@push('styles')
<style>
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
