import { BlockstudioEditorBlock } from '@/type/types';

export const getCurrentBlockFileValues = (
  block: BlockstudioEditorBlock,
  files: BlockstudioEditorBlock[],
) => {
  const newFiles = {};
  block.filesPaths.forEach((path: string) => {
    newFiles[path] = files[path]?.value || '';
  });

  return newFiles;
};
