import { FormTokenField } from '@wordpress/components';
import { isArray } from 'lodash-es';
import { BlockstudioAttribute } from '@/types/block';
import { BlockstudioFieldsOptions } from '@/types/types';
import { css } from '@/utils/css';

export const Token = ({
  item,
  transformedOptions,
  value,
  ...rest
}: {
  item: BlockstudioAttribute;
  transformedOptions: BlockstudioFieldsOptions[] | string[];
  value: string;
}) => {
  return (
    <div
      className={`components-base-control`}
      css={css({
        '.components-form-token-field__label': {
          display: 'none',
        },
      })}
    >
      <FormTokenField
        {...rest}
        value={isArray(value) ? value : []}
        isBorderless={false}
        label={undefined}
        suggestions={
          transformedOptions?.some((e) => typeof e !== 'string' && e.value)
            ? transformedOptions?.map((e) =>
                typeof e !== 'string' ? e?.value : e,
              )
            : (transformedOptions as string[])
        }
        __experimentalValidateInput={(e) =>
          item.optionsOnly
            ? (transformedOptions?.some(
                (opt) => typeof opt !== 'string' && opt.value,
              )
                ? transformedOptions?.map((opt) =>
                    typeof opt !== 'string' ? opt?.value : opt,
                  )
                : (transformedOptions as string[])
              ).includes(e)
            : true
        }
      />
    </div>
  );
};
