import { BlockstudioEditorFileStructure } from '@/type/types';

export const deepSearch = (
  blocks: BlockstudioEditorFileStructure,
  folders: string | string[],
  input = '',
  key = 'structure',
) => {
  const result = [];

  const searcher = (blocks: BlockstudioEditorFileStructure) => {
    Object.entries(blocks).forEach(([k, v]) => {
      if (folders.includes(k)) {
        searcher(blocks[k]);
      } else if (k === key && (v as string).includes(input)) {
        result.push(v);
      }
    });
  };

  searcher(blocks);

  return result;
};
