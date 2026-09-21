@extends('layouts.ai-plus')

@section('title', 'Image Studio — AI+ LSTS')
@section('breadcrumb', 'Agent Workspace')

@section('content')
<script>
window.__IMAGE_INITIAL_RESULTS__ = @json($initialImages);
window.__IMAGE_CONVERSATION_ID__ = @json($activeConversationId);
</script>
<div class="image-workspace">
  <aside class="image-sidebar">
    <div class="image-sidebar-header"><h2>Agent Workspace</h2></div>
    <nav class="workspace-tabs" aria-label="Workspace sections">
      <a href="{{ route('ai-plus.agent-workspace.index') }}" class="ws-tab"><span class="icon">💬</span><span>Chat</span></a>
      <a href="{{ route('ai-plus.agent-workspace.agents.index') }}" class="ws-tab"><span class="icon">🤖</span><span>Agents</span></a>
      <a href="{{ route('ai-plus.agent-workspace.images.index') }}" class="ws-tab active"><span class="icon">🖼️</span><span>Image</span></a>
    </nav>
    <div class="image-history">
      <div class="image-history-heading"><span>Image history</span><a href="{{ route('ai-plus.agent-workspace.images.index') }}" title="New image session">+</a></div>
      @forelse($imageConversations as $conversation)
      <div class="image-history-wrap"><a href="{{ route('ai-plus.agent-workspace.images.index', ['conversation_id' => $conversation->id]) }}" class="image-history-item {{ $activeConversationId === $conversation->id ? 'active' : '' }}"><span>🖼️</span><span>{{ $conversation->title }}</span></a><button type="button" class="image-session-delete" data-conversation-id="{{ $conversation->id }}" title="Delete this image session">×</button></div>
      @empty
      <p class="image-history-empty">Your generated images will appear here.</p>
      @endforelse
    </div>
    <div class="image-sidebar-footer"><div class="image-user-avatar">{{ $userInitials }}</div><div><strong>{{ $userName }}</strong>@if($tokenQuota)<small data-token-quota>{{ number_format($tokenQuota['used']) }} / {{ number_format($tokenQuota['limit']) }} tokens</small>@endif</div></div>
  </aside>
  <main class="image-main">
    <header class="image-topbar"><div><span class="image-model">GPT Image <b>School AI</b></span><h1>Image Studio</h1></div>@if($activeConversationTitle)<span class="image-session-title">{{ $activeConversationTitle }}</span>@endif</header>
    <section class="image-results" id="image-results" aria-live="polite">
      <div class="image-empty" id="image-empty"><div class="image-empty-icon">🖼️</div><h2>Create an image</h2><p>Describe the scene, style, subject, colours, and composition you want. Each request creates one image.</p><div class="image-prompt-examples"><button type="button">A warm illustration of students collaborating in a modern library</button><button type="button">Minimal poster for an AI workshop, navy and gold, no text</button></div></div>
    </section>
    <form class="image-composer" id="image-generation-form"><div class="image-composer-label"><label for="image-prompt">Describe the image you want to create or change</label><button type="button" id="image-start-new">Start new image</button></div><div id="image-active-source" class="image-active-source" hidden></div><div id="image-reference-previews" class="image-reference-previews" hidden></div><div class="image-composer-row"><textarea id="image-prompt" rows="2" maxlength="1000" placeholder="e.g. Change the background to a modern classroom, keeping the person and composition"></textarea><select id="image-model-select" aria-label="Image model">@foreach($imageModels as $id => $model)<option value="{{ $id }}" data-supports-edits="{{ $model['supports_edits'] ? 'true' : 'false' }}" {{ $defaultImageModel === $id ? 'selected' : '' }}>{{ $model['label'] }} — {{ $model['supports_edits'] ? 'Precise' : 'Fast' }}</option>@endforeach</select><button type="button" class="image-attach-button" id="image-reference-trigger" title="Add reference images">📎 <span>Add image</span></button><button type="submit" id="image-generate-submit">Generate image <span>→</span></button></div><input type="file" id="image-reference-input" accept="image/png,image/jpeg,image/webp" multiple hidden><p id="image-model-hint">Choose Flare for speed or Sunburst for precise edits. Paste an image or choose up to 4 PNG, JPEG, or WebP images (4 MB each).</p></form>
  </main>
</div>
@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('css/image-workspace.css') }}?v={{ filemtime(public_path('css/image-workspace.css')) }}">
@endpush
@push('scripts')
<script src="{{ asset('js/image-workspace.js') }}?v={{ filemtime(public_path('js/image-workspace.js')) }}"></script>
@endpush
