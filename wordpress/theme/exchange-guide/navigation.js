(() => {
  const nav = document.querySelector('#cg-primary-nav');
  const toggle = document.querySelector('.cg-menu-toggle');
  if (!nav || !toggle) return;
  document.documentElement.classList.add('cg-navigation-ready');
  toggle.hidden = false;
  const close = () => {
    toggle.setAttribute('aria-expanded', 'false');
    nav.classList.remove('cg-mobile-open');
    nav.querySelectorAll('.cg-submenu-toggle').forEach(button => {
      button.setAttribute('aria-expanded', 'false');
      button.closest('li').classList.remove('cg-submenu-open');
      button.closest('li').classList.add('cg-submenu-closed');
    });
  };
  toggle.addEventListener('click', () => {
    const open = toggle.getAttribute('aria-expanded') !== 'true';
    toggle.setAttribute('aria-expanded', String(open));
    nav.classList.toggle('cg-mobile-open', open);
  });
  nav.querySelectorAll('.cg-submenu-toggle').forEach((button, index) => {
    const menu = button.parentElement.querySelector(':scope > .sub-menu');
    if (!menu) return;
    button.closest('li').addEventListener('pointerenter', event => {
      if (event.pointerType === 'mouse') button.closest('li').classList.remove('cg-submenu-closed');
    });
    menu.id = `cg-submenu-${index}`;
    button.setAttribute('aria-controls', menu.id);
    button.addEventListener('click', () => {
      const open = button.getAttribute('aria-expanded') !== 'true';
      button.setAttribute('aria-expanded', String(open));
      button.closest('li').classList.toggle('cg-submenu-open', open);
      button.closest('li').classList.toggle('cg-submenu-closed', !open);
    });
  });
  document.addEventListener('keydown', event => {
    if (event.key === 'Escape') { close(); if (window.matchMedia('(max-width: 850px)').matches) toggle.focus(); else document.querySelector('.cg-brand')?.focus(); }
  });
  document.addEventListener('click', event => {
    if (!nav.contains(event.target) && !toggle.contains(event.target)) close();
  });
  window.matchMedia('(max-width: 850px)').addEventListener('change', close);
})();
