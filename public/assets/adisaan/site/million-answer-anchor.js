(() => {
  const revealAnswer = () => {
    if (!/^#answer-\d+$/.test(location.hash)) return;
    const card = document.getElementById(location.hash.slice(1));
    if (!card) return;
    const details = card.querySelector('details');
    if (details) details.open = true;
    card.scrollIntoView({block: 'center'});
    card.focus({preventScroll: true});
  };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', revealAnswer, {once: true});
  else revealAnswer();
  window.addEventListener('hashchange', revealAnswer);
})();
