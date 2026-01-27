import { MenuGroup, MenuItem } from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { useEffect, useRef, useState } from '@wordpress/element';
import {
  archive,
  blockDefault,
  download,
  file,
  plus,
  trash,
  textColor,
} from '@wordpress/icons';
import { useOnClickOutside } from 'usehooks-ts';
import { ModalDeleteFile } from '@/editor/components/Modal/DeleteFile';
import { ModalNewFile } from '@/editor/components/Modal/NewFile';
import { ModalNewFolder } from '@/editor/components/Modal/NewFolder';
import { useZip } from '@/editor/hooks/useZip';
import { selectors } from '@/editor/store/selectors';
import { getFilename } from '@/editor/utils/getFilename';
import { removeFilename } from '@/editor/utils/removeFilename';
import { __ } from '@/utils/__';
import { css } from '@/utils/css';

export const ContextMenu = () => {
  const { zip } = useZip();
  const [isModalDelete, setIsModalDelete] = useState(false);
  const {
    setContextMenu,
    setIsImport,
    setNewBlock,
    setNewFile,
    setNewFolder,
    setRename,
  } = useDispatch('blockstudio/editor');
  const contextMenu = useSelect(
    (select) =>
      (select('blockstudio/editor') as typeof selectors).getContextMenu(),
    [],
  );
  const files = useSelect(
    (select) => (select('blockstudio/editor') as typeof selectors).getFiles(),
    [],
  );

  const ref = useRef();
  useOnClickOutside(ref, () => setContextMenu({ x: 0, y: 0 }));

  useEffect(() => {
    const scroll = () => setContextMenu({ x: 0, y: 0 });
    window.addEventListener('scroll', scroll);

    return () => window.removeEventListener('scroll', scroll);
  }, []);

  const isBottomHalf = () => {
    const { y } = contextMenu;
    const windowHeight = window.innerHeight;
    return y > windowHeight / 2;
  };

  const onExport = async () => {
    await zip(contextMenu.path).then(() => setContextMenu({ x: 0, y: 0 }));
  };

  return (
    <>
      {contextMenu?.x !== 0 && contextMenu?.y !== 0 && !isModalDelete && (
        <div
          ref={ref}
          css={css({
            position: 'fixed',
            top: 0,
            left: 0,
            transform: `translate(calc(${contextMenu.x}px - ${
              contextMenu?.right ? '100%' : '0px'
            }), calc(${contextMenu.y}px - ${isBottomHalf() ? '100%' : '0px'}))`,
            zIndex: 999999,
            display: 'block',
            '.is-tertiary:not(.is-destructive)': {
              color: 'var(--wp-components-color-foreground, #1e1e1e)',
            },
          })}
          className={`components-dropdown components-dropdown-menu`}
        >
          <div
            className={`components-popover components-dropdown__content components-dropdown-menu__popover`}
          >
            <div
              className={`components-dropdown-menu__menu components-popover__content`}
            >
              <MenuGroup>
                {!files[`${contextMenu.path}/block.json`] && (
                  <MenuItem
                    icon={blockDefault}
                    onClick={() =>
                      setNewBlock(removeFilename(contextMenu.path))
                    }
                  >
                    {__('New block')}
                  </MenuItem>
                )}
                <MenuItem
                  icon={plus}
                  onClick={() => {
                    setNewFile(removeFilename(contextMenu.path));
                  }}
                >
                  {__('New file')}
                </MenuItem>
                <MenuItem
                  icon={file}
                  onClick={() => {
                    setNewFolder(removeFilename(contextMenu.path));
                  }}
                >
                  {__('New folder')}
                </MenuItem>
                {contextMenu?.type && (
                  <MenuItem
                    icon={textColor}
                    onClick={() => {
                      setRename(contextMenu.path);
                    }}
                  >
                    {__('Rename')}
                  </MenuItem>
                )}
                {/* <MenuItem icon={moveTo} onClick={() => setMove(contextMenu.path);}>{__('Move')}</MenuItem> */}
              </MenuGroup>
              <MenuGroup>
                <MenuItem
                  icon={archive}
                  onClick={() => {
                    setNewFolder(contextMenu.path);
                    setIsImport(true);
                  }}
                >
                  {__('Import')}
                </MenuItem>
                <MenuItem icon={download} onClick={onExport}>
                  {__('Export')}
                </MenuItem>
              </MenuGroup>
              {contextMenu.allowDelete && (
                <MenuGroup>
                  <MenuItem
                    icon={trash}
                    isDestructive
                    onClick={() => setIsModalDelete(true)}
                  >
                    {__(
                      `Delete ${
                        getFilename(contextMenu.path).endsWith('block.json') ||
                        getFilename(contextMenu.path).endsWith('index.php') ||
                        getFilename(contextMenu.path).endsWith('index.twig')
                          ? 'Block'
                          : contextMenu.type
                      }`,
                    )}
                  </MenuItem>
                </MenuGroup>
              )}
            </div>
          </div>
        </div>
      )}
      <ModalNewFolder />
      <ModalNewFile />
      <ModalDeleteFile
        {...{ setIsModalDelete }}
        block={contextMenu.block}
        path={contextMenu.path}
        show={isModalDelete && contextMenu.allowDelete}
      />
    </>
  );
};
