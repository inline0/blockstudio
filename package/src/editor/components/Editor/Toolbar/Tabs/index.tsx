import { Button } from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';
import { useState } from '@wordpress/element';
import { addCard } from '@wordpress/icons';
import { colors } from '@/admin/const/colors';
import { Tab } from '@/editor/components/Editor/Toolbar/Tab';
import { Modal } from '@/editor/components/Modal';
import { selectors } from '@/editor/store/selectors';
import { __ } from '@/utils/__';
import { css } from '@/utils/css';

export const Tabs = ({
  isLoading,
  onSave,
}: {
  isLoading: boolean;
  onSave: (_: () => void) => void;
}) => {
  const [isModal, setIsModal] = useState(false);
  const { setBlock, setFilesChanged, setNewFile } =
    useDispatch('blockstudio/editor');
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
  const isGutenberg = useSelect(
    (select) =>
      (select('blockstudio/editor') as typeof selectors).isGutenberg(),
    [],
  );
  const isStatic = useSelect(
    (select) => (select('blockstudio/editor') as typeof selectors).isStatic(),
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

  const set = (item = false) => {
    setBlock(files[`${block.file.dirname}/${item}`]);
    setIsModal(false);
  };

  return (
    <>
      <div
        id="blockstudio-editor-tabs"
        css={css({
          pointerEvents: isLoading ? 'none' : 'auto',
          height: '32px',
          display: 'flex',
          background: colors.gray[700],
          alignItems: 'center',
          overflowX: 'auto',
          boxShadow:
            !settings.fullscreen && !settings.files
              ? `inset 1px -1px 0 0 ${colors.gray[600]}`
              : `inset 0 -1px 0 0 ${colors.gray[600]}`,

          '@media (min-width: 1024px)': {
            marginLeft: settings.files && !isGutenberg && '300px',
            padding: '0 16px 0 0',
          },

          '&::-webkit-scrollbar': {
            display: 'none',
          },
        })}
      >
        {!isStatic && (
          <>
            {files[path]?.files
              ?.map((e: string) => block.file.dirname + '/' + e)
              ?.map((item: string) => {
                return <Tab key={`tab-${item}`} {...{ item }} />;
              })}
            <Button
              onClick={() => setNewFile(block.file.dirname)}
              css={css({
                marginLeft: '8px',
                display: 'flex',
                color: '#fff',
                '&:active': {
                  background: 'transparent',
                },
              })}
              icon={addCard}
              size="small"
              variant="tertiary"
              label={__('Add file')}
            />
          </>
        )}
      </div>
      <Modal
        show={isModal}
        onRequestClose={() => setIsModal(false)}
        onSecondary={() => {
          // @ts-ignore
          const { [block.path]: value, ...newObj } = filesChanged;
          setFilesChanged(newObj);
          set();
        }}
        onPrimary={() => {
          onSave(() => set());
        }}
      />
    </>
  );
};
