import apiFetch from '@wordpress/api-fetch';
import { useDispatch, useSelect } from '@wordpress/data';
import { useFetchData } from '@/editor/hooks/useFetchData';
import { selectors } from '@/editor/store/selectors';
import { BlockstudioEditorBlock, BlockstudioRestResponse } from '@/type/types';

export const useRename = () => {
  const { fetchData } = useFetchData();
  const { setConsole, setBlock } = useDispatch('blockstudio/editor');
  const path = useSelect(
    (select) => (select('blockstudio/editor') as typeof selectors).getPath(),
    [],
  );
  const rename = async ({ oldPath, newPath }) => {
    return await apiFetch({
      path: '/blockstudio/v1/editor/file/rename',
      method: 'POST',
      data: {
        oldPath,
        newPath,
      },
    })
      .then(async (res: BlockstudioRestResponse) => {
        await fetchData((d: BlockstudioEditorBlock) => {
          if (path) setBlock(d.files[newPath]);
        });
        setConsole(res.message);
      })
      .catch((err: BlockstudioRestResponse) => {
        setConsole({ text: err.message, type: 'error' });
        throw err;
      });
  };

  return { rename };
};
