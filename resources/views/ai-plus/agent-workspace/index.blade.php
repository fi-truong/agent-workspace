@extends('layouts.ai-plus')

@section('title', 'Agent Workspace — AI+ LSTS')

@section('breadcrumb', 'Agent Workspace')

@section('content')
<div class="app">
  <!-- Sidebar -->
  <aside class="sidebar">
    <div class="sidebar-header">
      <!-- <a href="{{ route('ai-plus.index') }}">← Back to AI+</a> -->
      <h2>Agent Workspace</h2>
    </div>

    <!-- Workspace Type Tabs -->
    <div class="workspace-tabs">
      <a href="{{ route('ai-plus.agent-workspace.index') }}" class="ws-tab active">
        <span class="icon">💬</span>
        <span>Chat</span>
      </a>
      <a href="{{ route('ai-plus.agent-workspace.agents.index') }}" class="ws-tab">
        <span class="icon">🤖</span>
        <span>Agents</span>
      </a>
      <a href="#" class="ws-tab" title="Coming soon">
        <span class="icon">⚡</span>
        <span>Workflows</span>
      </a>
    </div>

    <div class="chat-list">
      <div class="chat-list-section">
        Today
        <button class="add-btn" title="New chat">+</button>
      </div>
      @foreach($conversations as $conv)
      <div class="chat-item {{ $loop->first ? 'active' : '' }}">
        <span class="item-icon chat">💬</span>
        <span class="title">{{ $conv['title'] }}</span>
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

      <div class="chat-list-section">
        Workflows
        <button class="add-btn" title="New workflow">+</button>
      </div>
      @foreach($workflows as $workflow)
      <div class="chat-item">
        <span class="item-icon workflow">⚡</span>
        <span class="title">{{ $workflow['title'] }}</span>
      </div>
      @endforeach
    </div>

    <div class="sidebar-footer">
      <div class="user-info">
        <div class="user-avatar">{{ $userInitials }}</div>
        <div class="user-details">
          <div class="user-name">{{ $userName }}</div>
          <div class="user-quota">
            {{ $promptsUsed }}/{{ $promptsLimit }} prompts today
            <div class="quota-bar"><div class="quota-fill" style="width: {{ ($promptsUsed / $promptsLimit) * 100 }}%"></div></div>
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
      </div>
      <div class="topbar-right">
        <button class="icon-btn" title="Upload file (attach)">📎</button>
        <button class="icon-btn" title="Save as Agent">🤖</button>
        <button class="icon-btn" title="Export conversation">↓</button>
        <button class="icon-btn" title="Settings">⚙</button>
      </div>
    </div>

    <!-- Empty State for new chat -->
    <div class="empty-state">
      <div class="empty-icon">Agent Workspace</div>
      <h3>What would you like to create?</h3>
      <p>Start a conversation, build a custom agent, or set up an automated workflow to streamline your work.</p>

      <div class="quick-actions">
        @foreach($quickActions as $action)
        <button class="quick-btn">
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
            <button class="attach-btn" title="Attach file">📎</button>
            <button class="send-btn">Send →</button>
          </div>
        </div>
        <div class="input-hint">
          Press Enter to send, Shift+Enter for new line • Your data is protected by PII filtering • Upload files for analysis
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
  .add-btn{background:none;border:none;color: var(--text-soft);cursor:pointer;font-size:16px;padding:0;}
  .add-btn:hover{color: var(--gold-light);}
  .chat-item{padding:10px 12px;border-radius:8px;cursor:pointer;display:flex;align-items:center;gap:10px;transition: background 0.15s;}
  .chat-item:hover{background: var(--input-bg);}
  .chat-item.active{background: var(--surface);}
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
  .topbar{padding:16px 24px;background: var(--topbar-bg);border-bottom:1px solid var(--topbar-border);display:flex;align-items:center;justify-content:space-between;flex-shrink:0;}
  .workspace-title{display:flex;align-items:center;gap:10px;}
  .workspace-title h1{font-size:18px;font-weight:600;color: var(--text-main);margin:0;}
  .ws-type-badge{padding:4px 10px;background: var(--input-bg);border:1px solid var(--input-border);border-radius:6px;font-size:11px;font-family:'IBM Plex Mono', monospace;color: var(--text-soft);}
  .model-selector{padding:8px 12px;background: var(--input-bg);border:1px solid var(--input-border);border-radius:8px;font-size:13px;color: var(--input-text);cursor:pointer;display:flex;align-items:center;gap:8px;}
  .model-selector .badge{background: var(--navy);color:#fff;padding:2px 8px;border-radius:4px;font-size:11px;font-family:'IBM Plex Mono', monospace;}
  .topbar-right{display:flex;align-items:center;gap:8px;}
  .icon-btn{width:36px;height:36px;border-radius:8px;border:1px solid var(--topbar-border);background: var(--topbar-bg);cursor:pointer;display:flex;align-items:center;justify-content:center;color: var(--topbar-crumb);font-size:16px;transition: background 0.15s;}
  .icon-btn:hover{background: var(--surface);color: var(--topbar-link);}

  /* Empty State */
  .empty-state{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:40px;text-align:center;}
  .empty-icon{width:auto;min-width:80px;height:auto;padding:16px 28px;border-radius:20px;background: linear-gradient(135deg, var(--navy) 0%, var(--navy-light) 100%);display:flex;align-items:center;justify-content:center;color: var(--gold-light);font-family:'Fraunces', serif;font-size:32px;font-weight:600;margin-bottom:24px;white-space:nowrap;}
  .empty-state h3{font-family:'Fraunces', serif;font-size:28px;font-weight:600;color: var(--section-title);margin-bottom:12px;}
  .empty-state p{color: var(--text-soft);font-size:15px;max-width:480px;margin-bottom:32px;}
  .quick-actions{display:grid;grid-template-columns: repeat(3, 1fr);gap:12px;max-width:600px;}
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
  .input-box textarea{flex:1;border:none;outline:none;resize:none;font-family: inherit;font-size:15px;line-height:1.5;min-height:24px;max-height:200px;color: var(--text-main);background: transparent;}
  .input-box textarea::placeholder{color: var(--text-soft);}
  .input-actions{display:flex;align-items:center;gap:8px;flex-shrink:0;}
  .attach-btn{width:32px;height:32px;border-radius:8px;border:none;background: transparent;cursor:pointer;display:flex;align-items:center;justify-content:center;color: var(--text-soft);font-size:18px;transition: background 0.15s, color 0.15s;}
  .attach-btn:hover{background: var(--input-bg);color: var(--text-main);}
  .send-btn{padding:8px 16px;background: var(--navy);color:#fff;border:none;border-radius:8px;font-size:14px;font-weight:500;cursor:pointer;display:flex;align-items:center;gap:6px;transition: background 0.15s;}
  .send-btn:hover{background: var(--navy-light);}
  .input-hint{text-align:center;margin-top:10px;font-size:12px;color: var(--text-soft);}

  @media (max-width: 860px){
    .quick-actions{grid-template-columns:1fr;}
    .sidebar{width:260px;}
  }
</style>
@endpush

@push('scripts')
<script src="{{ asset('js/agent-workspace-chat.js') }}"></script>
@endpush