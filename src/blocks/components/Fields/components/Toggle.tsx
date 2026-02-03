import { ToggleControl } from '@wordpress/components';
import { Label } from '@/blocks/components/Label';
import { css } from '@/utils/css';

export const Toggle = ({
  checked,
  label,
  help,
  ...rest
}: {
  checked: boolean;
  label: string;
  help: string;
  onChange?: (value: boolean) => void;
  [key: string]: unknown;
}) => {
  return (
    <div
      css={css({
        display: 'flex',
        alignItems: 'center',
      })}
    >
      <ToggleControl
        {...{ ...rest, checked }}
        onChange={rest.onChange || (() => {})}
        className={`components-base-control`}
        help={false}
        label={!help && label}
      />
      {help && <Label {...{ label, help }} toggle={true} />}
    </div>
  );
};
