import apiFetch from '@wordpress/api-fetch';
import { useDispatch, useSelect } from '@wordpress/data';
import { useFetchData } from '@/editor/hooks/useFetchData';
import { selectors } from '@/editor/store/selectors';
import { BlockstudioRestResponse } from '@/type/types';

export const useCreateFolder = ({ formData, name }) => {
  const { fetchData } = useFetchData();
  const { setConsole } = useDispatch('blockstudio/editor');
  const isImport = useSelect(
    (select) => (select('blockstudio/editor') as typeof selectors).isImport(),
    [],
  );
  const newFolder = useSelect(
    (select) => (select('blockstudio/editor') as typeof selectors).newFolder(),
    [],
  );
  const path = newFolder + '/' + name;

  const create = async (importedFile = null) => {
    return apiFetch({
      path: '/blockstudio/v1/editor/file/create',
      method: 'POST',
      data: {
        files: [
          {
            path,
            folderOnly: true,
          },
        ],
        importedFile,
      },
    })
      .then(async (res: BlockstudioRestResponse) => {
        await fetchData();
        setConsole(res.message);
      })
      .catch((err: BlockstudioRestResponse) => {
        setConsole({
          text: err.message,
          type: 'error',
        });
        throw err;
      });
  };

  const createFolder = async () => {
    if (isImport) {
      return apiFetch({
        path: '/wp/v2/media',
        method: 'POST',
        body: formData,
        headers: {
          'Content-Disposition':
            'attachment; filename="blockstudio-import.zip"',
        },
      }).then((res) => {
        return create(res);
      });
    } else {
      return create();
    }
  };

  return { createFolder };
};
