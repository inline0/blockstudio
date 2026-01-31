import { getFilename } from '@/utils/getFilename';
import { BlockstudioEditorBlock } from '@/type/types';

export const getAssetId = (block: BlockstudioEditorBlock, file: string) => {
  return `blockstudio-${block.name}-${getFilename(file).replaceAll(
    '.',
    '-'
  )}`.replaceAll('/', '-');
};
