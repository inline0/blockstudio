import { createRoot } from 'react-dom/client';

import {
  setDefaultBlockName,
  setFreeformContentHandlerName,
  setUnregisteredTypeHandlerName,
} from '@wordpress/blocks';

import { Canvas } from './canvas';
import './store';

declare global {
  interface Window {
    blockstudioCanvas: {
      pages: Array<{
        title: string;
        slug: string;
        name: string;
        content: string;
      }>;
      blocks: Array<{
        title: string;
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
    '[data-canvas-surface] { opacity: 0; transition: opacity 0.4s ease; }',
    '.blockstudio-canvas-menu .components-button { color: rgba(255,255,255,0.6); border-radius: 4px; }',
    '.blockstudio-canvas-menu .components-button:hover, .blockstudio-canvas-menu .components-button:focus { color: #fff; background: rgba(255,255,255,0.1); }',
    '@keyframes blockstudio-canvas-pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.4; } }',
  ].join('\n');
  document.head.appendChild(style);

  window.wp?.blockLibrary?.registerCoreBlocks();
  setDefaultBlockName('core/paragraph');
  setFreeformContentHandlerName('core/freeform');
  setUnregisteredTypeHandlerName('core/missing');

  const pages = window.blockstudioCanvas?.pages ?? [];
  const blocks = window.blockstudioCanvas?.blocks ?? [];
  const settings = window.blockstudioCanvas?.settings ?? {};

  createRoot(root).render(
    <Canvas pages={pages} blocks={blocks} settings={settings} />,
  );
};

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', init);
} else {
  init();
}
