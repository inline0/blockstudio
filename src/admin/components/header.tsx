import type { ReactElement } from 'react';
import { Button, Card, CardBody, Flex, FlexItem } from '@wordpress/components';
import { css } from '@/utils/css';
import { __ } from '@/utils/__';

export const Header = (): ReactElement | null => {
  const logo = window.blockstudioAdminPage?.logo;

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
            })}
          >
            <img alt="Blockstudio" src={logo} width="32" height="32" />
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
