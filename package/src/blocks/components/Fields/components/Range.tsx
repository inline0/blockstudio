import { RangeControl } from '@wordpress/components';

export const Range = ({ ...rest }) => {
  return (
    <RangeControl
      {...rest}
      help={false}
      label={null}
      className={`components-base-control`}
    />
  );
};
