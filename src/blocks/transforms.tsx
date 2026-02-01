import { BlockInstance, createBlock } from '@wordpress/blocks';
import { BlockstudioBlock } from '@/types/types';

const isValidRegex = (value: string) => {
  const match = value.match(/\/(.*)\/(.*)?/);
  if (match) {
    try {
      new RegExp(match[1], match[2]);
      return true;
    } catch (e) {
      return false;
    }
  }
  return false;
};

const compareAttributeTypes = (
  block1: BlockstudioBlock,
  block2: BlockstudioBlock,
) => {
  const keys1 = Object.keys(block1.attributes);
  const keys2 = Object.keys(block2.attributes);

  const commonKeys = keys1.filter((value) => keys2.includes(value));

  for (const key of commonKeys) {
    if (block2.attributes[key].type !== block1.attributes[key].type) {
      return false;
    }
  }

  return true;
};

export const transforms = (
  block: BlockstudioBlock,
  blocks: BlockstudioBlock[],
) => {
  return block?.blockstudio?.transforms?.from?.length
    ? {
        from: block?.blockstudio?.transforms?.from
          .filter(
            (e) =>
              e.type === 'block' || e.type === 'enter' || e.type === 'prefix',
          )
          .map((e) => {
            if (e.type === 'block' && e.blocks?.length) {
              const filteredBlocks = e.blocks.filter((blockId) =>
                compareAttributeTypes(block, blocks[blockId]),
              );

              return {
                ...e,
                blocks: filteredBlocks,
                transform: (
                  attributes: BlockstudioBlock,
                  innerBlocks: BlockInstance[],
                ) => {
                  return createBlock(
                    block.name,
                    {
                      ...attributes,
                      blockstudio: {
                        ...attributes.blockstudio,
                        name: block.name,
                      },
                    },
                    innerBlocks,
                  );
                },
              };
            }

            if (e.type === 'enter' && isValidRegex(e.regExp)) {
              const regExpParts = e.regExp.match(/\/(.*)\/(.*)?/);
              const regExp = new RegExp(regExpParts[1], regExpParts[2]);

              return {
                ...e,
                regExp,
                transform: () => createBlock(block.name),
              };
            }

            if (e.type === 'prefix' && e.prefix && e.prefix !== '') {
              return {
                ...e,
                transform: () => createBlock(block.name),
              };
            }
          }),
      }
    : [];
};
