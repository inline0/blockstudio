import { BaseControl, Tooltip, Flex } from '@wordpress/components';
import { Icon, info } from '@wordpress/icons';
import { ConditionalWrapper } from '@/components/ConditionalWrapper';
import { __ } from '@/utils/__';
import { css } from '@/utils/css';

export const Label = ({
  label,
  help,
  toggle = false,
}: {
  label: string;
  help: string;
  toggle?: boolean;
}) => {
  if (!label) return null;

  const translatedLabel = __(label, true);

  return (
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
          marginBottom: !toggle ? '8px' : undefined,
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
  );
};
