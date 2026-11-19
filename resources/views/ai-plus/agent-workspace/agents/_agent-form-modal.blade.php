<!-- Create/Edit Agent Modal (dùng chung cho trang Agents + Save as Agent trong chat) -->
<div class="modal-overlay" id="agent-modal" style="display:none;">
  <div class="modal" role="dialog" aria-modal="true" aria-labelledby="modal-title">
    <div class="modal-header">
      <h3 id="modal-title">Create Agent</h3>
      <button class="modal-close" id="modal-close" type="button" aria-label="Close">&times;</button>
    </div>
    <form id="agent-form" method="POST" enctype="multipart/form-data">
      @csrf
      <input type="hidden" name="_method" value="POST" id="form-method">
      <input type="hidden" name="agent_id" id="agent-id">
      <input type="hidden" name="template_id" id="agent-template-id">
      <div class="form-group">
        <label for="title">Title <span class="required">*</span></label>
        <input type="text" id="title" name="title" required maxlength="255" placeholder="e.g., Math Quiz Generator">
      </div>
      <div class="form-group">
        <label for="description">Description</label>
        <textarea id="description" name="description" rows="3" placeholder="What does this agent do?"></textarea>
      </div>
      <div class="agent-classification-fields">
        <div class="form-group">
          <label for="agent-category-group">Group</label>
          <select id="agent-category-group" name="category_group">
            <option value="">Select a group</option>
            @foreach(\App\Support\AgentCategories::GROUPS as $group => $label)
              <option value="{{ $group }}">{{ $label }}</option>
            @endforeach
          </select>
        </div>
        <div class="form-group">
          <label for="agent-category">Category</label>
          <select id="agent-category" name="category" aria-describedby="agent-category-hint" disabled>
            <option value="">Select a group first</option>
          </select>
          <small class="form-hint" id="agent-category-hint">Choose the category that best describes what this Agent helps people do.</small>
        </div>
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
        <label for="system_prompt">Instructions (System Prompt)</label>
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
        <small class="form-hint">Choose up to {{ config('agent_connections.max_connections', 10) }} Agents to consult and describe their roles. The chat’s specialist model and your LSTS AI+ Credits apply to these calls. Specialists cannot call other Agents.</small>
        <input type="search" id="agent-connections-search" aria-label="Find an Agent to connect" placeholder="Find an Agent…">
        <div id="agent-connections-list" aria-label="Agents available to connect"></div>
        <div id="agent-connections-status" class="form-hint" role="status"></div>
      </fieldset>
      <div class="form-group checkbox-group">
        <input type="checkbox" id="is_shared" name="is_shared" value="1">
        <label for="is_shared">Share this Agent</label>
      </div>
      <div class="form-group" id="sharing-audience-group" hidden>
        <span id="sharing-audience-label">Who can access this Agent?</span>
        <small class="form-hint" id="sharing-audience-description">Select every audience that should have access. A person can match any selected option.</small>
        <div class="sharing-audience-options" role="group" aria-labelledby="sharing-audience-label" aria-describedby="sharing-audience-description">
          <label class="sharing-audience-choice"><input type="checkbox" id="sharing-school" name="sharing_scopes[]" value="school" checked> <span>Everyone at school</span></label>
          <label class="sharing-audience-choice"><input type="checkbox" id="sharing-department" name="sharing_scopes[]" value="department" @disabled(! trim(auth()->user()?->department ?? ''))> <span>My department / unit</span><small class="sharing-department-detail" id="sharing-department-hint" hidden></small></label>
          <div class="sharing-people-option">
            <label class="sharing-audience-choice" for="sharing-users"><input type="checkbox" id="sharing-users" name="sharing_scopes[]" value="users"> <span>Specific people</span></label>
            <div id="sharing-people-picker" hidden>
              <label for="sharing-user-search" class="form-hint sharing-search-label">Find people</label>
              <input type="search" id="sharing-user-search" autocomplete="off" maxlength="100" placeholder="Search name or email…" aria-describedby="sharing-search-status" aria-controls="sharing-search-results">
              <div id="sharing-search-status" class="form-hint" role="status" aria-live="polite"></div>
              <div id="sharing-search-results" aria-label="Matching people"></div>
              <div id="sharing-selected-users" aria-label="Selected people"></div>
            </div>
          </div>
        </div>
        <small class="form-hint">Only your chosen audience can discover this Agent in Sharing &amp; Showcase. Removing access stops future use of the original Agent; copies already created remain with their owners.</small>
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
  window.AgentModalConfig.sharingUsersUrl = @json(route('ai-plus.agent-workspace.agents.sharing-users'));
  window.AgentModalConfig.department = @json(trim(auth()->user()?->department ?? ''));
  window.AgentModalConfig.maxConnections = {{ config('agent_connections.max_connections', 10) }};
  window.AgentModalConfig.categories = {{ Illuminate\Support\Js::from(\App\Support\AgentCategories::CATEGORIES) }};
  window.AgentModalConfig.templateDraft = {{ Illuminate\Support\Js::from(session('agent_template_draft')) }};
</script>
<script src="{{ asset('js/agent-modal.js') }}?v={{ filemtime(public_path('js/agent-modal.js')) }}"></script>
@endpush

@pushOnce('styles')
<style>
  .agent-classification-fields {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    align-items: start;
    gap: 12px;
  }
  .agent-classification-fields .form-group { margin-bottom: 4px; }
  .agent-classification-fields select {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid var(--line);
    border-radius: 8px;
    background: var(--card-bg);
    color: var(--ink);
    font: inherit;
  }
  #agent-category-hint { line-height: 1.4; }
  @media (max-width: 540px) {
    .agent-classification-fields { grid-template-columns: 1fr; gap: 6px; }
  }
  #agent-connections-field[hidden]{display:none}.agent-connections-field{border:1px solid var(--line,#ddd);border-radius:10px;padding:12px;color:var(--ink,#22303f)}#agent-connections-list{max-height:240px;overflow-y:auto;margin-top:10px}.agent-connection-row{padding:10px 0;border-bottom:1px solid var(--line,#ddd)}.agent-connection-choice{display:flex;align-items:center;gap:8px}.agent-connection-choice input{width:auto;flex:none}.agent-connection-role{display:block;margin-top:8px;width:100%;box-sizing:border-box}#agent-connections-search{margin-top:10px;padding:10px 12px;border:1px solid var(--line,#ddd);border-radius:10px;background:var(--card-bg,#fff);color:var(--ink,#22303f);font:inherit}.agent-connections-field legend{font-weight:600}
  #sharing-audience-group[hidden],#sharing-people-picker[hidden],#sharing-department-hint[hidden]{display:none}#sharing-audience-label{font-size:18px;font-weight:600;color:var(--ink,#22303f)}#sharing-audience-group>.form-hint{line-height:1.45}.sharing-audience-options{display:grid;gap:8px;margin:4px 0 8px}.sharing-audience-choice{display:flex;align-items:center;gap:10px;min-height:44px;box-sizing:border-box;margin:0;padding:9px 12px;border:1px solid var(--line,#ddd);border-radius:10px;background:var(--card-bg,#fff);color:var(--ink,#22303f);font-size:15px;cursor:pointer}.sharing-audience-choice:hover{border-color:var(--sage,#2a6f61);background:var(--paper,#f4fbf9)}.sharing-audience-choice input{width:18px;height:18px;flex:none;margin:0;accent-color:var(--sage,#2a6f61)}.sharing-department-detail{margin-left:auto;color:var(--ink-soft,#4f6a63);font-size:13px;font-weight:500}.sharing-people-option{display:grid;gap:8px}.sharing-people-option>#sharing-people-picker{margin:0 0 4px 28px;padding-left:14px;border-left:2px solid var(--line,#ddd)}#sharing-people-picker .sharing-search-label{display:block;margin:0 0 6px;font-size:14px;font-weight:500}#sharing-user-search{box-sizing:border-box;width:100%;padding:10px 12px;border:1px solid var(--line,#ddd);border-radius:8px;background:var(--input-bg,#fff);color:var(--ink,#22303f);font:inherit}#sharing-search-status:not(:empty){margin-top:6px}#sharing-search-results{max-height:200px;overflow-y:auto}.sharing-person-result{display:block;text-align:left;width:100%;padding:10px;border:0;border-bottom:1px solid var(--line,#ddd);background:var(--card-bg,#fff);color:var(--ink,#22303f);font:inherit;cursor:pointer}.sharing-person-result:hover,.sharing-person-result:focus-visible{background:var(--paper,#f4fbf9)}.sharing-person-result small{display:block;overflow-wrap:anywhere;color:var(--ink-soft,#4f6a63)}#sharing-selected-users{display:flex;gap:6px;flex-wrap:wrap;margin-top:8px}.sharing-person-chip{display:inline-flex;gap:6px;align-items:center;max-width:100%;padding:5px 9px;border:1px solid var(--line,#ddd);border-radius:8px;background:var(--paper,#f4fbf9);font-size:14px;overflow-wrap:anywhere}.sharing-person-chip button{border:0;background:transparent;color:inherit;cursor:pointer;font:inherit}
  .agent-avatar-editor{display:flex;align-items:center;gap:12px}.agent-avatar-preview{width:56px;height:56px;border-radius:14px;overflow:hidden;background:var(--navy,#1f3864);color:#fff;display:flex;align-items:center;justify-content:center;font-size:26px;flex:none}.agent-avatar-preview img{width:100%;height:100%;object-fit:cover}#agent-avatar + .form-hint{display:block;margin-top:6px}
</style>
@endPushOnce
