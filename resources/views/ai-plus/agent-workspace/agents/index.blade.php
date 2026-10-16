@extends('layouts.ai-plus')

@section('title', 'My Agents — Agent Workspace')

@section('breadcrumb', 'Agents')

@section('content')
<div class="agents-workspace-shell">
  <aside class="agents-workspace-sidebar" aria-label="Agent Workspace navigation">
    <div class="agents-workspace-sidebar-header">
      <h2>Agent Workspace</h2>
    </div>
    <nav class="workspace-tabs" aria-label="Workspace">
      <a href="{{ route('ai-plus.agent-workspace.index') }}" class="ws-tab" data-behavior="new-quick-chat">
        <span class="icon">💬</span>
        <span>Chat</span>
      </a>
      <a href="{{ route('ai-plus.agent-workspace.agents.index') }}" class="ws-tab active" aria-current="page">
        <span class="icon">🤖</span>
        <span>Agents</span>
      </a>
      @if($imageGenerationEnabled)
      <a href="{{ route('ai-plus.agent-workspace.images.index') }}" class="ws-tab">
        <span class="icon">🖼️</span>
        <span>Image</span>
      </a>
      @endif
    </nav>
    <div class="agent-sidebar-section-title">My Agents</div>
    <div class="agent-sidebar-list">
      @foreach($agents->concat($usedSharedAgents) as $sidebarAgent)
      @php($sidebarConversations = $agentConversations[$sidebarAgent->id] ?? [])
      @php($conversationListId = 'agent-conversations-'.$sidebarAgent->id)
      <section class="agent-sidebar-group">
        <div class="agent-sidebar-heading">
          <a href="#agent-card-{{ $sidebarAgent->id }}" class="agent-sidebar-agent" title="Show {{ $sidebarAgent->title }}">
            <span class="agent-sidebar-icon">@if($sidebarAgent->avatar_url)<img src="{{ $sidebarAgent->avatar_url }}" alt="">@else 🤖 @endif</span>
            <span class="agent-sidebar-title">{{ $sidebarAgent->title }}</span>
          </a>
          <button class="agent-sidebar-toggle" type="button" aria-expanded="true" aria-controls="{{ $conversationListId }}" title="Collapse conversations">−</button>
        </div>
        <div class="agent-sidebar-conversations" id="{{ $conversationListId }}">
          @forelse($sidebarConversations as $conversation)
          <a href="{{ route('ai-plus.agent-workspace.index', ['conversation_id' => $conversation['id']]) }}" class="agent-sidebar-conversation {{ (string) request('conversation_id') === (string) $conversation['id'] ? 'active' : '' }}" @if((string) request('conversation_id') === (string) $conversation['id']) aria-current="page" @endif>
            <span aria-hidden="true">💬</span>
            <span class="agent-sidebar-conversation-title">{{ $conversation['title'] }}</span>
          </a>
          @empty
          <span class="agent-sidebar-empty">No chats yet</span>
          @endforelse
        </div>
      </section>
      @endforeach
      @if($agents->isEmpty() && $usedSharedAgents->isEmpty())
      <p class="agent-sidebar-empty-state">Your Agents and their chats will appear here.</p>
      @endif
    </div>
  </aside>

  <main class="agents-workspace-main">
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
    <article class="agent-card" id="agent-card-{{ $agent->id }}" tabindex="-1" data-agent-id="{{ $agent->id }}">
      <div class="agent-card-header">
        <div class="agent-icon">@if($agent->avatar_url)<img src="{{ $agent->avatar_url }}" alt="">@else 🤖 @endif</div>
        <div class="agent-title">{{ $agent->title }}</div>
      </div>
      <div class="agent-description-wrap">
        @if($agent->description)
        <p class="agent-description is-collapsed" id="agent-description-{{ $agent->id }}">{{ $agent->description }}</p>
        @if(\Illuminate\Support\Str::length($agent->description) > 120)
        <button class="agent-description-toggle" type="button" aria-expanded="false" aria-controls="agent-description-{{ $agent->id }}" data-description-toggle>More</button>
        @endif
        @endif
      </div>
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
    <article class="agent-card shared-agent-card" id="agent-card-{{ $agent->id }}" tabindex="-1" data-agent-id="{{ $agent->id }}">
      <div class="agent-card-header">
        <div class="agent-icon">@if($agent->avatar_url)<img src="{{ $agent->avatar_url }}" alt="">@else 🤖 @endif</div>
        <div class="agent-title">{{ $agent->title }}</div>
      </div>
      <div class="agent-description-wrap">
        @if($agent->description)
        <p class="agent-description is-collapsed" id="agent-description-{{ $agent->id }}">{{ $agent->description }}</p>
        @if(\Illuminate\Support\Str::length($agent->description) > 120)
        <button class="agent-description-toggle" type="button" aria-expanded="false" aria-controls="agent-description-{{ $agent->id }}" data-description-toggle>More</button>
        @endif
        @endif
      </div>
      <div class="agent-meta">
        <span class="badge shared">Shared · Use-only</span>
        <span class="updated-at">Shortcut in your workspace</span>
      </div>
      <div class="agent-actions">
        <button class="btn-icon use-agent" title="Start a new chat" data-agent-id="{{ $agent->id }}">💬</button>
        <form method="POST" action="{{ route('ai-plus.sharing-showcase.use.destroy', $agent->showcase_post_id) }}" class="remove-shared-agent-form" data-web-confirm="Remove this shared Agent from My Agents? Your existing conversations will be kept." data-web-confirm-title="Remove shared Agent" data-web-confirm-action="Remove" data-web-confirm-danger="true">
          @csrf
          @method('DELETE')
          <button class="btn-icon remove-shared-agent" type="submit" title="Remove from My Agents" aria-label="Remove {{ $agent->title }} from My Agents">🗑️</button>
        </form>
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
</main>
</div>

@include('ai-plus.agent-workspace.agents._agent-form-modal')

@push('styles')
<style>
.agents-workspace-shell { display: flex; min-height: calc(100vh - 52px); background: var(--body-bg); }
.agents-workspace-sidebar { width: var(--workspace-sidebar-width, 300px); flex: 0 0 var(--workspace-sidebar-width, 300px); background: var(--page-bg); border-right: 1px solid var(--surface-border); display: flex; flex-direction: column; }
.agents-workspace-sidebar-header { padding: 20px; border-bottom: 1px solid var(--surface-border); }
.agents-workspace-sidebar-header h2 { color: var(--page-header-text); font-family: 'Fraunces', serif; font-weight: 600; font-size: 20px; margin: 0; }
.agents-workspace-sidebar .workspace-tabs { display: flex; gap: 8px; padding: 16px 12px 12px; }
.agents-workspace-sidebar .ws-tab { flex: 1; padding: 10px 12px; background: var(--chip-bg); border: 1px solid var(--chip-border); border-radius: 8px; color: var(--chip-text); font-size: 12px; font-weight: 500; cursor: pointer; text-decoration: none; display: flex; flex-direction: column; align-items: center; gap: 4px; transition: all .15s; }
.agents-workspace-sidebar .ws-tab:hover { background: var(--surface); color: var(--text-main); }
.agents-workspace-sidebar .ws-tab.active { background: var(--chip-active-bg); color: var(--chip-active-text); border-color: var(--chip-active-bg); }
.agents-workspace-sidebar .ws-tab .icon { font-size: 18px; }
.agent-sidebar-section-title { color: var(--text-soft); font-size: 11px; font-weight: 500; letter-spacing: .08em; padding: 16px 16px 8px; text-transform: uppercase; }
.agent-sidebar-list { flex: 1; min-height: 0; overflow-y: auto; padding: 0 10px 16px; }
.agent-sidebar-group { margin-bottom: 4px; }
.agent-sidebar-heading { display: flex; align-items: center; gap: 4px; min-width: 0; }
.agent-sidebar-agent { display: flex; align-items: center; gap: 8px; min-width: 0; flex: 1; padding: 7px 6px; border-radius: 7px; color: var(--text-main); text-decoration: none; }
.agent-sidebar-agent:hover, .agent-sidebar-conversation:hover { background: var(--surface); }
.agent-sidebar-icon { width: 26px; height: 26px; flex: 0 0 26px; display: grid; place-items: center; overflow: hidden; border-radius: 7px; background: var(--chip-bg); font-size: 14px; }
.agent-sidebar-icon img { width: 100%; height: 100%; object-fit: cover; }
.agent-sidebar-title, .agent-sidebar-conversation-title { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.agent-sidebar-title { font-size: 13px; }
.agent-sidebar-toggle { width: 26px; height: 26px; flex: 0 0 26px; border: 0; border-radius: 6px; background: transparent; color: var(--text-soft); font-size: 18px; cursor: pointer; }
.agent-sidebar-toggle:hover { background: var(--surface); color: var(--text-main); }
.agent-sidebar-conversations { display: flex; flex-direction: column; margin: 0 0 4px 18px; padding-left: 7px; border-left: 1px solid var(--surface-border); }
.agent-sidebar-conversations[hidden] { display: none; }
.agent-sidebar-conversation { display: flex; align-items: center; gap: 7px; min-width: 0; padding: 7px 6px; border-radius: 6px; color: var(--text-soft); font-size: 12px; text-decoration: none; }
.agent-sidebar-conversation.active { background: var(--chip-active-bg); color: var(--chip-active-text); }
.agent-sidebar-empty, .agent-sidebar-empty-state { margin: 2px 6px 6px; color: var(--text-soft); font-size: 11px; line-height: 1.4; }
.agent-card:target { outline: 2px solid var(--chip-active-bg); outline-offset: 3px; }
.agents-workspace-main { flex: 1; min-width: 0; overflow: auto; }
.agents-page { max-width: 1080px; margin: 0 auto; padding: 24px 32px 48px; }
.page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 28px; gap: 16px; flex-wrap: wrap; }
.page-header .page-title { margin: 0; font-size: clamp(28px, 3.5vw, 36px); }

/* Grid */
.agents-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; align-items: start; }
.agent-card { box-sizing: border-box; height: 302px; background: var(--card-bg); border: 1px solid var(--line); border-radius: 14px; padding: 20px; display: flex; flex-direction: column; gap: 12px; transition: transform .15s, box-shadow .15s, border-color .15s; }
.agent-card.is-expanded { height: auto; }
.agent-card:hover { transform: translateY(-2px); box-shadow: 0 12px 24px -12px rgba(31,56,100,0.25); border-color: rgba(31,56,100,0.2); }
.shared-agent-card{border-color:rgba(35,95,78,.28);background:linear-gradient(135deg,rgba(238,248,243,.72),var(--card-bg));}
.agent-card-header { display: flex; align-items: center; gap: 12px; min-height: 56px; }
.agent-icon { width: 40px; height: 40px; border-radius: 10px; background: var(--navy); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0; overflow:hidden; }
.agent-icon img{width:100%;height:100%;object-fit:cover;}
.agent-title { display: -webkit-box; -webkit-box-orient: vertical; -webkit-line-clamp: 2; overflow: hidden; font-family: 'Fraunces', serif; font-weight: 600; font-size: 18px; color: var(--ink); }
.agent-description-wrap { min-width: 0; min-height: calc(4.5em + 20px); font-size: 14px; line-height: 1.5; }
.agent-description { min-height: 4.5em; color: var(--ink-soft); font-size: 14px; margin: 0; line-height: 1.5; }
.agent-description.is-collapsed { display: -webkit-box; -webkit-box-orient: vertical; -webkit-line-clamp: 3; overflow: hidden; }
.agent-description-toggle { margin: 5px 0 0; padding: 0; border: 0; background: transparent; color: var(--navy); font: inherit; font-size: 13px; font-weight: 600; cursor: pointer; }
.agent-description-toggle:hover { text-decoration: underline; }
.agent-meta { display: flex; align-items: center; gap: 10px; min-height: 52px; box-sizing: border-box; margin-top: auto; padding-top: 8px; border-top: 1px solid var(--line); }
.badge { font-family: 'IBM Plex Mono', monospace; font-size: 11px; padding: 3px 8px; border-radius: 999px; font-weight: 500; }
.badge.shared { background: var(--sage); color: #fff; }
.badge.private { background: var(--paper); color: var(--ink-soft); border: 1px solid var(--line); }
.updated-at { font-size: 12px; color: var(--ink-soft); font-family: 'IBM Plex Mono', monospace; }
.agent-actions { display: flex; justify-content: flex-end; gap: 6px; }
.btn-icon { width: 32px; height: 32px; border-radius: 8px; border: 1px solid var(--line); background: var(--card-bg); cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 14px; transition: background .15s, border-color .15s; }
.btn-icon:hover { background: var(--paper); border-color: var(--navy); }
.remove-shared-agent-form{display:inline-flex;margin:0;}
.btn-icon.remove-shared-agent:hover{background:rgba(220,53,69,.1);border-color:#dc3545;color:#dc3545;}
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
  .agents-workspace-shell { min-height: calc(100vh - 52px); flex-direction: column; }
  .agents-workspace-sidebar { width: auto; flex-basis: auto; border-right: 0; border-bottom: 1px solid var(--surface-border); }
  .agent-sidebar-list { max-height: 36vh; }
  .agents-workspace-main { overflow: visible; }
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

@push('scripts')
<script>
document.querySelectorAll('[data-description-toggle]').forEach(button => {
  button.addEventListener('click', () => {
    const description = document.getElementById(button.getAttribute('aria-controls'));
    const card = button.closest('.agent-card');
    const isExpanded = button.getAttribute('aria-expanded') === 'true';
    description.classList.toggle('is-collapsed', isExpanded);
    card.classList.toggle('is-expanded', !isExpanded);
    button.setAttribute('aria-expanded', String(!isExpanded));
    button.textContent = isExpanded ? 'More' : 'Less';
  });
});

document.querySelectorAll('.agent-sidebar-toggle').forEach(button => {
  button.addEventListener('click', () => {
    const list = document.getElementById(button.getAttribute('aria-controls'));
    const isExpanded = button.getAttribute('aria-expanded') === 'true';
    list.hidden = isExpanded;
    button.setAttribute('aria-expanded', String(!isExpanded));
    button.textContent = isExpanded ? '+' : '−';
    button.title = isExpanded ? 'Expand conversations' : 'Collapse conversations';
  });
});
</script>
@endpush
@endsection
