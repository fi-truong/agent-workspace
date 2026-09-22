@extends('layouts.ai-plus')

@section('title', 'Agent Workspace — AI+ LSTS')

@section('breadcrumb', 'Agent Workspace')

@section('content')
<div class="app">
  <script>
  window.__INITIAL_MESSAGES__ = @json($initialMessages);
  window.__MY_AGENTS__ = @json($myAgents);
  window.__SELECTED_AGENT_ID__ = @json($selectedAgentId);
  window.__SELECTED_AGENT_NAME__ = @json($selectedAgentName);
  window.__AGENT_ACCESS_MESSAGE__ = @json($agentAccessMessage);
</script>
  <!-- Sidebar -->
  <aside class="sidebar">
    <div class="sidebar-header">
      <!-- <a href="{{ route('ai-plus.index') }}">← Back to AI+</a> -->
      <h2>Agent Workspace</h2>
    </div>

    <!-- Workspace Type Tabs -->
    <div class="workspace-tabs">
      <a href="{{ route('ai-plus.agent-workspace.index') }}" class="ws-tab {{ request()->routeIs('ai-plus.agent-workspace.index') ? 'active' : '' }}">
        <span class="icon">💬</span>
        <span>Chat</span>
      </a>
      <a href="{{ route('ai-plus.agent-workspace.agents.index') }}" class="ws-tab {{ request()->routeIs('ai-plus.agent-workspace.agents.*') ? 'active' : '' }}">
        <span class="icon">🤖</span>
        <span>Agents</span>
      </a>
      @if($imageGenerationEnabled)
      <a href="{{ route('ai-plus.agent-workspace.images.index') }}" class="ws-tab">
        <span class="icon">🖼️</span>
        <span>Image</span>
      </a>
      @endif
    </div>

    <div class="chat-list">
      <div class="chat-list-section">
        Today
        <button class="add-btn" title="New chat">+</button>
      </div>
      @foreach($conversations as $conv)
      <div class="chat-item-wrap">
        <a href="{{ route('ai-plus.agent-workspace.index', ['conversation_id' => $conv['id']]) }}"
           class="chat-item {{ $loop->first ? 'active' : '' }}"
           data-conversation-id="{{ $conv['id'] }}" data-conversation-title="{{ $conv['title'] }}">
          <span class="item-icon chat">💬</span>
          <span class="title">{{ $conv['title'] }}</span>
        </a>
        <button class="conv-delete-btn" data-conversation-id="{{ $conv['id'] }}" title="Delete prompt">×</button>
      </div>
      @endforeach

      <div class="chat-list-section">
        <a href="{{ route('ai-plus.agent-workspace.agents.index') }}" class="section-link">My Agents</a>
        <a href="{{ route('ai-plus.agent-workspace.agents.index') }}" class="add-btn" title="Create agent">+</a>
      </div>
      @foreach($myAgents as $agent)
      <a href="{{ route('ai-plus.agent-workspace.agents.show', $agent['id']) }}" class="chat-item">
        <span class="item-icon agent">🤖</span>
        <span class="title">{{ $agent['title'] }}</span>
      </a>
      @endforeach

      @if($recentArtifacts->isNotEmpty())
      <div class="chat-list-section">Recent files</div>
      @foreach($recentArtifacts as $artifact)
      <a href="{{ route('ai-plus.artifacts.download', $artifact) }}" class="chat-item">
        <span class="item-icon chat">📄</span><span class="title">{{ $artifact->name }}</span>
      </a>
      @endforeach
      @endif

      @if($recentEmailDrafts->isNotEmpty())
      <div class="chat-list-section">Email drafts</div>
      @foreach($recentEmailDrafts as $draft)
      <div class="chat-item"><span class="item-icon chat">✉️</span><span class="title">{{ $draft->subject }}</span></div>
      @endforeach
      @endif

      </div>

    <div class="sidebar-footer">
      <div class="user-info">
        <div class="user-avatar">{{ $userInitials }}</div>
        <div class="user-details">
          <div class="user-name">{{ $userName }}</div>
          <div class="user-quota" data-token-quota>
            @if($tokenQuota)
            <span data-token-quota-text>{{ number_format($tokenQuota['used']) }} / {{ number_format($tokenQuota['limit']) }} tokens (in {{ $tokenQuota['month_name'] }})</span>
            <div class="quota-bar"><div class="quota-fill" data-token-quota-fill style="width: {{ $tokenQuota['percentage'] }}%"></div></div>
            @endif
          </div>
        </div>
      </div>
    </div>
  </aside>

  <!-- Main Content -->
  <main class="main">
    <!-- Top Bar -->
    <div class="topbar">
      <!-- <div class="topbar-left">
        <div class="workspace-title">
          <h1 class="page-title">New Conversation</h1>
          <span class="ws-type-badge">Chat</span>
        </div>
      </div> -->
      <div class="topbar-left">
        <div class="model-selector">
          GPT-5.6 Luna
          <span class="badge">School AI</span>
        </div>
        @if($activeConversationTitle)
        <div class="active-agent-breadcrumb">
          @if($activeAgent)
          <span class="agent-breadcrumb-name">🤖 {{ $activeAgent->title }}</span>
          <span class="agent-breadcrumb-sep">→</span>
          <span class="agent-breadcrumb-prompt">{{ $activeConversationTitle }}</span>
          @else
          <span class="conversation-breadcrumb-name">💬 {{ $activeConversationTitle }}</span>
          @endif
        </div>
        @endif
      </div>
      <div class="topbar-right">
        <button class="icon-btn" data-behavior="attach-topbar" title="Upload files">📎</button>
        <button class="icon-btn" data-behavior="save-as-agent" title="Save as Agent">🤖</button>
        <button class="icon-btn" data-behavior="export-chat" title="Export conversation">↓</button>
        <button class="icon-btn" data-behavior="settings" title="Settings">⚙</button>
      </div>
      <div class="settings-popover" id="settings-popover" style="display:none;">
        <div class="sp-item"><span class="sp-label">Model</span><span class="sp-value">GPT-5.6 Luna</span></div>
        <div class="sp-item"><span class="sp-label">Khu vực</span><span class="sp-value">School AI</span></div>
      </div>
    </div>

    <!-- Empty State for new chat -->
    <div class="empty-state">
      <div class="empty-icon">Agent Workspace</div>
      <h3>What would you like to create?</h3>
      <p>Start a conversation, build a custom agent, or set up an automated workflow to streamline your work.</p>

      <div class="quick-actions">
        @foreach($quickActions as $action)
        @if($action['label'] === 'New Workflow') @continue @endif
        <button class="quick-btn" type="button" data-behavior="quick-{{ \Illuminate\Support\Str::slug($action['label']) }}">
          <span class="icon">{{ $action['icon'] }}</span>
          <span class="label">{{ $action['label'] }}</span>
          <span class="desc">{{ $action['desc'] }}</span>
        </button>
        @endforeach
      </div>
    </div>

    <!-- Input Area -->
    <div class="input-area">
      <div class="input-wrapper">
        <div class="input-box">
          <textarea placeholder="Type your message, or describe what you want to build..." rows="1"></textarea>
          <div class="input-actions">
            <button class="attach-btn" title="Attach files">📎</button>
            <button class="send-btn">Send →</button>
          </div>
        </div>
        <input type="file" id="chat-image-input" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv" multiple style="display:none;">
        <div class="input-hint">
          Press Enter to send, Shift+Enter for new line • Your data is protected by PII filtering • Upload up to 5 files (15 MB total) for analysis
        </div>
      </div>
    </div>
  </main>
</div>
@endsection

@push('styles')
<style>
  /* Agent Workspace specific styles - uses theme variables for full theme support */
  .app{display:flex;height:100%;background: var(--body-bg);}

  /* Sidebar */
  .sidebar{width:300px;background: var(--page-bg);border-right:1px solid var(--surface-border);display:flex;flex-direction:column;flex-shrink:0;}
  .sidebar-header{padding:20px;border-bottom:1px solid var(--surface-border);}
  .sidebar-header a{color: var(--text-soft);font-size:13px;text-decoration:none;display:flex;align-items:center;gap:6px;}
  .sidebar-header a:hover{color: var(--text-main);}
  .sidebar-header h2{color: var(--page-header-text);font-family:'Fraunces', serif;font-weight:600;font-size:20px;margin-top:12px;}

  /* Workspace Tabs */
  .workspace-tabs{padding:16px 12px 12px;display:flex;gap:8px;}
  .ws-tab{flex:1;padding:10px 12px;background: var(--chip-bg);border:1px solid var(--chip-border);border-radius:8px;color: var(--chip-text);font-size:12px;font-weight:500;cursor:pointer;display:flex;flex-direction:column;align-items:center;gap:4px;transition: all 0.15s;}
  .ws-tab:hover{background: var(--surface);color: var(--text-main);}
  .ws-tab.active{background: var(--chip-active-bg);color: var(--chip-active-text);border-color: var(--chip-active-bg);}
  .ws-tab .icon{font-size:18px;}

  /* Chat List */
  .chat-list{flex:1;overflow-y:auto;padding:0 12px 16px;}
  .chat-list-section{color: var(--text-soft);font-size:11px;font-weight:500;text-transform:uppercase;letter-spacing:0.08em;padding:16px 8px 8px;display:flex;justify-content:space-between;align-items:center;}
  .section-link{color: var(--text-soft);text-decoration:none;transition:color 0.15s;}
  .section-link:hover{color: var(--topbar-link, var(--gold-light));}
  .add-btn{background:none;border:none;color: var(--text-soft);cursor:pointer;font-size:16px;padding:0;}
  .add-btn:hover{color: var(--gold-light);}
  .chat-item{padding:10px 12px;border-radius:8px;cursor:pointer;display:flex;align-items:center;gap:10px;transition: background 0.15s;}
  .chat-item:hover{background: var(--input-bg);}
  .chat-item.active{background: var(--surface);}
  .chat-item-wrap{position:relative;}
  .chat-item-wrap .chat-item{width:100%;box-sizing:border-box;padding-right:32px;}
  .conv-delete-btn{position:absolute;top:6px;right:8px;width:22px;height:22px;border-radius:50%;border:none;background:transparent;color:var(--text-soft);cursor:pointer;font-size:14px;line-height:1;display:none;align-items:center;justify-content:center;}
  .chat-item-wrap:hover .conv-delete-btn{display:flex;}
  .conv-delete-btn:hover{background:rgba(220,53,69,0.15);color:#dc3545;}
  .item-icon{width:28px;height:28px;border-radius:6px;background: var(--chip-bg);display:flex;align-items:center;justify-content:center;font-size:12px;flex-shrink:0;}
  .item-icon.chat{background: var(--navy-light);}
  .item-icon.agent{background: linear-gradient(135deg, var(--gold) 0%, #E5AB45 100%);}
  .item-icon.workflow{background: var(--sage);}
  .chat-item .title{color: var(--text-main);font-size:14px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;flex:1;}

  /* Sidebar Footer */
  .sidebar-footer{padding:16px 20px;border-top:1px solid var(--surface-border);}
  .user-info{display:flex;align-items:center;gap:10px;}
  .user-avatar{width:36px;height:36px;border-radius:50%;background: var(--navy-light);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:600;font-size:14px;}
  .user-details{flex:1;min-width:0;}
  .user-name{color: var(--page-header-text);font-size:14px;font-weight:500;}
  .user-quota{color: var(--text-soft);font-size:12px;display:flex;align-items:center;gap:4px;}
  .quota-bar{height:4px;background: var(--chip-border);border-radius:2px;width:60px;overflow:hidden;margin-top:2px;}
  .quota-fill{height:100%;background: var(--gold);border-radius:2px;}

  /* Main */
  .main{flex:1;display:flex;flex-direction:column;background: var(--body-bg);min-width:0;}

  /* Topbar */
  .topbar{padding:16px 24px;background: var(--topbar-bg);border-bottom:1px solid var(--topbar-border);display:flex;align-items:center;justify-content:space-between;flex-shrink:0;position:relative;}
  .workspace-title{display:flex;align-items:center;gap:10px;}
  .workspace-title h1{font-size:18px;font-weight:600;color: var(--text-main);margin:0;}
  .ws-type-badge{padding:4px 10px;background: var(--input-bg);border:1px solid var(--input-border);border-radius:6px;font-size:11px;font-family:'IBM Plex Mono', monospace;color: var(--text-soft);}
  .model-selector{padding:8px 12px;background: var(--input-bg);border:1px solid var(--input-border);border-radius:8px;font-size:13px;color: var(--input-text);cursor:pointer;display:flex;align-items:center;gap:8px;}
  .model-selector .badge{background: var(--navy);color:#fff;padding:2px 8px;border-radius:4px;font-size:11px;font-family:'IBM Plex Mono', monospace;}
  .topbar-left{display:flex;flex-direction:column;align-items:flex-start;gap:6px;}
  .active-agent-breadcrumb{display:flex;align-items:center;gap:8px;max-width:100%;}
  .agent-breadcrumb-name{display:inline-block;padding:6px 12px;background: linear-gradient(135deg, var(--gold) 0%, #E5AB45 100%);color:#fff;border-radius:8px;font-size:13px;font-weight:500;white-space:nowrap;}
  .agent-breadcrumb-sep{color:var(--text-soft,#5B6B7C);font-size:14px;}
  .agent-breadcrumb-prompt{color:var(--text-main);font-size:13px;white-space:nowrap;}
  .conversation-breadcrumb-name{display:inline-block;padding:6px 12px;background:var(--input-bg);border:1px solid var(--input-border);color:var(--text-main);border-radius:8px;font-size:13px;white-space:nowrap;}
  .topbar-right{display:flex;align-items:center;gap:8px;}
  .icon-btn{width:36px;height:36px;border-radius:8px;border:1px solid var(--topbar-border);background: var(--topbar-bg);cursor:pointer;display:flex;align-items:center;justify-content:center;color: var(--topbar-crumb);font-size:16px;transition: background 0.15s;}
  .icon-btn:hover{background: var(--surface);color: var(--topbar-link);}
  .settings-popover{position:absolute;top:54px;right:16px;z-index:50;background: var(--card-bg);border:1px solid var(--line);border-radius:10px;box-shadow:0 8px 24px -12px rgba(31,56,100,0.25);padding:12px;min-width:180px;display:flex;flex-direction:column;gap:8px;}
  .settings-popover .sp-item{display:flex;justify-content:space-between;align-items:center;gap:16px;font-size:13px;}
  .settings-popover .sp-label{color: var(--text-soft);}
  .settings-popover .sp-value{color: var(--text-main);font-family:'IBM Plex Mono',monospace;}

  /* Empty State */
  .empty-state{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:40px;text-align:center;}
  .empty-icon{width:auto;min-width:80px;height:auto;padding:16px 28px;border-radius:20px;background: linear-gradient(135deg, var(--navy) 0%, var(--navy-light) 100%);display:flex;align-items:center;justify-content:center;color: var(--gold-light);font-family:'Fraunces', serif;font-size:32px;font-weight:600;margin-bottom:24px;white-space:nowrap;}
  .empty-state h3{font-family:'Fraunces', serif;font-size:28px;font-weight:600;color: var(--section-title);margin-bottom:12px;}
  .empty-state p{color: var(--text-soft);font-size:15px;max-width:480px;margin-bottom:32px;}
  .quick-actions{display:grid;grid-template-columns: repeat(2, 1fr);gap:12px;max-width:420px;margin:0 auto;}
  .quick-btn{padding:16px 20px;background: var(--surface);border:1px solid var(--surface-border);border-radius:12px;font-size:14px;color: var(--text-main);cursor:pointer;display:flex;flex-direction:column;align-items:center;gap:8px;transition: border-color 0.15s, background 0.15s;}
  .quick-btn:hover{border-color: var(--navy);background: var(--input-bg);}
  .quick-btn .icon{font-size:24px;}
  .quick-btn .label{font-weight:500;}
  .quick-btn .desc{font-size:12px;color: var(--text-soft);}

  /* Input Area */
  .input-area{padding:20px 24px 24px;background: var(--body-bg);flex-shrink:0;position:relative;z-index:10;}
  .input-wrapper{max-width:760px;margin:0 auto;}
  .input-box{display:flex;align-items:flex-end;gap:12px;background: var(--surface);border:1px solid var(--surface-border);border-radius:14px;padding:12px 16px;transition: border-color 0.15s, box-shadow 0.15s;}
  .input-box:focus-within{border-color: var(--navy);box-shadow: 0 0 0 3px rgba(31,56,100,0.1);}
  /* The composer sits at the bottom of a flex layout, so the browser's native
     resize handle appears to grow upward. Content-driven auto-grow is clearer. */
  .input-box textarea{flex:1;border:none;outline:none;resize:none;font-family: inherit;font-size:15px;line-height:1.5;min-height:40px;max-height:400px;color: var(--text-main);background: transparent;}
  .input-box textarea::placeholder{color: var(--text-soft);}
  .input-actions{display:flex;align-items:center;gap:8px;flex-shrink:0;}
  .attach-btn{width:32px;height:32px;border-radius:8px;border:none;background: transparent;cursor:pointer;display:flex;align-items:center;justify-content:center;color: var(--text-soft);font-size:18px;transition: background 0.15s, color 0.15s;}
  .attach-btn:hover{background: var(--input-bg);color: var(--text-main);}
  .image-generate-btn{padding:7px 10px;border:1px solid var(--input-border);border-radius:8px;background:var(--input-bg);color:var(--text-main);font:600 12px inherit;cursor:pointer;white-space:nowrap;}
  .image-generate-btn:hover{border-color:var(--navy);}
  .image-generate-btn:disabled{opacity:.55;cursor:wait;}
  .send-btn{padding:8px 16px;background: var(--navy);color:#fff;border:none;border-radius:8px;font-size:14px;font-weight:500;cursor:pointer;display:flex;align-items:center;gap:6px;transition: background 0.15s;}
  .send-btn:hover{background: var(--navy-light);}
  .send-btn:disabled{opacity:.6;cursor:wait;}
  .input-hint{text-align:center;margin-top:10px;font-size:12px;color: var(--text-soft);}
  #chat-image-preview img{max-width:100%;}
  /* Markdown trong bubble AI */
  [style*="align-self:flex-start"] p{margin:0 0 8px;}
  [style*="align-self:flex-start"] p:last-child{margin-bottom:0;}
  [style*="align-self:flex-start"] ul,[style*="align-self:flex-start"] ol{margin:0 0 8px;padding-left:20px;}
  [style*="align-self:flex-start"] li{margin-bottom:4px;}
  [style*="align-self:flex-start"] h1,[style*="align-self:flex-start"] h2,[style*="align-self:flex-start"] h3{font-size:16px;font-weight:600;margin:10px 0 6px;}
  [style*="align-self:flex-start"] code{background:var(--paper);border:1px solid var(--line);border-radius:4px;padding:1px 5px;font-family:'IBM Plex Mono',monospace;font-size:12px;}
  [style*="align-self:flex-start"] pre{background:var(--navy-deep);color:#CCE3DE;padding:12px;border-radius:8px;overflow-x:auto;margin:8px 0;}
  [style*="align-self:flex-start"] pre code{background:transparent;border:none;padding:0;color:inherit;}
  [style*="align-self:flex-start"] table{border-collapse:collapse;margin:8px 0;}
  [style*="align-self:flex-start"] th,[style*="align-self:flex-start"] td{border:1px solid var(--line);padding:6px 10px;font-size:13px;}
  [style*="align-self:flex-start"] th{background:var(--paper);font-weight:600;}
  [style*="align-self:flex-start"] a{color:var(--navy);text-decoration:underline;}
  [style*="align-self:flex-start"] blockquote{border-left:3px solid var(--sage);margin:8px 0;padding-left:12px;color:var(--text-soft);}
  #chat-messages{max-width:100%;}

  @media (max-width: 860px){
    .quick-actions{grid-template-columns:1fr;}
    .sidebar{width:260px;}
  }
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/marked@12.0.2/marked.min.js"></script>
<script>
  window.MathJax = {
    tex: { inlineMath: [['\\(', '\\)']], displayMath: [['\\[', '\\]']] },
    svg: { fontCache: 'global' },
  };
</script>
<script src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-svg.js" async></script>
<script src="{{ asset('js/agent-workspace-chat.js') }}?v={{ filemtime(public_path('js/agent-workspace-chat.js')) }}"></script>
@endpush

@include('ai-plus.agent-workspace.agents._agent-form-modal')
