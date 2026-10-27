<!-- Create/Edit Agent Modal (dùng chung cho trang Agents + Save as Agent trong chat) -->
<div class="modal-overlay" id="agent-modal" style="display:none;">
  <div class="modal">
    <div class="modal-header">
      <h3 id="modal-title">Create Agent</h3>
      <button class="modal-close" id="modal-close">&times;</button>
    </div>
    <form id="agent-form" method="POST" enctype="multipart/form-data">
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
      <div class="form-group agent-avatar-field">
        <label for="agent-avatar">Agent avatar <span class="form-hint">(optional)</span></label>
        <div class="agent-avatar-editor">
          <div class="agent-avatar-preview" id="agent-avatar-preview" aria-label="Agent avatar preview">🤖</div>
          <div>
            <input type="file" id="agent-avatar" name="avatar" accept="image/jpeg,image/png,image/webp">
            <small class="form-hint">JPG, PNG, or WebP · up to 2 MB.</small>
          </div>
        </div>
      </div>
      <div class="form-group">
        <label for="system_prompt">System Prompt</label>
        <textarea id="system_prompt" name="system_prompt" rows="6" placeholder="Instructions for the AI (e.g., 'You are a helpful math teacher...')"></textarea>
        <small class="form-hint">This prompt guides the agent's behavior. Leave empty to use default.</small>
      </div>
      <div class="form-group">
        <label for="knowledge">Knowledge</label>
        <div class="knowledge-dropzone" id="knowledge-dropzone">
          <input type="file" id="knowledge" name="knowledge[]" multiple accept=".html,.pdf,.doc,.docx,.txt,.csv,.xls,.xlsx,.png,.jpg,.jpeg,.gif,.webp" hidden>
          <div class="knowledge-dropzone-inner">
            <div class="knowledge-dropzone-title">Drag & drop files here</div>
            <div class="knowledge-dropzone-sub">or click to select</div>
          </div>
        </div>
        <div class="knowledge-file-chips" id="knowledge-file-chips" aria-live="polite"></div>
        <div class="knowledge-saved-list" id="knowledge-saved-list" aria-live="polite"></div>
        <small class="form-hint">Up to {{ \App\Services\KnowledgeService::MAX_AGENT_FILES }} files per Agent · {{ \App\Services\KnowledgeService::MAX_AGENT_FILE_SIZE_MB }} MB per file · {{ \App\Services\KnowledgeService::MAX_AGENT_TOTAL_SIZE_MB }} MB total. Upload documents (HTML, PDF, DOCX, XLSX, TXT, CSV…) or images (JPG, PNG, GIF, WebP). Text in images is read with OCR and added to the agent’s Knowledge.</small>
        <div class="form-hint" id="knowledge-limit-status" aria-live="polite"></div>
      </div>
      <fieldset class="form-group agent-connections-field" id="agent-connections-field" hidden>
        <legend>Connected Agents <span class="form-hint">(optional)</span></legend>
        <small class="form-hint">Choose up to {{ config('agent_connections.max_connections', 10) }} Agents this Agent may consult, and describe their roles. It chooses which help is needed for each request. All calls use the chat's selected model and your AI+ Credits. Connected Agents do not call further Agents.</small>
        <input type="search" id="agent-connections-search" aria-label="Find an Agent to connect" placeholder="Find an Agent…">
        <div id="agent-connections-list" aria-label="Agents available to connect"></div>
        <div id="agent-connections-status" class="form-hint" role="status"></div>
      </fieldset>
      <div class="form-group checkbox-group">
        <input type="checkbox" id="is_shared" name="is_shared" value="1">
        <label for="is_shared">Share with school (publish to Sharing &amp; Showcase)</label>
      </div>
      <fieldset class="form-group" id="sharing-access-group" hidden>
        <legend>Sharing access <span class="required">*</span></legend>
        <label class="radio-option"><input type="radio" name="sharing_access" value="use_only" checked> Use-only <span class="form-hint">(recommended — people can chat with the agent but cannot see or copy its setup)</span></label>
        <label class="radio-option"><input type="radio" name="sharing_access" value="copy"> Allow users to copy and edit <span class="form-hint">(copies the full agent, including Knowledge files)</span></label>
      </fieldset>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" id="modal-cancel">Cancel</button>
        <button type="submit" class="btn btn-primary" id="modal-submit">Save</button>
      </div>
    </form>
  </div>
</div>

@push('scripts')
<script>
  window.AgentModalConfig = window.AgentModalConfig || {};
  window.AgentModalConfig.baseKnowledgeRoute = '{{ route("ai-plus.agent-workspace.agents.index") }}';
  window.AgentModalConfig.maxKnowledgeFiles = {{ \App\Services\KnowledgeService::MAX_AGENT_FILES }};
  window.AgentModalConfig.maxKnowledgeFileBytes = {{ \App\Services\KnowledgeService::MAX_AGENT_FILE_SIZE_KB * 1024 }};
  window.AgentModalConfig.maxKnowledgeTotalBytes = {{ \App\Services\KnowledgeService::MAX_AGENT_TOTAL_SIZE_KB * 1024 }};
  window.AgentModalConfig.connectionOptionsUrl = @json(route('ai-plus.agent-workspace.agents.connection-options'));
  window.AgentModalConfig.maxConnections = {{ config('agent_connections.max_connections', 10) }};
</script>
<script src="{{ asset('js/agent-modal.js') }}?v={{ filemtime(public_path('js/agent-modal.js')) }}"></script>
@endpush

@pushOnce('styles')
<style>
  #agent-connections-field[hidden]{display:none}.agent-connections-field{border:1px solid var(--line,#ddd);border-radius:10px;padding:12px;color:var(--ink,#22303f)}#agent-connections-list{max-height:240px;overflow-y:auto;margin-top:10px}.agent-connection-row{padding:10px 0;border-bottom:1px solid var(--line,#ddd)}.agent-connection-choice{display:flex;align-items:center;gap:8px}.agent-connection-choice input{width:auto;flex:none}.agent-connection-role{display:block;margin-top:8px;width:100%;box-sizing:border-box}#agent-connections-search{margin-top:10px;padding:10px 12px;border:1px solid var(--line,#ddd);border-radius:10px;background:var(--card-bg,#fff);color:var(--ink,#22303f);font:inherit}.agent-connections-field legend{font-weight:600}
  .agent-avatar-editor{display:flex;align-items:center;gap:12px}.agent-avatar-preview{width:56px;height:56px;border-radius:14px;overflow:hidden;background:var(--navy,#1f3864);color:#fff;display:flex;align-items:center;justify-content:center;font-size:26px;flex:none}.agent-avatar-preview img{width:100%;height:100%;object-fit:cover}#agent-avatar + .form-hint{display:block;margin-top:6px}
</style>
@endPushOnce
