import { createRoot } from 'react-dom/client';
import { App } from './app';
import './style.css';
import './types';

const init = (): void => {
  const root = document.getElementById('blockstudio-admin');

  if (!root) {
    return;
  }

  createRoot(root).render(<App />);
};

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', init);
} else {
  init();
}
