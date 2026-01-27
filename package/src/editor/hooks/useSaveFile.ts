import apiFetch from '@wordpress/api-fetch';
import { useDispatch, useSelect } from '@wordpress/data';
import parserPostCss from 'prettier/esm/parser-postcss.mjs';
import parserTypescript from 'prettier/esm/parser-typescript.mjs';
import prettier from 'prettier/esm/standalone.mjs';
import { useCheckForPhpErrors } from '@/editor/hooks/useCheckForPhpErrors';
import { selectors } from '@/editor/store/selectors';
import { getCurrentBlockFileValues } from '@/editor/utils/getCurrentBlockFileValues';
import { isCss } from '@/editor/utils/isCss';
import { useTailwindSaveEditor } from '@/tailwind/useTailwindSaveEditor';
import { BlockstudioRestResponse } from '@/type/types';

export const useSaveFile = ({ setIsLoading }) => {
  const { checkForPhpErrors } = useCheckForPhpErrors();
  const { formatCode, setFiles, setFilesChanged, setConsole } =
    useDispatch('blockstudio/editor');
  const tailwindSave = useTailwindSaveEditor();
  const block = useSelect(
    (select) => (select('blockstudio/editor') as typeof selectors).getBlock(),
    [],
  );
  const files = useSelect(
    (select) => (select('blockstudio/editor') as typeof selectors).getFiles(),
    [],
  );
  const filesChanged = useSelect(
    (select) =>
      (select('blockstudio/editor') as typeof selectors).getFilesChanged(),
    [],
  );
  const getFormatCode = useSelect(
    (select) =>
      (select('blockstudio/editor') as typeof selectors).getFormatCode(),
    [],
  );
  const isGutenberg = useSelect(
    (select) =>
      (select('blockstudio/editor') as typeof selectors).isGutenberg(),
    [],
  );
  const nextBlock = useSelect(
    (select) =>
      (select('blockstudio/editor') as typeof selectors).getNextBlock(),
    [],
  );
  const options = useSelect(
    (select) => (select('blockstudio/editor') as typeof selectors).getOptions(),
    [],
  );

  const formatOnSave = options?.editor?.formatOnSave;

  const saveFile = (cb: () => void) => {
    setIsLoading(true);

    const save = async () => {
      let renderingTemplate = '';
      let isJsonValid = true;

      Object.entries(filesChanged).forEach(([k, v]) => {
        if (k.endsWith('block.json')) {
          try {
            const blockJson = JSON.parse(v as unknown as string);
            if (!blockJson.blockstudio) setIsLoading(false);
          } catch (e) {
            setConsole({
              type: 'error',
              text: 'There is an error in your block.json',
            });
            isJsonValid = false;
            setIsLoading(false);
          }
        }
      });
      if (!isJsonValid) return;

      let formattedFilesChanged = {};
      if (formatOnSave) {
        formattedFilesChanged = {
          ...filesChanged,
        };
      }
      for (const [k, v] of Object.entries(filesChanged)) {
        if (formatOnSave) {
          if (isCss(k) || k.endsWith('.js')) {
            const newValue = prettier.format(v, {
              parser: k.endsWith('.js') ? 'typescript' : 'css',
              plugins: [k.endsWith('.js') ? parserTypescript : parserPostCss],
            });
            formattedFilesChanged = {
              ...formattedFilesChanged,
              [k]: newValue,
            };
          }
        }
        if (k.endsWith('.php') || k.endsWith('.twig')) {
          renderingTemplate = v as unknown as string;
        }
      }

      const changedFiles = formatOnSave ? formattedFilesChanged : filesChanged;

      await apiFetch({
        path: '/blockstudio/v1/editor/block/save',
        method: 'POST',
        data: {
          block,
          files: changedFiles,
          folder: block.file.dirname,
        },
      })
        .then((res: BlockstudioRestResponse) => {
          let obj = { ...files };
          Object.entries(changedFiles).forEach(([k, v]) => {
            obj = {
              ...obj,
              ...{
                [k]: {
                  ...obj[k],
                  value: v,
                },
              },
            };
          });
          setFiles(obj);
          setFilesChanged({});
          if (formatOnSave && !nextBlock) formatCode(getFormatCode + 1);
          setIsLoading(false);
          if (cb) {
            cb();
          }
          setConsole(res.message);

          if (isGutenberg) {
            window.parent.postMessage(
              {
                files: getCurrentBlockFileValues(block, files),
              },
              '*',
            );
          }
        })
        .catch(() => {
          setIsLoading(false);
        });

      if (renderingTemplate) await tailwindSave(renderingTemplate);
    };

    if (
      Object.entries(filesChanged).filter(
        ([k, v]) =>
          (k.endsWith('.php') || k.endsWith('.twig')) &&
          (v as unknown as boolean) !== false,
      ).length === 0
    ) {
      save();
    } else {
      checkForPhpErrors(filesChanged)
        .then(() => save())
        .catch(() => setIsLoading(false));
    }
  };

  return { saveFile };
};
