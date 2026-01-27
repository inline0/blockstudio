import { select } from '@wordpress/data';
import { isArray } from 'lodash-es';

export const sendEvents = () => {
  const blockTypes = window.blockstudioAdmin.data.blocksNative;

  const getAllNestedBlockNames = () => {
    const getBlockNames = (blocks: any[]) => {
      return blocks.reduce((acc, block) => {
        acc.push(block.name);
        if (block.innerBlocks && block.innerBlocks.length) {
          acc = acc.concat(getBlockNames(block.innerBlocks));
        }
        return acc;
      }, []);
    };

    const topLevelBlocks = select('core/block-editor').getBlocks();
    const allBlockNames = getBlockNames(topLevelBlocks);

    return Array.from(new Set(allBlockNames));
  };

  const blocks = getAllNestedBlockNames();

  blocks.forEach((item: string) => {
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
