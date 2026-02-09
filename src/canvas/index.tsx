import { createRoot } from 'react-dom/client';

import { Canvas } from './canvas';

declare global {
  interface Window {
    blockstudioCanvas: {
      pages: Array<{
        title: string;
        url: string;
        slug: string;
        name: string;
      }>;
      settings: {
        adminBar: boolean;
      };
    };
  }
}

const init = (): void => {
  const root = document.createElement('div');
  root.id = 'blockstudio-canvas-root';
  root.style.position = 'fixed';
  root.style.inset = '0';
  root.style.zIndex = '999999';

  Array.from(document.body.children).forEach((child) => {
    if (child instanceof HTMLElement) {
      child.style.display = 'none';
    }
  });

  document.body.style.margin = '0';
  document.body.style.overflow = 'hidden';
  document.body.appendChild(root);

  const pages = window.blockstudioCanvas?.pages ?? [];
  const settings = window.blockstudioCanvas?.settings ?? {
    adminBar: true,
  };

  if (!settings.adminBar) {
    const style = document.createElement('style');
    style.textContent =
      '#wpadminbar { display: none !important; } html { margin-top: 0 !important; }';
    document.head.appendChild(style);
  }

  createRoot(root).render(<Canvas pages={pages} settings={settings} />);
};

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', init);
} else {
  init();
}
