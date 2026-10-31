(() => {
  'use strict';
  document.documentElement.classList.add('js');
  // Keep the plan tucked into BACK until the visitor's first deliberate movement.
  const planBadge = document.querySelector('.hero__plan');
  if (planBadge) {
    const revealPlan = (event) => {
      if (!event.isTrusted) return;
      planBadge.classList.add('is-revealed');
      ['pointermove', 'pointerdown', 'touchstart', 'wheel', 'keydown'].forEach((type) => {
        window.removeEventListener(type, revealPlan);
      });
    };
    ['pointermove', 'pointerdown', 'touchstart', 'wheel', 'keydown'].forEach((type) => {
      window.addEventListener(type, revealPlan, { passive: true });
    });
  }
  const toast = document.querySelector('.toast');
  let toastTimer;
  const notify = (message) => {
    if (!toast) return;
    toast.textContent = message;
    toast.hidden = false;
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => { toast.hidden = true; }, 4500);
  };
  // Measurement (2026-10-20, website plan section 12 item 2): five events, nothing else.
  // Each call is also kept in window.adisaanEvents so a local render can be checked
  // without Google Analytics. gtag is cookieless (see layout.blade.php).
  const track = (name, params = {}) => {
    const payload = Object.assign({ page_path: location.pathname }, params);
    (window.adisaanEvents = window.adisaanEvents || []).push({ name, params: payload, at: Date.now() });
    if (typeof window.gtag === 'function') { try { window.gtag('event', name, payload); } catch { /* measurement must never break the page */ } }
  };
  // Conversion events are emitted only from server-confirmed receipt pages.
  document.querySelectorAll('[data-analytics-event]').forEach((receipt) => {
    const params = {};
    if (receipt.dataset.analyticsMode) params.share_mode = receipt.dataset.analyticsMode;
    if (receipt.dataset.analyticsEpisode) params.episode = receipt.dataset.analyticsEpisode;
    track(receipt.dataset.analyticsEvent, params);
  });
  // follow_click: any link that carries data-follow-channel (home list, /million runners, follow cards)
  document.addEventListener('click', (event) => {
    const link = event.target.closest('a[data-follow-channel]');
    if (link) track('follow_click', { channel: link.dataset.followChannel, link_url: link.href });
  });
  // episode_play: the first play of each video on the page
  document.querySelectorAll('video').forEach((video) => {
    video.addEventListener('play', () => {
      if (video.dataset.played) return;
      video.dataset.played = '1';
      const src = video.currentSrc || video.getAttribute('src') || '';
      track('episode_play', { episode: video.dataset.episode || src.split('/').pop().replace(/\.[a-z0-9]+$/i, '') });
    });
  });
  const narration = document.querySelector('[data-home-narration]');
  const narrationToggle = document.querySelector('[data-home-narration-toggle]');
  if (narration && narrationToggle) {
    const mutedIcon = narrationToggle.querySelector('[data-icon-muted]');
    const soundIcon = narrationToggle.querySelector('[data-icon-sound]');
    const syncNarrationControl = () => {
      const audible = !narration.paused && !narration.muted;
      if (mutedIcon) mutedIcon.hidden = audible;
      if (soundIcon) soundIcon.hidden = !audible;
      narrationToggle.setAttribute('aria-pressed', String(audible));
      narrationToggle.setAttribute('aria-label', narration.paused ? 'Play narration' : (audible ? 'Mute narration' : 'Unmute narration'));
    };
    narrationToggle.addEventListener('click', async () => {
      if (narration.paused || narration.ended) {
        narration.muted = false;
        try { await narration.play(); } catch { narration.muted = true; }
      } else {
        narration.muted = !narration.muted;
      }
      syncNarrationControl();
    });
    narration.addEventListener('play', syncNarrationControl);
    narration.addEventListener('pause', syncNarrationControl);
    narration.addEventListener('ended', syncNarrationControl);
    narration.addEventListener('volumechange', syncNarrationControl);
    syncNarrationControl();
  }
  // Hero fills the first screen: expose the menu bar height so CSS can subtract it.
  const tabs = document.querySelector('.tabs');
  if (tabs) {
    const setTabsHeight = () => document.documentElement.style.setProperty('--tabs-h', `${tabs.offsetHeight}px`);
    setTabsHeight();
    if ('ResizeObserver' in window) new ResizeObserver(setTabsHeight).observe(tabs);
    else window.addEventListener('resize', setTabsHeight);
  }
  // First screen stays hero + buttons only: the companion appears once the visitor scrolls.
  const markFirstScreen = () => document.documentElement.classList.toggle('is-first-screen', window.scrollY < (tabs ? Math.max(tabs.offsetTop - 1, 120) : 120));
  markFirstScreen();
  window.addEventListener('scroll', markFirstScreen, { passive: true });
  // Scroll reveal for blocks marked .reveal (one-shot; reduced-motion users get everything at once via CSS)
  const revealables = Array.from(document.querySelectorAll('.reveal'));
  if (revealables.length) {
    const revealInView = () => {
      const limit = window.innerHeight * 0.92;
      revealables.forEach((el) => {
        if (el.classList.contains('is-in')) return;
        const r = el.getBoundingClientRect();
        if (r.top < limit && r.bottom > 0) el.classList.add('is-in');
      });
    };
    if ('IntersectionObserver' in window) {
      const io = new IntersectionObserver((entries) => {
        entries.forEach((entry) => { if (entry.isIntersecting) { entry.target.classList.add('is-in'); io.unobserve(entry.target); } });
      }, { threshold: 0.12, rootMargin: '0px 0px -8% 0px' });
      revealables.forEach((el) => io.observe(el));
    }
    revealInView();
    window.addEventListener('scroll', revealInView, { passive: true });
    window.addEventListener('resize', revealInView);
    window.addEventListener('load', revealInView);
  }
  document.querySelectorAll('[data-share]').forEach((button) => {
    button.addEventListener('click', async () => {
      // A share button may carry its own result — the thank-you page sends the
      // score the visitor just joined. A bare link gives a reader no reason to
      // click. Buttons without these attributes keep the old wording.
      const url = button.dataset.shareUrl
        || `${document.body.dataset.publicUrl}${document.body.dataset.publicPath || '/'}`;
      const data = {
        title: button.dataset.shareTitle || 'AdiSaan — Your online partner',
        text: button.dataset.shareText || 'A real story. A question for your imagination. Come explore with AdiSaan.',
        url,
      };
      try {
        if (navigator.share) await navigator.share(data);
        else if (navigator.clipboard) { await navigator.clipboard.writeText(url); notify('Link copied. Share it with someone who might enjoy the story.'); }
        else notify(`Share this address: ${url}`);
      } catch (error) {
        if (error.name !== 'AbortError') notify(`Share this address: ${url}`);
      }
    });
  });
  document.querySelectorAll('[data-journey-play]').forEach((button) => {
    const card = button.closest('.journey-barometer');
    const speech = document.querySelector('[data-journey-speech]');
    const lines = ['Tiny spark. Big imagination. Shall we bring a friend?', 'One thought started this. Imagine where yours could take us.', 'No shortcuts to a million. Good company helps.', 'The scenic route looks better with you here.'];
    let turn = 0;
    button.addEventListener('click', () => {
      speech.textContent = lines[turn++ % lines.length];
      card.classList.remove('journey-celebrate');
      requestAnimationFrame(() => requestAnimationFrame(() => card.classList.add('journey-celebrate')));
      setTimeout(() => card.classList.remove('journey-celebrate'), 2200);
    });
  });
  const companion = document.querySelector('.adisaan-companion');
  const heroAvatar = document.querySelector('.brand-header--building .hero__avatar');
  const companionFace = companion?.querySelector('.adisaan-companion-face');
  if (heroAvatar && companionFace) {
    // One visual handoff, tied to scroll position so reversing the scroll reverses the journey.
    // The real Ambassador button stays in place and remains the only interactive avatar.
    const flight = document.createElement('div');
    flight.className = 'hero-avatar-flight';
    flight.setAttribute('aria-hidden', 'true');
    const full = document.createElement('img');
    full.src = heroAvatar.currentSrc || heroAvatar.src;
    full.alt = '';
    full.className = 'hero-avatar-flight__full';
    const face = document.createElement('img');
    face.src = companionFace.querySelector('img').src;
    face.alt = '';
    face.className = 'hero-avatar-flight__face';
    flight.append(full, face);
    document.body.append(flight);
    let geometry;
    let frame = 0;
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const measure = () => {
      const source = heroAvatar.getBoundingClientRect();
      const destination = companionFace.getBoundingClientRect();
      const start = Math.max(48, Math.min(88, window.innerHeight * .08));
      const end = start + Math.max(210, Math.min(300, window.innerHeight * .3));
      geometry = {
        x: source.left,
        y: source.top + window.scrollY - start,
        width: source.width,
        height: source.height,
        targetX: destination.left,
        targetY: destination.top,
        targetWidth: destination.width,
        targetHeight: destination.height,
        start,
        end,
      };
      flight.style.left = `${geometry.x}px`;
      flight.style.top = `${geometry.y}px`;
      flight.style.width = `${geometry.width}px`;
      flight.style.height = `${geometry.height}px`;
      update();
    };
    const update = () => {
      frame = 0;
      if (!geometry) return;
      const raw = Math.min(1, Math.max(0, (window.scrollY - geometry.start) / (geometry.end - geometry.start)));
      const active = !reducedMotion && raw > 0 && raw < 1;
      document.documentElement.classList.toggle('avatar-arrived', raw >= 1);
      flight.hidden = !active;
      heroAvatar.style.visibility = reducedMotion ? (raw >= 1 ? 'hidden' : '') : (raw > 0 ? 'hidden' : '');
      if (!active) return;
      // A short, fast handoff: the single interactive Ambassador appears the
      // moment this decorative flight reaches its face, even on a fast scroll.
      const progress = 1 - Math.pow(1 - raw, 3);
      const x = (geometry.targetX - geometry.x) * progress;
      const y = (geometry.targetY - geometry.y) * progress;
      const scaleX = 1 + (geometry.targetWidth / geometry.width - 1) * progress;
      const scaleY = 1 + (geometry.targetHeight / geometry.height - 1) * progress;
      flight.style.transform = `translate3d(${x}px,${y}px,0) scale(${scaleX},${scaleY})`;
      flight.style.borderRadius = `${Math.round(progress * 50)}%`;
      const faceMix = Math.min(1, Math.max(0, (raw - .35) / .35));
      full.style.opacity = String(1 - faceMix);
      face.style.opacity = String(faceMix);
      flight.style.opacity = '1';
    };
    const schedule = () => { if (!frame) frame = requestAnimationFrame(update); };
    window.addEventListener('scroll', schedule, { passive: true });
    window.addEventListener('resize', measure);
    window.addEventListener('load', measure);
    measure();
  }
  if (companion) {
    const cloud = companion.querySelector('[data-avatar-thought]');
    const chat = companion.querySelector('.adisaan-native-chat');
    const thought = companion.querySelector('[data-journey-speech]');
    const label = companion.querySelector('[data-thought-context]');
    // The homepage has one Ambassador conversation: dock that same node beside
    // the email form while this section is in view, then return it to the corner.
    const joinDock = document.querySelector('[data-companion-join-dock]');
    if (joinDock && 'IntersectionObserver' in window) {
      const home = companion.parentNode;
      const chatHeading = chat.querySelector('.adisaan-chat-heading');
      let inJoin = false;
      let dockFlight;
      const place = (visible) => {
        if (visible === inJoin) return;
        const from = dockFlight?.getBoundingClientRect() || companionFace.getBoundingClientRect();
        dockFlight?.getAnimations().forEach(animation => animation.cancel());
        dockFlight?.remove();
        companionFace.style.visibility = '';
        inJoin = visible;
        companion.classList.toggle('is-join-guide', visible);
        if (visible) chatHeading.prepend(companionFace);
        else companion.append(companionFace);
        (visible ? joinDock : home).append(companion);
        chat.hidden = !visible;
        cloud.hidden = visible || hiddenThought;
        companionFace?.setAttribute('aria-expanded', String(visible));
        label.textContent = visible ? 'Talk with me' : 'Let’s talk';
        if (!visible) thought.textContent = 'What is the best way you imagine? Tell me when you’re ready.';
        const to = companionFace.getBoundingClientRect();
        if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches && from.width && to.width) {
          dockFlight = document.createElement('img');
          const clone = dockFlight;
          dockFlight.className = 'companion-dock-flight';
          dockFlight.src = companionFace.querySelector('img').src;
          dockFlight.alt = '';
          Object.assign(dockFlight.style, {
            left: `${from.left}px`, top: `${from.top}px`,
            width: `${from.width}px`, height: `${from.height}px`
          });
          document.body.append(dockFlight);
          companionFace.style.visibility = 'hidden';
          const animation = dockFlight.animate([
            { transform: 'translate3d(0,0,0) scale(1)' },
            { transform: `translate3d(${to.left - from.left}px,${to.top - from.top}px,0) scale(${to.width / from.width})` }
          ], { duration: 480, easing: 'cubic-bezier(.22,1,.36,1)', fill: 'forwards' });
          animation.onfinish = () => {
            clone.remove();
            if (dockFlight === clone) {
              dockFlight = null;
              companionFace.style.visibility = '';
            }
          };
        }
        window.dispatchEvent(new Event('resize'));
      };
      new IntersectionObserver((entries) => place(entries[0].isIntersecting), {
        rootMargin: '-12% 0px -12% 0px', threshold: 0
      }).observe(document.querySelector('#join'));
    }
    const museConsent = chat.querySelector('#muse-public-consent');
    const museConsentLabel = chat.querySelector('label[for="muse-public-consent"]');
    if (museConsent && museConsentLabel) {
      const editConsent = document.createElement('button');
      editConsent.type = 'button';
      editConsent.className = 'adisaan-consent-edit';
      editConsent.textContent = 'Change consent';
      museConsentLabel.after(editConsent);
      const syncConsent = (moveFocus = false) => {
        chat.classList.toggle('is-muse-consented', museConsent.checked);
        museConsentLabel.inert = museConsent.checked;
        if (moveFocus && museConsent.checked) editConsent.focus({preventScroll: true});
      };
      museConsent.addEventListener('change', () => syncConsent(true));
      editConsent.addEventListener('click', () => {
        museConsent.checked = false;
        syncConsent();
        museConsent.focus();
      });
      syncConsent();
    }
    let hiddenThought = false;
    let section = '';
    let pending;
    const contexts = [
      ['#question-stage', 'The question', 'What does this question bring up for you? Tell me when you’re ready.'],
      ['#episode', 'The question behind every question', 'What is the best way you imagine? I’d like to hear your version.'],
      ['#answers', 'Your perspective', 'Would you answer differently? Your own words are enough.'],
      ['#who', 'Who is asking', 'What would you ask AdiSaan about this journey?'],
      ['#why', 'What comes next', 'If you started with one thought, where would you take it?'],
      ['#journey', 'The road to a million', 'Which conversation would you invite a friend into?'],
      ['#faq', 'Questions people ask', 'Is there something you still want to ask me?'],
      ['#join', 'Stay in the conversation', 'Want to talk now? I’m here beside the email form.'],
      ['.million-intro', 'The beginning', 'I imagined a million. Now comes the interesting part: showing up.'],
      ['.journey-barometer', 'Looking at the barometer', 'Yes, it’s a tiny green line. Even a long journey has to start somewhere.'],
      ['.race-section', 'The account race', 'Different doors. One journey. Which one brought you here?'],
      ['.total-chart', 'Looking at the chart', 'A line tells part of a story. This one still has missing pages.'],
      ['.runners', 'Our travelling companions', 'Some accounts are moving. Some are taking a breath. I know the feeling.'],
      ['.quiet', 'The missing measurement', 'No number yet. I’d rather leave a space than fill it with imagination.'],
      ['.chapter-section', 'The road ahead', 'Halfway home sounds lovely. Today, I only need to take the next step.'],
      ['.story-section', 'The story', 'When no one is looking, what do you see? I’m still curious about your answer.'],
      ['.rhythm', 'Morning and evening', 'Imagine an answer in the morning. Leave room to change your mind by evening.'],
      ['.lab-preview, .news-section', 'Ideas and tools', 'Another tool. Another possibility. What would you actually create with it?'],
      ['.answer-section', 'Your perspective', 'Your answer doesn’t have to sound like mine. That’s why I’m asking.'],
    ];
    let targets = contexts.flatMap(([selector, title, text]) => Array.from(document.querySelectorAll(selector), element => ({element, title, text})));
    const visible = new Map();
    if ('IntersectionObserver' in window) {
      const observer = new IntersectionObserver(entries => {
        entries.forEach(entry => visible.set(entry.target, entry.isIntersecting));
        clearTimeout(pending);
        pending = setTimeout(() => {
          const center = window.innerHeight / 2;
          let best;
          let score = Infinity;
          targets.forEach(target => {
            if (!visible.get(target.element)) return;
            const rect = target.element.getBoundingClientRect();
            const distance = rect.top <= center && rect.bottom >= center ? rect.height / 10000 : Math.min(Math.abs(rect.top - center), Math.abs(rect.bottom - center)) + 10;
            if (distance < score) { score = distance; best = target; }
          });
          if (best && best.title !== section && chat.hidden) {
            section = best.title;
            label.textContent = best.title;
            const selectedStory = best.title === 'The story' ? document.querySelector('[data-story-panel]:not([hidden]) [data-story-share]') : null;
            thought.textContent = selectedStory ? `${selectedStory.dataset.storyQuestion} Take your time. I’m curious about your answer.` : best.text;
          }
        }, 650);
      }, {rootMargin:'-20% 0px -20% 0px', threshold:[0, 0.25, 0.5, 0.75, 1]});
      targets.forEach(target => observer.observe(target.element));
      document.addEventListener('adisaan:board-refreshed', () => {
        observer.disconnect();
        visible.clear();
        targets = contexts.flatMap(([selector, title, text]) => Array.from(document.querySelectorAll(selector), element => ({element, title, text})));
        targets.forEach(target => observer.observe(target.element));
      });
    }
    document.addEventListener('aiohm:story-selected', event => {
      if (typeof event.detail?.question !== 'string' || !chat.hidden) return;
      const museForm = document.querySelector('#muse-public-form');
      if (museForm) museForm.dataset.storyRef = event.detail.id || '';
      label.textContent = event.detail.title || 'The story';
      thought.textContent = `${event.detail.question} Take your time. I’m curious about your answer.`;
    });
    const initialStory = document.querySelector('[data-story-panel]:not([hidden])');
    const museForm = document.querySelector('#muse-public-form');
    if (museForm && initialStory) museForm.dataset.storyRef = initialStory.id.replace(/^story-/, '');
    document.addEventListener('play', event => {
      if (event.target.matches?.('[data-story-panel] video')) cloud.hidden = true;
    }, true);
    const restoreAfterVideo = event => {
      if (event.target.matches?.('[data-story-panel] video') && chat.hidden && !hiddenThought) cloud.hidden = false;
    };
    document.addEventListener('pause', restoreAfterVideo, true);
    document.addEventListener('ended', restoreAfterVideo, true);
    const close = () => {
      chat.hidden = true;
      cloud.hidden = hiddenThought || companion.classList.contains('is-join-guide');
      document.querySelectorAll('[data-open-ambassador]').forEach(button => button.setAttribute('aria-expanded', 'false'));
      companion.querySelector('.adisaan-companion-face').focus();
    };
    document.querySelectorAll('[data-open-ambassador]').forEach(button => button.addEventListener('click', () => {
      chat.hidden = false;
      cloud.hidden = true;
      document.querySelectorAll('[data-open-ambassador]').forEach(opener => opener.setAttribute('aria-expanded', 'true'));
      document.querySelector('#muse-public-input')?.focus();
    }));
    companion.querySelector('[data-close-ambassador]').addEventListener('click', close);
    companion.querySelector('[data-hide-thought]').addEventListener('click', () => { hiddenThought = true; cloud.hidden = true; });
    companion.addEventListener('keydown', event => { if (event.key === 'Escape' && !chat.hidden) close(); });
  }
  const form = document.querySelector('[data-answer-form]');
  if (form) {
    const text = form.querySelector('textarea');
    const counter = form.querySelector('[data-character-count]');
    const button = form.querySelector('[type="submit"]');
    const minimum = Math.max(0, Number(form.dataset.minimumSeconds) || 3) * 1000;
    const loadedAt = Date.now();
    const count = () => { if (counter && text) counter.textContent = `${text.value.length.toLocaleString()} characters`; };
    text?.addEventListener('input', count);
    count();
    const modeCards = form.querySelectorAll('[data-share-mode-card]');
    const consentInput = form.querySelector('[data-consent-quote-input]');
    const modeIndicator = form.querySelector('[data-mode-indicator]');
    const submitLabel = form.querySelector('[data-submit-label]');
    modeCards.forEach((card) => {
      const radio = card.querySelector('input[type="radio"]');
      radio?.addEventListener('change', () => {
        const isPublic = radio.value === 'public';
        modeCards.forEach((c) => c.classList.toggle('is-selected', c === card));
        if (consentInput) consentInput.value = isPublic ? '1' : '';
        if (modeIndicator) modeIndicator.textContent = isPublic ? 'Everyone can read this' : 'Only AdiSaan’s creator reads this';
        if (submitLabel) submitLabel.textContent = isPublic ? 'Send' : 'Send privately';
      });
    });
    form.addEventListener('submit', (event) => {
      if (Date.now() - loadedAt < minimum) { event.preventDefault(); notify('Take a breath, then send your perspective.'); return; }
      if (button) { button.disabled = true; button.textContent = 'Sending…'; }
    });
  }
  const format = (number) => new Intl.NumberFormat('en').format(number);
  document.querySelector('[data-watch-story]')?.addEventListener('click', () => {
    document.querySelector('[data-story-tab="0"]')?.click();
    const player = document.querySelector('#story-film [data-story-panel]:not([hidden]) video') || document.querySelector('#story-film video');
    player?.play().catch(() => {});
  });
  const updateTime = (root = document) => {
    root.querySelectorAll('[data-measured-at], [data-synced]').forEach((node) => {
      const stamp = node.dataset.measuredAt || node.dataset.iso;
      if (!stamp) return;
      const date = new Date(stamp);
      if (!Number.isNaN(date.valueOf())) node.textContent = `Measured ${date.toLocaleDateString('en', {day:'numeric', month:'short'})}, ${date.toLocaleTimeString('en', {hour:'2-digit', minute:'2-digit'})}`;
    });
  };
  updateTime();
  const socialGrid = document.querySelector('[data-social-grid]');
  // AIOHM Social's analytics response has no profile URL. Keep the public
  // destinations tied to these known AdiSaan workspace account identities.
  const socialProfiles = {
    'bluesky:aiohmplatform.bsky.social': 'https://bsky.app/profile/aiohmplatform.bsky.social',
    'facebook:Adisaan.partenerul.tau.online': 'https://www.facebook.com/Adisaan.partenerul.tau.online/',
    'facebook:marketingwithAdiSaan': 'https://www.facebook.com/marketingwithAdiSaan/',
    'facebook:OHM.events.agency': 'https://www.facebook.com/OHM.events.agency/',
    'instagram:adrian.aparaschivei': 'https://www.instagram.com/adrian.aparaschivei/',
    'instagram:ohm.events.agency': 'https://www.instagram.com/ohm.events.agency/',
    'tiktok:adrian.aparaschivei': 'https://www.tiktok.com/@adrian.aparaschivei',
    'youtube:adisaan': 'https://www.youtube.com/@adisaan',
    'youtube:aiohm-platform': 'https://www.youtube.com/@aiohm-platform',
    'linkedin:Adrian': 'https://www.linkedin.com/in/adisaan/',
    'reddit:adisaan': 'https://www.reddit.com/user/adisaan',
    'reddit:u/adisaan': 'https://www.reddit.com/user/adisaan',
    'reddit:r/adisaan': 'https://www.reddit.com/r/adisaan/',
  };
  const addRaceFollowLinks = () => {
    document.querySelectorAll('.runner[data-profile-platform]').forEach((item) => {
      if (item.querySelector('.runner-follow')) return;
      const profile = socialProfiles[`${item.dataset.profilePlatform}:${item.dataset.profileHandle}`];
      if (!profile) return;
      const follow = document.createElement('a');
      follow.className = 'build-social__follow runner-follow';
      follow.dataset.followChannel = `${item.dataset.profilePlatform}:${item.dataset.profileHandle}`;
      follow.href = profile;
      follow.target = '_blank';
      follow.rel = 'noopener noreferrer';
      follow.textContent = 'Follow me ↗';
      follow.setAttribute('aria-label', `Follow ${item.dataset.profileHandle} on ${item.dataset.profilePlatform}`);
      item.append(follow);
    });
  };
  addRaceFollowLinks();
  document.addEventListener('adisaan:board-refreshed', addRaceFollowLinks);
  const socialUnavailable = () => {
    if (socialGrid && !socialGrid.querySelector('[data-social-list]').children.length) {
      socialGrid.querySelector('[data-social-coverage]').textContent = 'Measurements unavailable right now';
    }
  };
  const updateSocialGrid = (board) => {
    if (!socialGrid) return;
    const list = socialGrid.querySelector('[data-social-list]');
    const coverage = socialGrid.querySelector('[data-social-coverage]');
    const note = socialGrid.querySelector('[data-social-note]');
    list.replaceChildren();
    board.accounts.forEach((account) => {
      const item = document.createElement('li');
      item.className = 'build-social__account';
      const identity = document.createElement('span');
      identity.className = 'build-social__identity';
      const platform = document.createElement('small');
      platform.textContent = account.platform_label || account.platform || 'Social';
      const handle = document.createElement('b');
      handle.textContent = account.handle ? (account.handle.startsWith('@') ? account.handle : `@${account.handle}`) : (account.name || 'Connected account');
      identity.append(platform, handle);
      const count = document.createElement('span');
      count.className = 'build-social__count';
      if (account.measured && Number.isFinite(account.followers)) {
        const value = document.createElement('strong');
        value.textContent = format(account.followers);
        const unit = document.createElement('small');
        unit.textContent = 'followers';
        count.append(value, unit);
      } else {
        count.textContent = 'Not reporting';
        count.classList.add('build-social__count--missing');
      }
      item.append(identity, count);
      const profile = socialProfiles[`${account.platform}:${String(account.handle || '').replace(/^@/, '')}`];
      if (profile) {
        const follow = document.createElement('a');
        follow.className = 'build-social__follow';
        follow.href = profile;
        follow.target = '_blank';
        follow.rel = 'noopener noreferrer';
        follow.textContent = 'Follow me ↗';
        follow.setAttribute('aria-label', `Follow ${account.handle} on ${account.platform_label || account.platform}`);
        item.append(follow);
      }
      list.append(item);
    });
    coverage.textContent = `${board.measured_count} of ${board.accounts.length} reporting${board.total_followers === null ? '' : ` · ${format(board.total_followers)} measured follows`}`;
    const measuredAt = board.last_synced_at ? new Date(board.last_synced_at) : null;
    note.textContent = measuredAt && !Number.isNaN(measuredAt.valueOf())
      ? `Measured ${measuredAt.toLocaleDateString('en', { day: 'numeric', month: 'short', year: 'numeric' })} by AIOHM Social. Combined follows can include the same person on more than one account.`
      : 'Counts come from AIOHM Social. Accounts without a measurement are shown without a number.';
  };
  // The existing Social feed contains periodic totals, not individual follow events.
  // Compare only the same reporting accounts; coverage changes are not new fans.
  const fanDrops = document.querySelector('[data-fan-drops]');
  let lastFanTotal = fanDrops?.dataset.fanTotal ? Number(fanDrops.dataset.fanTotal) : null;
  let lastFanCoverage = fanDrops?.dataset.fanCoverage || '';
  const updateFanDrops = (board) => {
    if (!fanDrops) return;
    const coverage = board.accounts.filter(account => account.measured).map(account => String(account.id)).sort().join(',');
    const total = board.total_followers;
    if (Number.isFinite(total) && Number.isFinite(lastFanTotal) && coverage === lastFanCoverage && total > lastFanTotal) {
      const gained = total - lastFanTotal;
      const status = document.querySelector('[data-fan-update]');
      if (status) status.textContent = `${format(gained)} new ${gained === 1 ? 'fan' : 'fans'} reported in the latest update.${gained > 40 ? ' Shown as a grouped shower.' : ''}`;
      if (!matchMedia('(prefers-reduced-motion: reduce)').matches) {
        for (let i = 0; i < Math.min(gained, 40); i++) {
          const drop = document.createElement('i');
          drop.style.setProperty('--drop-delay', `${i * 140}ms`);
          drop.style.setProperty('--drop-left', `${30 + (i * 17) % 40}%`);
          fanDrops.append(drop);
          drop.addEventListener('animationend', () => drop.remove(), {once:true});
        }
      }
    }
    lastFanTotal = Number.isFinite(total) ? total : null;
    lastFanCoverage = coverage;
  };
  let refreshing = false;
  const refresh = async (initial = false) => {
    if ((!initial && document.hidden) || refreshing) return;
    refreshing = true;
    try {
      const response = await fetch(document.body.dataset.boardUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
      if (!response.ok) { socialUnavailable(); return; }
      const board = await response.json();
      if (!board || !Array.isArray(board.accounts) || (board.total_followers !== null && !Number.isFinite(board.total_followers))) { socialUnavailable(); return; }
      updateSocialGrid(board);
      updateFanDrops(board);
      document.querySelectorAll('[data-fan-marker]').forEach(node => node.style.setProperty('--fan-fraction', Math.max(0, Math.min(1, (board.total_followers ?? 0) / 1000000))));
      document.querySelectorAll('[data-followers]').forEach((node) => { node.textContent = board.total_followers === null ? '—' : format(board.total_followers); });
      document.querySelectorAll('[data-follow-percentage]').forEach((node) => { node.textContent = board.total_followers === null ? 'awaiting measurements' : `${(board.total_followers / 10000).toFixed(2)}%`; });
      document.querySelectorAll('[data-barometer-fill]').forEach((node) => { node.style.transform = `scaleY(${Math.max(0, Math.min(1, (board.total_followers ?? 0) / 1000000))})`; });
      document.querySelectorAll('[data-barometer]').forEach((node) => { node.setAttribute('aria-label', board.total_followers === null ? 'Awaiting follower measurements' : `${format(board.total_followers)} of 1,000,000 fans`); });
      document.querySelectorAll('[data-total-preview] progress').forEach((node) => { node.value = board.total_followers ?? 0; });
      document.querySelectorAll('[data-coverage]').forEach((node) => { node.textContent = `${board.measured_count} of ${board.accounts.length} accounts reporting`; });
      document.querySelectorAll('[data-measured-at]').forEach((node) => { node.dataset.measuredAt = board.last_synced_at || ''; });
      const race = document.querySelector('[data-race-fragment]');
      if (race) {
        // The chart's window buttons set data-race-window; every refresh keeps it.
        const windowDays = race.dataset.raceWindow ? `?window=${encodeURIComponent(race.dataset.raceWindow)}` : '';
        const fragment = await fetch(race.dataset.raceFragment + windowDays, { headers: { Accept: 'text/html' }, credentials: 'same-origin' });
        if (fragment.ok) {
          const html = new DOMParser().parseFromString(await fragment.text(), 'text/html');
          const next = html.querySelector('#board');
          const current = race.querySelector('#board');
          if (next && current) { current.replaceWith(document.importNode(next, true)); document.dispatchEvent(new Event('adisaan:board-refreshed')); }
        }
      }
      updateTime();
    } catch { socialUnavailable(); /* Preserve the last dated measurement when a refresh fails. */ }
    finally { refreshing = false; }
  };
  document.querySelector('[data-race-fragment]')?.addEventListener('click', (event) => {
    const button = event.target.closest('[data-board-window]');
    if (!button) return;
    const race = event.currentTarget;
    race.dataset.raceWindow = button.dataset.boardWindow;
    race.querySelectorAll('[data-board-window]').forEach((b) => b.setAttribute('aria-pressed', b === button ? 'true' : 'false'));
    refresh();
  });
  if (document.querySelector('[data-total-preview], [data-race-fragment], [data-social-grid]')) {
    refresh(true);
    setInterval(refresh, 60000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh(); });
  }

  // Netflix Episodic Stage & Rail interactivity
  const mainVideo = document.querySelector('#netflix-main-video');
  const epCards = Array.from(document.querySelectorAll('[data-ep-select]'));
  if (mainVideo && epCards.length) {
    let activeIdx = 0;
    const selectEpisode = (idx, autoPlay = true) => {
      const card = epCards[(idx + epCards.length) % epCards.length];
      if (!card) return;
      activeIdx = epCards.indexOf(card);
      epCards.forEach((c, i) => {
        c.classList.toggle('is-active', i === activeIdx);
        c.setAttribute('aria-selected', i === activeIdx ? 'true' : 'false');
      });
      const d = card.dataset;
      mainVideo.dataset.episode = d.epId || '';
      // Nine of the ten questions have no film yet. Leaving the previous
      // one's src in place would show question one's film under question
      // two's title, so the frame falls back to its placeholder instead.
      const film = document.querySelector('[data-film]');
      if (d.epVideo) {
        film?.classList.remove('is-soon');
        if (d.epPoster) mainVideo.poster = d.epPoster;
        if (mainVideo.getAttribute('src') !== d.epVideo) {
          mainVideo.src = d.epVideo;
          mainVideo.load();
        }
      } else {
        film?.classList.add('is-soon');
        mainVideo.pause();
        mainVideo.removeAttribute('src');
        mainVideo.load();
      }
      const setTxt = (sel, val) => { const el = document.querySelector(sel); if (el && val) el.textContent = val; };
      setTxt('[data-stage-badge]', d.epVideo ? (d.epDuration || '') : 'Film coming');
      setTxt('[data-stage-soon-badge]', d.epBadge);
      setTxt('[data-stage-label]', d.epVideo ? 'THE QUESTION' : 'YOUR TURN');
      setTxt('[data-stage-no]', d.epNo);
      setTxt('[data-stage-eyebrow]', d.epEyebrow);
      setTxt('[data-stage-title]', d.epTitle);
      setTxt('[data-stage-desc]', d.epDesc);
      setTxt('[data-stage-question]', d.epQuestion ? `“${d.epQuestion}”` : '');
      setTxt('[data-stage-invite]', d.epInvite);
      setTxt('[data-stage-action]', d.epAction);
      const ansLink = document.querySelector('[data-stage-answer-link]');
      if (ansLink && d.epAnswerUrl) ansLink.href = d.epAnswerUrl;
      document.querySelectorAll('[data-archive-episode-id]').forEach((item) => {
        const selected = item.dataset.archiveEpisodeId === d.epId;
        item.classList.toggle('is-active', selected);
        item.querySelector('[data-play-episode-id]')?.setAttribute('aria-pressed', String(selected));
      });
      document.dispatchEvent(new CustomEvent('aiohm:story-selected', {
        detail: { id: d.epId, title: d.epTitle, question: d.epQuestion, index: activeIdx }
      }));
      if (autoPlay && d.epVideo) mainVideo.play().catch(() => {});
    };
    // The answer reel turns the same stage. One selector, so "which question
    // am I on" cannot be answered two different ways on the same page.
    window.AdiSaanStage = { select: selectEpisode, count: epCards.length };
    epCards.forEach((card, idx) => {
      card.addEventListener('click', () => selectEpisode(idx, true));
    });
    document.querySelector('[data-stage-play]')?.addEventListener('click', () => {
      if (mainVideo.paused) mainVideo.play().catch(() => {});
      else mainVideo.pause();
    });
    document.querySelector('[data-stage-next]')?.addEventListener('click', () => {
      selectEpisode(activeIdx + 1, true);
    });
    document.querySelectorAll('[data-play-episode-id]').forEach((btn) => {
      btn.addEventListener('click', () => {
        const targetId = btn.dataset.playEpisodeId;
        const idx = epCards.findIndex((c) => c.dataset.epId === targetId);
        if (idx >= 0) {
          document.querySelector('.question-page-v2__film')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
          selectEpisode(idx, true);
        }
      });
    });
  }

  // Live 24h Arena Resonance Voting
  document.querySelectorAll('[data-vote-form]').forEach((vForm) => {
    vForm.addEventListener('submit', async (event) => {
      event.preventDefault();
      const btn = vForm.querySelector('button[type="submit"]');
      const countNode = vForm.querySelector('[data-vote-count]');
      if (btn?.classList.contains('is-voted')) {
        notify('You already added your resonance to this perspective.');
        return;
      }
      try {
        if (btn) btn.disabled = true;
        const res = await fetch(vForm.action, {
          method: 'POST',
          headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
          body: new FormData(vForm),
          credentials: 'same-origin'
        });
        if (res.ok) {
          const data = await res.json();
          if (countNode && typeof data.votes === 'number') countNode.textContent = String(data.votes);
          btn?.classList.add('is-voted');
          if (!data.already_voted) track('vote', { echo_id: String(vForm.querySelector('[name="vote_echo_id"]')?.value || '') });
          notify(data.already_voted ? 'You already voted for this answer.' : 'Thank you. Your vote is counted.');
        } else if (res.status === 403) {
          // Members only. Say so and offer the door — the fallback below
          // would post the form again and land them on the gate with no
          // explanation of why they moved.
          const data = await res.json().catch(() => ({}));
          notify(data.message || 'Resonance is for members.');
          if (data.unlock_url) window.location.href = data.unlock_url;
        } else {
          vForm.submit();
        }
      } catch {
        vForm.submit();
      } finally {
        if (btn) btn.disabled = false;
      }
    });
  });

})();
