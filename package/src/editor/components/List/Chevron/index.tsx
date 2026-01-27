import { useSelect } from '@wordpress/data';
import { chevronDown, Icon } from '@wordpress/icons';
import { colors } from '@/admin/const/colors';
import { selectors } from '@/editor/store/selectors';
import { css } from '@/utils/css';

export const Chevron = ({ path }: { path: string }) => {
  const isEditor = useSelect(
    (select) => (select('blockstudio/editor') as typeof selectors).isEditor(),
    [],
  );
  const tree = useSelect(
    (select) => (select('blockstudio/editor') as typeof selectors).getTree(),
    [],
  );
  const treeOpen = useSelect(
    (select) => (select('blockstudio/editor') as typeof selectors).isTreeOpen(),
    [],
  );

  return (
    <span
      css={css({
        height: '18px',
        width: '18px',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        transform:
          treeOpen || tree.includes(path) ? 'rotate(0deg)' : 'rotate(-90deg)',
      })}
    >
      <Icon
        css={css({
          position: 'relative',
          fill: isEditor && colors.gray[300],
          height: '18px',
          width: '18px',
        })}
        icon={chevronDown}
      />
    </span>
  );
};
