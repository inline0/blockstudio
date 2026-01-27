import apiFetch from '@wordpress/api-fetch';
import { useDispatch, useSelect } from '@wordpress/data';
import { useFetchData } from '@/editor/hooks/useFetchData';
import { selectors } from '@/editor/store/selectors';
import { getCurrentBlockFileValues } from '@/editor/utils/getCurrentBlockFileValues';
import { BlockstudioEditorBlock, BlockstudioRestResponse } from '@/type/types';

export const useCreateFile = () => {
  const { fetchData } = useFetchData();
  const { setBlock, setConsole } = useDispatch('blockstudio/editor');

  const block = useSelect(
    (select) => (select('blockstudio/editor') as typeof selectors).getBlock(),
    [],
  );
  const isGutenberg = useSelect(
    (select) =>
      (select('blockstudio/editor') as typeof selectors).isGutenberg(),
    [],
  );

  const createFile = async (file: string, absolute = false) => {
    const path = absolute ? file : `${block.file.dirname}/${file}`;
    await apiFetch({
      path: '/blockstudio/v1/editor/file/create',
      method: 'POST',
      data: {
        files: [{ path }],
      },
    }).then(async (res: BlockstudioRestResponse) => {
      await fetchData((d: { files: BlockstudioEditorBlock[] }) => {
        setBlock(d.files[path]);
        setConsole(res.message);

        if (isGutenberg) {
          window.parent.postMessage(
            {
              files: getCurrentBlockFileValues(d.files[path], d.files),
            },
            '*',
          );
        }
      });
    });
  };

  return { createFile };
};
