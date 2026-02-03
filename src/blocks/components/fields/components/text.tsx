import { TextControl } from '@wordpress/components';

export const Text = ({
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
    <TextControl
      {...rest}
      value={rest.value ?? ''}
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
