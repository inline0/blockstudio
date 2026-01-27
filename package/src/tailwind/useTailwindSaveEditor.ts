import apiFetch from '@wordpress/api-fetch';
import { useDispatch, useSelect } from '@wordpress/data';
import { useGetAllRenderingTemplates } from '@/editor/hooks/useGetAllRenderingTemplates';
import { selectors } from '@/editor/store/selectors';
import { useTailwindCompile } from '@/tailwind/useTailwindCompile';
import { BlockstudioRestResponse } from '@/type/types';

export const useTailwindSaveEditor = () => {
  const blockstudio = useSelect(
    (select) =>
      (select('blockstudio/editor') as typeof selectors)?.getBlockstudio(),
    [],
  );
  const compile = useTailwindCompile({});
  const allRenderingTemplates = useGetAllRenderingTemplates();
  const { setConsole } = useDispatch('blockstudio/editor');

  return async (additional = '') => {
    if (!blockstudio.options.tailwind?.enabled) return;

    const tailwind = await compile(allRenderingTemplates + additional);
    await apiFetch({
      path: '/blockstudio/v1/editor/tailwind/save',
      method: 'POST',
      data: {
        content: tailwind,
      },
    }).then((res: BlockstudioRestResponse) => {
      setConsole(res.message);
    });
  };
};
