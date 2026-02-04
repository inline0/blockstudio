import { ReactNode } from 'react';
import { __experimentalText as Text } from '@wordpress/components';
import { Label, LabelAction } from '@/blocks/components/label';
import { css } from '@/utils/css';

export const Control = ({
  actions,
  active = true,
  children = null,
  className = '',
  customCss = {},
  customCssInner = {},
  description = null,
  enabled = true,
  help = null,
  inRepeater = false,
  isRepeater = false,
  label = null,
  margin = true,
  name = null,
  onClick = () => {},
  type = null,
  ...rest
}: {
  actions?: LabelAction[];
  active?: boolean;
  children?: ReactNode;
  className?: string;
  customCss?: object;
  customCssInner?: object;
  description?: string | null;
  enabled?: boolean;
  help?: string | null;
  inRepeater?: boolean;
  isRepeater?: boolean;
  label?: string | null;
  margin?: boolean;
  name?: string | null;
  onClick?: () => void;
  type?: string | null;
  [key: string]: unknown;
}) => {
  return (
    <div
      {...rest}
      aria-disabled={!active}
      css={css({
        margin: margin ? '0 -16px' : undefined,
        padding: isRepeater ? '0 16px 0 20px' : '0 16px',
        position: 'relative',
        ...customCss,

        '.components-base-control__help, .components-form-token-field__help': {
          marginBottom: '0',
        },
      })}
      className={className}
      data-id={name}
    >
      {enabled && (
        <div
          className={`blockstudio-fields__field-toggle`}
          onClick={!isRepeater && !inRepeater ? onClick : () => {}}
          role="button"
          css={css({
            position: 'absolute',
            left: 0,
            top: 0,
            width: !isRepeater ? '16px' : '20px',
            height: '100%',
            cursor: !isRepeater && !inRepeater ? 'pointer' : undefined,
            zIndex: 50,

            '&:hover, &:focus-visible': {
              boxShadow:
                !isRepeater && !inRepeater
                  ? 'inset 4px 0 0 0 var(--wp-admin-theme-color)'
                  : undefined,
            },
          })}
        />
      )}
      <div
        css={css({
          opacity: active ? 1 : 0.5,
          pointerEvents: active ? 'auto' : 'none',
          ...customCssInner,

          '*': {
            userSelect: active ? 'auto' : 'none',
          },
        })}
      >
        {type !== 'toggle' && label && (
          <Label label={label} help={help || ''} actions={actions} />
        )}
        {children}
        {description && (
          <Text
            css={css({
              marginTop: '4px',
              display: 'block',
            })}
            variant="muted"
          >
            {description}
          </Text>
        )}
      </div>
    </div>
  );
};
