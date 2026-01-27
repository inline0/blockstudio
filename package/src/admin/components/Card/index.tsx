import { ReactNode } from 'react';
import { Card as C } from '@wordpress/components';
import { css } from '@/utils/css';

export const Card = ({ children }: { children: ReactNode }) => {
  return (
    <C
      isRounded
      isBorderless
      size="large"
      elevation={1}
      css={css({
        borderRadius: '0px',

        '@media (min-width: 640px)': {
          borderRadius: '8px',
          overflow: 'hidden',
        },

        '& > div': {
          borderRadius: '8px',
        },
      })}
    >
      {children}
    </C>
  );
};
