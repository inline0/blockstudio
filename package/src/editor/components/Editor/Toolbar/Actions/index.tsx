import {
  Button,
  DropdownMenu,
  MenuGroup,
  MenuItem,
} from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { useState } from '@wordpress/element';
import { trash, cog, external } from '@wordpress/icons';
import pako from 'pako';
import { ModalDeleteFile } from '@/editor/components/Modal/DeleteFile';
import { selectors } from '@/editor/store/selectors';
import { getFilename } from '@/editor/utils/getFilename';
import { __ } from '@/utils/__';
import { css } from '@/utils/css';

export const Actions = ({
  isLoading,
  onSave,
}: {
  isLoading: boolean;
  onSave: (cb?: () => void) => void;
}) => {
  const [isModalDelete, setIsModalDelete] = useState(false);
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
  const path = useSelect(
    (select) => (select('blockstudio/editor') as typeof selectors).getPath(),
    [],
  );

  const onOpenDemo = () => {
    const obj = {};
    block.filesPaths.forEach((item: string) => {
      const filename = getFilename(item);
      obj[filename] = files[item].value;
      if (filesChanged[item]) {
        obj[filename] = filesChanged[item];
      }
    });

    obj['block.json'] = {
      ...JSON.parse(obj['block.json']),
      name: 'demo/import',
      title: 'Imported Block',
    };

    const jsonString = JSON.stringify(obj);
    const compressed = pako.gzip(jsonString);

    let binary = '';
    const bytes = new Uint8Array(compressed);
    const len = bytes.byteLength;
    for (let i = 0; i < len; i++) {
      binary += String.fromCharCode(bytes[i]);
    }
    const encoded = btoa(binary)
      .replace(/\+/g, '-')
      .replace(/\//g, '_')
      .replace(/=+$/, '');

    window.open('https://blockstudio.dev/demo?data=' + encoded, '_blank');
  };

  return (
    <>
      <div
        css={css({
          marginLeft: 'auto',
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'center',

          '.components-spinner': {
            marginTop: 0,
          },
        })}
      >
        {(!isGutenberg ||
          (isGutenberg &&
            !path.endsWith('block.json') &&
            !path.endsWith('index.php') &&
            !path.endsWith('index.twig'))) && (
          <DropdownMenu
            css={css({
              marginLeft: '12px',
            })}
            icon={cog}
            label="Menu"
            toggleProps={{
              id: 'blockstudio-editor-toolbar-more',
            }}
          >
            {({ onClose }) => (
              <>
                <MenuGroup>
                  <MenuItem icon={external} onClick={onOpenDemo}>
                    {__('Open block in Demo')}
                  </MenuItem>
                </MenuGroup>
                <MenuGroup>
                  <MenuItem
                    icon={trash}
                    isDestructive
                    // @ts-ignore
                    variant="tertiary"
                    onClick={() => {
                      setIsModalDelete(true);
                      onClose();
                    }}
                  >
                    {__(
                      `Delete ${
                        getFilename(path).endsWith('block.json') ||
                        getFilename(path).endsWith('index.php') ||
                        getFilename(path).endsWith('index.twig')
                          ? 'Block'
                          : 'File'
                      }`,
                    )}
                  </MenuItem>
                </MenuGroup>
              </>
            )}
          </DropdownMenu>
        )}
        <Button
          disabled={
            Object.values(filesChanged).filter(
              (item) => (item as unknown as boolean) !== false,
            ).length === 0 || isLoading
          }
          css={css({
            minWidth: '92px',
            marginLeft: '12px',
            marginRight: '16px',
            display: 'flex',
            justifyContent: 'center',
            pointerEvents: isLoading ? 'none' : 'auto',
            userSelect: 'none',
          })}
          variant="primary"
          isBusy={isLoading}
          onClick={() => onSave()}
        >
          {__('Save Block')}
        </Button>
      </div>
      <ModalDeleteFile
        show={isModalDelete}
        {...{ setIsModalDelete, block, path }}
      />
    </>
  );
};
