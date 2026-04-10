import { createRoot } from 'react-dom/client';
import apiFetch from '@wordpress/api-fetch';
import { App } from './app';
import './style.css';
import './types';

const nonce = window.blockstudioAdminPage?.nonce;
if (nonce) {
  apiFetch.use(apiFetch.createNonceMiddleware(nonce));
}

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
