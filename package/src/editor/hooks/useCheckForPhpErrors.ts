import apiFetch from '@wordpress/api-fetch';
import { useDispatch } from '@wordpress/data';
import { useCollectErrors } from '@/editor/hooks/useCollectErrors';
import { getFilename } from '@/editor/utils/getFilename';

export const useCheckForPhpErrors = () => {
  const { setConsole } = useDispatch('blockstudio/editor');
  const { addError, removeError } = useCollectErrors();

  const check = async (file: { [key: string]: string }) => {
    const fileName = getFilename(file[0]);
    const isIndex = fileName === 'index.php';
    const isInit = fileName.startsWith('init') && fileName.endsWith('.php');
    const addBracket =
      (isInit && !file[1].endsWith('?>')) ||
      (isIndex &&
        file[1].startsWith('<?php') &&
        !/<\?php[\s\S]*\?>/g.test(file[1]));
    const content = addBracket ? `${file[1]}\n?>` : file[1];

    try {
      await apiFetch({
        path: '/blockstudio/v1/editor/block/test',
        method: 'POST',
        data: {
          name: file[0],
          content: encodeURIComponent(content),
        },
      });
      removeError(file[0]);
    } catch (err) {
      setConsole({
        type: 'error',
        text: `There is an error in your ${fileName}`,
      });
      addError(file[0]);
      throw err;
    }
  };

  const checkForPhpErrors = async (
    items: {
      [key: string]: string;
    }[],
  ) => {
    return await Promise.all(
      Object.entries(items)
        .filter(([k]) => k.endsWith('.php') || k.endsWith('.twig'))
        .map((item) => check(item as unknown as { [key: string]: string })),
    );
  };

  return { checkForPhpErrors };
};
