import { ColorPalette } from '@wordpress/components';
import { Base } from '@/blocks/components/base';

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
  onChange?: (value: string | undefined) => void;
  [key: string]: unknown;
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
