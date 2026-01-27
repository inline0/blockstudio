import { parseTemplate } from '@/blocks/utils/parseTemplate';
import { Any } from '@/type/types';

export const Message = ({
  value,
  ...rest
}: {
  value: string;
  [key: string]: Any;
}) => {
  const parsedValue = parseTemplate(value, {
    attributes: rest.attributes.blockstudio.attributes,
    block: rest.block,
  });

  return (
    parsedValue !== '' && (
      <div dangerouslySetInnerHTML={{ __html: parsedValue }} />
    )
  );
};
