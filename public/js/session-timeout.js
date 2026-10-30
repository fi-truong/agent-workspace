(() => {
  const timeoutMeta = document.querySelector('meta[name="ai-plus-session-timeout-ms"]');
  const loginMeta = document.querySelector('meta[name="ai-plus-login-url"]');
  const csrfMeta = document.querySelector('meta[name="csrf-token"]');
  const timeoutMs = Number(timeoutMeta?.content);
  if (!timeoutMeta || !loginMeta || !Number.isFinite(timeoutMs) || timeoutMs <= 0) return;

  const loginUrl = new URL(loginMeta.content, window.location.href);
  const activityKey = 'ai-plus-session-last-request';
  const expiredKey = 'ai-plus-session-expired';
  const ignoredActivityPaths = new Set(['/admin/tickets/new-count']);
  const originalFetch = window.fetch.bind(window);
  let expiryTimer = null;
  let redirecting = false;

  const loginTarget = () => {
    const target = new URL(loginUrl.href);
    target.searchParams.set('session_expired', '1');
    return target.href;
  };

  const navigateToLogin = () => {
    redirecting = true;
    window.clearTimeout(expiryTimer);
    window.location.replace(loginTarget());
  };

  const expireSession = () => {
    if (redirecting) return;
    redirecting = true;
    window.clearTimeout(expiryTimer);

    try {
      window.localStorage.setItem(expiredKey, String(Date.now()));
    } catch (_) {
      // The current tab still redirects if browser storage is unavailable.
    }

    const csrfToken = csrfMeta?.content || '';
    let navigationTimer = window.setTimeout(navigateToLogin, 1000);
    originalFetch('/logout', {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'X-CSRF-TOKEN': csrfToken,
        'X-Requested-With': 'XMLHttpRequest',
        Accept: 'application/json',
      },
    }).catch(() => {}).finally(() => {
      window.clearTimeout(navigationTimer);
      navigateToLogin();
    });
  };

  const scheduleExpiry = (lastRequestAt) => {
    window.clearTimeout(expiryTimer);
    const remainingMs = timeoutMs - (Date.now() - lastRequestAt);
    if (remainingMs <= 0) {
      expireSession();
      return;
    }
    expiryTimer = window.setTimeout(expireSession, remainingMs);
  };

  const markAuthenticatedRequest = () => {
    const now = Date.now();
    try {
      window.localStorage.setItem(activityKey, String(now));
      window.localStorage.removeItem(expiredKey);
    } catch (_) {
      // The timeout still works for this tab if browser storage is unavailable.
    }
    scheduleExpiry(now);
  };

  window.addEventListener('storage', (event) => {
    if (event.key === expiredKey && event.newValue) {
      navigateToLogin();
      return;
    }

    if (event.key === activityKey && event.newValue) {
      const lastRequestAt = Number(event.newValue);
      if (Number.isFinite(lastRequestAt)) scheduleExpiry(lastRequestAt);
    }
  });

  window.fetch = async (resource, options) => {
    let requestUrl;
    try {
      const url = typeof resource === 'string' || resource instanceof URL ? resource : resource.url;
      requestUrl = new URL(url, window.location.href);
    } catch (_) {
      return originalFetch(resource, options);
    }

    const isSameOrigin = requestUrl.origin === window.location.origin;
    const isBackgroundPoll = ignoredActivityPaths.has(requestUrl.pathname);
    if (isSameOrigin && !isBackgroundPoll) markAuthenticatedRequest();

    const response = await originalFetch(resource, options);
    if (!isSameOrigin) return response;

    const responsePath = response.url ? new URL(response.url, window.location.href).pathname : requestUrl.pathname;
    const redirectedToLogin = response.redirected && responsePath === loginUrl.pathname;
    if (response.status === 401 || response.status === 419 || redirectedToLogin) {
      expireSession();
      return response;
    }

    if (!isBackgroundPoll) markAuthenticatedRequest();
    return response;
  };

  markAuthenticatedRequest();
})();
