import { __experimentalNumberControl as NumberControl } from '@wordpress/components';

export const NumberField = ({ ...rest }) => {
  return <NumberControl {...rest} help={false} label={false} />;
};
