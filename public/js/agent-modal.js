// Global agent modal — dùng cho cả trang Agents và Save as Agent trong chat.
// Phụ thuộc: DOM có #agent-modal, #agent-form, #knowledge-dropzone, #knowledge-file-chips.
document.addEventListener('DOMContentLoaded', function () {
  const modal = document.getElementById('agent-modal');
  const form = document.getElementById('agent-form');
  const createBtns = document.querySelectorAll('[data-behavior="create-agent"], #create-agent-btn, #create-first-agent');
  const closeBtn = document.getElementById('modal-close');
  const cancelBtn = document.getElementById('modal-cancel');
  const modalTitle = document.getElementById('modal-title');
  const formMethod = document.getElementById('form-method');
  const agentIdInput = document.getElementById('agent-id');
  const titleInput = document.getElementById('title');
  const descInput = document.getElementById('description');
  const promptInput = document.getElementById('system_prompt');
  const sharedInput = document.getElementById('is_shared');

  if (!modal || !form) return;

  // Knowledge: dropzone + file chips
  const dropzone = document.getElementById('knowledge-dropzone');
  const fileInput = document.getElementById('knowledge');
  const chipsContainer = document.getElementById('knowledge-file-chips');
  const savedList = document.getElementById('knowledge-saved-list');

  // Danh sách file đã lưu khi edit (path), để gửi knowledge_remove
  let savedFiles = [];
  let removedPaths = [];

  // Danh sách file user đã chọn (tích lũy) — vì <input type=file> tự reset mỗi lần mở dialog.
  let allFiles = [];

  function syncInputFromAllFiles() {
    if (!fileInput) return;
    const dt = new DataTransfer();
    allFiles.forEach((f) => dt.items.add(f));
    fileInput.files = dt.files;
    renderChips(allFiles);
  }

  function clearKnowledgeUI() {
    if (fileInput) fileInput.value = '';
    if (chipsContainer) chipsContainer.innerHTML = '';
    if (savedList) savedList.innerHTML = '';
    savedFiles = [];
    removedPaths = [];
    allFiles = [];
  }

  function renderChips(files) {
    if (!chipsContainer) return;
    chipsContainer.innerHTML = '';

    Array.from(files || []).forEach((f, idx) => {
      const chip = document.createElement('div');
      chip.className = 'knowledge-chip';

      const name = document.createElement('div');
      name.className = 'knowledge-chip-name';
      name.textContent = f.name;

      const x = document.createElement('button');
      x.type = 'button';
      x.className = 'knowledge-chip-x';
      x.setAttribute('aria-label', 'Remove file ' + f.name);
      x.textContent = '×';
      x.addEventListener('click', () => {
        allFiles.splice(idx, 1);
        syncInputFromAllFiles();
      });

      chip.appendChild(name);
      chip.appendChild(x);
      chipsContainer.appendChild(chip);
    });
  }

  function renderSavedFiles(agentKnowledge) {
    if (!savedList) return;

    // Agent knowledge từ JSON show API có dạng array [{path, original_name}]
    const knowledge = agentKnowledge || [];
    const parsed = Array.isArray(knowledge) ? knowledge : [];

    savedFiles = parsed;
    savedList.innerHTML = '';

    if (parsed.length === 0) {
      savedList.style.display = 'none';
      return;
    }

    savedList.style.display = 'block';
    const heading = document.createElement('div');
    heading.className = 'knowledge-saved-heading';
    heading.textContent = 'Files đã lưu (click × để xóa):';
    savedList.appendChild(heading);

    parsed.forEach((file, idx) => {
      const row = document.createElement('div');
      row.className = 'knowledge-saved-row';

      const name = document.createElement('div');
      name.className = 'knowledge-saved-name';
      name.textContent = file.original_name || file.path || 'file';

      const x = document.createElement('button');
      x.type = 'button';
      x.className = 'knowledge-chip-x';
      x.setAttribute('aria-label', 'Remove saved file');
      x.textContent = '×';

      x.addEventListener('click', () => {
        removedPaths.push(file.path);
        // Đánh dấu row là sẽ xóa
        row.classList.add('removing');
        row.style.opacity = '0.5';
        x.disabled = true;
      });

      row.appendChild(name);
      row.appendChild(x);
      savedList.appendChild(row);
    });

    // Hidden input lưu paths sẽ xóa
    if (!document.getElementById('knowledge_remove')) {
      const hidden = document.createElement('input');
      hidden.type = 'hidden';
      hidden.name = 'knowledge_remove';
      hidden.id = 'knowledge_remove';
      form.appendChild(hidden);
    }
  }

  // Dropzone events
  if (dropzone && fileInput) {
    dropzone.addEventListener('click', () => fileInput.click());

    ['dragenter', 'dragover'].forEach((evt) => {
      dropzone.addEventListener(evt, (e) => {
        e.preventDefault();
        dropzone.classList.add('is-dragover');
      });
    });

    ['dragleave', 'drop'].forEach((evt) => {
      dropzone.addEventListener(evt, (e) => {
        e.preventDefault();
        dropzone.classList.remove('is-dragover');
      });
    });

    // Gộp file vào danh sách tích lũy (không ghi đè file đã chọn trước đó).
    function mergeFiles(newFiles) {
      Array.from(newFiles || []).forEach((f) => {
        // Tránh trùng file trùng tên (chọn lại) — ghi đè mới nhất.
        const existingIdx = allFiles.findIndex((ef) => ef.name === f.name && ef.size === f.size);
        if (existingIdx >= 0) allFiles[existingIdx] = f;
        else allFiles.push(f);
      });
      syncInputFromAllFiles();
    }

    dropzone.addEventListener('drop', (e) => {
      e.preventDefault();
      mergeFiles(e.dataTransfer.files);
    });

    fileInput.addEventListener('change', (e) => {
      mergeFiles(e.target.files);
    });
  }

  function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str == null ? '' : String(str);
    return div.innerHTML;
  }

  function openModal(agent = null) {
    modal.style.display = 'flex';
    form.reset();
    clearKnowledgeUI();

    if (agent) {
      modalTitle.textContent = 'Edit Agent';
      formMethod.value = 'PUT';
      agentIdInput.value = agent.id;
      if (titleInput) titleInput.value = agent.title || '';
      if (descInput) descInput.value = agent.description || '';
      if (promptInput) promptInput.value = agent.system_prompt || '';
      if (sharedInput) sharedInput.checked = !!agent.is_shared;
      renderSavedFiles(agent.knowledge_files || []);
    } else {
      modalTitle.textContent = 'Create Agent';
      formMethod.value = 'POST';
      agentIdInput.value = '';
    }
  }

  function closeModal() {
    modal.style.display = 'none';
    form.reset();
    clearKnowledgeUI();
  }

  createBtns.forEach((btn) => btn?.addEventListener('click', () => openModal()));

  closeBtn?.addEventListener('click', closeModal);
  cancelBtn?.addEventListener('click', closeModal);
  modal.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });

  // Edit buttons (delegated) — chỉ áp dụng trên trang Agents
  const agentsGrid = document.getElementById('agents-grid');
  if (agentsGrid) {
    agentsGrid.addEventListener('click', async (e) => {
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
        sessionStorage.setItem('selectedAgentId', agentId);
        // Điều hướng về chat với agent
        window.location.href = '/ai-plus/agent-workspace';
      }

      if (deleteBtn) {
        if (!confirm('Delete this agent?')) return;
        const agentId = deleteBtn.dataset.agentId;
        const res = await fetch(`/ai-plus/agent-workspace/agents/${agentId}`, {
          method: 'DELETE',
          headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
        });
        if (res.ok) {
          window.location.reload();
        } else {
          alert('Failed to delete agent');
        }
      }
    });
  }

  // Form submit via FormData (multipart hỗ trợ file)
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const isEdit = formMethod.value === 'PUT';
    const agentId = agentIdInput.value;
    const url = isEdit ? `/ai-plus/agent-workspace/agents/${agentId}` : '/ai-plus/agent-workspace/agents';
    const method = isEdit ? 'PUT' : 'POST';

    const formData = new FormData(form);
    formData.set('is_shared', sharedInput && sharedInput.checked ? '1' : '0');

    // Gửi danh sách paths sẽ xóa (mỗi path 1 entry)
    if (removedPaths.length > 0) {
      // Xóa hidden input cũ rồi set lại
      const existing = document.getElementById('knowledge_remove');
      if (existing) formData.delete('knowledge_remove');
      removedPaths.forEach((p) => formData.append('knowledge_remove[]', p));
    }

    const res = await fetch(url, {
      method,
      headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
      body: formData,
    });

    if (res.ok) {
      closeModal();
      window.location.reload();
    } else {
      const err = await res.json();
      const message = err.message || (err.errors ? Object.values(err.errors).flat().join(' ') : 'Failed to save agent');
      alert(message);
      // Nếu là lỗi 422, giữ modal mở để user sửa
      if (res.status === 422) return;
      closeModal();
    }
  });

  // Save as Agent (topbar chat) — gọi từ ngoài nếu cần
  const saveAsAgentBtn = document.querySelector('[data-behavior="save-as-agent"]');
  if (saveAsAgentBtn) {
    saveAsAgentBtn.addEventListener('click', () => openModal());
  }

  // Expose cho các context khác (vd Save as Agent từ chat muốn mở với title)
  window.openAgentModal = openModal;
});