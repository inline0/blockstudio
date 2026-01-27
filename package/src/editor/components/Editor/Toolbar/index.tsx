import { __experimentalText as Text, Button } from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';
import { useEffect } from '@wordpress/element';
import { Icon, closeSmall } from '@wordpress/icons';
import { Modal } from '@/editor/components/Modal';
import { useSaveFile } from '@/editor/hooks/useSaveFile';
import { selectors } from '@/editor/store/selectors';
import { __ } from '@/utils/__';
import { css } from '@/utils/css';
import { Actions } from './Actions';
import { Controls } from './Controls';
import { Tabs } from './Tabs';

export const Toolbar = ({
  isLoading,
  setIsLoading,
}: {
  isLoading: boolean;
  setIsLoading: (_?: boolean) => void;
}) => {
  const { saveFile } = useSaveFile({ setIsLoading });
  const {
    closeEditor,
    setBlock,
    setBlockResets,
    setFilesChanged,
    setIsModalSave,
    setNextBlock,
    setPath,
  } = useDispatch('blockstudio/editor');
  const block = useSelect(
    (select) => (select('blockstudio/editor') as typeof selectors).getBlock(),
    []
  );
  const filesChanged = useSelect(
    (select) =>
      (select('blockstudio/editor') as typeof selectors).getFilesChanged(),
    []
  );
  const isGutenberg = useSelect(
    (select) =>
      (select('blockstudio/editor') as typeof selectors).isGutenberg(),
    []
  );
  const isModalSave = useSelect(
    (select) =>
      (select('blockstudio/editor') as typeof selectors).isModalSave(),
    []
  );
  const isStatic = useSelect(
    (select) => (select('blockstudio/editor') as typeof selectors).isStatic(),
    []
  );
  const nextBlock = useSelect(
    (select) =>
      (select('blockstudio/editor') as typeof selectors).getNextBlock(),
    []
  );

  useEffect(() => {
    const save = (e: KeyboardEvent) => {
      if (
        e.key === 's' &&
        (navigator.userAgent.indexOf('Mac OS X') !== -1 ? e.metaKey : e.ctrlKey)
      ) {
        e.preventDefault();
        setTimeout(() => {
          (
            document.querySelector(
              '.components-button.is-primary'
            ) as HTMLButtonElement
          ).click();
        }, 1000);
      }
    };

    document.addEventListener('keydown', save);

    return () => {
      document.removeEventListener('keydown', save);
    };
  }, []);

  return (
    <>
      <div
        id="blockstudio-editor-toolbar"
        css={css({
          background: '#fff',
          height: '100%',

          '@media (max-width: 1023px)': {
            overflowX: 'auto',

            '.components-tooltip': {
              display: 'none',
            },
          },

          '&::-webkit-scrollbar': {
            display: 'none',
          },
        })}
      >
        <div
          css={css({
            paddingLeft: '16px',
            height: '100%',
            display: 'grid',
            gridAutoFlow: 'column',
            gridTemplateColumns: '283px auto auto',
            alignItems: 'center',
          })}
        >
          <div
            css={css({
              display: 'flex',
              alignItems: 'center',
              height: '100%',
            })}
          >
            {!isGutenberg && (
              <Button
                id="blockstudio-editor-toolbar-exit"
                onClick={() => {
                  if (
                    Object.values(filesChanged).filter(
                      (item) => (item as unknown as boolean) !== false
                    ).length === 0
                  ) {
                    closeEditor();
                  } else {
                    setIsModalSave(true);
                    setNextBlock('');
                  }
                }}
                css={css({
                  marginRight: '16px',
                  padding: '6px',
                })}
                variant="secondary"
              >
                <Icon icon={closeSmall} size={24} />
              </Button>
            )}
            <div
              css={css({
                width: '200px',
                display: 'flex',
                flexDirection: 'column',
              })}
            >
              {isStatic ? null : (
                <>
                  <Text
                    size="sm"
                    weight={500}
                    css={css({
                      overflow: 'hidden',
                      textOverflow: 'ellipsis',
                      width: '100%',
                      whiteSpace: 'nowrap',
                    })}
                    title={block.instance}
                  >
                    {block.instance}
                  </Text>
                  <Text
                    size="sm"
                    weight={500}
                    variant="muted"
                    title={block.path.split(block.instance)[1]}
                    css={css({
                      overflow: 'hidden',
                      textOverflow: 'ellipsis',
                      width: '100%',
                      whiteSpace: 'nowrap',
                    })}
                  >
                    {block.path.split(block.instance)[1]}
                  </Text>
                </>
              )}
            </div>
          </div>
          <Controls />
          <Actions isLoading={isLoading} onSave={saveFile} />
        </div>
      </div>
      <Tabs isLoading={isLoading} onSave={(cb) => saveFile(cb)} />
      <Modal
        show={isModalSave}
        onRequestClose={() => setIsModalSave(false)}
        onSecondary={() => {
          setFilesChanged({});
          setBlockResets([]);
          if (nextBlock !== '' && typeof nextBlock !== 'string') {
            setBlock(nextBlock.block);
            if (nextBlock.path) {
              setPath(nextBlock.path);
            }
            setIsModalSave(false);
          } else {
            closeEditor();
          }
        }}
        onPrimary={() =>
          saveFile(() => {
            if (nextBlock !== '' && typeof nextBlock !== 'string') {
              setBlock(nextBlock.block);
              if (nextBlock.path) {
                setPath(nextBlock.path);
              }
              setNextBlock(false);
              setIsModalSave(false);
            } else {
              closeEditor();
            }
            setBlockResets([]);
          })
        }
        textPrimary={__('Save')}
      />
    </>
  );
};
