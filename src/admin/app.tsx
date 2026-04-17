import type { ReactElement } from 'react';
import { useState } from '@wordpress/element';
import { Databases } from './components/databases';
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
      {currentPage === 'databases' && <Databases />}
      {currentPage === 'registry' && <RegistryBrowser />}
    </>
  );
};
