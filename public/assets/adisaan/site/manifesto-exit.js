(() => {
  const dialog = document.querySelector('.manifesto-exit');
  if (!dialog || typeof dialog.showModal !== 'function') return;
  const start = Date.now();
  let shown = false;
  try { shown = sessionStorage.getItem('adisaan-manifesto-exit') === 'shown'; } catch (_) {}
  const hasDraft = () => Array.from(document.querySelectorAll('[data-answer-form] textarea, [data-answer-form] input[type=file]'))
    .some((field) => field.value.trim() !== '');
  document.addEventListener('mouseout', (event) => {
    if (shown || Date.now() - start < 5000 || event.relatedTarget || event.clientY > 0 || hasDraft()) return;
    if (document.querySelector('dialog[open]')) return;
    shown = true;
    try { sessionStorage.setItem('adisaan-manifesto-exit', 'shown'); } catch (_) {}
    dialog.showModal();
  });
  dialog.querySelectorAll('.manifesto-exit__close, .manifesto-exit__later').forEach((button) => {
    button.addEventListener('click', () => dialog.close());
  });
  dialog.addEventListener('click', (event) => { if (event.target === dialog) dialog.close(); });
})();
