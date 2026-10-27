(() => {
  const clock = document.querySelector('[data-answer-countdown]');
  if (!clock) return;

  const deadline = Date.parse(clock.dataset.deadline || '');
  const serverNow = Date.parse(clock.dataset.serverNow || '');
  if (!Number.isFinite(deadline) || !Number.isFinite(serverNow)) return;

  // Keep the shared release time even if a visitor's device clock is wrong.
  // Days is optional: it only exists while the wait is long enough to need
  // it, and the clock still works on a page that does not show one.
  const dayDigit = clock.querySelector('[data-countdown-days]');
  const digits = ['hours', 'minutes', 'seconds'].map((part) => clock.querySelector(`[data-countdown-${part}]`));
  if (digits.some((digit) => !digit)) return;
  const content = clock.querySelector('.question-answer-clock__content');
  const videoUrl = clock.dataset.answerVideo;
  const serverAtLoad = serverNow;
  const elapsed = () => performance.now();
  const startedAt = elapsed();
  let opening = false;

  const openAnswer = () => {
    if (opening || !content || !videoUrl) return;
    opening = true;
    const note = content.querySelector('.question-answer-clock__note');
    if (note) note.textContent = 'Opening AdiSaan’s answer…';

    const video = document.createElement('video');
    video.className = 'question-answer-clock__video';
    video.controls = true;
    video.playsInline = true;
    video.preload = 'metadata';
    video.append('Your browser does not support video playback.');

    const onReady = () => {
      video.removeEventListener('error', onError);
      clock.classList.add('is-revealing');
      window.setTimeout(() => {
        const title = document.createElement('p');
        title.className = 'question-answer-clock__eyebrow';
        title.textContent = 'ADISAAN’S ANSWER';
        content.replaceChildren(title, video);
        clock.classList.remove('is-revealing');
        clock.classList.add('is-revealed');
      }, window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 0 : 240);
    };
    const onError = () => {
      video.removeEventListener('loadedmetadata', onReady);
      opening = false;
      if (!note) return;
      note.replaceChildren('The video could not load. ');
      const retry = document.createElement('button');
      retry.type = 'button';
      retry.className = 'question-answer-clock__retry';
      retry.textContent = 'Try again';
      retry.addEventListener('click', openAnswer, { once: true });
      note.append(retry);
    };
    video.addEventListener('loadedmetadata', onReady, { once: true });
    video.addEventListener('error', onError, { once: true });
    video.src = videoUrl;
    video.load();
  };

  const render = () => {
    const remaining = Math.max(0, Math.ceil((deadline - serverAtLoad - (elapsed() - startedAt)) / 1000));
    // With a days box, hours roll over at 24 — "151 hours" is a number
    // nobody converts in their head. Without one, hours keep counting up so
    // a short wait still reads correctly on a page that has no days box.
    const hours = dayDigit ? Math.floor(remaining / 3600) % 24 : Math.floor(remaining / 3600);
    const minutes = Math.floor((remaining % 3600) / 60);
    const seconds = remaining % 60;
    if (dayDigit) dayDigit.textContent = String(Math.floor(remaining / 86400)).padStart(2, '0');
    digits[0].textContent = String(hours).padStart(2, '0');
    digits[1].textContent = String(minutes).padStart(2, '0');
    digits[2].textContent = String(seconds).padStart(2, '0');
    if (remaining === 0) {
      clearInterval(tick);
      openAnswer(); // The private video route still enforces the shared release time.
    }
  };
  const tick = setInterval(render, 1000);
  render();

  document.addEventListener('aiohm:story-selected', (event) => {
    clock.hidden = event.detail?.id !== clock.dataset.storyId;
  });
})();

/* The bonus answer.

   The first answer plays. When it ends the frame holds a short stand-by,
   then the second answer takes the same place. Nothing starts until the
   first video has actually finished, and without JavaScript the page is what
   it always was: one answer that plays.

   Scoped per card, not per page. This was written when one question had an
   answer, so it reached for document.querySelector and wired up exactly one.
   The reel now carries ten cards and more than one of them has a why: with
   the old code the second card's stand-by never ran, and ending its video
   rewrote the first card's heading. Everything below is looked up inside the
   card it belongs to. */
(() => {
  const clocks = document.querySelectorAll('[data-answer-countdown]');
  if (!clocks.length) return;

  clocks.forEach((clock) => {
    const main = clock.querySelector('[data-answer-main]');
    const why = clock.querySelector('[data-answer-why]');
    const card = clock.querySelector('[data-answer-standby]');
    if (!main || !why || !card) return;

    const eyebrow = clock.querySelector('[data-answer-eyebrow]');
    const digits = card.querySelector('[data-standby-seconds]');
    const skip = card.querySelector('[data-standby-skip]');
    const wait = Math.max(1, parseInt(card.dataset.wait || '10', 10));
    let timer = null;
    let revealed = false;
    let counting = false;

    const reveal = () => {
      if (revealed) return;
      revealed = true;
      window.clearInterval(timer);
      card.hidden = true;
      why.hidden = false;
      if (eyebrow) eyebrow.textContent = 'AND WHY THAT WAY';
      // Autoplay can still be refused after a gesture. The controls are
      // already there, so a refusal just means the viewer presses play.
      why.play().catch(() => {});
    };

    main.addEventListener('ended', () => {
      if (revealed || counting) return;
      counting = true;
      main.hidden = true;
      card.hidden = false;
      let left = wait;
      if (digits) digits.textContent = String(left);
      timer = window.setInterval(() => {
        left -= 1;
        if (digits) digits.textContent = String(Math.max(0, left));
        if (left <= 0) reveal();
      }, 1000);
    });

    if (skip) skip.addEventListener('click', reveal);
  });
})();

/* Turning a question card by tap.

   Hover turns it on a desktop and focus turns it for a keyboard, both in
   CSS. Neither exists on a phone, and the actions live on the back — so a
   tap has to turn it too, or half the visitors can never reach Answer.

   A tap on a control inside the back is left alone: once the card is
   turned, the button and the link must work normally. */
(() => {
  const cards = document.querySelectorAll('[data-qcard]');
  if (!cards.length) return;
  if (!window.matchMedia('(hover: none)').matches) return;

  cards.forEach((card) => {
    card.addEventListener('click', (event) => {
      if (event.target.closest('a, button')) return;
      // One card turned at a time, so the deck does not end up all backs.
      cards.forEach((other) => { if (other !== card) other.classList.remove('is-turned'); });
      card.classList.toggle('is-turned');
    });
  });
})();

/* The launch countdown.

   Counts to the moment a question's film opens. The deadline is sent as an
   ISO string with the server's own clock beside it, so a visitor whose
   laptop is three hours out still sees the right number — the offset
   between the two is measured once and applied to every tick.

   When it reaches zero the page reloads, because the film itself is
   server-gated: the markup for a released film does not exist in a page
   rendered before its moment. */
(() => {
  'use strict';

  document.querySelectorAll('[data-film-countdown]').forEach((clock) => {
    const opensAt = Date.parse(clock.dataset.opensAt || '');
    const serverNow = Date.parse(clock.dataset.serverNow || '');
    if (Number.isNaN(opensAt)) return;

    // A clock that is wrong by hours is common; trust the server's.
    const skew = Number.isNaN(serverNow) ? 0 : serverNow - Date.now();

    const parts = {
      days: clock.querySelector('[data-countdown-days]'),
      hours: clock.querySelector('[data-countdown-hours]'),
      minutes: clock.querySelector('[data-countdown-minutes]'),
      seconds: clock.querySelector('[data-countdown-seconds]'),
    };
    const pad = (n) => String(Math.max(0, n)).padStart(2, '0');
    let reloading = false;

    const tick = () => {
      const left = opensAt - (Date.now() + skew);

      if (left <= 0) {
        if (reloading) return;
        reloading = true;
        // One reload, a moment after zero, so a server a second behind has
        // already crossed the line when the new page is built.
        window.setTimeout(() => window.location.reload(), 1500);
        Object.values(parts).forEach((el) => { if (el) el.textContent = '00'; });
        return;
      }

      const s = Math.floor(left / 1000);
      if (parts.days) parts.days.textContent = pad(Math.floor(s / 86400));
      if (parts.hours) parts.hours.textContent = pad(Math.floor(s / 3600) % 24);
      if (parts.minutes) parts.minutes.textContent = pad(Math.floor(s / 60) % 60);
      if (parts.seconds) parts.seconds.textContent = pad(s % 60);
    };

    tick();
    window.setInterval(tick, 1000);
  });
})();
