import apiFetch from '@wordpress/api-fetch';
import { useDispatch, useSelect } from '@wordpress/data';
import { useNavigate } from 'react-router-dom';
import { useFetchData } from '@/editor/hooks/useFetchData';
import { useIsBlock } from '@/editor/hooks/useIsBlock';
import { selectors } from '@/editor/store/selectors';
import { getFilenameUrl } from '@/editor/utils/getFilenameUrl';
import { BlockstudioEditorBlock, BlockstudioRestResponse } from '@/type/types';

export const useCreateBlock = ({ file, isTwig, name, setIsLoading }) => {
  const { fetchData } = useFetchData();
  const navigate = useNavigate();
  const isBlock = useIsBlock();

  const { setNewBlock, openEditor, setConsole, setPath, setPreview } =
    useDispatch('blockstudio/editor');
  const newBlock = useSelect(
    (select) => (select('blockstudio/editor') as typeof selectors).newBlock(),
    [],
  );

  const createBlock = async () => {
    const ext = isTwig ? '.twig' : '.php';
    const path = `${newBlock}/index${ext}`;

    const nativeContent = `<h1>Hello world!</h1>`;
    const nativeJson = () => {
      return {
        $schema: 'https://app.blockstudio.dev/schema',
        apiVersion: 2,
        name: file,
        title: name,
        category: 'blockstudio',
        icon: 'star-filled',
        blockstudio: true,
      };
    };

    setIsLoading(true);
    return apiFetch({
      path: '/blockstudio/v1/editor/file/create',
      method: 'POST',
      data: {
        files: [
          {
            path: `${newBlock}/block.json`,
            content: JSON.stringify(nativeJson(), null, 3),
          },
          {
            path,
            content: nativeContent,
          },
        ],
      },
    })
      .then(async (res: BlockstudioRestResponse) => {
        await fetchData((d: BlockstudioEditorBlock) => {
          setIsLoading(false);
          setNewBlock(false);
          openEditor(d.files[path]);
          setPath(path);

          if (isBlock) {
            setPreview();
            navigate(getFilenameUrl(path), {
              replace: true,
            });
          }

          setConsole(res.message);
        });
      })
      .catch((err: BlockstudioRestResponse) => {
        setConsole({ text: err.message, type: 'error' });
        setIsLoading(false);
        throw err;
      });
  };

  return { createBlock };
};
