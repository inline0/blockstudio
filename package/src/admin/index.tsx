import { useSelect } from '@wordpress/data';
import { createRoot, useEffect, useState } from '@wordpress/element';
import { ErrorBoundary } from 'react-error-boundary';
import {
  Outlet,
  RouterProvider,
  useLocation,
  useNavigate,
} from 'react-router-dom';
import { Header } from '@/admin/components/Header';
import { router } from '@/admin/router';
import { Error } from '@/components/Error';
import { Style } from '@/components/Style';
import { selectors } from '@/editor/store/selectors';
import { css } from '@/utils/css';

export default function Admin() {
  const [hasRendered, setHasRendered] = useState(false);
  const isEditor = useSelect(
    (select) => (select('blockstudio/editor') as typeof selectors).isEditor(),
    [],
  );
  const location = useLocation();
  const navigate = useNavigate();

  useEffect(() => {
    const params = new URLSearchParams(window.location.search);
    const isDemo = params.get('demo');
    if (isDemo) {
      navigate('/editor');
    } else if (location.pathname === '/') {
      navigate('/settings');
    }
    setHasRendered(true);
  }, []);

  return (
    <>
      <ErrorBoundary FallbackComponent={() => <Error />}>
        {isEditor ? (
          <Outlet />
        ) : (
          <div
            id="blockstudio-admin"
            css={css({
              opacity: !hasRendered ? '0' : '100',
            })}
          >
            <ErrorBoundary FallbackComponent={() => <Error />}>
              <Header />
              <div
                css={css({
                  position: 'relative',
                  maxWidth: '640px',
                  margin: '24px auto 48px auto',
                })}
              >
                <Outlet />
              </div>
            </ErrorBoundary>
          </div>
        )}
      </ErrorBoundary>
      <Style />
    </>
  );
}

createRoot(document.getElementById('blockstudio')).render(
  <RouterProvider router={router} />,
);
