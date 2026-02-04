import { BlockstudioEditorBlock } from '@/types/types';
import { getFilename } from '@/utils/get-filename';

export const getAssetId = (block: BlockstudioEditorBlock, file: string) => {
  return `blockstudio-${block?.name || 'block'}-${(
    getFilename(file) || ''
  ).replaceAll('.', '-')}`.replaceAll('/', '-');
};
