/* Progressive enhancement only: no navigation, forms, requests or data handling. */
(() => {
  'use strict';
  const root = document.body;
  if (!root || !root.classList.contains('gc-teacher-area')) return;
  const reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  if (reduce) return;
  const visible = Array.from(document.querySelectorAll(
    '.gc-dash-stat, .gc-dash-panel, .prof-metric, .prof-dashboard-box, .gc-model-card, .panel-card'
  ));
  if ('IntersectionObserver' in window) {
    const observer = new IntersectionObserver((entries) => {
      entries.forEach(({target, isIntersecting}) => {
        if (isIntersecting) {
          target.classList.add('gc-inview');
          observer.unobserve(target);
        }
      });
    }, { threshold: 0.08, rootMargin: '0px 0px 30px 0px' });
    visible.forEach((el, i) => {
      el.style.setProperty('--gc-delay', `${(i % 4) * 65}ms`);
      el.classList.add('gc-reveal-ready');
      observer.observe(el);
    });
  }
  if (!window.matchMedia || !window.matchMedia('(hover: hover) and (pointer: fine)').matches) return;
  document.querySelectorAll('.gc-dash-stat, .gc-dash-panel, .panel-card, .prof-metric, .prof-dashboard-box').forEach(el => {
    el.addEventListener('pointermove', e => {
      const bounds = el.getBoundingClientRect();
      if (bounds.width === 0 || bounds.height === 0) return;
      el.style.setProperty('--gc-pointer-x', `${((e.clientX - bounds.left) / bounds.width * 100).toFixed(1)}%`);
      el.style.setProperty('--gc-pointer-y', `${((e.clientY - bounds.top) / bounds.height * 100).toFixed(1)}%`);
    }, {passive: true});
  });
})();
