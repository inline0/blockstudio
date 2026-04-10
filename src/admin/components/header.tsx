import type { ReactElement } from 'react';
import { Button, Card, CardBody, Flex, FlexItem } from '@wordpress/components';
import type { AdminPageName } from '../types';
import { css } from '@/utils/css';
import { __ } from '@/utils/__';

type HeaderProps = {
  currentPage: AdminPageName;
  onNavigate: (page: AdminPageName) => void;
};

export const Header = ({
  currentPage,
  onNavigate,
}: HeaderProps): ReactElement | null => {
  const logo = window.blockstudioAdminPage?.logo;
  const hasRegistry = !!window.blockstudioAdminPage?.registryEnabled;

  if (!logo) {
    return null;
  }

  return (
    <Card
      css={css({
        borderTop: '0',
        borderLeft: '0',
        borderRight: '0',
        borderRadius: '0',
        boxShadow: '0 1px 1px rgba(0, 0, 0, 0.04)',
      })}
    >
      <CardBody
        css={css({
          padding: '18px 20px',
        })}
      >
        <Flex align="center" justify="space-between">
          <FlexItem
            css={css({
              display: 'flex',
              alignItems: 'center',
              gap: '20px',
            })}
          >
            <img alt="Blockstudio" src={logo} width="32" height="32" />
            <Flex
              gap={0}
              css={css({
                'button': {
                  padding: '6px 12px',
                  borderRadius: '4px',
                },
              })}
            >
              <Button
                onClick={() => onNavigate('overview')}
                variant={currentPage === 'overview' ? 'secondary' : 'tertiary'}
              >
                {__('Overview')}
              </Button>
              {hasRegistry && (
                <Button
                  onClick={() => onNavigate('registry')}
                  variant={currentPage === 'registry' ? 'secondary' : 'tertiary'}
                >
                  {__('Registry')}
                </Button>
              )}
            </Flex>
          </FlexItem>
          <FlexItem>
            <Button
              href="https://blockstudio.dev/docs"
              target="_blank"
              rel="noreferrer"
              variant="secondary"
            >
              {__('Docs')}
            </Button>
          </FlexItem>
        </Flex>
      </CardBody>
    </Card>
  );
};
