import type { ReactElement } from 'react';
import { Header } from './components/header';
import { Overview } from './components/overview';

export const App = (): ReactElement => {
  return (
    <>
      <Header />
      <Overview />
    </>
  );
};
