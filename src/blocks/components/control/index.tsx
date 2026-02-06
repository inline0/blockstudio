import { ReactNode } from 'react';
import { __experimentalText as Text } from '@wordpress/components';
import { Label, LabelAction } from '@/blocks/components/label';
import { __ } from '@/utils/__';
import { css } from '@/utils/css';

export const Control = ({
  actions,
  active = true,
  children = null,
  className = '',
  customCss = {},
  customCssInner = {},
  description = null,
  enabled: _enabled = true,
  help = null,
  inRepeater: _inRepeater = false,
  isRepeater = false,
  label = null,
  margin = true,
  name = null,
  onClick: _onClick = () => {},
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
      {/* Commented out: left-side toggle bar. May be re-enabled in future.
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
      */}
      <div
        css={css({
          ...customCssInner,
        })}
      >
        {type !== 'toggle' && label && (
          <Label label={label} help={help || ''} actions={actions} />
        )}
        <div css={css({ position: 'relative' })}>
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
          {!active && (
            <div
              className="blockstudio-fields__field-disabled"
              css={css({
                position: 'absolute',
                inset: 0,
                backdropFilter: 'blur(2px)',
                background: 'rgba(255, 255, 255, 0.6)',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                zIndex: 10,
                borderRadius: '2px',
                pointerEvents: 'none',
              })}
            >
              <span
                css={css({
                  fontSize: '12px',
                  color: 'rgba(0, 0, 0, 0.4)',
                })}
              >
                {__('This field is disabled')}
              </span>
            </div>
          )}
        </div>
      </div>
    </div>
  );
};
