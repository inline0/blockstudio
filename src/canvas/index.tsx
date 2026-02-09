import { createRoot } from 'react-dom/client';

import {
  setDefaultBlockName,
  setFreeformContentHandlerName,
  setUnregisteredTypeHandlerName,
} from '@wordpress/blocks';

import { Canvas } from './canvas';

declare global {
  interface Window {
    blockstudioCanvas: {
      pages: Array<{
        title: string;
        slug: string;
        name: string;
        content: string;
      }>;
      settings: Record<string, unknown>;
    };
    wp: {
      blockLibrary?: {
        registerCoreBlocks: () => void;
      };
      [key: string]: unknown;
    };
  }
}

const init = (): void => {
  const root = document.getElementById('blockstudio-canvas');

  if (!root) {
    return;
  }

  const style = document.createElement('style');
  style.textContent = [
    '@keyframes blockstudio-canvas-spin { to { transform: rotate(360deg); } }',
    '[data-canvas-surface] { opacity: 0; transition: opacity 0.4s ease; }',
    '[data-canvas-label] { opacity: 0; transition: opacity 0.4s ease; }',
  ].join('\n');
  document.head.appendChild(style);

  window.wp?.blockLibrary?.registerCoreBlocks();
  setDefaultBlockName('core/paragraph');
  setFreeformContentHandlerName('core/freeform');
  setUnregisteredTypeHandlerName('core/missing');

  const pages = window.blockstudioCanvas?.pages ?? [];
  const settings = window.blockstudioCanvas?.settings ?? {};

  createRoot(root).render(<Canvas pages={pages} settings={settings} />);
};

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', init);
} else {
  init();
}
