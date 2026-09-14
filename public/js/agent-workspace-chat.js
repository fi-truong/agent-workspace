document.addEventListener('DOMContentLoaded', function () {
  const sendBtn = document.querySelector('.send-btn');
  const textarea = document.querySelector('.input-box textarea');
  const emptyState = document.querySelector('.empty-state');
  const main = document.querySelector('main.main');
  const inputArea = document.querySelector('.input-area');
  const appEl = document.querySelector('.app');

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
    if (!appEl) return;
    const raw = appEl.getAttribute('data-initial-messages');
    if (!raw) return;

    let messages = [];
    try {
      messages = JSON.parse(raw);
    } catch (e) {
      return;
    }
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
      : 'align-self:flex-start;background:var(--card-bg);border:1px solid var(--line);padding:12px 16px;border-radius:14px;max-width:70%;white-space:pre-wrap;';
    bubble.textContent = text;
    container.appendChild(bubble);
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

  async function sendMessage() {
    const message = textarea.value.trim();
    if (!message || sending) return;

    sending = true;
    if (sendBtn) sendBtn.textContent = 'Sending…';

    if (emptyState) emptyState.style.display = 'none';
    appendMessage('user', message);
    textarea.value = '';

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

  // Mở lại conversation cũ → render history sau khi DOM sẵn sàng.
  renderInitialMessages();
});