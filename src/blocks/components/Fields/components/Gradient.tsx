import { GradientPicker } from '@wordpress/components';
import { Base } from '@/blocks/components/Base';
import { BlockstudioAttribute } from '@/types/block';
import { Any } from '@/types/types';

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
  [key: string]: Any;
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
