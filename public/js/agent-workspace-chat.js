document.addEventListener('DOMContentLoaded', function () {
  const sendBtn = document.querySelector('.send-btn');
  const textarea = document.querySelector('.input-box textarea');
  const emptyState = document.querySelector('.empty-state');
  const main = document.querySelector('main.main');
  const inputArea = document.querySelector('.input-area');

  if (!sendBtn || !textarea || !main) return;

  let conversationId = null;
  let messagesContainer = null;

  // Đọc selectedAgentId từ sessionStorage (set bởi trang Agents/show).
  const selectedAgentId = sessionStorage.getItem('selectedAgentId');

  // Nếu URL có ?conversation_id= → mở lại cuộc cũ (load đúng history).
  const urlParams = new URLSearchParams(window.location.search);
  const urlConversationId = urlParams.get('conversation_id');
  if (urlConversationId) {
    conversationId = urlConversationId;
  }

  // Render history từ server khi mở lại conversation cũ.
  function renderInitialMessages() {
    const messages = window.__INITIAL_MESSAGES__ || [];
    if (messages.length === 0) return;

    if (emptyState) emptyState.style.display = 'none';
    messages.forEach((m) => appendMessage(m.role, m.content));
  }

  // Hàm tạo bubble
  function ensureMessagesContainer() {
    if (messagesContainer) return messagesContainer;
    messagesContainer = document.createElement('div');
    messagesContainer.id = 'chat-messages';
    messagesContainer.style.cssText = 'flex:1;overflow-y:auto;padding:24px;display:flex;flex-direction:column;gap:16px;';
    main.insertBefore(messagesContainer, inputArea);
    return messagesContainer;
  }

  function appendMessage(role, text) {
    const container = ensureMessagesContainer();
    const bubble = document.createElement('div');
    bubble.style.cssText = role === 'user'
      ? 'align-self:flex-end;background:var(--navy);color:#fff;padding:12px 16px;border-radius:14px;max-width:70%;white-space:pre-wrap;'
      : 'align-self:flex-start;background:var(--card-bg);border:1px solid var(--line);padding:12px 16px;border-radius:14px;max-width:85%;white-space:normal;';

    // Câu trả lời của AI → render markdown đẹp; tin nhắn user → text thuần.
    if (role === 'assistant' && typeof marked !== 'undefined') {
      bubble.innerHTML = marked.parse(text);
    } else {
      bubble.textContent = text;
    }

    container.appendChild(bubble);

    // Render lại công thức LaTeX (MathJax) nếu có.
    if (role === 'assistant' && typeof MathJax !== 'undefined' && MathJax.typesetPromise) {
      try {
        MathJax.typesetPromise([bubble]);
      } catch (e) {
        // bỏ qua nếu typeset fail
      }
    }

    container.scrollTop = container.scrollHeight;
  }

  function appendWarning(text) {
    const container = ensureMessagesContainer();
    const warn = document.createElement('div');
    warn.style.cssText = 'align-self:center;background:#FDF3E0;color:#9A6B1F;border:1px solid #E5C88A;padding:10px 16px;border-radius:10px;font-size:13px;max-width:80%;text-align:center;';
    warn.textContent = '⚠️ ' + text;
    container.appendChild(warn);
    container.scrollTop = container.scrollHeight;
  }

  let sending = false;

  // Hình ảnh paste (data URL) chưa gửi.
  let pendingImages = [];

  // Container preview ảnh (đặt giữa input-box và hint).
  let imagePreviewBox = null;

  function ensureImagePreviewBox() {
    if (imagePreviewBox) return imagePreviewBox;
    const inputWrapper = document.querySelector('.input-wrapper');
    if (!inputWrapper) return null;

    imagePreviewBox = document.createElement('div');
    imagePreviewBox.id = 'chat-image-preview';
    imagePreviewBox.style.cssText = 'display:flex;flex-wrap:wrap;gap:8px;margin-bottom:8px;';
    inputWrapper.prepend(imagePreviewBox);

    return imagePreviewBox;
  }

  function renderImagePreviews() {
    const box = ensureImagePreviewBox();
    if (!box) return;
    box.innerHTML = '';

    pendingImages.forEach((dataUrl, idx) => {
      const wrap = document.createElement('div');
      wrap.style.cssText = 'position:relative;width:64px;height:64px;border-radius:8px;overflow:hidden;border:1px solid var(--surface-border);';

      const img = document.createElement('img');
      img.src = dataUrl;
      img.style.cssText = 'width:100%;height:100%;object-fit:cover;';
      wrap.appendChild(img);

      const remove = document.createElement('button');
      remove.textContent = '×';
      remove.style.cssText = 'position:absolute;top:2px;right:2px;width:18px;height:18px;border-radius:50%;border:none;background:rgba(0,0,0,0.6);color:#fff;font-size:12px;line-height:1;cursor:pointer;';
      remove.addEventListener('click', () => {
        pendingImages.splice(idx, 1);
        renderImagePreviews();
      });
      wrap.appendChild(remove);

      box.appendChild(wrap);
    });

    box.style.display = pendingImages.length > 0 ? 'flex' : 'none';
  }

  async function sendMessage() {
    const message = textarea.value.trim();
    // Cho phép gửi nếu có ít nhất message HOẶC 1 ảnh.
    if ((!message && pendingImages.length === 0) || sending) return;

    sending = true;
    if (sendBtn) sendBtn.textContent = 'Sending…';

    if (emptyState) emptyState.style.display = 'none';
    appendMessage('user', message || '[Gửi hình ảnh]');
    textarea.value = '';

    const images = pendingImages;
    pendingImages = [];
    renderImagePreviews();

    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';

    try {
      const response = await fetch('/ai-plus/agent-workspace/send', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrfToken,
          'Accept': 'application/json',
        },
        body: JSON.stringify({
          message: message,
          conversation_id: conversationId,
          agent_id: selectedAgentId,
          images: images,
        }),
      });

      const data = await response.json();

      if (data.blocked) {
        appendWarning(data.warning || 'Nội dung của bạn chứa thông tin nhạy cảm.');
        return;
      }

      if (!response.ok && data.error) {
        appendWarning(data.error);
        return;
      }

      if (!response.ok) {
        appendWarning('Có lỗi xảy ra, vui lòng thử lại.');
        return;
      }

      conversationId = data.conversation_id;
      appendMessage('assistant', data.reply);
    } catch (err) {
      appendWarning('Có lỗi xảy ra, vui lòng thử lại.');
    } finally {
      sending = false;
      if (sendBtn) sendBtn.textContent = 'Send →';
    }
  }

  // Paste ảnh vào ô chat (Ctrl/Cmd + V) → preview.
  textarea.addEventListener('paste', function (e) {
    const items = e.clipboardData?.items || [];

    for (const item of items) {
      if (item.type && item.type.startsWith('image/')) {
        const file = item.getAsFile();
        if (!file) continue;

        const reader = new FileReader();
        reader.onload = function (ev) {
          pendingImages.push(ev.target.result);
          renderImagePreviews();
        };
        reader.readAsDataURL(file);
      }
    }
  });

  // Kéo thả ảnh vào vùng chat → thêm vào preview (không mở tab mới).
  const dropTargets = [document.querySelector('.input-box'), document.querySelector('.input-area'), main];

  dropTargets.filter(Boolean).forEach((target) => {
    ['dragover', 'dragenter'].forEach((evt) => {
      target.addEventListener(evt, (e) => {
        e.preventDefault();
        e.stopPropagation();
        target.style.outline = '2px dashed var(--navy)';
      });
    });

    ['dragleave', 'drop'].forEach((evt) => {
      target.addEventListener(evt, (e) => {
        e.preventDefault();
        e.stopPropagation();
        target.style.outline = '';
      });
    });

    target.addEventListener('drop', (e) => {
      const files = Array.from(e.dataTransfer?.files || []);
      files.forEach((file) => {
        if (!file.type || !file.type.startsWith('image/')) return;

        const reader = new FileReader();
        reader.onload = function (ev) {
          pendingImages.push(ev.target.result);
          renderImagePreviews();
        };
        reader.readAsDataURL(file);
      });
    });
  });

  // Nút attach (📎) → mở file picker ảnh → đưa vào preview.
  const attachBtn = document.querySelector('.attach-btn');
  const imageInput = document.getElementById('chat-image-input');

  attachBtn?.addEventListener('click', function (e) {
    e.preventDefault();
    imageInput?.click();
  });

  imageInput?.addEventListener('change', function () {
    const files = Array.from(imageInput.files || []);
    files.forEach((file) => {
      if (!file.type || !file.type.startsWith('image/')) return;

      const reader = new FileReader();
      reader.onload = function (ev) {
        pendingImages.push(ev.target.result);
        renderImagePreviews();
      };
      reader.readAsDataURL(file);
    });
    imageInput.value = '';
    renderImagePreviews();
  });

  sendBtn.addEventListener('click', function (e) {
    e.preventDefault();
    sendMessage();
  });

  textarea.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      sendMessage();
    }
  });

  // ==== Topbar actions ====
  // 📎 Upload → mở file picker ảnh (dùng chung input ảnh ô chat).
  const topbarAttach = document.querySelector('[data-behavior="attach-topbar"]');
  topbarAttach?.addEventListener('click', function (e) {
    e.preventDefault();
    imageInput?.click();
  });

  // ↓ Export → tải file .txt toàn bộ message của conversation đang mở.
  const exportBtn = document.querySelector('[data-behavior="export-chat"]');
  exportBtn?.addEventListener('click', function () {
    exportConversationTxt();
  });

  function exportConversationTxt() {
    const container = ensureMessagesContainer();
    const bubbles = Array.from(container.querySelectorAll('div'));

    const lines = [];
    bubbles.forEach((bubble) => {
      const text = bubble.textContent?.trim();
      if (!text) return;
      const isUser = bubble.style.alignSelf === 'flex-end';
      lines.push((isUser ? '[Me] ' : '[AI] ') + text);
    });

    const content = lines.join('\n\n');
    const blob = new Blob([content], { type: 'text/plain;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    const title = (document.querySelector('.user-name')?.textContent || 'chat').trim().replace(/\s+/g, '_');
    a.href = url;
    a.download = 'agent-chat-' + title + '.txt';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
  }

  // ⚙ Settings → dropdown nhỏ (hiện model đang dùng).
  const settingsBtn = document.querySelector('[data-behavior="settings"]');
  const settingsPopover = document.getElementById('settings-popover');

  settingsBtn?.addEventListener('click', function (e) {
    e.preventDefault();
    e.stopPropagation();

    if (settingsPopover) {
      const visible = settingsPopover.style.display === 'block';
      settingsPopover.style.display = visible ? 'none' : 'block';
    }
  });

  // Click ngoài → đóng popover.
  document.addEventListener('click', function () {
    if (settingsPopover) settingsPopover.style.display = 'none';
  });

  // Mở lại conversation cũ → render history sau khi DOM sẵn sàng.
  renderInitialMessages();

  // ==== Quick action buttons (giữa trang) ====
  const quickBtnBehaviors = {
    'quick-chat': function () {
      textarea.focus();
    },
    'quick-create-agent': function () {
      if (window.openAgentModal) window.openAgentModal();
    },
    'quick-analyze-document': function () {
      imageInput?.click();
    },
    'quick-draft-email': function () {
      textarea.value = 'Draft a professional email in Vietnamese for the following situation:\n\n';
      textarea.focus();
    },
    'quick-generate-report': function () {
      textarea.value = 'Generate a concise report based on the following information:\n\n';
      textarea.focus();
    },
  };

  document.querySelectorAll('.quick-btn').forEach((btn) => {
    const behavior = quickBtnBehaviors[btn.dataset.behavior];
    if (behavior) {
      btn.addEventListener('click', behavior);
    }
  });
});