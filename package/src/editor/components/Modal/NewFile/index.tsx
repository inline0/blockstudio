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
import { useCreateFile } from '@/editor/hooks/useCreateFile';
import { selectors } from '@/editor/store/selectors';
import { __ } from '@/utils/__';

export const ModalNewFile = () => {
  const { createFile } = useCreateFile();
  const [isLoading, setIsLoading] = useState(false);
  const [isUnique, setIsUnique] = useState(true);
  const [name, setName] = useState('');

  const { setNewFile } = useDispatch('blockstudio/editor');
  const files = useSelect(
    (select) => (select('blockstudio/editor') as typeof selectors).getFiles(),
    [],
  );
  const newFile = useSelect(
    (select) => (select('blockstudio/editor') as typeof selectors).newFile(),
    [],
  );

  const onClose = () => {
    setIsLoading(false);
    setIsUnique(true);
    setName('');
    setNewFile('');
  };

  const onType = (e: SetStateAction<string>) => {
    setName(e);
    if (Object.keys(files).includes(newFile + '/' + e)) {
      setIsUnique(false);
    } else {
      setIsUnique(true);
    }
  };

  const onPrimary = async () => {
    setIsLoading(true);
    await createFile(newFile + '/' + name, true)
      .then(onClose)
      .finally(() => setIsLoading(false));
  };

  return (
    <Modal
      show={newFile}
      isLoading={isLoading}
      onRequestClose={onClose}
      onSecondary={onClose}
      onPrimary={onPrimary}
      disabled={name === '' || !isUnique}
      title={__('Create a new file')}
      textPrimary={__('Create file')}
    >
      <VStack spacing={SPACING.SECTION} alignment="left">
        <VStack>
          <Text variant="muted">
            {__('The file will be created in the following path:')}
          </Text>
          <Text weight={500}>{newFile}</Text>
        </VStack>
      </VStack>
      <TextControl label={__('File name')} value={name} onChange={onType} />
      {!isUnique && (
        <VStack spacing={4}>
          {!isUnique && (
            <Text isDestructive>{__('File name already exists')}</Text>
          )}
        </VStack>
      )}
    </Modal>
  );
};
