import { GradientPicker } from '@wordpress/components';
import { Base } from '@/blocks/components/base';
import { BlockstudioAttribute } from '@/types/block';

export const Gradient = ({
  value,
  transformedOptions = [],
  ...rest
}: {
  item: BlockstudioAttribute;
  value: string;
  transformedOptions: {
    label: string;
    value: string;
    name: string;
    slug: string;
  }[];
  onChange?: (value: string | undefined) => void;
  options?: unknown;
  [key: string]: unknown;
}) => {
  delete rest.options;

  return (
    <Base>
      <GradientPicker
        {...{ ...rest, value }}
        onChange={rest.onChange || (() => {})}
        gradients={transformedOptions.map((e) => {
          return {
            gradient: e.value,
            name: e.name,
            slug: e.slug,
          };
        })}
      />
    </Base>
  );
};
