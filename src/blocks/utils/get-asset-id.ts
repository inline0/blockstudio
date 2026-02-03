import { getFilename } from '@/utils/get-filename';
import { BlockstudioEditorBlock } from '@/types/types';

export const getAssetId = (block: BlockstudioEditorBlock, file: string) => {
  return `blockstudio-${block?.name || 'block'}-${(getFilename(file) || '').replaceAll(
    '.',
    '-'
  )}`.replaceAll('/', '-');
};
