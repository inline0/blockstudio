import { getDefaultBlock } from '@/blocks/utils/getDefaultBlock';
import { Block } from '@/types/block';
import { Any } from '@/types/types';

export const getDefaultsFromTemplate = (outerBlock: Block, item: Any) => {
  const block = getDefaultBlock(outerBlock, item[0]) as Any;
  return {
    ...block.attributes.blockstudio.default.attributes,
    ...(item?.[1] || {}),
    blockstudio: {
      attributes: {
        ...block.attributes.blockstudio.default.attributes,
        ...(item?.[1] || {}),
      },
    },
  };
};
