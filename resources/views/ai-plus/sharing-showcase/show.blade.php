@extends('layouts.ai-plus')

@section('title', '{{ $post['title'] }} — Sharing & Showcase — AI+ LSTS')

@section('breadcrumb', 'Sharing & Showcase / {{ $post['title'] }}')

@section('content')
<header class="page-header">
  <div class="wrap">
    <div class="back-link">
      <a href="{{ route('ai-plus.sharing-showcase.index') }}">&larr; Back to Showcase</a>
    </div>
    <h1>{{ $post['title'] }}</h1>
    <div class="post-meta">
      <div class="author-info">
        <div class="author-avatar">{{ $post['authorInitials'] }}</div>
        <div>
          <span class="author-name">{{ $post['author'] }}</span>
          <span class="author-dept">{{ $post['department'] }}</span>
        </div>
      </div>
      <div class="post-stats">
        <span class="stat"><span class="icon">👁</span> {{ $post['views'] }} views</span>
        <span class="stat"><span class="icon">💬</span> {{ $post['comments'] }} comments</span>
        <span class="stat"><span class="icon">⭐</span> {{ $post['uses'] }} uses</span>
        <span class="stat date">{{ $post['created'] }}</span>
      </div>
    </div>
    @if($post['badge'])
    <span class="post-badge">{{ $post['badge'] }}</span>
    @endif
    <div class="post-tags">
      @foreach($post['tags'] as $tag)
      <span class="tag">{{ $tag }}</span>
      @endforeach
    </div>
  </div>
</header>

<main class="content">
  <div class="wrap">
    <div class="post-layout">
      <article class="post-main">
        <div class="post-description">
          <p>{{ nl2br(e($post['description'])) }}</p>
        </div>

        <div class="post-actions">
          <button class="action-btn use-btn" data-post-id="{{ $post['id'] }}">
            <span>⭐</span> Use This Agent
          </button>
          <button class="action-btn share-btn">
            <span>🔗</span> Share
          </button>
          <button class="action-btn save-btn">
            <span>🔖</span> Save
          </button>
        </div>

        <section class="comments-section">
          <h2>Comments ({{ $post['comments'] }})</h2>
          <form class="comment-form" id="commentForm">
            @csrf
            <input type="hidden" name="post_id" value="{{ $post['id'] }}">
            <div class="form-row">
              <div class="comment-avatar">{{ Auth::user()?->initials ?? '?' }}</div>
              <div class="comment-input-wrapper">
                <textarea name="content" placeholder="Add a comment..." required minlength="3" maxlength="2000"></textarea>
                <div class="comment-footer">
                  <span class="char-count"><span id="charCount">0</span>/2000</span>
                  <button type="submit" class="btn-primary comment-submit">Post</button>
                </div>
              </div>
            </div>
          </form>

          <div class="comments-list" id="commentsList">
            <div class="empty-comments">No comments yet. Be the first to share your thoughts!</div>
          </div>
        </section>
      </article>

      <aside class="post-sidebar">
        <div class="sidebar-card related-section">
          <h3>Related Showcases</h3>
          @if($related->isEmpty())
          <p class="no-related">No related showcases found.</p>
          @else
          <ul class="related-list">
            @foreach($related as $item)
            <li>
              <a href="{{ $item['url'] }}" class="related-item">
                <div class="related-avatar">{{ $item['authorInitials'] }}</div>
                <div class="related-info">
                  <h4>{{ $item['title'] }}</h4>
                  <div class="related-meta">
                    <span>{{ $item['author'] }}</span>
                    <span>{{ $item['department'] }}</span>
                  </div>
                </div>
                @if($item['badge'])
                <span class="related-badge">{{ $item['badge'] }}</span>
                @endif
              </a>
            </li>
            @endforeach
          </ul>
          @endif
        </div>

        <div class="sidebar-card stats-card">
          <h3>Quick Stats</h3>
          <ul class="stats-list">
            <li><span class="stat-label">Views</span><span class="stat-value">{{ $post['views'] }}</span></li>
            <li><span class="stat-label">Comments</span><span class="stat-value">{{ $post['comments'] }}</span></li>
            <li><span class="stat-label">Uses</span><span class="stat-value">{{ $post['uses'] }}</span></li>
            <li><span class="stat-label">Shared</span><span class="stat-value">{{ $post['created'] }}</span></li>
          </ul>
        </div>
      </aside>
    </div>
  </div>
</main>
@endsection

@push('styles')
<style>
  .page-header {
    background: linear-gradient(135deg, var(--navy) 0%, #274B7F 100%);
    color: #fff;
    padding: 40px 0 48px;
  }
  .wrap { max-width: 1200px; margin: 0 auto; padding: 0 24px; }
  .back-link {
    margin-bottom: 16px;
  }
  .back-link a {
    color: #C7D3E2;
    text-decoration: none;
    font-size: 14px;
    transition: color 0.15s;
  }
  .back-link a:hover { color: var(--gold); }
  .page-header h1 {
    font-family: 'Fraunces', serif;
    font-weight: 600;
    font-size: clamp(28px, 4vw, 40px);
    margin-bottom: 20px;
    line-height: 1.2;
  }
  .post-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 24px;
    align-items: center;
    margin-bottom: 16px;
  }
  .author-info {
    display: flex;
    align-items: center;
    gap: 12px;
  }
  .author-avatar {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: var(--navy);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 18px;
  }
  .author-name { font-weight: 600; font-size: 16px; display: block; }
  .author-dept { font-size: 13px; color: #CBD6E4; }
  .post-stats {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
  }
  .post-stats .stat {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 13px;
    color: #CBD6E4;
  }
  .post-stats .stat.date {
    color: var(--gold);
    font-family: 'IBM Plex Mono', monospace;
  }
  .post-badge {
    display: inline-block;
    padding: 6px 14px;
    background: #E8F5F0;
    color: var(--sage);
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
    margin-bottom: 12px;
  }
  .post-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
  }
  .tag {
    padding: 5px 12px;
    background: rgba(255,255,255,0.15);
    border-radius: 4px;
    font-size: 12px;
    color: #C7D3E2;
  }

  .content { padding: 32px 0 48px; }
  .post-layout {
    display: grid;
    grid-template-columns: 1fr 320px;
    gap: 32px;
  }
  .post-main {
    background: var(--card-bg);
    border: 1px solid var(--line);
    border-radius: 14px;
    padding: 32px;
  }
  .post-description {
    font-size: 16px;
    line-height: 1.8;
    color: var(--ink);
    margin-bottom: 24px;
    padding-bottom: 24px;
    border-bottom: 1px solid var(--line);
  }
  .post-actions {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 32px;
  }
  .action-btn {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 18px;
    background: var(--paper);
    border: 1px solid var(--line);
    border-radius: 8px;
    font-size: 14px;
    font-weight: 500;
    color: var(--ink);
    cursor: pointer;
    transition: all 0.15s;
  }
  .action-btn:hover {
    background: #fff;
    border-color: var(--navy);
  }
  .action-btn.use-btn {
    background: var(--navy);
    color: #fff;
    border-color: var(--navy);
  }
  .action-btn.use-btn:hover {
    background: var(--navy-deep);
  }

  .comments-section h2 {
    font-family: 'Fraunces', serif;
    font-size: 22px;
    font-weight: 600;
    color: var(--navy);
    margin-bottom: 16px;
  }
  .comment-form {
    background: var(--paper);
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 24px;
  }
  .form-row {
    display: flex;
    gap: 12px;
  }
  .comment-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: var(--navy);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 14px;
    flex-shrink: 0;
  }
  .comment-input-wrapper {
    flex: 1;
    display: flex;
    flex-direction: column;
  }
  .comment-input-wrapper textarea {
    width: 100%;
    min-height: 80px;
    padding: 12px;
    background: var(--card-bg);
    border: 1px solid var(--line);
    border-radius: 8px;
    font-family: inherit;
    font-size: 14px;
    color: var(--ink);
    resize: vertical;
    transition: border-color 0.15s, box-shadow 0.15s;
  }
  .comment-input-wrapper textarea:focus {
    outline: none;
    border-color: var(--navy);
    box-shadow: 0 0 0 3px rgba(31, 56, 100, 0.15);
  }
  .comment-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 10px;
  }
  .char-count {
    font-size: 12px;
    color: var(--ink-soft);
  }
  .comment-submit {
    padding: 8px 18px;
    background: var(--navy);
    color: #fff;
    border: none;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
  }
  .comment-submit:hover { background: var(--navy-deep); }
  .comments-list {
    display: flex;
    flex-direction: column;
    gap: 16px;
  }
  .empty-comments {
    text-align: center;
    color: var(--ink-soft);
    padding: 32px;
    font-size: 14px;
  }
  .comment {
    padding: 16px;
    background: var(--paper);
    border-radius: 10px;
  }
  .comment-header {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 8px;
  }
  .comment-body { font-size: 14px; color: var(--ink); line-height: 1.6; }

  .post-sidebar { display: flex; flex-direction: column; gap: 20px; }
  .sidebar-card {
    background: var(--card-bg);
    border: 1px solid var(--line);
    border-radius: 14px;
    padding: 24px;
  }
  .sidebar-card h3 {
    font-family: 'Fraunces', serif;
    font-size: 18px;
    font-weight: 600;
    color: var(--navy);
    margin-bottom: 16px;
  }
  .related-list { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 12px; }
  .related-item {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    text-decoration: none;
    padding: 8px;
    border-radius: 8px;
    transition: background 0.15s;
  }
  .related-item:hover { background: var(--paper); }
  .related-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: var(--navy);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 14px;
    flex-shrink: 0;
  }
  .related-info { flex: 1; min-width: 0; }
  .related-info h4 { font-size: 14px; font-weight: 600; color: var(--ink); margin: 0 0 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .related-meta { display: flex; flex-wrap: wrap; gap: 12px; font-size: 11px; color: var(--ink-soft); }
  .related-badge {
    padding: 2px 8px;
    background: #E8F5F0;
    color: var(--sage);
    border-radius: 4px;
    font-size: 10px;
    font-weight: 500;
    flex-shrink: 0;
  }
  .no-related { color: var(--ink-soft); font-size: 14px; text-align: center; padding: 16px 0; }

  .stats-list { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 12px; }
  .stats-list li { display: flex; justify-content: space-between; align-items: center; }
  .stat-label { font-size: 14px; color: var(--ink-soft); }
  .stat-value { font-family: 'IBM Plex Mono', monospace; font-size: 20px; font-weight: 600; color: var(--navy); }

  @media (max-width: 900px) {
    .post-layout { grid-template-columns: 1fr; }
    .post-sidebar { flex-direction: row; flex-wrap: wrap; }
    .sidebar-card { flex: 1; min-width: 280px; }
  }
  @media (max-width: 700px) {
    .page-header { padding: 24px 0 32px; }
    .post-main { padding: 20px; }
    .post-actions { flex-direction: column; }
    .action-btn { justify-content: center; }
    .sidebar-card { min-width: 100%; }
  }
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
  // Character counter
  const textarea = document.querySelector('#commentForm textarea');
  const charCount = document.getElementById('charCount');
  if (textarea && charCount) {
    textarea.addEventListener('input', () => {
      charCount.textContent = textarea.value.length;
    });
  }

  // Comment form submit (placeholder - would need backend endpoint)
  const commentForm = document.getElementById('commentForm');
  if (commentForm) {
    commentForm.addEventListener('submit', (e) => {
      e.preventDefault();
      // In production: fetch POST to comment endpoint
      // For now just show placeholder
      alert('Comment submission would go to backend. Connect to your comments API.');
    });
  }

  // Use button (placeholder)
  const useBtn = document.querySelector('.use-btn');
  if (useBtn) {
    useBtn.addEventListener('click', () => {
      alert('This would redirect to Agent Workspace with this agent pre-loaded. Connect to your agent system.');
    });
  }

  // Share button (placeholder)
  const shareBtn = document.querySelector('.share-btn');
  if (shareBtn) {
    shareBtn.addEventListener('click', () => {
      if (navigator.share) {
        navigator.share({
          title: '{{ addslashes($post['title']) }}',
          text: 'Check out this agent on AI+ Sharing & Showcase',
          url: window.location.href,
        });
      } else {
        navigator.clipboard.writeText(window.location.href);
        shareBtn.textContent = 'Copied!';
        setTimeout(() => shareBtn.innerHTML = '<span>🔗</span> Share', 2000);
      }
    });
  }
});
</script>
@endpush