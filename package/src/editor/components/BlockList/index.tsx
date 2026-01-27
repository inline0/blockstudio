import { Button } from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';
import { List } from '@/editor/components/List';
import { selectors } from '@/editor/store/selectors';
import { __ } from '@/utils/__';
import { css } from '@/utils/css';

export const BlockList = () => {
  const { setNewInstance } = useDispatch('blockstudio/editor');
  const blocks = useSelect(
    (select) => (select('blockstudio/editor') as typeof selectors).getBlocks(),
    [],
  );
  const paths = useSelect(
    (select) => (select('blockstudio/editor') as typeof selectors).getPaths(),
    [],
  );
  const hasBlocks = Object.values(blocks).length >= 1 || paths.length >= 1;

  return (
    <>
      {hasBlocks ? (
        <List />
      ) : (
        <Button
          css={css({ margin: '0 auto' })}
          variant="primary"
          onClick={() => setNewInstance(true)}
        >
          {__('No blocks found, click here to get started')}
        </Button>
      )}
    </>
  );
};
