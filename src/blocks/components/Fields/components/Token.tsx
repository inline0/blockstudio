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
        label={null}
        suggestions={
          transformedOptions?.some((e) => e.value)
            ? transformedOptions?.map((e) => e?.value)
            : transformedOptions
        }
        __experimentalValidateInput={(e) =>
          item.optionsOnly
            ? (transformedOptions?.some((e) => e.value)
                ? transformedOptions?.map((e) => e?.value)
                : transformedOptions
              ).includes(e)
            : true
        }
      />
    </div>
  );
};
