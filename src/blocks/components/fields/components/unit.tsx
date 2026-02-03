import { __experimentalUnitControl as UnitControl } from '@wordpress/components';

export const Unit = ({ ...rest }) => {
  return <UnitControl {...rest} label={false} size="default" />;
};
