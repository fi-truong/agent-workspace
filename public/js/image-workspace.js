document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('image-generation-form');
  const promptInput = document.getElementById('image-prompt');
  const submit = document.getElementById('image-generate-submit');
  const results = document.getElementById('image-results');
  const empty = document.getElementById('image-empty');
  const referenceInput = document.getElementById('image-reference-input');
  const referenceTrigger = document.getElementById('image-reference-trigger');
  const referencePreviews = document.getElementById('image-reference-previews');
  const modelSelect = document.getElementById('image-model-select');
  const modelHint = document.getElementById('image-model-hint');
  const activeSourceBox = document.getElementById('image-active-source');
  const startNewButton = document.getElementById('image-start-new');
  let conversationId = window.__IMAGE_CONVERSATION_ID__ || null;
  let referenceImages = [];
  let activeSource = null;
  const MAX_REFERENCE_IMAGES = 4;
  const MAX_REFERENCE_BYTES = 4 * 1024 * 1024;

  if (!form || !promptInput || !submit || !results) return;

  function appendImage({url, prompt, messageId, downloadUrl, isLatest = false}) {
    if (empty) empty.remove();
    const card = document.createElement('article');
    card.className = 'generated-image-card';
    if (messageId) card.dataset.messageId = messageId;
    const image = document.createElement('img');
    image.src = url;
    image.alt = prompt || 'Generated image';
    image.loading = 'lazy';
    const caption = document.createElement('p');
    caption.textContent = prompt || 'Generated image';
    const actions = document.createElement('div');
    actions.className = 'generated-image-actions';
    const download = document.createElement('a');
    download.textContent = 'Download PNG';
    download.href = downloadUrl || url;
    download.download = 'ai-plus-image.png';
    const copy = document.createElement('button');
    copy.type = 'button';
    copy.textContent = 'Copy prompt';
    copy.addEventListener('click', () => copyPrompt(prompt));
    const edit = document.createElement('button');
    edit.type = 'button';
    edit.className = 'image-edit-button';
    edit.textContent = 'Edit this image';
    edit.addEventListener('click', () => setActiveSource({messageId, url, prompt}, true));
    const remove = document.createElement('button');
    remove.type = 'button';
    remove.className = 'image-delete-button';
    remove.textContent = 'Delete';
    remove.addEventListener('click', () => deleteImage(card, messageId, remove));
    actions.append(download, copy, edit, remove);
    card.append(image, caption, actions);
    results.prepend(card);
    if (isLatest) setActiveSource({messageId, url, prompt}, false);
  }

  function setActiveSource(source, focusPrompt) {
    if (!source?.messageId || !activeSourceBox) return;
    activeSource = source;
    if (focusPrompt) {
      referenceImages = [];
      renderReferencePreviews();
    }
    document.querySelectorAll('.generated-image-card.is-active-source').forEach((card) => card.classList.remove('is-active-source'));
    document.querySelector(`.generated-image-card[data-message-id="${source.messageId}"]`)?.classList.add('is-active-source');
    activeSourceBox.hidden = false;
    activeSourceBox.style.display = 'flex';
    activeSourceBox.innerHTML = '';
    const image = document.createElement('img');
    image.src = source.url;
    image.alt = 'Active image version';
    const text = document.createElement('span');
    text.textContent = 'Editing selected image version with Sunburst';
    const clear = document.createElement('button');
    clear.type = 'button';
    clear.textContent = 'Change';
    clear.title = 'Choose a different image or start new';
    clear.addEventListener('click', clearActiveSource);
    activeSourceBox.append(image, text, clear);
    syncModelForReferences();
    if (focusPrompt) promptInput.focus();
  }

  function clearActiveSource() {
    activeSource = null;
    if (activeSourceBox) {
      activeSourceBox.hidden = true;
      activeSourceBox.style.display = 'none';
      activeSourceBox.replaceChildren();
    }
    document.querySelectorAll('.generated-image-card.is-active-source').forEach((card) => card.classList.remove('is-active-source'));
    syncModelForReferences();
  }

  async function copyPrompt(prompt) {
    try {
      await navigator.clipboard.writeText(prompt || '');
    } catch (_) {
      const fallback = document.createElement('textarea');
      fallback.value = prompt || '';
      document.body.appendChild(fallback);
      fallback.select();
      document.execCommand('copy');
      fallback.remove();
    }
  }

  async function deleteImage(card, messageId, button) {
    if (!messageId || !await WebUI.confirm('This cannot be undone.', {
      title: 'Delete generated image', confirmText: 'Delete image', danger: true,
    })) return;
    button.disabled = true;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    try {
      const response = await fetch(`/ai-plus/agent-workspace/images/${messageId}`, {
        method: 'DELETE', headers: {'X-CSRF-TOKEN': csrf, Accept: 'application/json'},
      });
      if (!response.ok) throw new Error();
      const data = await response.json();
      card.remove();
      if (activeSource?.messageId === messageId) clearActiveSource();
      if (data.conversation_deleted) window.location.assign('/ai-plus/agent-workspace/images');
    } catch (_) {
      button.disabled = false;
      await WebUI.notice('This image could not be deleted. Please try again.', { title: 'Could not delete image' });
    }
  }

  function imageUrlFromMarkdown(content) {
    const match = /!\[[^\]]*\]\(([^)]+)\)/.exec(content || '');
    return match ? match[1] : null;
  }

  (window.__IMAGE_INITIAL_RESULTS__ || []).forEach((entry) => {
    const url = imageUrlFromMarkdown(entry.content);
    if (url) appendImage({url, prompt: entry.prompt, messageId: entry.message_id, downloadUrl: entry.download_url, isLatest: entry.is_latest});
  });

  document.querySelectorAll('.image-prompt-examples button').forEach((button) => {
    button.addEventListener('click', () => {
      promptInput.value = button.textContent.trim();
      promptInput.focus();
    });
  });

  startNewButton?.addEventListener('click', () => {
    conversationId = null;
    clearActiveSource();
    referenceImages = [];
    renderReferencePreviews();
    promptInput.value = '';
    window.history.replaceState({}, '', '/ai-plus/agent-workspace/images');
    promptInput.focus();
  });

  document.querySelectorAll('.image-session-delete').forEach((button) => {
    button.addEventListener('click', async (event) => {
      event.preventDefault();
      event.stopPropagation();
      if (!await WebUI.confirm('All generated images in this session will be removed. Token usage remains in your monthly total.', {
        title: 'Delete image session', confirmText: 'Delete session', danger: true,
      })) return;
      const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
      button.disabled = true;
      try {
        const response = await fetch(`/ai-plus/agent-workspace/conversations/${button.dataset.conversationId}`, {
          method: 'DELETE', headers: {'X-CSRF-TOKEN': csrf, Accept: 'application/json'},
        });
        if (!response.ok) throw new Error('This image session could not be deleted. Please try again.');
        window.location.assign('/ai-plus/agent-workspace/images');
      } catch (error) {
        button.disabled = false;
        await WebUI.notice(error.message || 'We could not reach the server. Please try again.', { title: 'Could not delete session' });
      }
    });
  });

  function renderReferencePreviews() {
    if (!referencePreviews) return;
    referencePreviews.innerHTML = '';
    referenceImages.forEach((reference, index) => {
      const wrap = document.createElement('div');
      wrap.className = 'image-reference-preview';
      const image = document.createElement('img');
      image.src = reference.dataUrl;
      image.alt = reference.name;
      const remove = document.createElement('button');
      remove.type = 'button';
      remove.textContent = '×';
      remove.title = `Remove ${reference.name}`;
      remove.addEventListener('click', () => {
        referenceImages.splice(index, 1);
        renderReferencePreviews();
      });
      wrap.append(image, remove);
      referencePreviews.append(wrap);
    });
    referencePreviews.hidden = referenceImages.length === 0;
    syncModelForReferences();
  }

  function syncModelForReferences() {
    if (!modelSelect) return;
    const hasReferences = referenceImages.length > 0 || activeSource !== null;
    const sunburst = [...modelSelect.options].find((option) => option.dataset.supportsEdits === 'true');
    if (hasReferences && sunburst) {
      modelSelect.value = sunburst.value;
      modelSelect.disabled = true;
      if (modelHint) modelHint.textContent = activeSource ? 'Sunburst is selected to refine the active image version. Start a new image to use a different model.' : 'Sunburst is selected for precise editing with your reference images. Remove all references to choose a different model.';
    } else {
      modelSelect.disabled = false;
      if (modelHint) modelHint.textContent = 'Choose Flare for speed or Sunburst for precise edits. Paste an image or choose up to 4 PNG, JPEG, or WebP images (4 MB each).';
    }
  }

  function addReferenceImage(file) {
    if (!file) return;
    if (![...(modelSelect?.options || [])].some((option) => option.dataset.supportsEdits === 'true')) {
      WebUI.notice('Image editing is not currently enabled by the administrator.', { title: 'Image editing unavailable' });
      return;
    }
    if (!['image/png', 'image/jpeg', 'image/webp'].includes(file.type) || file.size > MAX_REFERENCE_BYTES) {
      WebUI.notice('Reference images must be PNG, JPEG, or WebP files up to 4 MB.', { title: 'Unsupported reference image' });
      return;
    }
    if (referenceImages.length >= MAX_REFERENCE_IMAGES) {
      WebUI.notice(`You can add up to ${MAX_REFERENCE_IMAGES} reference images.`, { title: 'Reference image limit' });
      return;
    }
    const reader = new FileReader();
    reader.onload = () => {
      referenceImages.push({name: file.name || 'Reference image', dataUrl: reader.result});
      renderReferencePreviews();
    };
    reader.readAsDataURL(file);
  }

  referenceTrigger?.addEventListener('click', () => referenceInput?.click());
  referenceInput?.addEventListener('change', () => {
    [...referenceInput.files].forEach(addReferenceImage);
    referenceInput.value = '';
  });
  promptInput.addEventListener('paste', (event) => {
    [...(event.clipboardData?.items || [])].forEach((item) => {
      if (item.type.startsWith('image/')) {
        event.preventDefault();
        addReferenceImage(item.getAsFile());
      }
    });
  });

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    const prompt = promptInput.value.trim();
    if (!prompt || submit.disabled) return;

    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    submit.disabled = true;
    submit.setAttribute('aria-busy', 'true');
    form.setAttribute('aria-busy', 'true');
    if (referenceTrigger) referenceTrigger.disabled = true;
    if (modelSelect) modelSelect.disabled = true;
    submit.textContent = 'Generating…';
    const progress = document.createElement('div');
    progress.className = 'image-generating';
    progress.textContent = 'Creating your image…';
    results.prepend(progress);

    try {
      const response = await fetch('/ai-plus/agent-workspace/generate-image', {
        method: 'POST',
        headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, Accept: 'application/json'},
        body: JSON.stringify({prompt, conversation_id: conversationId, reference_images: referenceImages.map((image) => image.dataUrl), source_message_id: activeSource?.messageId, model: modelSelect?.value}),
      });
      const data = await response.json();
      if (!response.ok) throw new Error(data.error || 'Image generation failed. Please try again.');

      conversationId = data.conversation_id;
      appendImage({url: data.image_url, prompt: data.prompt, messageId: data.image_message_id, downloadUrl: data.download_url, isLatest: true});
      promptInput.value = '';
      referenceImages = [];
      renderReferencePreviews();
      window.history.replaceState({}, '', `/ai-plus/agent-workspace/images?conversation_id=${conversationId}`);
    } catch (error) {
      await WebUI.notice(error.message || 'Image generation failed. Please try again.', { title: 'Could not generate image' });
    } finally {
      progress.remove();
      submit.disabled = false;
      submit.removeAttribute('aria-busy');
      form.removeAttribute('aria-busy');
      if (referenceTrigger) referenceTrigger.disabled = false;
      syncModelForReferences();
      submit.innerHTML = 'Generate image <span>→</span>';
    }
  });
});
