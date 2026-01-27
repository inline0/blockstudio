import { useRef } from 'react';
import { css, Global } from '@emotion/react';
import { useSelect, useDispatch } from '@wordpress/data';
import { useEffect, useState } from '@wordpress/element';
import {
  ImperativePanelHandle,
  Panel,
  PanelGroup,
  PanelResizeHandle,
} from 'react-resizable-panels';
import { colors } from '@/admin/const/colors';
import { Toolbar } from '@/editor/components/Editor/Toolbar';
import { useIsBlock } from '@/editor/hooks/useIsBlock';
import { useIsExtension } from '@/editor/hooks/useIsExtension';
import { selectors } from '@/editor/store/selectors';
import { __ } from '@/utils/__';
import { Code } from './Code';
import { Console } from './Console';
import { Files } from './Files';
import { Preview } from './Preview';
import { PreviewAttributes } from './PreviewAttributes';

export const Editor = () => {
  const isPreview = useIsBlock();
  const isExtension = useIsExtension();

  const left = useRef<ImperativePanelHandle>(null);
  const right = useRef<ImperativePanelHandle>(null);
  const [isLoading, setIsLoading] = useState(false);
  const { setIsModalSave } = useDispatch('blockstudio/editor');
  const block = useSelect(
    (select) => (select('blockstudio/editor') as typeof selectors).getBlock(),
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

  const showPreview = isPreview || isExtension;
  const showPreviewPanel =
    (isPreview && path.endsWith('block.json')) ||
    (settings.preview && isExtension);

  const onReset = () => {
    if (left.current && right.current) {
      left.current.resize(50);
      right.current.resize(50);
    }
  };

  useEffect(() => {
    setIsModalSave(false);
  }, []);

  useEffect(() => {
    // console.log('block:', block);
  }, [block]);

  return (
    <div id="blockstudio-editor">
      <Global
        styles={css({
          html: {
            paddingTop: settings.fullscreen && '0',
          },

          body: {
            overflow: 'hidden',
            '--blockstudio-editor-admin-bar':
              settings.fullscreen || isGutenberg ? '0px' : '46px',
            '--blockstudio-editor-toolbar': '92px',
            '--blockstudio-editor-console': settings.console ? '144px' : '0px',
          },

          '@media (min-width: 783px)': {
            body: {
              '--blockstudio-editor-admin-bar':
                settings.fullscreen || isGutenberg ? '0px' : '32px',
            },
          },
        })}
      />
      <div
        css={css({
          position: settings.fullscreen ? 'fixed' : 'static',
          zIndex: settings.fullscreen ? '100000' : '0',
          display: 'grid',
          gridTemplateRows: '60px 32px 1fr',
          background: colors.gray[800],
          width: '100%',
          height: '100%',
          left: '0',
          top: '0',
        })}
      >
        <Toolbar isLoading={isLoading} setIsLoading={setIsLoading} />
        <div
          css={css({
            pointerEvents: isLoading ? 'none' : 'auto',
            display: 'grid',
            gridTemplateColumns: isGutenberg
              ? '1fr'
              : showPreview && settings.files
                ? '300px 1fr'
                : settings.files
                  ? '300px 1fr'
                  : '1fr',
            '@media (max-width: 1023px)': {
              gridTemplateColumns: '1fr',
            },
          })}
        >
          {settings.files && !isGutenberg && (
            <div
              css={css({
                '@media (max-width: 1023px)': {
                  display: showPreview && !isGutenberg ? 'none' : 'block',
                },
              })}
            >
              <Files />
            </div>
          )}
          {isStatic ? (
            <div
              css={css({
                height:
                  'calc(100vh - var(--blockstudio-editor-admin-bar) - var(--blockstudio-editor-toolbar) - var(--blockstudio-editor-console))',
                width: '100%',
                gridColumn: showPreview && !isGutenberg && 'span 2',
                color: '#fff',
              })}
            >
              <div
                css={css({
                  display: 'flex',
                  justifyContent: 'center',
                  alignItems: 'center',
                  height: '100%',
                  width: '100%',
                })}
              >
                {__('Select a block to get started')}
              </div>
              <Console />
            </div>
          ) : (
            <PanelGroup autoSaveId="blockstudioLayout" direction="horizontal">
              <Panel
                ref={left}
                minSize={20}
                css={css({
                  '@media (max-width: 1023px)': {
                    display:
                      (showPreview || settings.files) && !isGutenberg
                        ? 'none !important'
                        : 'block',
                  },
                  '@media (min-width: 1024px)': {
                    flex: !showPreview && 'unset !important',
                    width: !showPreview && '100% !important',
                  },
                })}
              >
                <div>
                  <Code />
                  <Console />
                </div>
              </Panel>
              <PanelResizeHandle
                onDoubleClick={onReset}
                css={css({
                  width: '4px',
                  background: colors.gray[800],
                  transition: 'background 0.2s ease-in-out',
                  transitionDelay: '0.2s',
                  ':hover': {
                    background: colors.primary,
                  },

                  '@media (max-width: 1023px)': {
                    display: 'none',
                  },
                  '@media (min-width: 1024px)': {
                    display: !showPreview && 'none !important',
                  },
                })}
              />
              {!isGutenberg && settings.preview && (
                <Panel
                  ref={right}
                  minSize={20}
                  css={css({
                    '@media (max-width: 1023px)': {
                      display: !showPreview && 'none',
                    },
                  })}
                >
                  {showPreviewPanel ? (
                    <PreviewAttributes />
                  ) : (
                    isPreview && <Preview />
                  )}
                </Panel>
              )}
            </PanelGroup>
          )}
        </div>
      </div>
    </div>
  );
};
