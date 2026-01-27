import { ColorPalette } from '@wordpress/components';
import { Base } from '@/blocks/components/Base';
import { Any } from '@/type/types';

export const Color = ({
  value,
  transformedOptions = [],
  ...rest
}: {
  value: string;
  transformedOptions: {
    label: string;
    value: string;
    name: string;
    slug: string;
  }[];
  [key: string]: Any;
}) => {
  return (
    <Base>
      <ColorPalette
        {...{ ...rest, value }}
        onChange={rest.onChange || (() => {})}
        colors={transformedOptions?.map((e) => {
          return {
            color: e.value,
            name: e.name,
            slug: e.slug,
          };
        })}
      />
    </Base>
  );
};
