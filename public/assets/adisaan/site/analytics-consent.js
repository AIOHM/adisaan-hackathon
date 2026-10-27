(() => {
  'use strict';
  const key = 'adisaan_analytics_choice_v1';
  const panel = document.querySelector('[data-analytics-choice-panel]');
  if (!panel) return;
  const readChoice = () => {
    try { return JSON.parse(localStorage.getItem(key) || 'null'); } catch { return null; }
  };
  const clearAnalyticsCookies = () => {
    for (const part of document.cookie.split(';')) {
      const name = part.trim().split('=')[0];
      if (!/^_ga(?:_|$)/.test(name)) continue;
      for (const domain of ['', `; domain=${location.hostname}`, `; domain=.${location.hostname}`]) {
        document.cookie = `${name}=; Max-Age=0; path=/${domain}; SameSite=Lax`;
      }
    }
  };
  const logChoice = (allowed) => {
    const token = document.querySelector('meta[name="csrf-token"]')?.content;
    if (!token) return;
    fetch('/cookie-consent/log', {
      method: 'POST', credentials: 'same-origin', keepalive: true,
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token },
      body: JSON.stringify({
        consent_id: crypto.randomUUID ? crypto.randomUUID() : `choice-${Date.now()}`,
        decision: allowed ? 'analytics_allowed' : 'analytics_denied',
        preferences: { essential: true, analytics: allowed, marketing: false },
        page_url: `${location.origin}${location.pathname}`,
      }),
    }).catch(() => {});
  };
  const choose = (allowed) => {
    try { localStorage.setItem(key, JSON.stringify({ analytics: allowed, updated_at: new Date().toISOString() })); } catch {}
    if (typeof window.gtag === 'function') {
      window.gtag('consent', 'update', { analytics_storage: allowed ? 'granted' : 'denied' });
      if (allowed) window.gtag('event', 'page_view', { page_location: `${location.origin}${location.pathname}` });
    }
    if (!allowed) clearAnalyticsCookies();
    logChoice(allowed);
    panel.hidden = true;
    document.querySelector('[data-analytics-preferences]')?.focus({ preventScroll: true });
  };
  panel.querySelector('[data-analytics-choice="allow"]')?.addEventListener('click', () => choose(true));
  panel.querySelector('[data-analytics-choice="deny"]')?.addEventListener('click', () => choose(false));
  document.querySelector('[data-analytics-preferences]')?.addEventListener('click', () => {
    panel.hidden = false;
    panel.querySelector('[data-analytics-choice="allow"]')?.focus({ preventScroll: true });
  });
  if (!readChoice()) panel.hidden = false;
})();
