import { addFilter } from '@wordpress/hooks';
import { shouldExtend } from '@/blocks/filters/extensions';
import { getDefaultBlock } from '@/blocks/utils/get-default-block';
import { normalizeImportedAttributes } from '@/blocks/utils/normalize-imported-attributes';

const blocks = window.blockstudioAdmin.data.blocksNative;

addFilter(
  'blocks.getBlockAttributes',
  'blockstudio/imported-attributes',
  (attributes, block, _content, raw) => {
    if (!Object.prototype.hasOwnProperty.call(blocks, block.name))
      return attributes;
    // Use the owned definition, not merged extension schemas or core fields.
    return normalizeImportedAttributes(
      attributes,
      raw,
      blocks[block.name].attributes ?? {},
    );
  },
);

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
