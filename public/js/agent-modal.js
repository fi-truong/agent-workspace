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
  const avatarInput = document.getElementById('agent-avatar');
  const avatarPreview = document.getElementById('agent-avatar-preview');
  const sharedInput = document.getElementById('is_shared');
  const sharingAccessGroup = document.getElementById('sharing-access-group');
  const sharingAccessInputs = document.querySelectorAll('input[name="sharing_access"]');

  if (!modal || !form) return;

  // Knowledge: dropzone + file chips
  const dropzone = document.getElementById('knowledge-dropzone');
  const fileInput = document.getElementById('knowledge');
  const chipsContainer = document.getElementById('knowledge-file-chips');
  const savedList = document.getElementById('knowledge-saved-list');
  const limitStatus = document.getElementById('knowledge-limit-status');
  const MAX_KNOWLEDGE_FILES = 10;
  const MAX_KNOWLEDGE_FILE_BYTES = 5 * 1024 * 1024;
  const MAX_KNOWLEDGE_TOTAL_BYTES = 25 * 1024 * 1024;

  // Danh sách file đã lưu khi edit (path), để gửi knowledge_remove
  let savedFiles = [];
  let removedPaths = [];

  // Danh sách file user đã chọn (tích lũy) — vì <input type=file> tự reset mỗi lần mở dialog.
  let allFiles = [];
  let avatarPreviewObjectUrl = null;

  function renderAvatarPreview(url = null) {
    if (!avatarPreview) return;
    if (avatarPreviewObjectUrl) {
      URL.revokeObjectURL(avatarPreviewObjectUrl);
      avatarPreviewObjectUrl = null;
    }
    avatarPreview.replaceChildren();
    if (!url) {
      avatarPreview.textContent = '🤖';
      return;
    }
    const image = document.createElement('img');
    image.src = url;
    image.alt = 'Agent avatar preview';
    avatarPreview.appendChild(image);
  }

  function resetAvatarUI(agent = null) {
    if (avatarInput) avatarInput.value = '';
    renderAvatarPreview(agent?.avatar_url || null);
  }

  function syncInputFromAllFiles() {
    if (!fileInput) return;
    const dt = new DataTransfer();
    allFiles.forEach((f) => dt.items.add(f));
    fileInput.files = dt.files;
    renderChips(allFiles);
    renderLimitStatus();
  }

  function clearKnowledgeUI() {
    if (fileInput) fileInput.value = '';
    if (chipsContainer) chipsContainer.innerHTML = '';
    if (savedList) savedList.innerHTML = '';
    savedFiles = [];
    removedPaths = [];
    allFiles = [];
    renderLimitStatus();
  }

  function activeSavedFiles() {
    return savedFiles.filter((file) => !removedPaths.includes(file.path));
  }

  function renderLimitStatus() {
    if (!limitStatus) return;
    const fileCount = activeSavedFiles().length + allFiles.length;
    const newBytes = allFiles.reduce((total, file) => total + file.size, 0);
    limitStatus.textContent = fileCount + ' / ' + MAX_KNOWLEDGE_FILES + ' files selected'
      + (newBytes ? ' · ' + (newBytes / 1024 / 1024).toFixed(1) + ' MB new files' : '')
      + (activeSavedFiles().length ? ' · total size including saved files is verified when saving' : '');
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
      renderLimitStatus();
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
        renderLimitStatus();
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

    renderLimitStatus();
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
        const prospectiveFiles = existingIdx >= 0
          ? allFiles.map((file, index) => index === existingIdx ? f : file)
          : [...allFiles, f];

        if (activeSavedFiles().length + prospectiveFiles.length > MAX_KNOWLEDGE_FILES) {
          showToast('⚠️ Each Agent can have up to ' + MAX_KNOWLEDGE_FILES + ' Knowledge files');
          return;
        }
        if (f.size > MAX_KNOWLEDGE_FILE_BYTES) {
          showToast('⚠️ "' + f.name + '" exceeds the 5 MB per-file limit');
          return;
        }
        const newBytes = prospectiveFiles.reduce((total, file) => total + file.size, 0);
        if (newBytes > MAX_KNOWLEDGE_TOTAL_BYTES) {
          showToast('⚠️ New Knowledge files exceed the 25 MB total limit');
          return;
        }

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
    resetAvatarUI(agent);

    if (agent) {
      modalTitle.textContent = 'Edit Agent';
      formMethod.value = 'PUT';
      agentIdInput.value = agent.id;
      if (titleInput) titleInput.value = agent.title || '';
      if (descInput) descInput.value = agent.description || '';
      if (promptInput) promptInput.value = agent.system_prompt || '';
      if (sharedInput) sharedInput.checked = !!agent.is_shared;
      const access = agent.sharing_access === 'copy' ? 'copy' : 'use_only';
      sharingAccessInputs.forEach((input) => { input.checked = input.value === access; });
      renderSavedFiles(agent.knowledge_files || []);
    } else {
      modalTitle.textContent = 'Create Agent';
      formMethod.value = 'POST';
      agentIdInput.value = '';
    }
    syncSharedKnowledgeControl();
  }

  function syncSharedKnowledgeControl() {
    const isShared = !!sharedInput?.checked;
    if (sharingAccessGroup) sharingAccessGroup.hidden = !isShared;
    sharingAccessInputs.forEach((input) => { input.disabled = !isShared; });
  }

  sharedInput?.addEventListener('change', syncSharedKnowledgeControl);

  function closeModal() {
    modal.style.display = 'none';
    form.reset();
    clearKnowledgeUI();
    resetAvatarUI();
  }

  avatarInput?.addEventListener('change', () => {
    const file = avatarInput.files?.[0];
    if (!file) return;
    if (file.size > 2 * 1024 * 1024) {
      avatarInput.value = '';
      showToast('⚠️ Agent avatar must be 2 MB or smaller');
      return;
    }
    avatarPreviewObjectUrl = URL.createObjectURL(file);
    if (avatarPreview) {
      avatarPreview.replaceChildren();
      const image = document.createElement('img');
      image.src = avatarPreviewObjectUrl;
      image.alt = 'Agent avatar preview';
      avatarPreview.appendChild(image);
    }
  });

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
        editBtn.disabled = true;
        try {
          const res = await fetch(`/ai-plus/agent-workspace/agents/${agentId}`, { headers: { 'Accept': 'application/json' } });
          if (!res.ok) {
            let data = {};
            try { data = await res.json(); } catch (_) {}
            throw new Error(data.message || 'The agent details could not be loaded. Please try again.');
          }
          const agent = await res.json();
          openModal(agent);
        } catch (error) {
          await noticeDialog(error.message || 'We could not reach the server. Please try again.', 'Could not load agent');
        } finally {
          editBtn.disabled = false;
        }
      }

      if (useBtn) {
        const agentId = useBtn.dataset.agentId;
        sessionStorage.setItem('selectedAgentId', agentId);
        // Điều hướng về chat với agent
        window.location.href = '/ai-plus/agent-workspace';
      }

      if (deleteBtn) {
        const okConfirmed = await confirmDialog('Are you sure you want to delete this agent? This cannot be undone.');
        if (!okConfirmed) return;
        const agentId = deleteBtn.dataset.agentId;
        try {
          const res = await fetch(`/ai-plus/agent-workspace/agents/${agentId}`, {
            method: 'DELETE',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
          });
          if (res.ok) {
            showToast('🗑️ Agent deleted');
            setTimeout(() => window.location.reload(), 1000);
          } else {
            await noticeDialog('The agent could not be deleted. Please try again.', 'Could not delete agent');
          }
        } catch (_) {
          await noticeDialog('We could not reach the server. Check your connection and try again.', 'Connection interrupted');
        }
      }
    });
  }

  // Toast nhỏ thông báo lưu thành công.
  function showToast(message) {
    const existing = document.getElementById('agent-toast');
    if (existing) existing.remove();

    const toast = document.createElement('div');
    toast.id = 'agent-toast';
    toast.textContent = message;
    toast.style.cssText = 'position:fixed;top:20px;left:50%;transform:translateX(-50%);z-index:2000;background:#1F3864;color:#fff;padding:12px 20px;border-radius:10px;font-size:14px;font-weight:500;box-shadow:0 8px 24px rgba(0,0,0,0.2);opacity:0;transition:opacity .25s;';
    document.body.appendChild(toast);
    requestAnimationFrame(() => { toast.style.opacity = '1'; });
    setTimeout(() => { toast.style.opacity = '0'; }, 1000);
  }

  // Modal xác nhận tùy chỉnh (thay cho confirm() trình duyệt).
  // Trả về Promise<boolean> — true nếu nhấn Yes, false nếu No / đóng.
  function confirmDialog(message, { title = 'Delete agent', confirmText = 'Delete', danger = true } = {}) {
    return new Promise((resolve) => {
      const overlay = document.createElement('div');
      overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:2000;display:flex;align-items:center;justify-content:center;padding:20px;';

      const box = document.createElement('div');
      box.style.cssText = 'background:var(--card-bg,#fff);border-radius:16px;width:100%;max-width:400px;box-shadow:0 24px 48px -12px rgba(31,56,100,0.35);overflow:hidden;';

      const header = document.createElement('div');
      header.style.cssText = 'padding:20px 24px;border-bottom:1px solid var(--line,#E1DACB);font-family:Fraunces,serif;font-size:20px;font-weight:600;color:var(--navy,#1F3864);';
      header.textContent = title;
      box.appendChild(header);

      const body = document.createElement('div');
      body.style.cssText = 'padding:20px 24px;font-size:14px;color:var(--ink,#22303F);line-height:1.5;';
      body.textContent = message;
      box.appendChild(body);

      const footer = document.createElement('div');
      footer.style.cssText = 'display:flex;justify-content:flex-end;gap:12px;padding:16px 24px;';

      const cancelBtn = document.createElement('button');
      cancelBtn.type = 'button';
      cancelBtn.textContent = 'Cancel';
      cancelBtn.style.cssText = 'padding:10px 20px;border-radius:8px;background:var(--paper,#F6F3EC);color:var(--ink,#22303F);border:1px solid var(--line,#E1DACB);cursor:pointer;font-size:14px;';
      cancelBtn.addEventListener('click', () => { overlay.remove(); resolve(false); });
      footer.appendChild(cancelBtn);

      const okBtn = document.createElement('button');
      okBtn.type = 'button';
      okBtn.textContent = confirmText;
      okBtn.style.cssText = 'padding:10px 20px;border-radius:8px;border:none;cursor:pointer;font-size:14px;color:#fff;'+(danger?'background:#dc3545;':'background:#1F3864;');
      okBtn.addEventListener('click', () => { overlay.remove(); resolve(true); });
      footer.appendChild(okBtn);

      box.appendChild(footer);
      overlay.appendChild(box);
      overlay.addEventListener('click', (e) => { if (e.target === overlay) { overlay.remove(); resolve(false); } });
      document.body.appendChild(overlay);
    });
  }

  // Informational dialog for errors. This keeps feedback consistent with the
  // application UI instead of falling back to a browser alert.
  function noticeDialog(message, title = 'Something went wrong') {
    return new Promise((resolve) => {
      const overlay = document.createElement('div');
      overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:2000;display:flex;align-items:center;justify-content:center;padding:20px;';

      const box = document.createElement('div');
      box.style.cssText = 'background:var(--card-bg,#fff);border-radius:16px;width:100%;max-width:400px;box-shadow:0 24px 48px -12px rgba(31,56,100,0.35);overflow:hidden;';

      const header = document.createElement('div');
      header.style.cssText = 'padding:20px 24px;border-bottom:1px solid var(--line,#E1DACB);font-family:Fraunces,serif;font-size:20px;font-weight:600;color:#b42318;';
      header.textContent = title;

      const body = document.createElement('div');
      body.style.cssText = 'padding:20px 24px;font-size:14px;color:var(--ink,#22303F);line-height:1.5;white-space:pre-line;';
      body.textContent = message;

      const footer = document.createElement('div');
      footer.style.cssText = 'display:flex;justify-content:flex-end;padding:16px 24px;';

      const close = () => { overlay.remove(); resolve(); };
      const closeBtn = document.createElement('button');
      closeBtn.type = 'button';
      closeBtn.textContent = 'Close';
      closeBtn.style.cssText = 'padding:10px 20px;border-radius:8px;border:none;cursor:pointer;font-size:14px;color:#fff;background:#1F3864;';
      closeBtn.addEventListener('click', close);

      footer.appendChild(closeBtn);
      box.append(header, body, footer);
      overlay.appendChild(box);
      overlay.addEventListener('click', (event) => { if (event.target === overlay) close(); });
      document.body.appendChild(overlay);
      closeBtn.focus();
    });
  }

  // Form submit via FormData (multipart hỗ trợ file)
  form.addEventListener('submit', async (e) => {
    e.preventDefault();

    // Copyable shared agents give other school users a private copy of every
    // remaining Knowledge file. Make that consequence explicit before saving.
    const sharingAccess = Array.from(sharingAccessInputs).find((input) => input.checked)?.value;
    const knowledgeFileCount = activeSavedFiles().length + allFiles.length;
    if (sharedInput?.checked && sharingAccess === 'copy' && knowledgeFileCount > 0) {
      const shouldShareKnowledge = await confirmDialog(
        `Anyone at LSTS who copies this agent will receive ${knowledgeFileCount} Knowledge file${knowledgeFileCount === 1 ? '' : 's'} in their own workspace. Continue?`,
        {
          title: 'Share Knowledge files',
          confirmText: 'Share and allow copying',
          danger: false,
        }
      );

      if (!shouldShareKnowledge) return;
    }

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

    try {
      const res = await fetch(url, {
        method,
        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
        body: formData,
      });

      if (res.ok) {
        closeModal();
        showToast('✅ Agent saved successfully');
        setTimeout(() => window.location.reload(), 1200);
        return;
      }

      let err = {};
      try {
        err = await res.json();
      } catch (_) {
        // A proxy/server can return a non-JSON error page; show a safe message.
      }
      const message = err.message || (err.errors ? Object.values(err.errors).flat().join(' ') : 'The agent could not be saved. Please try again.');
      await noticeDialog(message, 'Could not save agent');
      // Keep validation errors visible in the form so they can be corrected.
      if (res.status !== 422) closeModal();
    } catch (_) {
      await noticeDialog('We could not reach the server. Check your connection and try again.', 'Connection interrupted');
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
