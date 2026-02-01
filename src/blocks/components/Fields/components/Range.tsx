import { RangeControl } from '@wordpress/components';

export const Range = ({ ...rest }) => {
  return (
    <RangeControl
      {...rest}
      help={false}
      label={undefined}
      className={`components-base-control`}
    />
  );
};
