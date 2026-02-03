import { parseTemplate } from '@/blocks/utils/parseTemplate';
import { BlockstudioBlock, BlockstudioBlockAttributes } from '@/types/types';

export const Message = ({
  value,
  ...rest
}: {
  value: string;
  attributes: BlockstudioBlockAttributes;
  block: BlockstudioBlock;
  [key: string]: unknown;
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
