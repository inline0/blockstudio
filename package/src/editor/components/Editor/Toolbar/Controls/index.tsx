import {
  ToolbarButton,
  DropdownMenu,
  MenuGroup,
  MenuItem,
  MenuItemsChoice,
  __experimentalNumberControl as NumberControl,
  __experimentalVStack as VStack,
} from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';
import { useState } from '@wordpress/element';
import {
  color,
  fullscreen,
  gallery,
  listView,
  preformatted,
  pullRight,
  resizeCornerNE,
  shortcode,
  title,
} from '@wordpress/icons';
import { Modal } from '@/editor/components/Modal';
import { SPACING } from '@/editor/const/spacings';
import { MONACO_THEMES } from '@/editor/const/themes';
import { selectors } from '@/editor/store/selectors';
import { __ } from '@/utils/__';
import { css } from '@/utils/css';

export const Controls = () => {
  const [isSizeModal, setIsSizeModal] = useState(false);
  const [size, setSize] = useState(null);
  const [sizeDelete, setSizeDelete] = useState(false);
  const { setSettings, setEditor, formatCode } =
    useDispatch('blockstudio/editor');
  const editor = useSelect(
    (select) => (select('blockstudio/editor') as typeof selectors).getEditor(),
    [],
  );
  const getFormatCode = useSelect(
    (select) =>
      (select('blockstudio/editor') as typeof selectors).getFormatCode(),
    [],
  );
  const isGutenberg = useSelect(
    (select) =>
      (select('blockstudio/editor') as typeof selectors).isGutenberg(),
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
    <>
      <div
        css={css({
          borderRight: '1px solid rgba(0,0,0,0.1)',
          paddingRight: '16px',
          display: 'flex',
          alignItems: 'center',
          height: '100%',
          maxWidth: 'max-content',
        })}
      >
        {settings?.previewSizeSelected !== __('None') && (
          <style>
            {`
            #blockstudio-editor-toolbar-preview-size {
                position: relative;
            }
            #blockstudio-editor-toolbar-preview-size:after,
            #blockstudio-editor-toolbar-preview-size:after {
                content: 'Size ${settings.previewSizeSelected}px';
                display: ${
                  settings.previewSizeSelected &&
                  settings.previewSizeSelected !== __('None')
                    ? 'flex'
                    : 'none'
                };
                position: absolute;
                left: 0;
                top: 0;
                height: 100%;
                width: 100%;
                background: #fff;
                font-size: 9px;
                font-weight: 600;
                align-items: center;
                justify-content: center;
            }
            `}
          </style>
        )}
        {[
          {
            condition: !isGutenberg,
            items: [
              {
                condition: true,
                id: 'blockstudio-editor-toolbar-files',
                icon: listView,
                label: __('Files'),
                onClick: () => {
                  setSettings({ files: !settings.files });
                },
                pressed: settings.files,
              },
              {
                condition: true,
                id: 'blockstudio-editor-toolbar-fullscreen',
                icon: fullscreen,
                label: __('Fullscreen'),
                onClick: () => {
                  setSettings({ fullscreen: !settings.fullscreen });
                },
                pressed: settings.fullscreen,
              },
            ],
          },
          {
            condition: true,
            items: [
              {
                condition: true,
                id: 'blockstudio-editor-toolbar-console',
                icon: shortcode,
                label: __('Console'),
                onClick: () => {
                  setSettings({ console: !settings.console });
                },
                pressed: settings.console,
              },
              {
                condition: true,
                id: 'blockstudio-editor-toolbar-minimap',
                icon: pullRight,
                label: __('Minimap'),
                onClick: () => {
                  setEditor({
                    minimap: {
                      enabled: !editor.minimap.enabled,
                    },
                  });
                },
                pressed: editor.minimap.enabled,
              },
              {
                condition: true,
                id: 'blockstudio-editor-toolbar-fontsize',
                icon: title,
                label: __('Bigger text'),
                onClick: () => {
                  setEditor({
                    fontSize: editor.fontSize === 16 ? 12 : 16,
                    lineHeight: editor.fontSize === 12 ? 24 : 20,
                  });
                },
                pressed: editor.fontSize === 16,
              },
              {
                condition: true,
                id: 'blockstudio-editor-toolbar-theme',
                icon: color,
                label: __('Theme'),
                type: 'choice',
                choices: Object.entries(MONACO_THEMES).map(([k, v]) => {
                  return {
                    value: k,
                    label: v,
                  };
                }),
                default: 'blockstudio',
                key: 'theme',
              },
              {
                condition: true,
                id: 'blockstudio-editor-toolbar-format',
                icon: preformatted,
                label: __('Format'),
                pressed: false,
                onClick: () => formatCode(getFormatCode + 1),
              },
            ],
          },
          {
            condition: !isGutenberg,
            items: [
              {
                condition: true,
                id: 'blockstudio-editor-toolbar-preview',
                icon: gallery,
                label: __('Preview'),
                onClick: () => {
                  setSettings({ preview: !settings.preview });
                },
                pressed: settings.preview,
              },
              {
                condition: settings.preview && !path.endsWith('.json'),
                id: 'blockstudio-editor-toolbar-preview-size',
                icon: resizeCornerNE,
                label: __('Preview size'),
                type: 'previewSize',
                choices: [__('None'), ...(settings?.previewSizes || [])]
                  .map((v) => {
                    return {
                      value: v,
                      label:
                        sizeDelete && v !== __('None')
                          ? `Delete ${v}`
                          : `${v !== __('None') ? v + 'px' : v}`,
                    };
                  })
                  .sort((a, b) => Number(b.value) - Number(a.value))
                  .filter((e) => (sizeDelete ? e.value !== __('None') : e)),
              },
            ],
          },
        ].map((item, index) => {
          return (
            item.condition && (
              <div
                key={`toolbar-${index}`}
                css={css({
                  display: 'grid',
                  gridAutoFlow: 'column',
                  gap: '8px',
                  marginLeft: index !== 0 && '16px',
                  paddingLeft: '16px',
                  borderLeft: '1px solid rgba(0,0,0,0.1)',
                  alignItems: 'center',
                  height: '100%',
                })}
              >
                {item.items.map((itemInner, indexInner) => {
                  return (
                    itemInner.condition && (
                      <div key={`toolbar-${itemInner.label}-${indexInner}`}>
                        {!itemInner?.type ? (
                          <ToolbarButton
                            id={itemInner.id}
                            icon={itemInner.icon}
                            label={itemInner.label}
                            onClick={itemInner.onClick}
                            isPressed={itemInner.pressed}
                          />
                        ) : itemInner.type === 'choice' ? (
                          <DropdownMenu
                            icon={itemInner.icon}
                            label={itemInner.label}
                            toggleProps={{
                              id: itemInner.id,
                            }}
                          >
                            {() => (
                              <MenuGroup>
                                <MenuItemsChoice
                                  choices={itemInner.choices}
                                  value={
                                    settings?.[itemInner.key] ||
                                    itemInner.default
                                  }
                                  onSelect={(value) => {
                                    setSettings({
                                      [itemInner.key]: value,
                                    });
                                  }}
                                  onHover={() => {}}
                                />
                              </MenuGroup>
                            )}
                          </DropdownMenu>
                        ) : (
                          <DropdownMenu
                            icon={itemInner.icon}
                            label={itemInner.label}
                            toggleProps={{
                              id: itemInner.id,
                              onClick: () => {
                                setSizeDelete(false);
                                setSettings({
                                  previewSizeSelected:
                                    !settings?.previewSizes?.length ||
                                    settings?.previewSizeSelected === null ||
                                    (!settings.previewSizes.includes(
                                      settings.previewSizeSelected,
                                    ) &&
                                      !size) ||
                                    (!settings.previewSizes.includes(size) &&
                                      size)
                                      ? __('None')
                                      : settings?.previewSizeSelected ||
                                        size ||
                                        __('None'),
                                });
                              },
                            }}
                          >
                            {({ onClose }) => (
                              <>
                                <MenuGroup>
                                  <MenuItemsChoice
                                    choices={itemInner.choices}
                                    value={
                                      sizeDelete
                                        ? ''
                                        : settings?.previewSizeSelected
                                    }
                                    onHover={() => {}}
                                    onSelect={(value) => {
                                      if (sizeDelete) {
                                        setSettings({
                                          previewSizes:
                                            settings.previewSizes.filter(
                                              (e) => e !== value,
                                            ),
                                          previewSizeSelected: __('None'),
                                        });
                                        if (
                                          settings?.previewSizes?.length === 1
                                        ) {
                                          setSizeDelete(false);
                                          setSettings({
                                            previewSizeSelected: __('None'),
                                          });
                                          onClose();
                                        }
                                      } else {
                                        setSettings({
                                          previewSizeSelected: value,
                                        });
                                      }
                                    }}
                                  />
                                </MenuGroup>
                                <MenuGroup>
                                  {!sizeDelete && (
                                    <MenuItem
                                      id="blockstudio-editor-toolbar-preview-size-add"
                                      onClick={() => {
                                        setSize('');
                                        setIsSizeModal(true);
                                        onClose();
                                      }}
                                    >
                                      {__('Add size')}
                                    </MenuItem>
                                  )}
                                  {settings?.previewSizes?.length ? (
                                    <MenuItem
                                      id="blockstudio-editor-toolbar-preview-size-edit"
                                      onClick={() => {
                                        if (!sizeDelete) {
                                          setSize(settings.previewSizeSelected);
                                        } else {
                                          setSettings({
                                            previewSizeSelected:
                                              !settings.previewSizes.includes(
                                                settings.previewSizeSelected,
                                              ) &&
                                              !settings.previewSizes.includes(
                                                size,
                                              )
                                                ? __('None')
                                                : size,
                                          });
                                        }

                                        setSizeDelete(!sizeDelete);
                                      }}
                                    >
                                      {sizeDelete
                                        ? __('Stop editing')
                                        : __('Edit sizes')}
                                    </MenuItem>
                                  ) : null}
                                </MenuGroup>
                              </>
                            )}
                          </DropdownMenu>
                        )}
                      </div>
                    )
                  );
                })}
              </div>
            )
          );
        })}
      </div>
      <Modal
        show={isSizeModal}
        onRequestClose={() => setIsSizeModal(false)}
        textPrimary={__('Create')}
        onPrimary={() => {
          setSettings({
            previewSizes: [
              ...new Set([...(settings?.previewSizes || []), size]),
            ],
            previewSizeSelected: size,
          });
          setIsSizeModal(false);
          setSize(null);
        }}
        title={__('Create a new size')}
        isValid={
          size !== 0 &&
          size !== null &&
          size !== '' &&
          !settings.previewSizes?.includes(size)
        }
      >
        <VStack spacing={SPACING.INPUT}>
          <NumberControl
            value={size}
            onChange={(value) => setSize(Number(value))}
            label="Width"
            max={9999}
          />
        </VStack>
      </Modal>
    </>
  );
};
