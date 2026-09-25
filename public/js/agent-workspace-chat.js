document.addEventListener('DOMContentLoaded', function () {
  const sendBtn = document.querySelector('.send-btn');
  const textarea = document.querySelector('.input-box textarea');
  const emptyState = document.querySelector('.empty-state');
  const main = document.querySelector('main.main');
  const inputArea = document.querySelector('.input-area');

  if (!sendBtn || !textarea || !main) return;

  let conversationId = null;
  let messagesContainer = null;

  const workspace = document.querySelector('.app');
  const sidebar = document.querySelector('.sidebar');
  const workspaceResizer = document.querySelector('.workspace-resizer');
  const sidebarWidthStorageKey = 'ai-plus.workspace-sidebar-width';

  function sidebarWidthLimits() {
    return { min: 220, max: Math.min(480, Math.max(220, window.innerWidth - 420)) };
  }

  function applySidebarWidth(width, persist = false) {
    if (!workspace || window.innerWidth <= 860) return;
    const { min, max } = sidebarWidthLimits();
    const safeWidth = Math.round(Math.min(max, Math.max(min, width)));
    workspace.style.setProperty('--workspace-sidebar-width', `${safeWidth}px`);
    if (persist) localStorage.setItem(sidebarWidthStorageKey, String(safeWidth));
  }

  function restoreSidebarWidth() {
    if (!workspace) return;
    if (window.innerWidth <= 860) {
      workspace.style.removeProperty('--workspace-sidebar-width');
      return;
    }
    const savedWidth = Number(localStorage.getItem(sidebarWidthStorageKey));
    if (Number.isFinite(savedWidth) && savedWidth > 0) applySidebarWidth(savedWidth);
  }

  if (workspaceResizer && sidebar) {
    let resizing = false;
    workspaceResizer.addEventListener('pointerdown', (event) => {
      if (window.innerWidth <= 860) return;
      resizing = true;
      workspaceResizer.setPointerCapture(event.pointerId);
      workspaceResizer.classList.add('is-resizing');
      document.body.classList.add('workspace-resizing');
      event.preventDefault();
    });
    workspaceResizer.addEventListener('pointermove', (event) => {
      if (resizing) applySidebarWidth(event.clientX);
    });
    const finishResize = (event) => {
      if (!resizing) return;
      resizing = false;
      workspaceResizer.classList.remove('is-resizing');
      document.body.classList.remove('workspace-resizing');
      applySidebarWidth(sidebar.getBoundingClientRect().width, true);
      if (workspaceResizer.hasPointerCapture(event.pointerId)) workspaceResizer.releasePointerCapture(event.pointerId);
    };
    workspaceResizer.addEventListener('pointerup', finishResize);
    workspaceResizer.addEventListener('pointercancel', finishResize);
  }
  restoreSidebarWidth();
  window.addEventListener('resize', restoreSidebarWidth);

  const urlParams = new URLSearchParams(window.location.search);
  const urlConversationId = urlParams.get('conversation_id');
  if (urlConversationId) {
    conversationId = urlConversationId;
  }

  // An existing conversation already owns its Agent context on the server.
  // Never revive the Agent saved for a previous "new chat" here: doing so
  // could turn an old Quick Chat into an Agent chat on its next message.
  let selectedAgentId = null;
  if (window.__SELECTED_AGENT_ID__) {
    selectedAgentId = window.__SELECTED_AGENT_ID__;
    sessionStorage.setItem('selectedAgentId', selectedAgentId);
  } else if (!urlConversationId) {
    selectedAgentId = sessionStorage.getItem('selectedAgentId');
  } else {
    sessionStorage.removeItem('selectedAgentId');
  }

  // Drafts are intentionally kept in sessionStorage, not the database or
  // localStorage: they survive moving between chats in this browser tab but
  // disappear when the tab/session closes. They are also scoped to the user.
  const draftStoragePrefix = 'ai-plus.workspace-draft.v1';
  let draftSaveTimer = null;

  function currentDraftKey() {
    const userId = window.__WORKSPACE_USER_ID__ || 'anonymous';
    const context = conversationId
      ? `conversation:${conversationId}`
      : `new:${selectedAgentId || 'quick-chat'}`;

    return `${draftStoragePrefix}:${userId}:${context}`;
  }

  function resizeComposer() {
    textarea.style.height = 'auto';
    textarea.style.height = Math.min(textarea.scrollHeight, 400) + 'px';
  }

  function saveDraft() {
    if (draftSaveTimer) {
      window.clearTimeout(draftSaveTimer);
      draftSaveTimer = null;
    }

    try {
      const value = textarea.value;
      const key = currentDraftKey();
      if (value === '') {
        sessionStorage.removeItem(key);
        return;
      }

      sessionStorage.setItem(key, JSON.stringify({ text: value, savedAt: Date.now() }));
    } catch (_) {
      // Storage can be unavailable in a strict privacy mode. Chat remains usable.
    }
  }

  function queueDraftSave() {
    if (draftSaveTimer) window.clearTimeout(draftSaveTimer);
    draftSaveTimer = window.setTimeout(saveDraft, 250);
  }

  function restoreDraft() {
    try {
      const raw = sessionStorage.getItem(currentDraftKey());
      if (!raw) return;
      const draft = JSON.parse(raw);
      if (typeof draft?.text !== 'string' || draft.text === '') return;

      textarea.value = draft.text;
      resizeComposer();
      showMiniToast('✏️ Draft restored');
    } catch (_) {
      // Ignore an invalid or inaccessible browser storage entry.
    }
  }

  function resolveSentDraft(draft) {
    if (!draft) return;
    try {
      const saved = sessionStorage.getItem(draft.key);
      const parsed = saved ? JSON.parse(saved) : null;
      // The user may have started typing the next prompt while AI was working.
      // Only remove the stored draft if it is still the message just sent. A
      // new chat receives its conversation ID after the first reply, so move a
      // newer draft to that permanent conversation key instead of losing it.
      if (parsed?.text === draft.text) {
        sessionStorage.removeItem(draft.key);
      } else if (saved && draft.key !== currentDraftKey()) {
        sessionStorage.setItem(currentDraftKey(), saved);
        sessionStorage.removeItem(draft.key);
      }
    } catch (_) {}
  }

  window.addEventListener('pagehide', saveDraft);

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
    if (agent.avatar_url) {
      const avatar = document.createElement('img');
      avatar.src = agent.avatar_url;
      avatar.alt = '';
      n.appendChild(avatar);
    } else {
      n.append('🤖 ');
    }
    n.append(agent.title);
    crumb.appendChild(n);
    crumb.appendChild(createLeaveAgentButton());
    topbarLeft.appendChild(crumb);
  }

  function createLeaveAgentButton() {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'agent-exit-btn';
    button.dataset.behavior = 'leave-agent';
    button.title = 'Leave this Agent and start a regular chat';
    button.setAttribute('aria-label', 'Leave this Agent');
    button.textContent = '×';

    return button;
  }

  function startQuickChat() {
    // The selected Agent is client-side until the first message. Clear it
    // before navigating so a fresh chat is truly a regular Quick Chat.
    clearUnavailableSharedAgent();
    window.location.assign('/ai-plus/agent-workspace');
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
        if (agent.avatar_url) {
          const avatar = document.createElement('img');
          avatar.src = agent.avatar_url;
          avatar.alt = '';
          agentName.appendChild(avatar);
        } else {
          agentName.append('🤖 ');
        }
        agentName.append(agent.title);
        crumb.appendChild(agentName);
        crumb.appendChild(createLeaveAgentButton());
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

  function updateTokenQuota(quota) {
    if (!quota) return;

    const quotaText = document.querySelector('[data-token-quota-text]');
    const quotaFill = document.querySelector('[data-token-quota-fill]');
    if (quotaText) {
      quotaText.textContent = `${Number(quota.used).toLocaleString()} / ${Number(quota.limit).toLocaleString()} tokens (in ${quota.month_name})`;
    }
    if (quotaFill) quotaFill.style.width = `${quota.percentage}%`;
  }

  function renderInitialMessages() {
    const messages = window.__INITIAL_MESSAGES__ || [];
    if (messages.length === 0) return;

    if (emptyState) emptyState.style.display = 'none';
    messages.forEach((m) => appendMessage(m.role, m.content, {
      messageId: m.id,
      editable: Boolean(m.editable),
    }));
  }

  function ensureMessagesContainer() {
    if (messagesContainer) return messagesContainer;
    messagesContainer = document.createElement('div');
    messagesContainer.id = 'chat-messages';
    messagesContainer.style.cssText = 'flex:1;overflow-y:auto;padding:24px;display:flex;flex-direction:column;gap:16px;';
    main.insertBefore(messagesContainer, inputArea);
    return messagesContainer;
  }

  function appendMessage(role, text, { messageId = null, editable = false } = {}) {
    const container = ensureMessagesContainer();

    // Wrapper chứa bubble + nút copy (để copy nằm ở góc).
    const wrap = document.createElement('div');
    wrap.className = 'chat-msg-wrap';
    if (messageId) wrap.dataset.messageId = String(messageId);
    wrap.style.cssText = 'display:flex;flex-direction:column;gap:4px;max-width:85%;'+(role === 'user' ? 'align-self:flex-end;' : 'align-self:flex-start;');

    const bubble = document.createElement('div');
    bubble.className = 'chat-bubble';
    bubble.style.cssText = role === 'user'
      ? 'background:var(--navy);color:#fff;padding:12px 16px;border-radius:14px;white-space:pre-wrap;'
      : 'background:var(--card-bg);border:1px solid var(--line);padding:12px 16px;border-radius:14px;white-space:normal;';

    // Render markdown cho assistant + user (nếu có ảnh kèm) — để ảnh hiện trong lịch sử.
    const displayText = role === 'assistant' ? prioritizeArtifactLinks(text) : text;
    const hasImageMd = typeof displayText === 'string' && displayText.includes('![');
    if (typeof marked !== 'undefined' && (role === 'assistant' || hasImageMd)) {
      bubble.innerHTML = renderSafeMarkdown(displayText);
    } else {
      bubble.textContent = displayText;
    }

    wrap.appendChild(bubble);
    if (role === 'user' && editable && messageId) addEditButton(wrap, bubble, messageId);
    addCopyButton(wrap, bubble);

    container.appendChild(wrap);

    container.scrollTop = container.scrollHeight;
    return wrap;
  }

  function disableAllPromptEditing() {
    document.querySelectorAll('.chat-edit-btn, .chat-edit-form').forEach((element) => element.remove());
  }

  function addEditButton(wrap, bubble, messageId) {
    if (!messageId || wrap.querySelector('.chat-edit-btn')) return;

    const editBtn = document.createElement('button');
    editBtn.type = 'button';
    editBtn.className = 'chat-edit-btn';
    editBtn.textContent = '✎ Edit';
    editBtn.title = 'Edit and regenerate this prompt';
    editBtn.style.cssText = 'align-self:flex-end;background:transparent;border:none;cursor:pointer;color:var(--text-soft,#5B6B7C);font-size:12px;padding:2px 6px;border-radius:6px;transition:background .15s;';
    editBtn.addEventListener('mouseenter', () => { editBtn.style.background = 'var(--input-bg, rgba(0,0,0,0.05))'; });
    editBtn.addEventListener('mouseleave', () => { editBtn.style.background = 'transparent'; });
    editBtn.addEventListener('click', () => startPromptEdit(wrap, bubble, messageId));
    wrap.appendChild(editBtn);
  }

  let editingPrompt = null;

  function startPromptEdit(wrap, bubble, messageId) {
    if (sending || wrap.querySelector('.chat-edit-form')) return;

    const originalText = bubble.innerText || bubble.textContent || '';
    const form = document.createElement('div');
    form.className = 'chat-edit-form';
    form.style.cssText = 'display:flex;flex-direction:column;gap:8px;width:100%;';
    const editor = document.createElement('textarea');
    editor.value = originalText;
    editor.rows = 3;
    editor.setAttribute('aria-label', 'Edit prompt');
    editor.style.cssText = 'width:100%;box-sizing:border-box;resize:vertical;min-height:76px;padding:10px 12px;border:1px solid var(--line);border-radius:10px;background:var(--input-bg);color:var(--text-main);font:inherit;line-height:1.45;';
    const actions = document.createElement('div');
    actions.style.cssText = 'display:flex;justify-content:flex-end;gap:8px;';
    const cancel = document.createElement('button');
    cancel.type = 'button';
    cancel.textContent = 'Cancel';
    cancel.style.cssText = 'padding:7px 11px;border:1px solid var(--line);border-radius:7px;background:var(--card-bg);color:var(--text-main);cursor:pointer;';
    const save = document.createElement('button');
    save.type = 'button';
    save.textContent = 'Update & resend';
    save.style.cssText = 'padding:7px 11px;border:none;border-radius:7px;background:var(--navy);color:#fff;cursor:pointer;';
    cancel.addEventListener('click', () => form.remove());
    save.addEventListener('click', () => {
      const updated = editor.value.trim();
      if (!updated) return;
      if (updated === originalText.trim()) {
        form.remove();
        return;
      }
      form.remove();
      editingPrompt = { messageId, wrap, bubble, originalText, removedNodes: [] };
      textarea.value = updated;
      resizeComposer();
      sendMessage();
    });
    actions.append(cancel, save);
    form.append(editor, actions);
    wrap.insertBefore(form, bubble.nextSibling);
    editor.focus();
  }

  function addCopyButton(wrap, bubble) {
    if (wrap.querySelector('.chat-copy-btn')) return;

    // Nút copy ở góc phải wrapper.
    const copyBtn = document.createElement('button');
    copyBtn.type = 'button';
    copyBtn.className = 'chat-copy-btn';
    copyBtn.textContent = '⧉';
    copyBtn.title = 'Copy';
    copyBtn.style.cssText = 'align-self:flex-end;background:transparent;border:none;cursor:pointer;color:var(--text-soft,#5B6B7C);font-size:14px;padding:2px 6px;border-radius:6px;transition:background .15s;';
    copyBtn.addEventListener('mouseenter', () => { copyBtn.style.background = 'var(--input-bg, rgba(0,0,0,0.05))'; });
    copyBtn.addEventListener('mouseleave', () => { copyBtn.style.background = 'transparent'; });
    copyBtn.addEventListener('click', async () => {
      const plainText = bubble.innerText || bubble.textContent || '';
      let ok = false;

      // On HTTPS, provide both representations explicitly. Rich destinations
      // (Word, email, Teams) use HTML; plain-text fields use text/plain.
      if (window.isSecureContext && navigator.clipboard && window.ClipboardItem) {
        try {
          const item = new ClipboardItem({
            'text/html': new Blob([bubble.innerHTML], { type: 'text/html' }),
            'text/plain': new Blob([plainText], { type: 'text/plain' }),
          });
          await navigator.clipboard.write([item]);
          ok = true;
        } catch (e) {
          // Fall through for browsers that deny the Clipboard API.
        }
      }

      // HTTP on the local network cannot use ClipboardItem. Copying an actual
      // DOM selection lets the browser place text/html on the clipboard too.
      if (!ok) {
        const selection = window.getSelection();
        const range = document.createRange();
        range.selectNodeContents(bubble);
        selection.removeAllRanges();
        selection.addRange(range);
        try {
          ok = document.execCommand('copy');
        } catch (e) {
          ok = false;
        }
        selection.removeAllRanges();
      }
      showMiniToast(ok ? '✅ Copied to clipboard' : '⚠️ Copy failed — select manually and copy');
    });
    wrap.appendChild(copyBtn);
  }

  // A streamed response starts as a typing bubble, so it is not built through
  // appendMessage(). Add its copy control only after there is actual content.
  function enableCopyForAssistantBubble(bubble) {
    if (!bubble || !bubble.isConnected) return;

    const existingWrap = bubble.closest('.chat-msg-wrap');
    if (existingWrap) {
      addCopyButton(existingWrap, bubble);
      return;
    }

    const wrap = document.createElement('div');
    wrap.className = 'chat-msg-wrap';
    wrap.style.cssText = 'display:flex;flex-direction:column;gap:4px;max-width:85%;align-self:flex-start;';
    bubble.replaceWith(wrap);
    wrap.appendChild(bubble);
    addCopyButton(wrap, bubble);
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
      bubble.innerHTML = renderSafeMarkdown(prioritizeArtifactLinks(text));
    } else {
      bubble.textContent = text;
    }

  }

  // File generation happens after the model has written its response, so the
  // persisted download link is normally at the end of a long bubble. Move it
  // to the top for visibility without altering the stored conversation.
  function prioritizeArtifactLinks(text) {
    if (typeof text !== 'string' || !text.includes('/ai-plus/artifacts/')) return text;

    const artifactLine = /(?:^|\n)(📄 \*\*File ready:\*\* \[[^\]]+\]\((?:https?:\/\/[^\s)]+)?\/ai-plus\/artifacts\/\d+\/download\))/g;
    const links = [];
    const remainder = text.replace(artifactLine, (_, link) => {
      links.push(link);
      return '';
    }).trim();

    return links.length === 0 ? text : `${links.join('\n')}\n\n${remainder}`;
  }

  // marked turns Markdown into HTML but does not sanitize it. Keep only the HTML that
  // Markdown needs; AI output and shared-agent prompts must never run browser code.
  function renderSafeMarkdown(text) {
    const knowledgeNotice = /^ℹ️ \*\*Knowledge notice:\*\* This question appears to be outside this Agent’s Knowledge\. The response below is based on the AI model’s general knowledge, not the Agent’s uploaded materials\.\s*/;
    const showKnowledgeWarning = knowledgeNotice.test(text);
    if (showKnowledgeWarning) text = text.replace(knowledgeNotice, '');
    text = normalizeBareMathNotation(text);
    // Render equations with our deterministic in-app formatter. MathJax was
    // loaded from a CDN and could race page restoration, intermittently
    // leaving users with raw $$...$$ source after refresh.
    text = latexToReadableText(text);
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
        const isExternalHttpLink = /^https?:\/\//i.test(href);
        const isArtifactDownloadLink = /^\/ai-plus\/artifacts\/\d+\/download(?:[?#].*)?$/i.test(href);
        if (!isExternalHttpLink && !isArtifactDownloadLink) {
          element.removeAttribute('href');
        } else {
          // Attributes were removed above to prevent event handlers and other
          // unsafe markup. Restore only the URL we just validated.
          element.setAttribute('href', href);
          if (isExternalHttpLink) {
            element.setAttribute('target', '_blank');
            element.setAttribute('rel', 'noopener noreferrer');
          }
        }
      }
      if (tag === 'img') {
        const src = element.getAttribute('src') || '';
        [...element.attributes].forEach((attribute) => element.removeAttribute(attribute.name));
        // A just-pasted image is rendered from a temporary data URL before the
        // server has saved it. On reload it is replaced with the private
        // attachment URL below. Allow only raster image MIME types and base64,
        // never SVG or arbitrary data: payloads.
        const isTemporaryPastedImage = /^data:image\/(?:png|jpeg|gif|webp);base64,[A-Za-z0-9+/=]+$/i.test(src);
        const isSavedChatAttachment = src.startsWith('/ai-plus/agent-workspace/attachments/');
        if (!isTemporaryPastedImage && !isSavedChatAttachment) {
          element.remove();
        } else {
          element.setAttribute('src', src);
          element.setAttribute('alt', 'Attached image');
          element.setAttribute('loading', 'lazy');
          // Keep uploads compact inside a chat bubble regardless of the
          // original camera/screenshot resolution.
          element.style.maxWidth = 'min(320px, 100%)';
          element.style.maxHeight = '260px';
          element.style.width = 'auto';
          element.style.height = 'auto';
          element.style.objectFit = 'contain';
          element.style.borderRadius = '8px';
          element.style.display = 'block';
        }
      } else if (tag !== 'a') {
        [...element.attributes].forEach((attribute) => element.removeAttribute(attribute.name));
      }
    });

    if (showKnowledgeWarning) {
      const callout = document.createElement('section');
      callout.setAttribute('role', 'alert');
      callout.style.cssText = 'display:flex;align-items:flex-start;gap:12px;margin:0 0 18px;padding:15px 16px;border:1px solid #d8921d;border-left:5px solid #b66b00;border-radius:10px;background:#fff4d9;color:#513000;box-shadow:0 2px 8px rgba(128,78,0,.10);';

      const icon = document.createElement('span');
      icon.textContent = '⚠️';
      icon.style.cssText = 'font-size:21px;line-height:1.2;flex:0 0 auto;';
      callout.appendChild(icon);

      const copy = document.createElement('div');
      const title = document.createElement('div');
      title.textContent = 'Outside Agent Knowledge';
      title.style.cssText = 'font-weight:800;font-size:16px;letter-spacing:.01em;margin-bottom:5px;';
      const detail = document.createElement('div');
      detail.textContent = 'This answer is based on the AI model’s general knowledge, not this Agent’s uploaded Knowledge. Please verify it before using it for school work.';
      detail.style.cssText = 'font-size:15px;line-height:1.5;';
      copy.append(title, detail);
      callout.appendChild(copy);
      template.content.prepend(callout);
    }

    return template.innerHTML;
  }

  // Models occasionally emit a small LaTex fragment without MathJax delimiters
  // (for example `35^\\circ` instead of `\\(35^\\circ\\)`). Render those
  // common forms readably rather than exposing LaTex braces to users.
  function normalizeBareMathNotation(text) {
    if (typeof text !== 'string') return text;

    const superscripts = { 0: '⁰', 1: '¹', 2: '²', 3: '³', 4: '⁴', 5: '⁵', 6: '⁶', 7: '⁷', 8: '⁸', 9: '⁹', '+': '⁺', '-': '⁻' };
    return text
      // Recover from a common model typo such as
      // $$ \\boxed{\\alpha\\approx38{,}0°. $$ (missing the final }).
      // Only math-looking dollar blocks are touched; normal currency prose is
      // left alone by repairLatexFormula().
      .replace(/\$\$([\s\S]*?)\$\$/g, (_, formula) => `$$\n${repairLatexFormula(formula.trim())}\n$$`)
      // Preserve the standard LaTex delimiters emitted by the model before
      // marked() can consume their backslashes as Markdown escapes.
      .replace(/\\\[([\s\S]*?)\\\]/g, (_, formula) => `\n$$\n${formula.trim()}\n$$`)
      .replace(/\\\(([^()\n]*?)\\\)/g, (_, formula) => `$${formula.trim()}$`)
      // AI models often use [ BD=... ] or [ \\frac{...} ] as informal
      // display-math delimiters. Markdown treats them as ordinary text, so
      // convert a standalone bracketed expression into $$...$$. Plain prose
      // in brackets remains untouched.
      .replace(/(^|\n)\s*\[\s*([^\]\n]+?)\s*\](?=\s*(?:\n|$))/g,
        (whole, prefix, formula) => /\\[A-Za-z]+|[=^]|\d\s*[+*/-]\s*\d/.test(formula)
          ? `${prefix}\n$$\n${formula.trim()}\n$$`
          : whole)
      // Inline formulae are commonly wrapped in regular parentheses, e.g.
      // (40\\text{ m}) or (AD=AB=40\\text{ m}). Preserve normal prose such
      // as (A) by requiring an equation or a LaTex command.
      .replace(/\(([^()\n]*(?:\\[A-Za-z]+|=)[^()\n]*)\)/g, (_, formula) => `$${formula.trim()}$`)
      .replace(/(\d+(?:[.,]\d+)?)\s*\^\s*\{?\\circ\}?/g, '$1°')
      .replace(/\^\{([0-9+-]+)\}/g, (_, exponent) => [...exponent].map((character) => superscripts[character] || character).join(''))
      .replace(/\\qquad/g, ' ')
      // A malformed \frac without brace groups cannot be interpreted safely;
      // keep its numeric text rather than exposing a broken LaTex command.
      .replace(/\\frac\s*([0-9][0-9,.)]*)/g, '$1')
      .replace(/\\times/g, '×')
      .replace(/\\div/g, '÷');
  }

  function repairLatexFormula(formula) {
    if (!/[\\^{}]/.test(formula)) return formula;

    // A final period belongs in the sentence, not inside \boxed{...}.
    const punctuation = formula.match(/([.!?;:])\s*$/)?.[1] || '';
    const expression = punctuation ? formula.replace(/[.!?;:]\s*$/, '') : formula;
    let openGroups = 0;
    for (let index = 0; index < expression.length; index++) {
      if (expression[index] === '\\') {
        index++;
        continue;
      }
      if (expression[index] === '{') openGroups++;
      if (expression[index] === '}' && openGroups > 0) openGroups--;
    }

    return expression + '}'.repeat(openGroups) + punctuation;
  }

  // A reliable in-browser fallback for environments where the MathJax CDN is
  // unavailable or still loading. It intentionally favors legibility over
  // typesetting: users see a readable formula, never an empty space or raw
  // LaTex command.
  function latexToReadableText(text) {
    const format = (formula) => {
      let output = repairLatexFormula(formula.trim());
      // Unwrap boxed results before removing grouping braces. A box may itself
      // contain groups such as 29{,}5, which a simple regular expression
      // cannot safely match as a whole.
      output = output.replace(/\\boxed\s*\{/g, '▣ ');
      // Work inside-out so \boxed{...\text{ m}} can be unwrapped safely.
      for (let pass = 0; pass < 3; pass++) {
        output = output.replace(/\\text\{([^{}]*)\}/g, '$1');
        // LaTex often protects a decimal comma as {,}; flatten it before
        // parsing fractions so \frac{15{,}951}{20} remains a valid fraction.
        output = output.replace(/\{,\}/g, ',');
        output = output.replace(/\\frac\{([^{}]*)\}\{([^{}]*)\}/g, '($1 / $2)');
      }
      return output
        .replace(/\\arctan/g, 'arctan')
        .replace(/\\(?:tan|sin|cos|cot)\s*/g, (command) => command.slice(1).trim() + ' ')
        .replace(/\\alpha/g, 'α')
        .replace(/\\beta/g, 'β')
        .replace(/\\theta/g, 'θ')
        .replace(/\\approx/g, '≈')
        .replace(/\\cdot/g, '·')
        .replace(/\\circ/g, '°')
        .replace(/\\qquad/g, ' ')
        .replace(/\\frac\s*([0-9][0-9,.)]*)/g, '$1')
        .replace(/[{}]/g, '');
    };

    return text
      .replace(/\$\$([\s\S]*?)\$\$/g, (_, formula) => `\n\n${format(formula)}\n\n`)
      .replace(/\$([^$\n]+)\$/g, (_, formula) => format(formula));
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
  const CHAT_DOCUMENT_EXTENSIONS = ['txt', 'csv', 'html', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'];
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
    const activeEdit = editingPrompt;
    if ((!message && pendingImages.length === 0 && pendingDocuments.length === 0) || sending) return;
    if (activeEdit && (pendingImages.length > 0 || pendingDocuments.length > 0)) {
      await WebUI.notice('Editing a prompt cannot include new attachments. Send attachments as a new message instead.', { title: 'Attachments unavailable while editing' });
      return;
    }

    const sentDraft = { key: currentDraftKey(), text: textarea.value };
    saveDraft();

    sending = true;
    if (sendBtn) {
      sendBtn.disabled = true;
      sendBtn.setAttribute('aria-busy', 'true');
      sendBtn.setAttribute('aria-label', 'AI is responding');
      sendBtn.textContent = 'Sending…';
    }

    if (emptyState) emptyState.style.display = 'none';

    disableAllPromptEditing();

    // Hiện ảnh + tên tài liệu kèm trong bubble user ngay khi gửi (dùng snapshot trước khi clear).
    const pendingSnapshot = pendingImages.slice();
    const pendingDocsSnapshot = pendingDocuments.slice();
    const docsLine = pendingDocsSnapshot.length > 0
      ? pendingDocsSnapshot.map((d) => '📎 ' + d.name).join('\n')
      : '';
    let userWrap;
    if (activeEdit) {
      editingPrompt = null;
      activeEdit.bubble.textContent = message;
      let node = activeEdit.wrap.nextSibling;
      while (node) {
        const next = node.nextSibling;
        activeEdit.removedNodes.push(node);
        node.remove();
        node = next;
      }
      userWrap = activeEdit.wrap;
    } else if (pendingSnapshot.length > 0 || docsLine) {
      const mdImg = pendingSnapshot.map((d) => `![Ảnh đính kèm](${d})`).join('\n');
      const parts = [message, mdImg, docsLine].filter(Boolean);
      userWrap = appendMessage('user', parts.join('\n\n'));
    } else {
      userWrap = appendMessage('user', message || '[Gửi tệp đính kèm]');
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
    let committedUserMessageId = null;
    const restoreUncommittedEdit = () => {
      if (!activeEdit || committedUserMessageId !== null) return;
      activeEdit.bubble.textContent = activeEdit.originalText;
      let anchor = activeEdit.wrap;
      activeEdit.removedNodes.forEach((node) => {
        anchor.after(node);
        anchor = node;
      });
      activeEdit.removedNodes = [];
    };
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
          edit_message_id: activeEdit?.messageId || null,
          images: images,
          documents: documents,
        }),
      },
    );

    const showOutputs = (data) => {
      // Artifact links are already included in the final assistant reply so
      // they remain available after a reload. Do not append a duplicate bubble.
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
            committedUserMessageId = data.user_message_id || null;
            resolveSentDraft(sentDraft);
            syncConversationTitle(data.title);
            updateTokenQuota(data.token_quota);
            assistantBubble.style.whiteSpace = 'normal';
            renderAssistantMarkdown(assistantBubble, data.reply);
            enableCopyForAssistantBubble(assistantBubble);
            showOutputs(data);
            ensureMessagesContainer().scrollTop = ensureMessagesContainer().scrollHeight;
          } else if (data.blocked) {
            assistantBubble.remove();
            restoreUncommittedEdit();
            appendWarning(data.warning || 'Nội dung của bạn chứa thông tin nhạy cảm.');
          } else if (data.error) {
            assistantBubble.remove();
            restoreUncommittedEdit();
            if (data.agent_unavailable || data.agent_copy_required) clearUnavailableSharedAgent();
            appendWarning(data.error);
          } else {
            assistantBubble.remove();
            restoreUncommittedEdit();
            appendWarning(data.message || `Yêu cầu không thành công (HTTP ${response.status}).`);
          }
          return;
        }
      }

      if (!response.ok || !response.body) {
        assistantBubble.remove();
        restoreUncommittedEdit();
        appendWarning(`Yêu cầu không thành công (HTTP ${response.status}).`);
        return;
      }

      const reader = response.body.getReader();
      const decoder = new TextDecoder('utf-8');
      let buffer = '';
      const container = ensureMessagesContainer();

      function handleStreamEvent(parsed) {
        if (parsed.event === 'turn_started') {
          committedUserMessageId = parsed.data.user_message_id || null;
          if (committedUserMessageId) userWrap.dataset.messageId = String(committedUserMessageId);
        } else if (parsed.event === 'delta') {
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
          committedUserMessageId = parsed.data.user_message_id || committedUserMessageId;
          resolveSentDraft(sentDraft);
          syncConversationTitle(parsed.data.title);
          updateTokenQuota(parsed.data.token_quota);
          assistantBubble.style.whiteSpace = 'normal';
          renderAssistantMarkdown(assistantBubble, parsed.data.reply || rawText);
          enableCopyForAssistantBubble(assistantBubble);
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
          enableCopyForAssistantBubble(assistantBubble);
        } else {
          assistantBubble.remove();
          appendWarning('Kết nối bị gián đoạn trước khi có phản hồi. Vui lòng thử lại.');
        }
      }
    } catch (err) {
      assistantBubble.remove();
      restoreUncommittedEdit();
      appendWarning('Có lỗi xảy ra, vui lòng thử lại.');
    } finally {
      sending = false;
      if (committedUserMessageId && userWrap?.isConnected) {
        userWrap.dataset.messageId = String(committedUserMessageId);
        const userBubble = userWrap.querySelector('.chat-bubble');
        if (userBubble) addEditButton(userWrap, userBubble, committedUserMessageId);
      } else if (activeEdit && userWrap?.isConnected) {
        const userBubble = userWrap.querySelector('.chat-bubble');
        if (userBubble) addEditButton(userWrap, userBubble, activeEdit.messageId);
      }
      if (sendBtn) {
        sendBtn.disabled = false;
        sendBtn.removeAttribute('aria-busy');
        sendBtn.setAttribute('aria-label', 'Send message');
        sendBtn.textContent = 'Send →';
      }
    }
  }

  function clipboardImageFile(item, index) {
    if (item.kind !== 'file') return null;

    const file = item.getAsFile();
    if (!file) return null;

    // Chrome, Edge, Office and screenshot tools do not always put the same
    // MIME type on ClipboardItem and File. Normalize it so FileReader emits
    // an image data URL that the server can persist and display.
    const mimeType = file.type || item.type;
    if (!mimeType || !mimeType.startsWith('image/')) return null;

    if (file.type === mimeType && file.name) return file;

    const extension = mimeType.split('/')[1]?.replace('jpeg', 'jpg') || 'png';
    return new File(
      [file],
      file.name || `pasted-image-${Date.now()}-${index}.${extension}`,
      { type: mimeType },
    );
  }

  textarea.addEventListener('paste', function (e) {
    const clipboard = e.clipboardData;
    if (!clipboard) return;

    const images = Array.from(clipboard.items || [])
      .map((item, index) => clipboardImageFile(item, index))
      .filter(Boolean);

    // Some browsers expose clipboard screenshots only through FileList.
    if (images.length === 0) {
      Array.from(clipboard.files || []).forEach((file, index) => {
        if (!file.type.startsWith('image/')) return;
        images.push(file.type && file.name
          ? file
          : new File([file], file.name || `pasted-image-${Date.now()}-${index}.png`, { type: file.type || 'image/png' }));
      });
    }

    if (images.length === 0) return;

    // Keep normal text paste intact, but do not paste an unreadable object
    // marker into the composer when the clipboard contains an image.
    e.preventDefault();
    images.forEach((file) => handleIncomingFile(file));
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

  // Delete a generated file directly from Recent files in the sidebar.
  document.querySelectorAll('.artifact-delete-btn').forEach((btn) => {
    btn.addEventListener('click', async (event) => {
      event.preventDefault();
      event.stopPropagation();

      const artifactId = btn.dataset.artifactId;
      if (!artifactId) return;
      const ok = await confirmDialog('This generated file will be permanently deleted. This cannot be undone.', {
        title: 'Delete file',
        confirmText: 'Delete file',
      });
      if (!ok) return;

      btn.disabled = true;
      try {
        const response = await fetch('/ai-plus/artifacts/' + encodeURIComponent(artifactId), {
          method: 'DELETE',
          headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
          },
        });
        if (!response.ok) {
          const data = await response.json().catch(() => ({}));
          throw new Error(data.message || 'The file could not be deleted. Please try again.');
        }

        btn.closest('.artifact-item-wrap')?.remove();
        showMiniToast('🗑️ File deleted');
      } catch (error) {
        btn.disabled = false;
        await WebUI.notice(error.message || 'We could not reach the server. Please try again.', { title: 'Could not delete file' });
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
    resizeComposer();
    queueDraftSave();
  });

  const topbarAttach = document.querySelector('[data-behavior="attach-topbar"]');
  topbarAttach?.addEventListener('click', function (e) {
    e.preventDefault();
    imageInput?.click();
  });

  const exportBtn = document.querySelector('[data-behavior="export-chat"]');
  const exportModal = document.getElementById('export-file-modal');
  const exportForm = document.getElementById('export-file-form');
  exportBtn?.addEventListener('click', function () {
    if (!conversationId) {
      showMiniToast('⚠️ Send a message first, then export the AI response');
      return;
    }
    exportModal?.removeAttribute('hidden');
  });

  document.querySelectorAll('[data-behavior="close-export-modal"]').forEach((button) => {
    button.addEventListener('click', () => exportModal?.setAttribute('hidden', ''));
  });
  exportModal?.addEventListener('click', (event) => {
    if (event.target === exportModal) exportModal.setAttribute('hidden', '');
  });
  exportForm?.addEventListener('submit', async (event) => {
    event.preventDefault();
    if (!conversationId) return;

    const submit = exportForm.querySelector('.export-submit');
    const form = new FormData(exportForm);
    submit.disabled = true;
    submit.textContent = 'Preparing…';
    try {
      const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
      const response = await fetch(`/ai-plus/agent-workspace/conversations/${encodeURIComponent(conversationId)}/export`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
        body: JSON.stringify(Object.fromEntries(form.entries())),
      });
      const data = await response.json();
      if (!response.ok) throw new Error(data.error || data.message || 'Could not create the file.');

      appendMessage('assistant', `📄 **File ready:** [${data.name}](${data.url})`);
      updateTokenQuota(data.token_quota);
      exportModal?.setAttribute('hidden', '');
      showMiniToast('✅ File is ready to download');
    } catch (error) {
      appendWarning(error.message || 'Could not create the file.');
    } finally {
      submit.disabled = false;
      submit.textContent = 'Create file';
    }
  });

  const moveConversationModal = document.getElementById('move-conversation-modal');
  const moveConversationForm = document.getElementById('move-conversation-form');
  document.querySelector('[data-behavior="move-conversation"]')?.addEventListener('click', (event) => {
    event.preventDefault();
    moveConversationModal?.removeAttribute('hidden');
  });
  document.querySelectorAll('[data-behavior="close-move-modal"]').forEach((button) => {
    button.addEventListener('click', () => moveConversationModal?.setAttribute('hidden', ''));
  });
  moveConversationModal?.addEventListener('click', (event) => {
    if (event.target === moveConversationModal) moveConversationModal.setAttribute('hidden', '');
  });
  moveConversationForm?.addEventListener('submit', async (event) => {
    event.preventDefault();
    if (!conversationId) return;

    const submit = moveConversationForm.querySelector('.export-submit');
    const agentId = document.getElementById('move-conversation-agent')?.value || null;
    submit.disabled = true;
    submit.textContent = 'Moving…';
    try {
      const response = await fetch(`/ai-plus/agent-workspace/conversations/${encodeURIComponent(conversationId)}/move`, {
        method: 'PATCH',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
        },
        body: JSON.stringify({ agent_id: agentId }),
      });
      const data = await response.json().catch(() => ({}));
      if (!response.ok) throw new Error(data.error || data.message || 'The conversation could not be moved.');

      moveConversationModal?.setAttribute('hidden', '');
      showMiniToast(agentId ? '🤖 Conversation moved to Agent' : '💬 Conversation moved to Quick Chat');
      window.setTimeout(() => window.location.reload(), 550);
    } catch (error) {
      await WebUI.notice(error.message || 'The conversation could not be moved. Please try again.', { title: 'Could not move conversation' });
    } finally {
      submit.disabled = false;
      submit.textContent = 'Move conversation';
    }
  });

  renderInitialMessages();
  syncAgentBadge();
  restoreDraft();
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
      resizeComposer();
      queueDraftSave();
      textarea.focus();
    },
    'quick-generate-report': function () {
      textarea.value = 'Generate a concise report based on the following information:\n\n';
      resizeComposer();
      queueDraftSave();
      textarea.focus();
    },
  };

  document.querySelectorAll('.quick-btn').forEach((btn) => {
    const behavior = quickBtnBehaviors[btn.dataset.behavior];
    if (behavior) {
      btn.addEventListener('click', behavior);
    }
  });

  // Returning to the Chat tab intentionally clears the client-side Agent
  // selection. Existing Agent conversations remain available in the sidebar.
  document.querySelector('[data-behavior="new-quick-chat"]')?.addEventListener('click', (event) => {
    event.preventDefault();
    startQuickChat();
  });
  document.addEventListener('click', (event) => {
    if (event.target.closest('[data-behavior="leave-agent"]')) {
      event.preventDefault();
      startQuickChat();
    }
  });
});
