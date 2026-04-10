import type { ReactElement } from 'react';
import { useState } from '@wordpress/element';
import { Header } from './components/header';
import { Overview } from './components/overview';
import { RegistryBrowser } from './components/registry-browser';
import type { AdminPageName } from './types';

export const App = (): ReactElement => {
  const [currentPage, setCurrentPage] = useState<AdminPageName>('overview');

  return (
    <>
      <Header currentPage={currentPage} onNavigate={setCurrentPage} />
      {currentPage === 'overview' && <Overview />}
      {currentPage === 'registry' && <RegistryBrowser />}
    </>
  );
};
