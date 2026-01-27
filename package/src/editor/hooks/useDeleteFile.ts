import apiFetch from '@wordpress/api-fetch';
import { useDispatch, useSelect } from '@wordpress/data';
import { getAssetId } from '@/blocks/utils/getAssetId';
import { useFetchData } from '@/editor/hooks/useFetchData';
import { selectors } from '@/editor/store/selectors';
import { BlockstudioEditorBlock, BlockstudioRestResponse } from '@/type/types';
import { __ } from '@/utils/__';

export const useDeleteFile = ({ block, path }) => {
  const { fetchData } = useFetchData();
  const { setIsStatic, setConsole, setBlock, setFilesChanged } =
    useDispatch('blockstudio/editor');

  const filePath = useSelect(
    (select) => (select('blockstudio/editor') as typeof selectors).getPath(),
    [],
  );
  const isGutenberg = useSelect(
    (select) =>
      (select('blockstudio/editor') as typeof selectors).isGutenberg(),
    [],
  );

  const deleteFile = async () => {
    const isBlock =
      path.endsWith('block.json') ||
      path.endsWith('index.php') ||
      path.endsWith('index.twig');
    const isDir = block.directory;

    let paths: string[];
    if (isBlock) {
      paths = block.filesPaths;
    } else if (isDir) {
      paths = [block.file.dirname];
    } else {
      paths = [path];
    }

    return apiFetch({
      path: '/blockstudio/v1/editor/file/delete',
      method: 'POST',
      data: {
        files: paths,
      },
    })
      .then(async (res: BlockstudioRestResponse) => {
        return await fetchData((d: BlockstudioEditorBlock) => {
          let message: { text: string; type: string } | string = res.message;
          if (isDir) {
            if (Object.keys(d.files).includes(`${paths[0]}/.`)) {
              message = {
                text: __("Folder is not empty and couldn't be deleted"),
                type: 'error',
              };
            }
          }

          setConsole(message);

          if (isGutenberg) {
            setBlock(d.files[block.filesPaths[1]]);
            window.parent.postMessage(
              {
                deletedFile: `#${getAssetId(block, paths[0])}`,
              },
              '*',
            );
            return;
          }

          if (d.files[filePath]) {
            setBlock(d.files[filePath]);
            return;
          }

          if (!d.files[path] && d.files[`${block.file.dirname}/block.json`]) {
            setBlock(d.files[`${block.file.dirname}/block.json`]);
            return;
          }

          if (isBlock || !d.files[path]) {
            setFilesChanged({});
            setIsStatic(true);
          }
        }, false);
      })
      .catch((err: BlockstudioRestResponse) => {
        setConsole({
          text: err.message,
          type: 'error',
        });
        throw err;
      });
  };

  return { deleteFile };
};
