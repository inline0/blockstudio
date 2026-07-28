(() => {
  const connection = window.navigator?.connection;
  if (
    connection &&
    (connection.saveData ||
      /(^|-)2g$/.test(String(connection.effectiveType || '')))
  ) {
    return;
  }

  const warmed = new Set();

  const hrefFor = (anchor) => {
    if (!anchor || anchor.tagName !== 'A') return null;
    if (anchor.dataset && 'noPrefetch' in anchor.dataset) return null;
    if (anchor.target && anchor.target !== '_self') return null;
    if (anchor.hasAttribute('download')) return null;

    let url;
    try {
      url = new URL(anchor.href, window.location.href);
    } catch {
      return null;
    }

    if (url.origin !== window.location.origin || url.search) return null;
    if (url.pathname === window.location.pathname && url.hash && !url.search) {
      return null;
    }

    return url.href;
  };

  const preload = (target) => {
    const anchor = target?.closest?.('a[href]');
    const href = hrefFor(anchor);
    if (!href || warmed.has(href)) return;

    warmed.add(href);
    const link = document.createElement('link');
    link.rel = 'prefetch';
    link.href = href;
    link.as = 'document';
    document.head.appendChild(link);
  };

  document.addEventListener(
    'pointerenter',
    (event) => preload(event.target),
    true,
  );
  document.addEventListener('focusin', (event) => preload(event.target), true);
  document.addEventListener('touchstart', (event) => preload(event.target), {
    capture: true,
    passive: true,
  });
})();
