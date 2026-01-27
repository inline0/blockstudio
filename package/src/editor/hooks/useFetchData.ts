import apiFetch from '@wordpress/api-fetch';
import { useDispatch, useSelect } from '@wordpress/data';
import { selectors } from '@/editor/store/selectors';
import { BlockstudioEditorBlock } from '@/type/types';

export const useFetchData = () => {
  const { setBlocks, setFiles, setBlock } = useDispatch('blockstudio/editor');
  const block = useSelect(
    (select) => (select('blockstudio/editor') as typeof selectors).getBlock(),
    [],
  );

  const fetchData = async (cb = (_: object) => {}, b = true) => {
    return apiFetch({
      path: '/blockstudio/v1/data',
      method: 'GET',
    }).then(
      (res: {
        dataSorted: BlockstudioEditorBlock[];
        files: BlockstudioEditorBlock[];
      }) => {
        const { dataSorted, files } = res;
        setBlocks(dataSorted);
        setFiles(files);
        if (b && files?.[block.path]) {
          setBlock(files[block.path]);
        }
        cb(res);
      },
    );
  };

  return { fetchData };
};
