import { CheckboxControl } from '@wordpress/components';
import { Base } from '@/blocks/components/Base';
import { BlockstudioAttribute } from '@/types/block';
import { BlockstudioFieldsOptions } from '@/types/types';
import { __ } from '@/utils/__';

export const Checkbox = ({
  item,
  change,
  v,
  options = [],
}: {
  item: BlockstudioAttribute;
  change: (value: string | BlockstudioFieldsOptions[], force?: boolean) => void;
  v: BlockstudioAttribute;
  options: BlockstudioFieldsOptions[];
}) => {
  return (
    <Base>
      <div className={`blockstudio-space blockstudio-space--half`}>
        {item?.toggle && (
          <CheckboxControl
            checked={v?.length === options.length}
            className={`components-base-control`}
            label={__(
              typeof item?.toggle === 'string'
                ? item?.toggle
                : 'Toggle all options',
              typeof item?.toggle === 'string'
            )}
            onChange={() =>
              v?.length === options.length
                ? change([], true)
                : change(options, true)
            }
          />
        )}
        {options.map((itemInner) => {
          return (
            <CheckboxControl
              key={itemInner.value}
              onChange={() => change(itemInner.value)}
              checked={
                v?.length && v.map((e) => e.value).includes(itemInner.value)
              }
              label={itemInner.label}
              className={`components-base-control`}
            />
          );
        })}
      </div>
    </Base>
  );
};
