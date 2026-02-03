import { TimePicker } from '@wordpress/components';
import { Base } from '@/blocks/components/base';
import { BlockstudioAttribute } from '@/types/block';

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
