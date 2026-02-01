import { getFilename } from '@/utils/getFilename';
import { BlockstudioEditorBlock } from '@/types/types';

export const getAssetId = (block: BlockstudioEditorBlock, file: string) => {
  return `blockstudio-${block?.name || 'block'}-${(getFilename(file) || '').replaceAll(
    '.',
    '-'
  )}`.replaceAll('/', '-');
};
