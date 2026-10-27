/*
 * The answer reel.
 *
 * Ten question cards in one frame: the middle one is where you are, its two
 * neighbours sit behind it turned on their edge. Turning the reel also turns
 * the stage above it — heading, film, answer link — through the stage's own
 * selector, so there is one idea of "which question am I on".
 *
 * Only three cards carry a position at any time. The rest are left flat and
 * invisible, so a turn animates three elements and not ten.
 *
 * Progressive: with no script the first card is the one the server rendered
 * and everything on it still works. This file only adds the walking.
 */
(() => {
  'use strict';

  const reel = document.querySelector('[data-qreel]');
  if (!reel) return;

  const track = reel.querySelector('[data-qreel-track]');
  const slides = Array.from(reel.querySelectorAll('[data-qreel-slide]'));
  if (!track || slides.length === 0) return;

  const prevBtn = reel.querySelector('[data-qreel-prev]');
  const nextBtn = reel.querySelector('[data-qreel-next]');
  const dots = Array.from(reel.querySelectorAll('[data-qreel-dot]'));
  const counter = reel.querySelector('[data-qreel-current]');
  const pad = (n) => String(n).padStart(2, '0');

  let index = 0;

  /* The cards are absolutely positioned, so the track has no height of its
     own. A fixed one would clip a three-line question or leave a hole under
     a one-line one, so it follows whichever card is showing. */
  const sizeTrack = () => {
    const active = slides[index];
    if (active) track.style.height = `${active.offsetHeight}px`;
  };

  /* Nothing offscreen should be playing. A card you turned away from is a
     card you stopped watching. */
  const pauseOthers = () => {
    slides.forEach((slide, i) => {
      if (i === index) return;
      slide.querySelectorAll('video').forEach((v) => { if (!v.paused) v.pause(); });
    });
  };

  const render = () => {
    slides.forEach((slide, i) => {
      let pos = 'far';
      if (i === index) pos = 'active';
      else if (i === (index - 1 + slides.length) % slides.length) pos = 'prev';
      else if (i === (index + 1) % slides.length) pos = 'next';
      slide.dataset.pos = pos;
      slide.setAttribute('aria-hidden', pos === 'active' ? 'false' : 'true');
      // A card you cannot see must not be a tab stop.
      slide.querySelectorAll('a,button,video').forEach((el) => {
        if (pos === 'active') el.removeAttribute('tabindex');
        else el.setAttribute('tabindex', '-1');
      });
    });

    dots.forEach((dot, i) => dot.setAttribute('aria-selected', String(i === index)));
    if (counter) counter.textContent = pad(index + 1);
    if (prevBtn) prevBtn.setAttribute('aria-label', `Question ${pad(((index - 1 + slides.length) % slides.length) + 1)}`);
    if (nextBtn) nextBtn.setAttribute('aria-label', `Question ${pad(((index + 1) % slides.length) + 1)}`);

    pauseOthers();
    sizeTrack();
  };

  const goTo = (next, fromUser = true) => {
    const target = (next + slides.length) % slides.length;
    if (target === index) return;
    index = target;
    render();
    // Turn the stage with the reel. autoPlay false: the film is above the
    // fold you are looking at, and a video starting on its own behind your
    // back is a jump scare, not a feature.
    if (fromUser) window.AdiSaanStage?.select?.(index, false);
  };

  const press = (btn) => {
    if (!btn) return;
    btn.classList.remove('is-pressed');
    // Reflow, or the class goes on and off inside one frame and the ring
    // never restarts on a second click.
    void btn.offsetWidth;
    btn.classList.add('is-pressed');
  };

  prevBtn?.addEventListener('click', () => { press(prevBtn); goTo(index - 1); });
  nextBtn?.addEventListener('click', () => { press(nextBtn); goTo(index + 1); });
  dots.forEach((dot) => dot.addEventListener('click', () => goTo(Number(dot.dataset.index))));

  /* A neighbour is a target, not decoration: clicking one brings it forward. */
  slides.forEach((slide, i) => {
    slide.addEventListener('click', (event) => {
      if (slide.dataset.pos === 'active') return;
      event.preventDefault();
      goTo(i);
    });
  });

  reel.addEventListener('keydown', (event) => {
    if (event.key === 'ArrowLeft') { event.preventDefault(); press(prevBtn); goTo(index - 1); }
    if (event.key === 'ArrowRight') { event.preventDefault(); press(nextBtn); goTo(index + 1); }
  });

  /* Swipe, because this is a reel on a phone before it is anything else.
     The vertical guard keeps a scroll from being read as a turn. */
  let startX = 0;
  let startY = 0;
  let tracking = false;
  reel.addEventListener('touchstart', (event) => {
    const t = event.changedTouches[0];
    startX = t.clientX; startY = t.clientY; tracking = true;
  }, { passive: true });
  reel.addEventListener('touchend', (event) => {
    if (!tracking) return;
    tracking = false;
    const t = event.changedTouches[0];
    const dx = t.clientX - startX;
    const dy = t.clientY - startY;
    if (Math.abs(dx) < 46 || Math.abs(dx) < Math.abs(dy) * 1.4) return;
    goTo(index + (dx < 0 ? 1 : -1));
  }, { passive: true });

  /* The archive cards below can also change the question. The reel follows
     rather than keeping its own count. */
  document.addEventListener('aiohm:story-selected', (event) => {
    const id = event.detail?.id;
    const found = slides.findIndex((s) => s.dataset.storyId === id);
    if (found >= 0 && found !== index) { index = found; render(); }
  });

  /* The card's height changes with the viewport and when a video's metadata
     finally gives the frame its real aspect. */
  window.addEventListener('resize', sizeTrack);
  slides.forEach((slide) => {
    slide.querySelectorAll('video').forEach((v) => v.addEventListener('loadedmetadata', sizeTrack));
  });
  if (document.fonts?.ready) document.fonts.ready.then(sizeTrack).catch(() => {});

  render();
})();
