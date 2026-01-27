import { useSelect } from '@wordpress/data';
import { selectors } from '@/editor/store/selectors';

export const useGetAllRenderingTemplates = () => {
  const files = useSelect(
    (select) => (select('blockstudio/editor') as typeof selectors).getFiles(),
    [],
  );

  return Object.values(files)
    .filter((file) => file.path.endsWith('.php') || file.path.endsWith('.twig'))
    .map((file) => file.value)
    .join('\n');
};
