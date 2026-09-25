@extends('layouts.ai-plus')

@section('title', '{{ $agent->title }} — Agent Workspace')

@section('breadcrumb', $agent->title)

@section('content')
<div class="agent-detail-page">
  <div class="page-header">
    <a href="{{ route('ai-plus.agent-workspace.agents.index') }}" class="back-link">← Back to Agents</a>
    <div>
      <h1 class="page-title agent-detail-title">@if($agent->avatar_url)<img src="{{ $agent->avatar_url }}" alt="">@else <span>🤖</span> @endif {{ $agent->title }}</h1>
      @if($agent->description)
      <p class="page-desc">{{ $agent->description }}</p>
      @endif
    </div>
    <div class="page-actions">
      <span class="badge {{ $agent->is_shared ? 'shared' : 'private' }}">
        {{ $agent->is_shared ? 'Shared' : 'Private' }}
      </span>
      <button class="btn btn-primary use-agent-btn" data-agent-id="{{ $agent->id }}">
        Use in Chat →
      </button>
      <button class="btn btn-secondary edit-agent-btn" data-agent-id="{{ $agent->id }}">
        Edit
      </button>
      <button class="btn btn-danger delete-agent-btn" data-agent-id="{{ $agent->id }}">
        Delete
      </button>
    </div>
  </div>

  <div class="agent-detail-grid">
    <section class="agent-section">
      <h2>System Prompt</h2>
      @if($agent->system_prompt)
      <pre class="system-prompt">{{ $agent->system_prompt }}</pre>
      @else
      <p class="no-prompt">No custom system prompt set. Will use default behavior.</p>
      @endif

      <h2 style="margin-top:28px;">Knowledge</h2>
      @if($agent->knowledge_files)
        <ul class="knowledge-files">
          @foreach($agent->knowledge_files as $file)
          <li>{{ $file['original_name'] ?? $file['path'] }}</li>
          @endforeach
        </ul>
      @else
        <p class="no-prompt">No knowledge files uploaded.</p>
      @endif
    </section>

    <aside class="agent-sidebar">
      <div class="info-card">
        <h3>Details</h3>
        <dl>
          <dt>Owner</dt>
          <dd>{{ $agent->user->name }}</dd>
          <dt>Created</dt>
          <dd>{{ $agent->created_at->format('d/m/Y H:i') }}</dd>
          <dt>Last Updated</dt>
          <dd>{{ $agent->updated_at->format('d/m/Y H:i') }}</dd>
        </dl>
      </div>

      <div class="info-card">
        <h3>Usage</h3>
        <p class="usage-hint">Conversation history will appear here when you use this agent in Chat.</p>
      </div>
    </aside>
  </div>
</div>

@push('styles')
<style>
.agent-detail-page { max-width: 1080px; margin: 0 auto; padding: 24px 32px 48px; }
.page-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 24px; margin-bottom: 32px; flex-wrap: wrap; }
.agent-detail-title{display:flex;align-items:center;gap:10px}.agent-detail-title img,.agent-detail-title span{width:42px;height:42px;object-fit:cover;border-radius:11px;background:var(--navy);display:inline-flex;align-items:center;justify-content:center;font-size:22px;}
.back-link { color: var(--navy); font-family: 'IBM Plex Mono', monospace; font-size: 13px; text-decoration: none; align-self: flex-start; margin-top: 4px; }
.back-link:hover { text-decoration: underline; }
.page-title { font-family: 'Fraunces', serif; font-weight: 600; font-size: clamp(28px, 3.5vw, 36px); color: var(--navy); margin: 0 0 6px; }
.page-desc { color: var(--ink-soft); margin: 0; }
.page-actions { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.badge { font-family: 'IBM Plex Mono', monospace; font-size: 11px; padding: 4px 10px; border-radius: 999px; font-weight: 500; }
.badge.shared { background: var(--sage); color: #fff; }
.badge.private { background: var(--paper); color: var(--ink-soft); border: 1px solid var(--line); }
.btn { padding: 10px 20px; border-radius: 8px; font-size: 14px; font-weight: 500; cursor: pointer; border: none; transition: background .15s, border-color .15s; display: inline-flex; align-items: center; gap: 6px; }
.btn-primary { background: var(--navy); color: #fff; }
.btn-primary:hover { background: var(--navy-deep); }
.btn-secondary { background: var(--paper); color: var(--ink); border: 1px solid var(--line); }
.btn-secondary:hover { background: var(--line); }
.btn-danger { background: #dc3545; color: #fff; }
.btn-danger:hover { background: #c82333; }

.agent-detail-grid { display: grid; grid-template-columns: 1fr 320px; gap: 32px; }
.agent-section h2 { font-family: 'Fraunces', serif; font-weight: 600; font-size: 20px; color: var(--navy); margin-bottom: 16px; padding-bottom: 8px; border-bottom: 1px solid var(--line); }
.system-prompt { background: var(--navy-deep); color: #CCE3DE; padding: 20px; border-radius: 12px; font-family: 'IBM Plex Mono', monospace; font-size: 13px; line-height: 1.6; white-space: pre-wrap; overflow-x: auto; }
.no-prompt { color: var(--ink-soft); font-style: italic; background: var(--paper); padding: 20px; border-radius: 12px; border: 1px dashed var(--line); }

.agent-sidebar { display: flex; flex-direction: column; gap: 20px; }
.info-card { background: var(--card-bg); border: 1px solid var(--line); border-radius: 12px; padding: 20px; }
.info-card h3 { font-family: 'Fraunces', serif; font-weight: 600; font-size: 16px; color: var(--navy); margin: 0 0 16px; padding-bottom: 10px; border-bottom: 1px solid var(--line); }
.info-card dl { display: grid; grid-template-columns: auto 1fr; gap: 8px 16px; margin: 0; }
.info-card dt { color: var(--ink-soft); font-size: 13px; font-family: 'IBM Plex Mono', monospace; }
.info-card dd { margin: 0; color: var(--ink); font-size: 13px; }
.usage-hint { color: var(--ink-soft); font-size: 13px; margin: 0; }
.knowledge-files { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 8px; }
.knowledge-files li {
  background: var(--paper); border: 1px solid var(--line); border-radius: 8px;
  padding: 8px 12px; font-size: 13px; color: var(--ink);
}

@media (max-width: 860px) {
  .agent-detail-grid { grid-template-columns: 1fr; }
  .page-header { flex-direction: column; align-items: flex-start; }
  .page-actions { width: 100%; justify-content: flex-start; }
}
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
  const useBtn = document.querySelector('.use-agent-btn');
  const editBtn = document.querySelector('.edit-agent-btn');
  const deleteBtn = document.querySelector('.delete-agent-btn');

  // Modal xác nhận tùy chỉnh (thay confirm() trình duyệt).
  function showDeleteConfirm() {
    return new Promise((resolve) => {
      const overlay = document.createElement('div');
      overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:2000;display:flex;align-items:center;justify-content:center;padding:20px;';

      const box = document.createElement('div');
      box.style.cssText = 'background:var(--card-bg,#fff);border-radius:16px;width:100%;max-width:400px;box-shadow:0 24px 48px -12px rgba(31,56,100,0.35);overflow:hidden;';

      const header = document.createElement('div');
      header.style.cssText = 'padding:20px 24px;border-bottom:1px solid var(--line,#E1DACB);font-family:Fraunces,serif;font-size:20px;font-weight:600;color:var(--navy,#1F3864);';
      header.textContent = 'Delete agent';
      box.appendChild(header);

      const body = document.createElement('div');
      body.style.cssText = 'padding:20px 24px;font-size:14px;color:var(--ink,#22303F);line-height:1.5;';
      body.textContent = 'Are you sure you want to delete this agent? This cannot be undone.';
      box.appendChild(body);

      const footer = document.createElement('div');
      footer.style.cssText = 'display:flex;justify-content:flex-end;gap:12px;padding:16px 24px;';

      const cancelBtn = document.createElement('button');
      cancelBtn.textContent = 'Cancel';
      cancelBtn.style.cssText = 'padding:10px 20px;border-radius:8px;background:var(--paper,#F6F3EC);color:var(--ink,#22303F);border:1px solid var(--line,#E1DACB);cursor:pointer;font-size:14px;';
      cancelBtn.addEventListener('click', () => { overlay.remove(); resolve(false); });
      footer.appendChild(cancelBtn);

      const okBtn = document.createElement('button');
      okBtn.textContent = 'Delete';
      okBtn.style.cssText = 'padding:10px 20px;border-radius:8px;border:none;cursor:pointer;font-size:14px;color:#fff;background:#dc3545;';
      okBtn.addEventListener('click', () => { overlay.remove(); resolve(true); });
      footer.appendChild(okBtn);

      box.appendChild(footer);
      overlay.appendChild(box);
      overlay.addEventListener('click', (e) => { if (e.target === overlay) { overlay.remove(); resolve(false); } });
      document.body.appendChild(overlay);
    });
  }

  useBtn?.addEventListener('click', () => {
    const agentId = useBtn.dataset.agentId;
    sessionStorage.setItem('selectedAgentId', agentId);
    window.location.href = '{{ route("ai-plus.agent-workspace.index") }}';
  });

  editBtn?.addEventListener('click', () => {
    const agentId = editBtn.dataset.agentId;
    window.location.href = '{{ route("ai-plus.agent-workspace.agents.index") }}#edit-' + agentId;
  });

  deleteBtn?.addEventListener('click', async () => {
    const ok = await showDeleteConfirm();
    if (!ok) return;
    const agentId = deleteBtn.dataset.agentId;
    deleteBtn.disabled = true;
    try {
      const res = await fetch(`{{ route('ai-plus.agent-workspace.agents.destroy', ':id') }}`.replace(':id', agentId), {
        method: 'DELETE',
        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
      });
      if (!res.ok) throw new Error('The agent could not be deleted. Please try again.');
      window.location.href = '{{ route("ai-plus.agent-workspace.agents.index") }}';
    } catch (error) {
      deleteBtn.disabled = false;
      await WebUI.notice(error.message || 'We could not reach the server. Please try again.', { title: 'Could not delete agent' });
    }
  });
});
</script>
@endpush
@endsection
