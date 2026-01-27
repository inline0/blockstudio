import { TextareaControl } from '@wordpress/components';
import { Any } from '@/type/types';

export const Textarea = ({
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
    <TextareaControl
      {...rest}
      value={rest.value || undefined}
      onChange={rest.onChange || (() => {})}
      className={`components-base-control`}
      hideLabelFromVision
      help={false}
      label={false}
      maxLength={properties.max}
      minLength={properties.min}
    />
  );
};
