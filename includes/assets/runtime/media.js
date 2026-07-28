(() => {
  const config = window.BlockstudioMedia || {};
  const elements = document.querySelectorAll('[data-blockstudio-media]');

  const load = (element) => {
    element.querySelectorAll('[data-srcset]').forEach((source) => {
      source.srcset = source.dataset.srcset || '';
      source.removeAttribute('data-srcset');
    });

    const image = element.querySelector('img[data-src]');
    if (!image) return;

    image.addEventListener(
      'load',
      () => {
        element.dataset.state = 'loaded';
      },
      { once: true },
    );
    image.addEventListener(
      'error',
      () => {
        element.dataset.state = 'error';
      },
      { once: true },
    );
    image.src = image.dataset.src || '';
    image.removeAttribute('data-src');
  };

  if (!('IntersectionObserver' in window)) {
    elements.forEach(load);
    return;
  }

  const observer = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (!entry.isIntersecting) return;
        observer.unobserve(entry.target);
        load(entry.target);
      });
    },
    { rootMargin: config.rootMargin || '300px' },
  );

  elements.forEach((element) => observer.observe(element));
})();
