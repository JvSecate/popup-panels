(function () {
  document.addEventListener('click', (event) => {
    const trigger = event.target.closest('[data-popup-target], a[href^="#"]');

    if (!trigger) {
      return;
    }

    let targetName = trigger.getAttribute('data-popup-target') || '';

    if (!targetName && trigger.getAttribute('href') && trigger.getAttribute('href').startsWith('#')) {
      targetName = trigger.getAttribute('href').slice(1);
    }

    if (!targetName) {
      return;
    }

    const panels = Array.from(document.querySelectorAll('[data-popup-panel]'));
    const panel = panels.find((candidate) => candidate.getAttribute('data-popup-panel') === targetName);

    if (!panel) {
      return;
    }

    event.preventDefault();
    panel.classList.add('is-open');
    panel.setAttribute('aria-hidden', 'false');
    panel.dataset.previousBodyOverflow = document.body.style.overflow || '';
    document.body.style.overflow = 'hidden';
  });

  document.addEventListener('click', (event) => {
    const closeControl = event.target.closest('[data-popup-close]');

    if (!closeControl) {
      return;
    }

    const panel = closeControl.closest('[data-popup-panel]');

    if (!panel) {
      return;
    }

    event.preventDefault();
    panel.classList.remove('is-open');
    panel.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = panel.dataset.previousBodyOverflow || '';
  });

  document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') {
      return;
    }

    document.querySelectorAll('[data-popup-panel].is-open').forEach((panel) => {
      panel.classList.remove('is-open');
      panel.setAttribute('aria-hidden', 'true');
    });

    document.body.style.overflow = '';
  });
}());
