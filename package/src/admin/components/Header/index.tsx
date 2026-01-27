import { Button, TabPanel } from '@wordpress/components';
import { useNavigate, useLocation } from 'react-router-dom';
import { Logo } from '@/admin/components/Logo';
import { __ } from '@/utils/__';
import { css } from '@/utils/css';

const menu = ['settings', 'editor'];

export const Header = () => {
  const location = useLocation();
  const navigate = useNavigate();

  return (
    <div
      css={css({
        background: '#fff',
        padding: '0 8px',
        borderBottom: '1px solid rgba(0, 0, 0, 0.1)',
        '@media (min-width: 640px)': {
          padding: '0 16px',
        },
      })}
    >
      <div
        css={css({
          display: 'flex',
          justifyContent: 'space-between',
          flexWrap: 'wrap',
          alignItems: 'center',
          gap: '16px',
        })}
      >
        <div
          css={css({
            display: 'flex',
            alignItems: 'center',
          })}
        >
          <div
            css={css({
              width: '32px',
              height: '36px',
              position: 'relative',
              overflow: 'hidden',
              marginRight: '8px',

              '@media (min-width: 640px)': {
                width: '175px',
              },
            })}
          >
            <span
              css={css({
                position: 'absolute',
                width: '175px',
                top: 'calc(50% + 2px)',
                transform: 'translateY(-50%)',
                left: 0,

                '@media (min-width: 640px)': {
                  position: 'static',
                },
              })}
            >
              <Logo />
            </span>
          </div>
          <TabPanel
            css={css({
              '.components-tab-panel__tabs-item': {
                transition: 'none',

                '&:after': {
                  width: 'calc(100% - 32px)',
                  left: '50%',
                  transform: 'translateX(-50%)',
                },
              },
            })}
            tabs={[
              {
                name: 'settings',
                title: __('Settings'),
              },
              {
                name: 'editor',
                title: __('Editor'),
              },
            ]}
            onSelect={(tabName) => navigate(`${tabName}`)}
            initialTabName={
              menu.some((e) => location.pathname.replace('/', '').includes(e))
                ? location.pathname.replace('/', '')
                : 'settings'
            }
          >
            {() => null}
          </TabPanel>
        </div>
        <Button
          href="https://blockstudio.dev/documentation"
          target="_blank"
          variant="tertiary"
          css={css({
            '@media(max-width: 370px)': {
              display: 'none',
            },
          })}
        >
          {__('Documentation')}
        </Button>
      </div>
    </div>
  );
};
