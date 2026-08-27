@extends('layouts.ai-plus')

@section('title', 'My Agents — Agent Workspace')

@section('breadcrumb', 'Agents')

@section('content')
<div class="agents-page">
  <div class="page-header">
    <h1 class="page-title">My Agents</h1>
    <button class="btn btn-primary" id="create-agent-btn">
      <span>+</span> Create Agent
    </button>
  </div>

  <div class="agents-grid" id="agents-grid">
    @if($agents->count() > 0)
    @foreach($agents as $agent)
    <article class="agent-card" data-agent-id="{{ $agent->id }}">
      <div class="agent-card-header">
        <div class="agent-icon">🤖</div>
        <div class="agent-title">{{ $agent->title }}</div>
      </div>
      @if($agent->description)
      <p class="agent-description">{{ $agent->description }}</p>
      @endif
      <div class="agent-meta">
        <span class="badge {{ $agent->is_shared ? 'shared' : 'private' }}">
          {{ $agent->is_shared ? 'Shared' : 'Private' }}
        </span>
        <span class="updated-at">Updated {{ $agent->updated_at->diffForHumans() }}</span>
      </div>
      <div class="agent-actions">
        <button class="btn-icon edit-agent" title="Edit" data-agent-id="{{ $agent->id }}">✏️</button>
        <button class="btn-icon use-agent" title="Use in Chat" data-agent-id="{{ $agent->id }}">💬</button>
        <button class="btn-icon delete-agent" title="Delete" data-agent-id="{{ $agent->id }}">🗑️</button>
      </div>
    </article>
    @endforeach
    @else
    <div class="empty-state">
      <div class="empty-icon">🤖</div>
      <h3>No agents yet</h3>
      <p>Create your first custom AI assistant with a system prompt tailored to your needs.</p>
      <button class="btn btn-primary" id="create-first-agent">Create Agent</button>
    </div>
    @endif
  </div>
</div>

<!-- Create/Edit Agent Modal -->
<div class="modal-overlay" id="agent-modal" style="display:none;">
  <div class="modal">
    <div class="modal-header">
      <h3 id="modal-title">Create Agent</h3>
      <button class="modal-close" id="modal-close">&times;</button>
    </div>
    <form id="agent-form" method="POST">
      @csrf
      <input type="hidden" name="_method" value="POST" id="form-method">
      <input type="hidden" name="agent_id" id="agent-id">
      <div class="form-group">
        <label for="title">Title <span class="required">*</span></label>
        <input type="text" id="title" name="title" required maxlength="255" placeholder="e.g., Math Quiz Generator">
      </div>
      <div class="form-group">
        <label for="description">Description</label>
        <textarea id="description" name="description" rows="3" placeholder="What does this agent do?"></textarea>
      </div>
      <div class="form-group">
        <label for="system_prompt">System Prompt</label>
        <textarea id="system_prompt" name="system_prompt" rows="6" placeholder="Instructions for the AI (e.g., 'You are a helpful math teacher...')"></textarea>
        <small class="form-hint">This prompt guides the agent's behavior. Leave empty to use default.</small>
      </div>
      <div class="form-group checkbox-group">
        <input type="checkbox" id="is_shared" name="is_shared" value="1">
        <label for="is_shared">Share with team (visible in Sharing & Showcase)</label>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" id="modal-cancel">Cancel</button>
        <button type="submit" class="btn btn-primary" id="modal-submit">Save</button>
      </div>
    </form>
  </div>
</div>

@push('styles')
<style>
.agents-page { max-width: 1080px; margin: 0 auto; padding: 24px 32px 48px; }
.page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 28px; gap: 16px; flex-wrap: wrap; }
.page-header .page-title { margin: 0; font-size: clamp(28px, 3.5vw, 36px); }

/* Grid */
.agents-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; }
.agent-card { background: var(--card-bg); border: 1px solid var(--line); border-radius: 14px; padding: 20px; display: flex; flex-direction: column; gap: 12px; transition: transform .15s, box-shadow .15s, border-color .15s; }
.agent-card:hover { transform: translateY(-2px); box-shadow: 0 12px 24px -12px rgba(31,56,100,0.25); border-color: rgba(31,56,100,0.2); }
.agent-card-header { display: flex; align-items: center; gap: 12px; }
.agent-icon { width: 40px; height: 40px; border-radius: 10px; background: var(--navy); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0; }
.agent-title { font-family: 'Fraunces', serif; font-weight: 600; font-size: 18px; color: var(--ink); }
.agent-description { color: var(--ink-soft); font-size: 14px; margin: 0; line-height: 1.5; }
.agent-meta { display: flex; align-items: center; gap: 10px; margin-top: auto; padding-top: 8px; border-top: 1px solid var(--line); }
.badge { font-family: 'IBM Plex Mono', monospace; font-size: 11px; padding: 3px 8px; border-radius: 999px; font-weight: 500; }
.badge.shared { background: var(--sage); color: #fff; }
.badge.private { background: var(--paper); color: var(--ink-soft); border: 1px solid var(--line); }
.updated-at { font-size: 12px; color: var(--ink-soft); font-family: 'IBM Plex Mono', monospace; }
.agent-actions { display: flex; justify-content: flex-end; gap: 6px; }
.btn-icon { width: 32px; height: 32px; border-radius: 8px; border: 1px solid var(--line); background: var(--card-bg); cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 14px; transition: background .15s, border-color .15s; }
.btn-icon:hover { background: var(--paper); border-color: var(--navy); }
.btn-icon.delete-agent:hover { background: rgba(220, 53, 69, 0.1); border-color: #dc3545; color: #dc3545; }

/* Empty State */
.empty-state { grid-column: 1 / -1; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 60px 20px; text-align: center; background: var(--card-bg); border: 1px solid var(--line); border-radius: 14px; }
.empty-icon { font-size: 48px; margin-bottom: 16px; }
.empty-state h3 { font-family: 'Fraunces', serif; font-size: 22px; color: var(--navy); margin-bottom: 8px; }
.empty-state p { color: var(--ink-soft); max-width: 400px; margin-bottom: 24px; }

/* Modal */
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000; display: flex; align-items: center; justify-content: center; padding: 20px; }
.modal { background: var(--card-bg); border-radius: 16px; width: 100%; max-width: 560px; max-height: 90vh; overflow-y: auto; box-shadow: 0 24px 48px -12px rgba(31,56,100,0.35); }
.modal-header { display: flex; align-items: center; justify-content: space-between; padding: 20px 24px; border-bottom: 1px solid var(--line); }
.modal-header h3 { margin: 0; font-family: 'Fraunces', serif; font-size: 20px; color: var(--navy); }
.modal-close { background: none; border: none; font-size: 24px; cursor: pointer; color: var(--ink-soft); line-height: 1; padding: 4px; }
.modal-close:hover { color: var(--ink); }
#agent-form { padding: 24px; display: flex; flex-direction: column; gap: 20px; }
.form-group { display: flex; flex-direction: column; gap: 6px; }
.form-group label { font-weight: 500; color: var(--ink); font-size: 14px; }
.form-group label .required { color: #dc3545; }
.form-group input[type="text"],
.form-group textarea { padding: 12px 14px; border: 1px solid var(--line); border-radius: 10px; font-family: inherit; font-size: 14px; color: var(--ink); background: var(--card-bg); transition: border-color .15s, box-shadow .15s; }
.form-group input:focus, .form-group textarea:focus { outline: none; border-color: var(--navy); box-shadow: 0 0 0 3px rgba(31,56,100,0.1); }
.form-group textarea { resize: vertical; min-height: 100px; }
.form-hint { font-size: 12px; color: var(--ink-soft); }
.checkbox-group { flex-direction: row; align-items: center; gap: 10px; }
.checkbox-group input[type="checkbox"] { width: 18px; height: 18px; accent-color: var(--navy); }
.checkbox-group label { margin: 0; font-weight: 400; }
.modal-footer { display: flex; justify-content: flex-end; gap: 12px; padding-top: 8px; }
.btn { padding: 10px 20px; border-radius: 8px; font-size: 14px; font-weight: 500; cursor: pointer; border: none; transition: background .15s, border-color .15s; display: inline-flex; align-items: center; gap: 6px; }
.btn-primary { background: var(--navy); color: #fff; }
.btn-primary:hover { background: var(--navy-deep); }
.btn-secondary { background: var(--paper); color: var(--ink); border: 1px solid var(--line); }
.btn-secondary:hover { background: var(--line); }

@media (max-width: 640px) {
  .agents-page { padding: 16px 20px 32px; }
  .agents-grid { grid-template-columns: 1fr; }
}
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
  const modal = document.getElementById('agent-modal');
  const form = document.getElementById('agent-form');
  const createBtn = document.getElementById('create-agent-btn');
  const createFirstBtn = document.getElementById('create-first-agent');
  const closeBtn = document.getElementById('modal-close');
  const cancelBtn = document.getElementById('modal-cancel');
  const modalTitle = document.getElementById('modal-title');
  const formMethod = document.getElementById('form-method');
  const agentIdInput = document.getElementById('agent-id');

  function openModal(agent = null) {
    modal.style.display = 'flex';
    form.reset();
    if (agent) {
      modalTitle.textContent = 'Edit Agent';
      formMethod.value = 'PUT';
      agentIdInput.value = agent.id;
      document.getElementById('title').value = agent.title;
      document.getElementById('description').value = agent.description || '';
      document.getElementById('system_prompt').value = agent.system_prompt || '';
      document.getElementById('is_shared').checked = agent.is_shared;
    } else {
      modalTitle.textContent = 'Create Agent';
      formMethod.value = 'POST';
      agentIdInput.value = '';
    }
  }

  function closeModal() {
    modal.style.display = 'none';
    form.reset();
  }

  createBtn?.addEventListener('click', () => openModal());
  createFirstBtn?.addEventListener('click', () => openModal());
  closeBtn.addEventListener('click', closeModal);
  cancelBtn.addEventListener('click', closeModal);
  modal.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });

  // Edit buttons (delegated)
  document.getElementById('agents-grid').addEventListener('click', async (e) => {
    const editBtn = e.target.closest('.edit-agent');
    const useBtn = e.target.closest('.use-agent');
    const deleteBtn = e.target.closest('.delete-agent');

    if (editBtn) {
      const agentId = editBtn.dataset.agentId;
      const res = await fetch(`/ai-plus/agent-workspace/agents/${agentId}`, { headers: { 'Accept': 'application/json' } });
      const agent = await res.json();
      openModal(agent);
    }

    if (useBtn) {
      const agentId = useBtn.dataset.agentId;
      // Store selected agent in sessionStorage for chat tab
      sessionStorage.setItem('selectedAgentId', agentId);
      // Switch to Chat tab
      document.querySelector('.ws-tab[data-tab="chat"]')?.click();
      // Notify chat to load agent
      window.dispatchEvent(new CustomEvent('agent-selected', { detail: { agentId } }));
    }

    if (deleteBtn) {
      if (!confirm('Delete this agent?')) return;
      const agentId = deleteBtn.dataset.agentId;
      const res = await fetch(`/ai-plus/agent-workspace/agents/${agentId}`, {
        method: 'DELETE',
        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
      });
      if (res.ok) {
        location.reload();
      } else {
        alert('Failed to delete agent');
      }
    }
  });

  // Form submit
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const isEdit = formMethod.value === 'PUT';
    const agentId = agentIdInput.value;
    const url = isEdit ? `/ai-plus/agent-workspace/agents/${agentId}` : '/ai-plus/agent-workspace/agents';
    const method = isEdit ? 'PUT' : 'POST';

    const formData = new FormData(form);
    // Convert checkbox
    formData.set('is_shared', formData.get('is_shared') ? '1' : '0');

    const res = await fetch(url, {
      method,
      headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
      body: formData
    });

    if (res.ok) {
      closeModal();
      location.reload();
    } else {
      const err = await res.json();
      alert(err.message || 'Failed to save agent');
    }
  });
});
</script>
@endpush
@endsection
