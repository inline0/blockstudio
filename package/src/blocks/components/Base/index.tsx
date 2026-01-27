import { ReactNode } from 'react';
import { BaseControl } from '@wordpress/components';
import { Any } from '@/type/types';
import { css } from '@/utils/css';

export const Base = ({
  children,
  wrap = false,
  ...rest
}: {
  children: ReactNode;
  wrap?: boolean;
  [key: string]: Any;
}) => {
  return wrap ? (
    <BaseControl {...rest} help={false} label={false}>
      <div
        css={css({
          border: 'var(--blockstudio-border)',
          borderRadius: 'var(--blockstudio-border-radius)',
          overflow: 'hidden',
        })}
      >
        <div css={css({ marginTop: '-1px' })}>{children}</div>
      </div>
    </BaseControl>
  ) : (
    <BaseControl {...rest} help={false} label={false}>
      {children}
    </BaseControl>
  );
};
