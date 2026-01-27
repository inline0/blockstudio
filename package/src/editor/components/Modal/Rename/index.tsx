import { SetStateAction } from 'react';
import {
  __experimentalText as Text,
  __experimentalVStack as VStack,
  TextControl,
} from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { useState } from '@wordpress/element';
import { Modal } from '@/editor/components/Modal';
import { SPACING } from '@/editor/const/spacings';
import { useRename } from '@/editor/hooks/useRename';
import { selectors } from '@/editor/store/selectors';
import { getFilename } from '@/editor/utils/getFilename';
import { __ } from '@/utils/__';

export const ModalRename = () => {
  const { rename } = useRename();
  const [isLoading, setIsLoading] = useState(false);
  const [isUnique, setIsUnique] = useState(true);
  const [name, setName] = useState('');
  const [newPath, setNewPath] = useState('');

  const { setRename } = useDispatch('blockstudio/editor');
  const files = useSelect(
    (select) => (select('blockstudio/editor') as typeof selectors).getFiles(),
    [],
  );
  const oldPath = useSelect(
    (select) => (select('blockstudio/editor') as typeof selectors).getRename(),
    [],
  );

  const isFolder = files?.[oldPath + '/.'];
  const type = isFolder ? 'Folder' : 'File';

  const onClose = () => {
    setIsLoading(false);
    setIsUnique(true);
    setName('');
    setRename('');
  };

  const onType = (e: SetStateAction<string>) => {
    const newP = `${oldPath.replace(/\/[^/]*$/, '')}/${e}`;
    setName(e);
    setNewPath(newP);

    if (
      isFolder
        ? Object.keys(files).includes(`${newP}/.`)
        : Object.keys(files).includes(newP)
    ) {
      setIsUnique(false);
    } else {
      setIsUnique(true);
    }
  };

  const onPrimary = async () => {
    setIsLoading(true);
    await rename({ oldPath, newPath }).finally(onClose);
  };

  return (
    <Modal
      show={oldPath !== ''}
      isLoading={isLoading}
      onRequestClose={onClose}
      onSecondary={onClose}
      onPrimary={onPrimary}
      disabled={name === '' || !isUnique}
      title={__(`Rename ${type.toLowerCase()}`)}
      textPrimary={__('Rename')}
      textSecondary={__("Don't rename")}
    >
      <VStack spacing={SPACING.SECTION} alignment="left">
        <VStack>
          <Text variant="muted">{__('Current path:')}</Text>
          <Text weight={500}>{oldPath}</Text>
        </VStack>
      </VStack>
      <TextControl
        label={__('Name')}
        value={name}
        onChange={onType}
        placeholder={getFilename(oldPath)}
      />
      {!isUnique && (
        <VStack spacing={4}>
          {!isUnique && (
            <Text isDestructive>{__(`${type} name already exists`)}</Text>
          )}
        </VStack>
      )}
    </Modal>
  );
};
