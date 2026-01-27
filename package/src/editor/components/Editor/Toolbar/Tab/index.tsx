import { KeyboardEvent } from 'react';
import { __experimentalText as Text } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { colors } from '@/admin/const/colors';
import { Type } from '@/editor/components/Editor/Type';
import { useOpen } from '@/editor/hooks/useOpen';
import { selectors } from '@/editor/store/selectors';
import { getFilename } from '@/editor/utils/getFilename';
import { css } from '@/utils/css';

export const Tab = ({ item }: { item: string }) => {
  const open = useOpen();
  const errors = useSelect(
    (select) => (select('blockstudio/editor') as typeof selectors).getErrors(),
    [],
  );
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
  const path = useSelect(
    (select) => (select('blockstudio/editor') as typeof selectors).getPath(),
    [],
  );

  const hasChanged = filesChanged[item] || filesChanged[item] === '';

  const handleOpenFile = () => open(files[item]);

  const handleKeyDown = (event: KeyboardEvent) => {
    if (event.key === 'Enter' || event.key === ' ') {
      handleOpenFile();
      event.preventDefault();
    }
  };

  return (
    <div
      id={`blockstudio-editor-tab-${getFilename(item).replace('.', '-')}`}
      tabIndex={path === item ? -1 : 0}
      role="button"
      key={`tab-${item}`}
      onClick={handleOpenFile}
      onKeyDown={handleKeyDown}
      css={css({
        height: '100%',
        padding: item === block.path ? '0 8px 0 8px' : '0 8px',
        display: 'flex',
        justifyContent: 'center',
        alignItems: 'center',
        background: path === item ? '#283135' : 'transparent',
        cursor: 'pointer',
        pointerEvents: path === item ? 'none' : 'auto',
        boxShadow: path === item ? `inset 0 -2px 0 0 ${colors.highlight}` : '',
        '&:hover': {
          background: path !== item && 'rgba(255,255,255,0.05)',
        },
        '&:focus': {
          boxShadow: `inset 0 0 0 var(--wp-admin-border-width-focus) ${colors.highlight}`,
        },
      })}
    >
      <Type lang={item} />
      <Text
        weight={500}
        color={
          errors.includes(item) && hasChanged
            ? colors.error
            : hasChanged
              ? colors.changed
              : '#fff'
        }
        css={css({
          whiteSpace: 'nowrap',
          fontSize: '11px',
        })}
      >
        {getFilename(item)}
      </Text>
    </div>
  );
};
