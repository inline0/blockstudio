import { DatePicker } from '@wordpress/components';
import { Base } from '@/blocks/components/base';
import { BlockstudioAttribute } from '@/types/block';

export const Date = ({
  v,
  change,
  ...rest
}: {
  item: BlockstudioAttribute;
  v: string;
  change: (value: string) => void;
}) => {
  return (
    <Base>
      <DatePicker {...rest} currentDate={v} onChange={(date) => change(date)} />
    </Base>
  );
};
