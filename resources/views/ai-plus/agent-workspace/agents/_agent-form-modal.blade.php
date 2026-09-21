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
      <div class="form-group">
        <label for="system_prompt">System Prompt</label>
        <textarea id="system_prompt" name="system_prompt" rows="6" placeholder="Instructions for the AI (e.g., 'You are a helpful math teacher...')"></textarea>
        <small class="form-hint">This prompt guides the agent's behavior. Leave empty to use default.</small>
      </div>
      <div class="form-group">
        <label for="knowledge">Knowledge</label>
        <div class="knowledge-dropzone" id="knowledge-dropzone">
          <input type="file" id="knowledge" name="knowledge[]" multiple accept=".pdf,.doc,.docx,.txt,.csv,.xls,.xlsx,.png,.jpg,.jpeg,.gif,.webp" hidden>
          <div class="knowledge-dropzone-inner">
            <div class="knowledge-dropzone-title">Drag & drop files here</div>
            <div class="knowledge-dropzone-sub">or click to select</div>
          </div>
        </div>
        <div class="knowledge-file-chips" id="knowledge-file-chips" aria-live="polite"></div>
        <div class="knowledge-saved-list" id="knowledge-saved-list" aria-live="polite"></div>
        <small class="form-hint">Up to 10 files per Agent · 5 MB per file · 25 MB total. Upload documents (pdf, docx, xlsx, txt, csv…) for the agent to reference when answering.</small>
        <div class="form-hint" id="knowledge-limit-status" aria-live="polite"></div>
      </div>
      <div class="form-group checkbox-group">
        <input type="checkbox" id="is_shared" name="is_shared" value="1">
        <label for="is_shared">Share with school (publish to Sharing &amp; Showcase)</label>
      </div>
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
</script>
<script src="{{ asset('js/agent-modal.js') }}"></script>
@endpush
