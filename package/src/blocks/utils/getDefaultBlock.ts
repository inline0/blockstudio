import { getMatches } from '@/blocks/filters/extensions';
import { getDefaults } from '@/blocks/utils/getDefaults';
import { Block } from '@/type/block';
import { BlockstudioBlock } from '@/type/types';

export const getDefaultBlock = (block: Block, name: string) => {
  let otherAttributes = {};
  let defaults = getDefaults(Object.values(block?.attributes || {}), {});

  Object.values(getMatches(name)).forEach((e: BlockstudioBlock) => {
    defaults = {
      ...defaults,
      ...getDefaults(Object.values(e?.attributes || {}), defaults),
    };
    otherAttributes = {
      ...otherAttributes,
      ...e.attributes,
    };
  });

  block.attributes = {
    ...block.attributes,
    ...otherAttributes,
    blockstudio: {
      type: 'object',
      default: {
        attributes: defaults,
      },
    },
  };

  return block;
};
