import { useSelect } from '@wordpress/data';
import { selectors } from '@/editor/store/selectors';
import { isCss } from '@/editor/utils/isCss';

export const useIsBlock = () => {
  const block = useSelect(
    (select) => (select('blockstudio/editor') as typeof selectors).getBlock(),
    [],
  );
  const path = useSelect(
    (select) => (select('blockstudio/editor') as typeof selectors).getPath(),
    [],
  );
  const settings = useSelect(
    (select) =>
      (select('blockstudio/editor') as typeof selectors).getSettings(),
    [],
  );

  return (
    settings?.preview &&
    (path.endsWith('index.php') ||
      path.endsWith('index.twig') ||
      path.endsWith('block.json') ||
      path.endsWith('.js') ||
      isCss(path)) &&
    block?.files?.includes('block.json')
  );
};
