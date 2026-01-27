import { TimePicker } from '@wordpress/components';
import { Base } from '@/blocks/components/Base';
import { BlockstudioAttribute } from '@/type/block';

export const Datetime = ({
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
      <TimePicker {...rest} currentTime={v} onChange={(date) => change(date)} />
    </Base>
  );
};
