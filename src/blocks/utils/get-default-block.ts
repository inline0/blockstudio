import { getMatches } from '@/blocks/filters/extensions';
import { getDefaults } from '@/blocks/utils/get-defaults';
import { Block } from '@/types/block';
import { BlockstudioBlock } from '@/types/types';

export const getDefaultBlock = (block: Block, name: string) => {
  let otherAttributes = {};
  let defaults = getDefaults(Object.values(block?.attributes || {}), {});

  (Object.values(getMatches(name)) as BlockstudioBlock[]).forEach((e) => {
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
