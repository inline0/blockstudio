import { BaseControl, Button, Tooltip, Flex } from '@wordpress/components';
import { Icon, info } from '@wordpress/icons';
import { ConditionalWrapper } from '@/components/conditional-wrapper';
import { __ } from '@/utils/__';
import { css } from '@/utils/css';

export type LabelAction = {
  icon: JSX.Element;
  onClick: () => void;
  label?: string;
};

export const Label = ({
  label,
  help,
  toggle = false,
  actions,
}: {
  label: string;
  help: string;
  toggle?: boolean;
  actions?: LabelAction[];
}) => {
  if (!label) return null;

  const translatedLabel = __(label, true);

  return (
    <div
      css={css({
        display: 'flex',
        alignItems: 'center',
        marginBottom: !toggle
          ? actions && actions.length > 0
            ? '5px'
            : '8px'
          : undefined,
      })}
    >
      <ConditionalWrapper
        condition={help as unknown as boolean}
        wrapper={(children) => (
          <Tooltip text={__(help, true)} position="top center" delay={500}>
            {children as JSX.Element}
          </Tooltip>
        )}
      >
        <div
          aria-label={translatedLabel}
          css={css({
            display: 'flex',
            alignItems: 'center',
            cursor: help ? 'pointer' : undefined,
            width: 'max-content',
            maxWidth: 'fit-content',

            '*': {
              marginBottom: '0',
            },
          })}
        >
          {toggle ? (
            <Flex as="label" className="components-toggle-control__label">
              {translatedLabel}
            </Flex>
          ) : (
            <BaseControl.VisualLabel>{translatedLabel}</BaseControl.VisualLabel>
          )}
          {help && (
            <Icon
              className={`blockstudio-field__label-info`}
              icon={info}
              size={16}
              css={css({ marginLeft: '4px', display: 'flex', flexShrink: '0' })}
            />
          )}
        </div>
      </ConditionalWrapper>
      {actions && actions.length > 0 && (
        <div
          css={css({
            marginLeft: 'auto',
            display: 'flex',
            alignItems: 'center',
            gap: '4px',
          })}
        >
          {actions.map((action, index) => (
            <Button
              key={index}
              className="blockstudio-fields__action"
              icon={action.icon}
              iconSize={16}
              onClick={action.onClick}
              label={action.label}
              size="small"
            />
          ))}
        </div>
      )}
    </div>
  );
};
