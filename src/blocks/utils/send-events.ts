import { select } from '@wordpress/data';
import { isArray } from 'lodash-es';
import { BlockstudioBlock } from '@/types/types';

type EditorBlock = {
  name: string;
  innerBlocks?: EditorBlock[];
};

export const sendEvents = () => {
  const blockTypes = window.blockstudioAdmin.data
    .blocksNative as unknown as Record<string, BlockstudioBlock>;

  const getAllNestedBlockNames = (): string[] => {
    const getBlockNames = (blocks: EditorBlock[]): string[] => {
      return blocks.reduce((acc: string[], block) => {
        acc.push(block.name);
        if (block.innerBlocks && block.innerBlocks.length) {
          acc = acc.concat(getBlockNames(block.innerBlocks));
        }
        return acc;
      }, []);
    };

    const topLevelBlocks = select(
      'core/block-editor',
    ).getBlocks() as EditorBlock[];
    const allBlockNames = getBlockNames(topLevelBlocks);

    return Array.from(new Set(allBlockNames));
  };

  const blocks = getAllNestedBlockNames();

  blocks.forEach((item) => {
    if (Object.keys(blockTypes).includes(item)) {
      const refreshOn = blockTypes[item]?.blockstudio?.refreshOn;

      if (isArray(refreshOn) && refreshOn?.includes('save')) {
        setTimeout(() => {
          const RefreshEvent = new CustomEvent(`blockstudio/${item}/refresh`);
          return document.dispatchEvent(RefreshEvent);
        }, 1000);
      }
    }
  });
};
