import { TextControl } from '@wordpress/components';
import { Any } from '@/types/types';

export const Text = ({
  properties,
  ...rest
}: {
  properties: {
    max: number;
    min: number;
  };
  [key: string]: Any;
}) => {
  return (
    <TextControl
      {...rest}
      value={rest.value || undefined}
      onChange={rest.onChange || (() => {})}
      className={`components-base-control`}
      help={false}
      label={false}
      maxLength={properties.max}
      minLength={properties.min}
      type="text"
    />
  );
};
