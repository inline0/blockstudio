import { TextareaControl } from '@wordpress/components';

export const Textarea = ({
  properties,
  ...rest
}: {
  properties: {
    max: number;
    min: number;
  };
  value?: string;
  onChange?: (value: string) => void;
  [key: string]: unknown;
}) => {
  return (
    <TextareaControl
      {...rest}
      value={rest.value ?? ''}
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
