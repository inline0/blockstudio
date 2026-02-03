import { __experimentalText as Text } from '@wordpress/components';
import { style } from '@/const/index';
import { __ } from '@/utils/__';
import { css } from '@/utils/css';

export const Placeholder = ({ name }: { name: string }) => {
  return (
    <div
      className="blockstudio-placeholder"
      css={css({
        border: style.border,
        borderRadius: style.borderRadius,
        padding: '32px',
        display: 'flex',
        justifyContent: 'center',
        alignItems: 'center',
        fontFamily: 'sans-serif',
        flexDirection: 'column',
        gap: '4px',
      })}
    >
      <Text size="medium">{name}</Text>
      <Text variant="muted">{__('Click to load')}</Text>
    </div>
  );
};
