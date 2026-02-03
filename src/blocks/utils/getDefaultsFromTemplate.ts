import { getDefaultBlock } from '@/blocks/utils/getDefaultBlock';
import { Block } from '@/types/block';

type TemplateItem = string | readonly [string, ...unknown[]];
type DefaultBlock = Block & {
  attributes: {
    blockstudio: {
      default: {
        attributes: Record<string, unknown>;
      };
    };
  };
};

export const getDefaultsFromTemplate = (outerBlock: Block, item: TemplateItem) => {
  const blockName = typeof item === 'string' ? item : item[0];
  const itemAttributes = (typeof item === 'string' ? {} : (item[1] || {})) as Record<string, unknown>;
  const block = getDefaultBlock(outerBlock, blockName) as DefaultBlock;
  return {
    ...block.attributes.blockstudio.default.attributes,
    ...itemAttributes,
    blockstudio: {
      attributes: {
        ...block.attributes.blockstudio.default.attributes,
        ...itemAttributes,
      },
    },
  };
};
