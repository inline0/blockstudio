import { createBlock } from '@wordpress/blocks';
import { select, dispatch } from '@wordpress/data';
import { getDefaultsFromTemplate } from '@/blocks/utils/getDefaultsFromTemplate';
import { Block } from '@/types/block';
import { BlockstudioBlock } from '@/types/types';

type BlockData = {
  attributes: BlockstudioBlock['attributes'];
  clientId?: string;
  innerBlocks?: BlockData[];
  name: string;
};

const createNestedBlocks = (blockData: BlockData) => {
  const { name, attributes, innerBlocks = [] } = blockData;

  const defaults = getDefaultsFromTemplate(
    {
      attributes,
    } as Block,
    name,
  );
  const attr = {
    ...defaults,
    ...attributes,
    blockstudio: {
      attributes: {
        ...defaults.blockstudio.attributes,
        ...attributes,
      },
    },
  };

  const ib = innerBlocks.map((innerBlock) => {
    const defaults = getDefaultsFromTemplate(
      { attributes: innerBlock.attributes } as Block,
      [innerBlock.name, innerBlock.attributes],
    );
    innerBlock.attributes = {
      ...defaults,
      ...innerBlock.attributes,
      blockstudio: {
        attributes: {
          ...defaults.blockstudio.attributes,
          ...innerBlock.attributes,
        },
      },
    };

    return innerBlock;
  });

  const createdInnerBlocks = ib.map(createNestedBlocks);

  return createBlock(name, attr, createdInnerBlocks);
};

export const createBlocks = (blocks: [], clientId: string) => {
  const currentBlock =
    select('core/block-editor').getBlocksByClientId(clientId)[0];
  const childBlocks = currentBlock.innerBlocks;

  const clientIds = childBlocks.map((block: BlockData) => block.clientId);
  clientIds.forEach((item) => {
    // @ts-ignore
    dispatch('core/block-editor').removeBlock(item);
  });

  blocks.forEach((block, index) => {
    const newBlock = createNestedBlocks(block);
    // @ts-ignore
    dispatch('core/block-editor').insertBlock(newBlock, index, clientId);
  });
};
