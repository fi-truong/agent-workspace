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
    @if($agents->count() > 0 || $usedSharedAgents->isNotEmpty())
    @foreach($agents as $agent)
    <article class="agent-card" data-agent-id="{{ $agent->id }}">
      <div class="agent-card-header">
        <div class="agent-icon">@if($agent->avatar_url)<img src="{{ $agent->avatar_url }}" alt="">@else 🤖 @endif</div>
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
    @foreach($usedSharedAgents as $agent)
    <article class="agent-card shared-agent-card" data-agent-id="{{ $agent->id }}">
      <div class="agent-card-header">
        <div class="agent-icon">@if($agent->avatar_url)<img src="{{ $agent->avatar_url }}" alt="">@else 🤖 @endif</div>
        <div class="agent-title">{{ $agent->title }}</div>
      </div>
      @if($agent->description)
      <p class="agent-description">{{ $agent->description }}</p>
      @endif
      <div class="agent-meta">
        <span class="badge shared">Shared · Use-only</span>
        <span class="updated-at">Shortcut in your workspace</span>
      </div>
      <div class="agent-actions">
        <button class="btn-icon use-agent" title="Start a new chat" data-agent-id="{{ $agent->id }}">💬</button>
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

@include('ai-plus.agent-workspace.agents._agent-form-modal')

@push('styles')
<style>
.agents-page { max-width: 1080px; margin: 0 auto; padding: 24px 32px 48px; }
.page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 28px; gap: 16px; flex-wrap: wrap; }
.page-header .page-title { margin: 0; font-size: clamp(28px, 3.5vw, 36px); }

/* Grid */
.agents-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; }
.agent-card { background: var(--card-bg); border: 1px solid var(--line); border-radius: 14px; padding: 20px; display: flex; flex-direction: column; gap: 12px; transition: transform .15s, box-shadow .15s, border-color .15s; }
.agent-card:hover { transform: translateY(-2px); box-shadow: 0 12px 24px -12px rgba(31,56,100,0.25); border-color: rgba(31,56,100,0.2); }
.shared-agent-card{border-color:rgba(35,95,78,.28);background:linear-gradient(135deg,rgba(238,248,243,.72),var(--card-bg));}
.agent-card-header { display: flex; align-items: center; gap: 12px; }
.agent-icon { width: 40px; height: 40px; border-radius: 10px; background: var(--navy); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0; overflow:hidden; }
.agent-icon img{width:100%;height:100%;object-fit:cover;}
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
.agent-avatar-editor{display:flex;align-items:center;gap:12px}.agent-avatar-preview{width:56px;height:56px;border-radius:14px;overflow:hidden;background:var(--navy);color:#fff;display:flex;align-items:center;justify-content:center;font-size:26px;flex:none}.agent-avatar-preview img{width:100%;height:100%;object-fit:cover}

/* Knowledge dropzone */
.knowledge-dropzone {
  border: 1px dashed var(--line);
  border-radius: 12px;
  padding: 14px;
  background: rgba(31,56,100,0.03);
  cursor: pointer;
  transition: border-color .15s, background .15s, box-shadow .15s;
}
.knowledge-dropzone:hover {
  border-color: rgba(31,56,100,0.35);
  background: rgba(31,56,100,0.05);
}
.knowledge-dropzone.is-dragover {
  border-color: var(--navy);
  background: rgba(31,56,100,0.08);
  box-shadow: 0 0 0 3px rgba(31,56,100,0.08);
}
.knowledge-dropzone-inner {
  display: flex;
  flex-direction: column;
  gap: 4px;
  align-items: center;
  text-align: center;
  user-select: none;
}
.knowledge-dropzone-title {
  font-weight: 600;
  color: var(--ink);
  font-size: 14px;
}
.knowledge-dropzone-sub {
  font-size: 12px;
  color: var(--ink-soft);
}

.knowledge-file-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 10px;
}
.knowledge-chip {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  border: 1px solid var(--line);
  border-radius: 999px;
  padding: 6px 10px;
  background: var(--paper);
  color: var(--ink);
  font-size: 12px;
  max-width: 100%;
}
.knowledge-chip-name {
  max-width: 240px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.knowledge-chip-x {
  border: none;
  background: transparent;
  cursor: pointer;
  color: var(--ink-soft);
  font-size: 14px;
  padding: 0;
  line-height: 1;
}
.knowledge-chip-x:hover { color: var(--ink); }

.badge-coming { display: inline-block; font-size: 10px; background: var(--gold); color: var(--navy-deep); padding: 2px 8px; border-radius: 999px; font-weight: 500; margin-left: 6px; text-transform: uppercase; letter-spacing: 0.04em; }
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

/* Knowledge saved files (edit mode) */
.knowledge-saved-list { display: none; margin-top: 12px; }
.knowledge-saved-heading { font-size: 12px; color: var(--ink-soft); margin-bottom: 8px; }
.knowledge-saved-row {
  display: flex; align-items: center; justify-content: space-between; gap: 8px;
  border: 1px solid var(--line); border-radius: 10px; padding: 8px 12px;
  background: var(--paper); margin-bottom: 6px; font-size: 13px; color: var(--ink);
}
.knowledge-saved-row.removing { text-decoration: line-through; }
.knowledge-saved-name { flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
</style>
@endpush
@endsection
