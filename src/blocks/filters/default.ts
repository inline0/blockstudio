import { addFilter } from '@wordpress/hooks';
import { shouldExtend } from '@/blocks/filters/extensions';
import { getDefaultBlock } from '@/blocks/utils/get-default-block';

const blocks = window.blockstudioAdmin.data.blocksNative;

addFilter(
  'blocks.registerBlockType',
  'blockstudio/extensions/register',
  (block, name) => {
    if (!Object.keys(blocks).includes(name) && !shouldExtend(name)) {
      return block;
    }

    return getDefaultBlock(block, name);
  },
);
