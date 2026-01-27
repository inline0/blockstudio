import { DatePicker } from '@wordpress/components';
import { Base } from '@/blocks/components/Base';
import { BlockstudioAttribute } from '@/type/block';

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
