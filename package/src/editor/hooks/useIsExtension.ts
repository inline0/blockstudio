import { useSelect } from '@wordpress/data';
import { useEffect, useState } from '@wordpress/element';
import { selectors } from '@/editor/store/selectors';

export const useIsExtension = () => {
  const block = useSelect(
    (select) => (select('blockstudio/editor') as typeof selectors).getBlock(),
    [],
  );
  const path = useSelect(
    (select) => (select('blockstudio/editor') as typeof selectors).getPath(),
    [],
  );
  const [isExtension, setIsExtension] = useState(false);

  useEffect(() => {
    try {
      const data = JSON.parse(block.value);
      if (data?.blockstudio?.extend && path.endsWith('.json')) {
        setIsExtension(true);
      }
    } catch (e) {
      setIsExtension(false);
    }
  }, [path]);

  return isExtension;
};
