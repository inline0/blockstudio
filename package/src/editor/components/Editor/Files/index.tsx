import { useSelect } from '@wordpress/data';
import { colors } from '@/admin/const/colors';
import { Table } from '@/editor/components/List/Table';
import { selectors } from '@/editor/store/selectors';
import { css } from '@/utils/css';

export const Files = () => {
  const blocks = useSelect(
    (select) => (select('blockstudio/editor') as typeof selectors).getBlocks(),
    [],
  );
  const settings = useSelect(
    (select) =>
      (select('blockstudio/editor') as typeof selectors).getSettings(),
    [],
  );

  return (
    <div
      id="blockstudio-editor-files"
      css={css({
        marginTop: settings.files && '-32px',
        position: 'relative',
        background: colors.gray[700],
        boxShadow: `inset 1px 0 0 0 ${colors.gray[600]}`,
        height: settings.files
          ? 'calc(100vh - var(--blockstudio-editor-admin-bar) - var(--blockstudio-editor-toolbar) + 32px)'
          : 'calc(100vh - var(--blockstudio-editor-admin-bar) - var(--blockstudio-editor-toolbar))',
        '&:after': {
          content: '""',
          position: 'absolute',
          right: '1px',
          bottom: 0,
          height: '12px',
          width: '12px',
          background: colors.gray[700],
        },
      })}
    >
      <Table blocks={blocks} />
    </div>
  );
};
