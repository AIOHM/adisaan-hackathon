(function () {
    const form      = document.getElementById('muse-public-form');
    const input     = document.getElementById('muse-public-input');
    const sendBtn   = document.getElementById('muse-public-send');
    const msgBox    = document.getElementById('muse-public-messages');
    const askMuse   = document.getElementById('ask-muse');
    const hero      = document.getElementById('hero');
    const floatWrap = document.getElementById('muse-float');
    const floatForm = document.getElementById('muse-float-form');
    const floatIn   = document.getElementById('muse-float-input');
    const followUp  = document.getElementById('muse-follow-up');
    const followForm = document.getElementById('muse-follow-up-form');
    const followStatus = document.getElementById('muse-follow-up-status');
    const followConsent = document.getElementById('muse-follow-up-consent');
    const publicConsent = document.getElementById('muse-public-consent');
    const floatConsent = document.getElementById('muse-float-consent');
    if (!form || !input || !sendBtn || !msgBox) return;
    let   sessionId = null;
    let   busy      = false;

    // Keep the floating widget at the document root so section stacking/overflow
    // cannot push it underneath later sections.
    if (floatWrap && floatWrap.parentElement !== document.body) {
        document.body.appendChild(floatWrap);
    }

    function esc(s) { const d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

    function appendMsg(role, text) {
        msgBox.classList.remove('hidden');
        const b = document.createElement('div');
        b.className = role === 'user'
            ? 'ml-auto max-w-[80%] rounded-2xl rounded-br-md px-4 py-2.5 text-sm text-white bg-white/[0.1]'
            : 'mr-auto max-w-[85%] rounded-2xl rounded-bl-md px-4 py-2.5 text-sm text-white/90 bg-white/[0.06] border border-white/[0.05]';
        b.innerHTML = role === 'user' ? esc(text) : esc(text).replace(/\n/g,'<br>').replace(/•/g,'<span class="text-ohm-attention/80">•</span>');
        msgBox.appendChild(b);
        msgBox.scrollTop = msgBox.scrollHeight;
        if (role === 'assistant' && followUp) followUp.classList.remove('hidden');
    }

    function showTyping() {
        const el = document.createElement('div'); el.id = 'muse-typing';
        el.className = 'mr-auto flex items-center gap-1.5 px-4 py-2';
        el.innerHTML = '<span class="w-1.5 h-1.5 rounded-full bg-ohm-attention/60 animate-pulse"></span>'
            + '<span class="w-1.5 h-1.5 rounded-full bg-ohm-attention/60 animate-pulse [animation-delay:150ms]"></span>'
            + '<span class="w-1.5 h-1.5 rounded-full bg-ohm-attention/60 animate-pulse [animation-delay:300ms]"></span>';
        msgBox.classList.remove('hidden'); msgBox.appendChild(el); msgBox.scrollTop = msgBox.scrollHeight;
    }
    function hideTyping() { const el = document.getElementById('muse-typing'); if (el) el.remove(); }

    function setBusy(v) {
        busy = v; sendBtn.disabled = v; input.disabled = v;
        sendBtn.style.opacity = v ? '0.5' : '1';
    }

    async function sendQuestion(msg) {
        appendMsg('user', msg); setBusy(true); showTyping();
        try {
            const res = await fetch('/api/ask-muse', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', ...(document.querySelector('meta[name="csrf-token"]') ? {'X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content} : {}) },
                body: JSON.stringify({ message: msg, session: sessionId, story: form.dataset.storyRef || null }),
            });
            hideTyping();
            if (!res.ok) { const e = await res.json().catch(() => ({})); appendMsg('assistant', e.reply || 'Something went wrong.'); return; }
            const data = await res.json();
            sessionId = data.session || sessionId;
            appendMsg('assistant', data.reply || 'No response received.');
        } catch (err) { hideTyping(); appendMsg('assistant', 'Network error — please check your connection.'); }
        finally { setBusy(false); input.focus(); }
    }

    /* ── main form ─── */
    form.addEventListener('submit', function (e) {
        e.preventDefault(); if (!publicConsent.checked) { publicConsent.reportValidity(); return; }
        const m = input.value.trim(); if (!m||busy) return; input.value=''; sendQuestion(m);
    });

    /* ── floating widget ─── */
    if (floatForm) floatForm.addEventListener('submit', function (e) {
        e.preventDefault(); if (!floatConsent.checked) { floatConsent.reportValidity(); return; }
        const m = floatIn.value.trim(); if (!m||busy) return;
        floatIn.value = ''; input.value = '';
        askMuse.scrollIntoView({ behavior: 'smooth', block: 'center' });
        setTimeout(function () { sendQuestion(m); }, 500);
    });

    if (followForm) followForm.addEventListener('submit', async function (e) {
        e.preventDefault();
        if (!followConsent.checked) {
            followStatus.textContent = 'Please confirm you want an email follow-up.';
            followStatus.className = 'mt-2 text-xs text-red-300';
            followStatus.classList.remove('hidden');
            return;
        }
        const submit = followForm.querySelector('button[type="submit"]');
        submit.disabled = true;
        try {
            const res = await fetch('/api/ask-muse/follow-up', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', ...(document.querySelector('meta[name="csrf-token"]') ? {'X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content} : {}) },
                body: JSON.stringify(Object.assign(
                    Object.fromEntries(new FormData(followForm).entries()),
                    {
                        name: document.getElementById('muse-follow-up-name').value.trim(),
                        email: document.getElementById('muse-follow-up-email').value.trim(),
                        consent: followConsent.checked ? 1 : 0,
                        session: sessionId,
                    }
                )),
            });
            const data = await res.json().catch(() => ({}));
            followStatus.textContent = res.ok ? (data.message || 'Thanks — we will follow up soon.') : 'We could not save that request. Please try again.';
            followStatus.className = 'mt-2 text-xs ' + (res.ok ? 'text-green-300' : 'text-red-300');
            followStatus.classList.remove('hidden');
            if (res.ok) {
                followForm.querySelectorAll('input').forEach((field) => { field.disabled = true; });
                submit.remove();
            }
        } catch (err) {
            followStatus.textContent = 'Network error — please try again.';
            followStatus.className = 'mt-2 text-xs text-red-300';
            followStatus.classList.remove('hidden');
        } finally { submit.disabled = false; }
    });

    /* ── float visibility: show after scrolling past #hero ─── */
    let floatVisible = false;
    function checkFloat() {
        const trigger = hero;
        if (!trigger || !floatWrap) return;
        const shouldShow = trigger.getBoundingClientRect().bottom < 0;
        if (shouldShow && !floatVisible) {
            floatVisible = true;
            floatWrap.style.opacity = '1';
            floatWrap.style.transform = 'translateX(-50%) translateY(0)';
            floatWrap.style.pointerEvents = 'auto';
        } else if (!shouldShow && floatVisible) {
            floatVisible = false;
            floatWrap.style.opacity = '0';
            floatWrap.style.transform = 'translateX(-50%) translateY(1rem)';
            floatWrap.style.pointerEvents = 'none';
        }
    }
    let ticking = false;
    window.addEventListener('scroll', function () {
        if (!ticking) { window.requestAnimationFrame(function () { checkFloat(); ticking = false; }); ticking = true; }
    }, { passive: true });
    checkFloat();
})();
