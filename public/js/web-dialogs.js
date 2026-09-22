// Shared, accessible application dialogs. They replace browser alert(),
// confirm(), and prompt() so feedback stays consistent across AI+ and Admin.
(function () {
  function createOverlay() {
    const overlay = document.createElement('div');
    overlay.setAttribute('role', 'presentation');
    overlay.style.cssText = 'position:fixed;inset:0;z-index:10000;display:flex;align-items:center;justify-content:center;padding:20px;background:rgba(15,23,42,.52);';
    return overlay;
  }

  function createBox(title, tone = 'default') {
    const box = document.createElement('section');
    box.setAttribute('role', 'dialog');
    box.setAttribute('aria-modal', 'true');
    box.setAttribute('aria-label', title);
    box.style.cssText = 'width:min(100%,440px);border-radius:16px;overflow:hidden;background:var(--card-bg,#fff);box-shadow:0 24px 48px -12px rgba(15,23,42,.45);color:var(--ink,#22303F);';

    const header = document.createElement('h2');
    header.textContent = title;
    header.style.cssText = `margin:0;padding:20px 24px;border-bottom:1px solid var(--line,#E1DACB);font:600 20px Fraunces,Georgia,serif;color:${tone === 'danger' ? '#b42318' : 'var(--navy,#1F3864)'};`;
    box.appendChild(header);
    return box;
  }

  function button(label, primary, danger = false) {
    const element = document.createElement('button');
    element.type = 'button';
    element.textContent = label;
    element.style.cssText = primary
      ? `padding:10px 18px;border:0;border-radius:8px;cursor:pointer;font:500 14px inherit;color:#fff;background:${danger ? '#b42318' : 'var(--navy,#1F3864)'};`
      : 'padding:10px 18px;border:1px solid var(--line,#E1DACB);border-radius:8px;cursor:pointer;font:500 14px inherit;color:var(--ink,#22303F);background:var(--paper,#F6F3EC);';
    return element;
  }

  function appendBody(box, message) {
    const body = document.createElement('p');
    body.textContent = message;
    body.style.cssText = 'margin:0;padding:20px 24px;font-size:14px;line-height:1.55;white-space:pre-line;';
    box.appendChild(body);
    return body;
  }

  function appendFooter(box) {
    const footer = document.createElement('div');
    footer.style.cssText = 'display:flex;justify-content:flex-end;gap:10px;padding:16px 24px;';
    box.appendChild(footer);
    return footer;
  }

  function mount(overlay, box, onDismiss) {
    overlay.appendChild(box);
    document.body.appendChild(overlay);
    overlay.addEventListener('click', (event) => { if (event.target === overlay) onDismiss(); });
    const escape = function (event) {
      if (event.key !== 'Escape') return;
      document.removeEventListener('keydown', escape);
      onDismiss();
    };
    overlay._webUiEscape = escape;
    document.addEventListener('keydown', escape);
  }

  function removeOverlay(overlay) {
    if (overlay._webUiEscape) document.removeEventListener('keydown', overlay._webUiEscape);
    overlay.remove();
  }

  function notice(message, { title = 'Something went wrong', danger = true } = {}) {
    return new Promise((resolve) => {
      const overlay = createOverlay();
      const box = createBox(title, danger ? 'danger' : 'default');
      appendBody(box, message);
      const footer = appendFooter(box);
      const close = () => { removeOverlay(overlay); resolve(); };
      const closeButton = button('Close', true);
      closeButton.addEventListener('click', close);
      footer.appendChild(closeButton);
      mount(overlay, box, close);
      closeButton.focus();
    });
  }

  function confirm(message, { title = 'Please confirm', confirmText = 'Continue', danger = false } = {}) {
    return new Promise((resolve) => {
      const overlay = createOverlay();
      const box = createBox(title, danger ? 'danger' : 'default');
      appendBody(box, message);
      const footer = appendFooter(box);
      const close = (result) => { removeOverlay(overlay); resolve(result); };
      const cancel = button('Cancel', false);
      const accept = button(confirmText, true, danger);
      cancel.addEventListener('click', () => close(false));
      accept.addEventListener('click', () => close(true));
      footer.append(cancel, accept);
      mount(overlay, box, () => close(false));
      cancel.focus();
    });
  }

  function prompt({ title = 'Enter a value', label = 'Value', value = '', submitText = 'Save' } = {}) {
    return new Promise((resolve) => {
      const overlay = createOverlay();
      const box = createBox(title);
      const form = document.createElement('form');
      form.style.cssText = 'padding:20px 24px 0;';
      const inputLabel = document.createElement('label');
      inputLabel.textContent = label;
      inputLabel.style.cssText = 'display:block;margin-bottom:8px;font-size:14px;font-weight:600;';
      const input = document.createElement('input');
      input.type = 'text';
      input.value = value;
      input.maxLength = 255;
      input.style.cssText = 'box-sizing:border-box;width:100%;padding:10px 12px;border:1px solid var(--line,#E1DACB);border-radius:8px;background:var(--input-bg,#fff);color:inherit;font:14px inherit;';
      inputLabel.appendChild(input);
      form.appendChild(inputLabel);
      box.appendChild(form);
      const footer = appendFooter(box);
      const close = (result) => { removeOverlay(overlay); resolve(result); };
      const cancel = button('Cancel', false);
      const save = button(submitText, true);
      cancel.addEventListener('click', () => close(null));
      form.addEventListener('submit', (event) => { event.preventDefault(); close(input.value.trim()); });
      save.addEventListener('click', () => close(input.value.trim()));
      footer.append(cancel, save);
      mount(overlay, box, () => close(null));
      input.focus();
      input.select();
    });
  }

  function copyText(text, { title = 'Copy link', message = 'Copy this link:' } = {}) {
    return new Promise((resolve) => {
      const overlay = createOverlay();
      const box = createBox(title);
      appendBody(box, message);
      const input = document.createElement('input');
      input.type = 'text';
      input.readOnly = true;
      input.value = text;
      input.style.cssText = 'box-sizing:border-box;width:calc(100% - 48px);margin:0 24px;padding:10px 12px;border:1px solid var(--line,#E1DACB);border-radius:8px;background:var(--paper,#F6F3EC);color:inherit;font:14px inherit;';
      box.appendChild(input);
      const footer = appendFooter(box);
      const close = () => { removeOverlay(overlay); resolve(); };
      const done = button('Close', false);
      const copy = button('Copy', true);
      copy.addEventListener('click', async () => {
        try { await navigator.clipboard.writeText(text); } catch (_) { input.select(); document.execCommand('copy'); }
        copy.textContent = 'Copied';
      });
      done.addEventListener('click', close);
      footer.append(done, copy);
      mount(overlay, box, close);
      input.focus();
      input.select();
    });
  }

  function setPageLoading(active, label = 'Loading…') {
    const id = 'webui-page-loading';
    const existing = document.getElementById(id);
    if (!active) {
      existing?.remove();
      return;
    }
    if (existing) return;
    const overlay = document.createElement('div');
    overlay.id = id;
    overlay.setAttribute('role', 'status');
    overlay.setAttribute('aria-live', 'polite');
    overlay.style.cssText = 'position:fixed;inset:0;z-index:9999;display:flex;align-items:flex-start;justify-content:center;padding-top:24px;background:rgba(255,255,255,.01);cursor:wait;';
    const status = document.createElement('div');
    status.textContent = label;
    status.style.cssText = 'padding:10px 16px;border-radius:999px;background:var(--navy,#1F3864);box-shadow:0 8px 24px rgba(15,23,42,.22);color:#fff;font:500 14px inherit;';
    overlay.appendChild(status);
    document.body.appendChild(overlay);
  }

  window.WebUI = { notice, confirm, prompt, copyText, setPageLoading };

  document.addEventListener('submit', (event) => {
    const form = event.target.closest('form[data-web-confirm]');
    if (!form || form.dataset.webConfirmed === 'true') {
      if (form) delete form.dataset.webConfirmed;
      return;
    }
    event.preventDefault();
    const submitter = event.submitter;
    confirm(form.dataset.webConfirm, {
      title: form.dataset.webConfirmTitle || 'Please confirm',
      confirmText: form.dataset.webConfirmAction || 'Continue',
      danger: form.dataset.webConfirmDanger === 'true',
    }).then((accepted) => {
      if (!accepted) return;
      form.dataset.webConfirmed = 'true';
      form.requestSubmit(submitter);
    });
  });
})();
