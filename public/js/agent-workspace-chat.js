document.addEventListener('DOMContentLoaded', function () {
  const sendBtn = document.querySelector('.send-btn');
  const textarea = document.querySelector('.input-box textarea');
  const emptyState = document.querySelector('.empty-state');
  const main = document.querySelector('main.main');
  const inputArea = document.querySelector('.input-area');

  if (!sendBtn || !textarea || !main) return;

  let conversationId = null;
  let messagesContainer = null;

  let selectedAgentId = window.__SELECTED_AGENT_ID__ || sessionStorage.getItem('selectedAgentId');
  if (window.__SELECTED_AGENT_ID__) {
    sessionStorage.setItem('selectedAgentId', window.__SELECTED_AGENT_ID__);
  }

  const urlParams = new URLSearchParams(window.location.search);
  const urlConversationId = urlParams.get('conversation_id');
  if (urlConversationId) {
    conversationId = urlConversationId;
  }

  // Hiển thị breadcrumb tên agent ở topbar (từ selectedAgentId hoặc activeAgent server-side).
  function syncAgentBadge() {
    const topbarLeft = document.querySelector('.topbar-left');
    if (!topbarLeft) return;

    // Server đã render breadcrumb (có conversation) → giữ nguyên, đừng tạo thêm.
    if (topbarLeft.querySelector('.active-agent-breadcrumb')) return;
    // Cũng bỏ qua nếu có badge cũ (trước khi có breadcrumb).
    if (topbarLeft.querySelector('.active-agent-badge')) return;

    if (!selectedAgentId) return;
    const agent = (window.__MY_AGENTS__ || []).find((a) => String(a.id) === String(selectedAgentId))
      || (window.__SELECTED_AGENT_NAME__ ? {title: window.__SELECTED_AGENT_NAME__} : null);
    if (!agent) return;

    const crumb = document.createElement('div');
    crumb.className = 'active-agent-breadcrumb';
    const n = document.createElement('span');
    n.className = 'agent-breadcrumb-name';
    n.textContent = '🤖 ' + agent.title;
    crumb.appendChild(n);
    topbarLeft.appendChild(crumb);
  }

  function syncConversationTitle(title) {
    if (!title) return;

    const topbarLeft = document.querySelector('.topbar-left');
    if (!topbarLeft) return;

    let crumb = topbarLeft.querySelector('.active-agent-breadcrumb');
    if (!crumb) {
      crumb = document.createElement('div');
      crumb.className = 'active-agent-breadcrumb';
      topbarLeft.appendChild(crumb);
    }

    let hasAgent = Boolean(crumb.querySelector('.agent-breadcrumb-name'));
    if (!hasAgent && selectedAgentId) {
      const agent = (window.__MY_AGENTS__ || []).find((a) => String(a.id) === String(selectedAgentId))
        || (window.__SELECTED_AGENT_NAME__ ? {title: window.__SELECTED_AGENT_NAME__} : null);
      if (agent) {
        const agentName = document.createElement('span');
        agentName.className = 'agent-breadcrumb-name';
        agentName.textContent = '🤖 ' + agent.title;
        crumb.appendChild(agentName);
        hasAgent = true;
      }
    }

    crumb.querySelectorAll('.agent-breadcrumb-sep, .agent-breadcrumb-prompt, .conversation-breadcrumb-name').forEach((element) => element.remove());

    if (hasAgent) {
      const separator = document.createElement('span');
      separator.className = 'agent-breadcrumb-sep';
      separator.textContent = '→';
      crumb.appendChild(separator);
    }

    const conversationName = document.createElement('span');
    conversationName.className = hasAgent ? 'agent-breadcrumb-prompt' : 'conversation-breadcrumb-name';
    conversationName.textContent = (hasAgent ? '' : '💬 ') + title;
    crumb.appendChild(conversationName);
  }

  function clearUnavailableSharedAgent() {
    selectedAgentId = null;
    sessionStorage.removeItem('selectedAgentId');
    const url = new URL(window.location.href);
    url.searchParams.delete('agent_id');
    window.history.replaceState({}, '', url);
  }

  function showMiniToast(message) {
    const existing = document.getElementById('mini-toast');
    if (existing) existing.remove();

    const toast = document.createElement('div');
    toast.id = 'mini-toast';
    toast.textContent = message;
    toast.style.cssText = 'position:fixed;top:20px;left:50%;transform:translateX(-50%);z-index:2000;background:#1F3864;color:#fff;padding:12px 20px;border-radius:10px;font-size:14px;font-weight:500;box-shadow:0 8px 24px rgba(0,0,0,0.2);opacity:0;transition:opacity .25s;';
    document.body.appendChild(toast);
    requestAnimationFrame(() => { toast.style.opacity = '1'; });
    setTimeout(() => { toast.style.opacity = '0'; }, 850);
  }

  function renderInitialMessages() {
    const messages = window.__INITIAL_MESSAGES__ || [];
    if (messages.length === 0) return;

    if (emptyState) emptyState.style.display = 'none';
    messages.forEach((m) => appendMessage(m.role, m.content));
  }

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

    // Wrapper chứa bubble + nút copy (để copy nằm ở góc).
    const wrap = document.createElement('div');
    wrap.className = 'chat-msg-wrap';
    wrap.style.cssText = 'display:flex;flex-direction:column;gap:4px;max-width:85%;'+(role === 'user' ? 'align-self:flex-end;' : 'align-self:flex-start;');

    const bubble = document.createElement('div');
    bubble.className = 'chat-bubble';
    bubble.style.cssText = role === 'user'
      ? 'background:var(--navy);color:#fff;padding:12px 16px;border-radius:14px;white-space:pre-wrap;'
      : 'background:var(--card-bg);border:1px solid var(--line);padding:12px 16px;border-radius:14px;white-space:normal;';

    // Render markdown cho assistant + user (nếu có ảnh kèm) — để ảnh hiện trong lịch sử.
    const hasImageMd = typeof text === 'string' && text.includes('![');
    if (typeof marked !== 'undefined' && (role === 'assistant' || hasImageMd)) {
      bubble.innerHTML = renderSafeMarkdown(text);
    } else {
      bubble.textContent = text;
    }

    // Nút copy ở góc phải wrapper.
    const copyBtn = document.createElement('button');
    copyBtn.type = 'button';
    copyBtn.textContent = '⧉';
    copyBtn.title = 'Copy';
    copyBtn.style.cssText = 'align-self:flex-end;background:transparent;border:none;cursor:pointer;color:var(--text-soft,#5B6B7C);font-size:14px;padding:2px 6px;border-radius:6px;transition:background .15s;';
    copyBtn.addEventListener('mouseenter', () => { copyBtn.style.background = 'var(--input-bg, rgba(0,0,0,0.05))'; });
    copyBtn.addEventListener('mouseleave', () => { copyBtn.style.background = 'transparent'; });
    copyBtn.addEventListener('click', () => {
      const plainText = bubble.innerText || bubble.textContent || '';

      // Dùng fallback textarea luôn (clipboard API yêu cầu HTTPS, không hoạt động trên http://LAN).
      const ta = document.createElement('textarea');
      ta.value = plainText;
      ta.style.position = 'fixed';
      ta.style.opacity = '0';
      document.body.appendChild(ta);
      ta.select();
      ta.setSelectionRange(0, plainText.length);
      let ok = false;
      try {
        ok = document.execCommand('copy');
      } catch (e) {
        ok = false;
      }
      document.body.removeChild(ta);
      showMiniToast(ok ? '✅ Copied to clipboard' : '⚠️ Copy failed — select manually and copy');
    });

    wrap.appendChild(bubble);
    wrap.appendChild(copyBtn);
    container.appendChild(wrap);

    if (role === 'assistant' && typeof MathJax !== 'undefined' && MathJax.typesetPromise) {
      try {
        MathJax.typesetPromise([bubble]);
      } catch (e) {
        // bỏ qua nếu typeset fail
      }
    }

    container.scrollTop = container.scrollHeight;
    return wrap;
  }

  function appendWarning(text) {
    const container = ensureMessagesContainer();
    const warn = document.createElement('div');
    warn.style.cssText = 'align-self:center;background:#FDF3E0;color:#9A6B1F;border:1px solid #E5C88A;padding:10px 16px;border-radius:10px;font-size:13px;max-width:80%;text-align:center;';
    warn.textContent = '⚠️ ' + text;
    container.appendChild(warn);
    container.scrollTop = container.scrollHeight;
  }

  // ==== Streaming helpers (mới) ====

  // Chèn CSS cho hiệu ứng 3 chấm nhấp nháy ("đang suy nghĩ") — chỉ chèn 1 lần.
  (function injectTypingStyles() {
    if (document.getElementById('aiplus-typing-style')) return;
    const style = document.createElement('style');
    style.id = 'aiplus-typing-style';
    style.textContent = `
      @keyframes aiplusTypingBounce {
        0%, 80%, 100% { transform: translateY(0); opacity: 0.4; }
        40% { transform: translateY(-4px); opacity: 1; }
      }
      .aiplus-typing-dot {
        display: inline-block;
        width: 6px;
        height: 6px;
        margin-right: 4px;
        border-radius: 50%;
        background: var(--text-soft, #999);
        animation: aiplusTypingBounce 1.2s infinite ease-in-out;
      }
      .aiplus-typing-dot:nth-child(2) { animation-delay: 0.15s; }
      .aiplus-typing-dot:nth-child(3) { animation-delay: 0.3s; margin-right:0; }
    `;
    document.head.appendChild(style);
  })();

  function createTypingBubble() {
    const container = ensureMessagesContainer();
    const bubble = document.createElement('div');
    bubble.style.cssText = 'align-self:flex-start;background:var(--card-bg);border:1px solid var(--line);padding:12px 16px;border-radius:14px;max-width:85%;white-space:normal;';
    bubble.innerHTML = '<span class="aiplus-typing-dot"></span><span class="aiplus-typing-dot"></span><span class="aiplus-typing-dot"></span>';
    container.appendChild(bubble);
    container.scrollTop = container.scrollHeight;
    return bubble;
  }

  function renderAssistantMarkdown(bubble, text) {
    if (typeof marked !== 'undefined') {
      bubble.innerHTML = renderSafeMarkdown(text);
    } else {
      bubble.textContent = text;
    }

    if (typeof MathJax !== 'undefined' && MathJax.typesetPromise) {
      try {
        MathJax.typesetPromise([bubble]);
      } catch (e) {
        // bỏ qua nếu typeset fail
      }
    }
  }

  // marked turns Markdown into HTML but does not sanitize it. Keep only the HTML that
  // Markdown needs; AI output and shared-agent prompts must never run browser code.
  function renderSafeMarkdown(text) {
    const template = document.createElement('template');
    template.innerHTML = marked.parse(text);
    const allowedTags = new Set(['a', 'b', 'blockquote', 'br', 'code', 'del', 'div', 'em', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'hr', 'i', 'img', 'li', 'ol', 'p', 'pre', 'span', 'strong', 'sub', 'sup', 'table', 'tbody', 'td', 'th', 'thead', 'tr', 'ul']);
    const removeTags = new Set(['base', 'embed', 'form', 'iframe', 'link', 'meta', 'object', 'script', 'style', 'svg']);

    [...template.content.querySelectorAll('*')].forEach((element) => {
      const tag = element.tagName.toLowerCase();
      if (removeTags.has(tag)) {
        element.remove();
        return;
      }
      if (!allowedTags.has(tag)) {
        element.replaceWith(...element.childNodes);
        return;
      }
      if (tag === 'a') {
        const href = element.getAttribute('href') || '';
        [...element.attributes].forEach((attribute) => element.removeAttribute(attribute.name));
        if (!/^https?:\/\//i.test(href)) {
          element.removeAttribute('href');
        } else {
          element.setAttribute('target', '_blank');
          element.setAttribute('rel', 'noopener noreferrer');
        }
      }
      if (tag === 'img') {
        const src = element.getAttribute('src') || '';
        [...element.attributes].forEach((attribute) => element.removeAttribute(attribute.name));
        if (!src.startsWith('/ai-plus/agent-workspace/attachments/')) {
          element.remove();
        } else {
          element.setAttribute('src', src);
        }
      } else if (tag !== 'a') {
        [...element.attributes].forEach((attribute) => element.removeAttribute(attribute.name));
      }
    });

    return template.innerHTML;
  }

  // Parse 1 khối SSE thô (đã tách bằng \n\n) → {event, data} hoặc null nếu không hợp lệ.
  function parseSseBlock(rawBlock) {
    let eventName = 'message';
    let dataLines = [];

    rawBlock.split('\n').forEach((line) => {
      if (line.startsWith('event:')) {
        eventName = line.slice(6).trim();
      } else if (line.startsWith('data:')) {
        dataLines.push(line.slice(5).trim());
      }
    });

    if (dataLines.length === 0) return null;

    try {
      return { event: eventName, data: JSON.parse(dataLines.join('\n')) };
    } catch (e) {
      return null;
    }
  }

  let sending = false;
  let pendingImages = [];
  let pendingImageBytes = [];
  let pendingDocuments = [];
  let pendingAttachmentBytes = 0;
  let pendingReads = 0;
  let imagePreviewBox = null;

  // Cac dinh dang tai lieu (ngoai anh) duoc phep dinh kem trong o chat - khop voi
  // KnowledgeService::CHAT_DOCUMENT_EXTENSIONS phia backend.
  const CHAT_DOCUMENT_EXTENSIONS = ['txt', 'csv', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'];
  const MAX_ATTACHMENTS = 5;
  const MAX_TOTAL_ATTACHMENT_BYTES = 15 * 1024 * 1024;
  const MAX_IMAGE_BYTES = 4 * 1024 * 1024;
  const MAX_DOCUMENT_BYTES = 5 * 1024 * 1024;

  function getFileExt(filename) {
    const m = /\.([a-zA-Z0-9]+)$/.exec(filename || '');
    return m ? m[1].toLowerCase() : '';
  }

  // Phan loai 1 file duoc keo-tha/chon: anh -> doc base64 vao pendingImages (giu hanh vi cu);
  // tai lieu (txt/pdf/doc/docx/xls/xlsx/ppt/pptx) -> doc base64 vao pendingDocuments.
  // File khong thuoc 2 nhom tren -> bao loi, khong them vao hang cho gui.
  function handleIncomingFile(file) {
    if (!file) return;

    const isImage = file.type && file.type.startsWith('image/');
    if (pendingImages.length + pendingDocuments.length + pendingReads >= MAX_ATTACHMENTS) {
      appendWarning('Mỗi lượt chat chỉ hỗ trợ tối đa ' + MAX_ATTACHMENTS + ' tệp đính kèm.');
      return;
    }
    if (file.size > (isImage ? MAX_IMAGE_BYTES : MAX_DOCUMENT_BYTES)) {
      appendWarning(isImage ? 'Mỗi ảnh chỉ được tối đa 4 MB.' : 'Mỗi tài liệu chỉ được tối đa 5 MB.');
      return;
    }
    if (pendingAttachmentBytes + file.size > MAX_TOTAL_ATTACHMENT_BYTES) {
      appendWarning('Tổng dung lượng tệp trong một lượt chat chỉ được tối đa 15 MB.');
      return;
    }

    if (isImage) {
      pendingReads++;
      pendingAttachmentBytes += file.size;
      const reader = new FileReader();
      reader.onload = function (ev) {
        pendingImages.push(ev.target.result);
        pendingImageBytes.push(file.size);
        pendingReads--;
        renderImagePreviews();
      };
      reader.onerror = function () {
        pendingReads--;
        pendingAttachmentBytes -= file.size;
        appendWarning('Không thể đọc ảnh "' + file.name + '". Vui lòng thử lại.');
      };
      reader.readAsDataURL(file);
      return;
    }

    const ext = getFileExt(file.name);
    if (!CHAT_DOCUMENT_EXTENSIONS.includes(ext)) {
      appendWarning('Khong ho tro dinh dang "' + (ext ? '.' + ext : (file.type || 'khong ro')) + '". Ho tro: anh, ' + CHAT_DOCUMENT_EXTENSIONS.join(', ') + '.');
      return;
    }

    pendingReads++;
    pendingAttachmentBytes += file.size;
    const reader = new FileReader();
    reader.onload = function (ev) {
      pendingDocuments.push({ name: file.name, dataUrl: ev.target.result, size: file.size });
      pendingReads--;
      renderImagePreviews();
    };
    reader.onerror = function () {
      pendingReads--;
      pendingAttachmentBytes -= file.size;
      appendWarning('Không thể đọc tệp "' + file.name + '". Vui lòng thử lại.');
    };
    reader.readAsDataURL(file);
  }

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
        pendingAttachmentBytes -= pendingImageBytes[idx] || 0;
        pendingImages.splice(idx, 1);
        pendingImageBytes.splice(idx, 1);
        renderImagePreviews();
      });
      wrap.appendChild(remove);

      box.appendChild(wrap);
    });

    pendingDocuments.forEach((doc, idx) => {
      const wrap = document.createElement('div');
      wrap.style.cssText = 'position:relative;display:flex;align-items:center;gap:6px;height:64px;padding:0 26px 0 12px;border-radius:8px;border:1px solid var(--surface-border);background:var(--surface);font-size:12px;color:var(--text-main);max-width:180px;';

      const icon = document.createElement('span');
      icon.textContent = '📄';
      icon.style.cssText = 'flex-shrink:0;';
      wrap.appendChild(icon);

      const name = document.createElement('span');
      name.textContent = doc.name;
      name.title = doc.name;
      name.style.cssText = 'overflow:hidden;text-overflow:ellipsis;white-space:nowrap;';
      wrap.appendChild(name);

      const remove = document.createElement('button');
      remove.textContent = '×';
      remove.style.cssText = 'position:absolute;top:2px;right:4px;width:18px;height:18px;border-radius:50%;border:none;background:rgba(0,0,0,0.12);color:var(--text-main);font-size:12px;line-height:1;cursor:pointer;';
      remove.addEventListener('click', () => {
        pendingAttachmentBytes -= pendingDocuments[idx].size || 0;
        pendingDocuments.splice(idx, 1);
        renderImagePreviews();
      });
      wrap.appendChild(remove);

      box.appendChild(wrap);
    });

    box.style.display = (pendingImages.length > 0 || pendingDocuments.length > 0) ? 'flex' : 'none';
  }

  async function sendMessage() {
    const message = textarea.value.trim();
    if ((!message && pendingImages.length === 0 && pendingDocuments.length === 0) || sending) return;

    sending = true;
    if (sendBtn) {
      sendBtn.disabled = true;
      sendBtn.setAttribute('aria-busy', 'true');
      sendBtn.setAttribute('aria-label', 'AI is responding');
      sendBtn.textContent = 'Sending…';
    }

    if (emptyState) emptyState.style.display = 'none';

    // Hien anh + ten tai lieu kem trong bubble user ngay khi gui (dung snapshot truoc khi clear).
    const pendingSnapshot = pendingImages.slice();
    const pendingDocsSnapshot = pendingDocuments.slice();
    const docsLine = pendingDocsSnapshot.length > 0
      ? pendingDocsSnapshot.map((d) => '📎 ' + d.name).join('\n')
      : '';
    if (pendingSnapshot.length > 0 || docsLine) {
      const mdImg = pendingSnapshot.map((d) => `![Ảnh đính kèm](${d})`).join('\n');
      const parts = [message, mdImg, docsLine].filter(Boolean);
      appendMessage('user', parts.join('\n\n'));
    } else {
      appendMessage('user', message || '[Gửi tệp đính kèm]');
    }
    textarea.value = '';
    // Reset chiều cao đã auto-grow để prompt kế tiếp bắt đầu bằng ô nhập mặc định.
    textarea.style.height = '';

    const images = pendingImages;
    const documents = pendingDocuments.map((d) => ({ name: d.name, data_url: d.dataUrl }));
    pendingImages = [];
    pendingImageBytes = [];
    pendingDocuments = [];
    pendingAttachmentBytes = 0;
    renderImagePreviews();

    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';

    // Bubble "đang suy nghĩ" hiện ngay lập tức, trước khi có delta đầu tiên.
    const assistantBubble = createTypingBubble();
    let rawText = '';
    let firstDeltaReceived = false;
    let sawDoneOrError = false;
    const sendChatRequest = (activeConversationId) => fetch(
      '/ai-plus/agent-workspace/send-stream',
      {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrfToken,
          'X-Requested-With': 'XMLHttpRequest',
          'Accept': 'text/event-stream',
        },
        body: JSON.stringify({
          message: message,
          conversation_id: activeConversationId,
          agent_id: selectedAgentId,
          images: images,
          documents: documents,
        }),
      },
    );

    const updateTokenQuota = (quota) => {
      if (!quota) return;

      const quotaText = document.querySelector('[data-token-quota-text]');
      const quotaFill = document.querySelector('[data-token-quota-fill]');
      if (quotaText) {
        quotaText.textContent = `${Number(quota.used).toLocaleString()} / ${Number(quota.limit).toLocaleString()} tokens (in ${quota.month_name})`;
      }
      if (quotaFill) {
        quotaFill.style.width = `${quota.percentage}%`;
      }
    };
    const showOutputs = (data) => {
      (data.artifacts || []).forEach((artifact) => appendMessage('assistant', `📄 Đã tạo file: [${artifact.name}](${artifact.url})`));
      if (data.email_draft) appendMessage('assistant', `✉️ Đã tạo email nháp: **${data.email_draft.subject}**`);
    };

    try {
      let response = await sendChatRequest(conversationId);

      let contentType = response.headers.get('content-type') || '';

      // Trường hợp bị chặn PII (server trả JSON thường, không mở stream).
      if (contentType.includes('application/json')) {
        let data = await response.json();

        // URL cũ có thể trỏ đến conversation đã bị xóa. Gửi lại đúng lượt này
        // như một chat mới, để không bắt người dùng chọn/tải lại từng file.
        if (data.errors?.conversation_id && conversationId !== null) {
          conversationId = null;
          response = await sendChatRequest(null);
          contentType = response.headers.get('content-type') || '';
          if (contentType.includes('application/json')) {
            data = await response.json();
          }
        }

        if (contentType.includes('application/json')) {
          if (response.ok && data.reply) {
            sawDoneOrError = true;
            conversationId = data.conversation_id;
            syncConversationTitle(data.title);
            updateTokenQuota(data.token_quota);
            assistantBubble.style.whiteSpace = 'normal';
            renderAssistantMarkdown(assistantBubble, data.reply);
            showOutputs(data);
            ensureMessagesContainer().scrollTop = ensureMessagesContainer().scrollHeight;
          } else if (data.blocked) {
            assistantBubble.remove();
            appendWarning(data.warning || 'Nội dung của bạn chứa thông tin nhạy cảm.');
          } else if (data.error) {
            assistantBubble.remove();
            if (data.agent_unavailable || data.agent_copy_required) clearUnavailableSharedAgent();
            appendWarning(data.error);
          } else {
            assistantBubble.remove();
            appendWarning(data.message || `Yêu cầu không thành công (HTTP ${response.status}).`);
          }
          return;
        }
      }

      if (!response.ok || !response.body) {
        assistantBubble.remove();
        appendWarning(`Yêu cầu không thành công (HTTP ${response.status}).`);
        return;
      }

      const reader = response.body.getReader();
      const decoder = new TextDecoder('utf-8');
      let buffer = '';
      const container = ensureMessagesContainer();

      function handleStreamEvent(parsed) {
        if (parsed.event === 'delta') {
          if (!firstDeltaReceived) {
            firstDeltaReceived = true;
            if (sendBtn) sendBtn.textContent = 'Generating…';
            assistantBubble.innerHTML = '';
            assistantBubble.style.whiteSpace = 'pre-wrap';
          }
          rawText += parsed.data.text || '';
          assistantBubble.textContent = rawText;
          container.scrollTop = container.scrollHeight;
        } else if (parsed.event === 'progress') {
          if (!firstDeltaReceived) assistantBubble.textContent = parsed.data.message || 'Đang xử lý…';
        } else if (parsed.event === 'error') {
          sawDoneOrError = true;
          assistantBubble.remove();
          appendWarning(parsed.data.message || 'Có lỗi xảy ra, vui lòng thử lại.');
        } else if (parsed.event === 'done') {
          sawDoneOrError = true;
          conversationId = parsed.data.conversation_id;
          syncConversationTitle(parsed.data.title);
          updateTokenQuota(parsed.data.token_quota);
          assistantBubble.style.whiteSpace = 'normal';
          renderAssistantMarkdown(assistantBubble, parsed.data.reply || rawText);
          showOutputs(parsed.data);
          container.scrollTop = container.scrollHeight;
        }
      }

      while (true) {
        const { value, done } = await reader.read();
        if (done) break;

        buffer += decoder.decode(value, { stream: true });

        let sepIndex;
        while ((sepIndex = buffer.indexOf('\n\n')) !== -1) {
          const rawBlock = buffer.slice(0, sepIndex);
          buffer = buffer.slice(sepIndex + 2);

          const parsed = parseSseBlock(rawBlock);
          if (!parsed) continue;

          handleStreamEvent(parsed);
        }
      }

      // Một số proxy có thể đóng stream ngay sau event cuối và không giữ delimiter \n\n.
      // Vẫn xử lý phần buffer còn lại để không đánh dấu nhầm là kết nối gián đoạn.
      buffer += decoder.decode();
      const finalEvent = parseSseBlock(buffer);
      if (finalEvent) handleStreamEvent(finalEvent);

      // Phòng trường hợp stream đóng đột ngột mà chưa nhận được "done"/"error"
      // (vd mất mạng giữa chừng) — vẫn hiển thị phần đã nhận được thay vì để bubble "..." treo mãi.
      if (!sawDoneOrError) {
        if (rawText !== '') {
          assistantBubble.style.whiteSpace = 'normal';
          renderAssistantMarkdown(assistantBubble, rawText);
        } else {
          assistantBubble.remove();
          appendWarning('Kết nối bị gián đoạn trước khi có phản hồi. Vui lòng thử lại.');
        }
      }
    } catch (err) {
      assistantBubble.remove();
      appendWarning('Có lỗi xảy ra, vui lòng thử lại.');
    } finally {
      sending = false;
      if (sendBtn) {
        sendBtn.disabled = false;
        sendBtn.removeAttribute('aria-busy');
        sendBtn.setAttribute('aria-label', 'Send message');
        sendBtn.textContent = 'Send →';
      }
    }
  }

  textarea.addEventListener('paste', function (e) {
    const items = e.clipboardData?.items || [];

    for (const item of items) {
      if (item.type && item.type.startsWith('image/')) {
        const file = item.getAsFile();
        if (!file) continue;
        handleIncomingFile(file);
      }
    }
  });

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
      files.forEach((file) => handleIncomingFile(file));
    });
  });

  const attachBtn = document.querySelector('.attach-btn');
  const imageInput = document.getElementById('chat-image-input');

  attachBtn?.addEventListener('click', function (e) {
    e.preventDefault();
    imageInput?.click();
  });

  imageInput?.addEventListener('change', function () {
    const files = Array.from(imageInput.files || []);
    files.forEach((file) => handleIncomingFile(file));
    imageInput.value = '';
    renderImagePreviews();
  });

  // ==== Right-click Rename conversation ====
  document.querySelectorAll('.chat-item[data-conversation-id]').forEach((item) => {
    item.addEventListener('contextmenu', async (e) => {
      e.preventDefault();
      const convId = item.dataset.conversationId;
      const currentTitle = item.dataset.conversationTitle || '';
      const newTitle = await WebUI.prompt({
        title: 'Rename conversation',
        label: 'Conversation title',
        value: currentTitle,
        submitText: 'Rename',
      });

      if (newTitle === null) return;
      const title = newTitle.trim();
      if (!title) {
        await WebUI.notice('A conversation title is required.', { title: 'Title required' });
        return;
      }
      if (title === currentTitle) return;

      try {
        const response = await fetch('/ai-plus/agent-workspace/conversations/' + convId, {
          method: 'PATCH',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
          },
          body: JSON.stringify({ title }),
        });
        if (!response.ok) {
          let data = {};
          try { data = await response.json(); } catch (_) {}
          throw new Error(data.message || 'The conversation could not be renamed. Please try again.');
        }

        showMiniToast('✏️ Conversation renamed');
        setTimeout(() => location.reload(), 1000);
      } catch (error) {
        await WebUI.notice(error.message || 'We could not reach the server. Please try again.', { title: 'Could not rename conversation' });
      }
    });
  });

  // Modal xác nhận tùy chỉnh (thay cho confirm() trình duyệt).
  function confirmDialog(message, { title = 'Delete prompt', confirmText = 'Delete', danger = true } = {}) {
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
      cancelBtn.textContent = 'Cancel';
      cancelBtn.style.cssText = 'padding:10px 20px;border-radius:8px;background:var(--paper,#F6F3EC);color:var(--ink,#22303F);border:1px solid var(--line,#E1DACB);cursor:pointer;font-size:14px;';
      cancelBtn.addEventListener('click', () => { overlay.remove(); resolve(false); });
      footer.appendChild(cancelBtn);

      const okBtn = document.createElement('button');
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

  // ==== Delete conversation (prompt) — nút × trong sidebar ====
  document.querySelectorAll('.conv-delete-btn').forEach((btn) => {
    btn.addEventListener('click', async (e) => {
      e.preventDefault();
      e.stopPropagation();

      const convId = btn.dataset.conversationId;
      if (!convId) return;

      const ok = await confirmDialog('All messages in this conversation will be permanently deleted. This cannot be undone.', {
        title: 'Delete conversation',
        confirmText: 'Delete conversation',
      });
      if (!ok) return;

      btn.disabled = true;
      try {
        const response = await fetch('/ai-plus/agent-workspace/conversations/' + convId, {
          method: 'DELETE',
          headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
          },
        });
        if (!response.ok) {
          let data = {};
          try { data = await response.json(); } catch (_) {}
          throw new Error(data.message || 'The conversation could not be deleted. Please try again.');
        }

        showMiniToast('🗑️ Conversation deleted');
        setTimeout(() => location.reload(), 1000);
      } catch (error) {
        btn.disabled = false;
        await WebUI.notice(error.message || 'We could not reach the server. Please try again.', { title: 'Could not delete conversation' });
      }
    });
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

  // Auto-grow textarea: tự giãn chiều cao theo nội dung nhập vào (xem hết prompt dài).
  textarea.addEventListener('input', function () {
    textarea.style.height = 'auto';
    textarea.style.height = Math.min(textarea.scrollHeight, 400) + 'px';
  });

  const topbarAttach = document.querySelector('[data-behavior="attach-topbar"]');
  topbarAttach?.addEventListener('click', function (e) {
    e.preventDefault();
    imageInput?.click();
  });

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

  document.addEventListener('click', function () {
    if (settingsPopover) settingsPopover.style.display = 'none';
  });

  renderInitialMessages();
  syncAgentBadge();
  if (window.__AGENT_ACCESS_MESSAGE__) showMiniToast(window.__AGENT_ACCESS_MESSAGE__);

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
