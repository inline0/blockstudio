import { RadioControl, ButtonGroup, Button } from '@wordpress/components';
import { BlockstudioAttribute } from '@/type/block';
import { BlockstudioFieldsOptions } from '@/type/types';
import { css } from '@/utils/css';

export const Radio = ({
  item,
  val,
  change,
  options = [],
  ...rest
}: {
  item: BlockstudioAttribute;
  val: string;
  change: (value: string | boolean) => void;
  options: BlockstudioFieldsOptions[];
}) => {
  return item?.display === 'buttonGroup' ? (
    <ButtonGroup css={css({})}>
      {options.map((option, i) => {
        return (
          <Button
            key={`option-${i}`}
            variant={val === option.value ? 'primary' : 'secondary'}
            onClick={() => change(option.value)}
          >
            {option.label}
          </Button>
        );
      })}
    </ButtonGroup>
  ) : (
    <RadioControl
      {...rest}
      {...{ options }}
      className={`components-base-control`}
      help={false}
      label={false}
      onChange={(value) => change(value)}
      onClick={() => item?.allowNull && val && change(false)}
      selected={val}
    />
  );
};
