import { useDispatch, useSelect } from '@wordpress/data';
import { useNavigate } from 'react-router-dom';
import { selectors } from '@/editor/store/selectors';
import { getFilenameUrl } from '@/editor/utils/getFilenameUrl';
import { BlockstudioEditorBlock } from '@/type/types';

export const useOpen = () => {
  const navigate = useNavigate();

  const { setBlock, setPath, openEditor, setIsModalSave, setNextBlock } =
    useDispatch('blockstudio/editor');
  const block = useSelect(
    (select) => (select('blockstudio/editor') as typeof selectors).getBlock(),
    [],
  );
  const filesChanged = useSelect(
    (select) =>
      (select('blockstudio/editor') as typeof selectors).getFilesChanged(),
    [],
  );
  const isEditor = useSelect(
    (select) => (select('blockstudio/editor') as typeof selectors).isEditor(),
    [],
  );

  return (item: BlockstudioEditorBlock) => {
    const b = (
      item as unknown as {
        block: BlockstudioEditorBlock;
      }
    )?.block
      ? (
          item as unknown as {
            block: BlockstudioEditorBlock;
          }
        ).block
      : item;
    if (!b?.path) return;

    const isFile = b.path !== block.path;

    if (
      Object.values(filesChanged).length >= 1 &&
      Object.keys(filesChanged).filter((value) => b.filesPaths.includes(value))
        .length === 0 &&
      isEditor
    ) {
      setIsModalSave(true);
      setNextBlock({ block: b, path: isFile ? item.path : false });
      return;
    }

    if (block?.file?.dirname !== b?.file?.dirname) setBlock(item);
    setPath(item.path);
    if (!isEditor) openEditor(b);
    navigate(getFilenameUrl(item.path), {
      replace: true,
    });
  };
};
