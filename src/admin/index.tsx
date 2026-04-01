import { createRoot } from 'react-dom/client';
import { Header } from './components/header';
import './types';

const init = (): void => {
  const root = document.getElementById('blockstudio-admin');

  if (!root) {
    return;
  }

  createRoot(root).render(<Header />);
};

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', init);
} else {
  init();
}
