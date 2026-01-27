import { SetStateAction, FormEvent } from 'react';
import {
  __experimentalHStack as HStack,
  __experimentalText as Text,
  __experimentalVStack as VStack,
  FormFileUpload,
  TextControl,
} from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { useState } from '@wordpress/element';
import { Modal } from '@/editor/components/Modal';
import { SPACING } from '@/editor/const/spacings';
import { useCreateFolder } from '@/editor/hooks/useCreateFolder';
import { selectors } from '@/editor/store/selectors';
import { __ } from '@/utils/__';
import { css } from '@/utils/css';

export const ModalNewFolder = () => {
  const [file, setFile] = useState<File | null>(null);
  const [formData, setFormData] = useState<FormData | null>(null);
  const [isLoading, setIsLoading] = useState(false);
  const [isUnique, setIsUnique] = useState(true);
  const [name, setName] = useState('');

  const onClose = () => {
    setFile(null);
    setIsImport(false);
    setIsLoading(false);
    setIsUnique(true);
    setName('');
    setNewFolder('');
  };
  const { createFolder } = useCreateFolder({
    formData,
    name,
  });

  const { setNewFolder, setIsImport } = useDispatch('blockstudio/editor');
  const files = useSelect(
    (select) => (select('blockstudio/editor') as typeof selectors).getFiles(),
    [],
  );
  const isImport = useSelect(
    (select) => (select('blockstudio/editor') as typeof selectors).isImport(),
    [],
  );
  const newFolder = useSelect(
    (select) => (select('blockstudio/editor') as typeof selectors).newFolder(),
    [],
  );
  const filesSplit = [
    ...new Set(
      Object.keys(files).map((e) => {
        return e.substring(0, e.lastIndexOf('/') + 1).slice(0, -1);
      }),
    ),
    ...new Set(
      Object.keys(files).map((e) => {
        const first = e.substring(0, e.lastIndexOf('/') + 1).slice(0, -1);
        return first.substring(0, first.lastIndexOf('/') + 1).slice(0, -1);
      }),
    ),
  ];

  const onType = (e: SetStateAction<string>) => {
    setName(e);
    if (filesSplit.includes(newFolder + '/' + e)) {
      setIsUnique(false);
    } else {
      setIsUnique(true);
    }
  };

  const onImport = async (
    e: FormEvent & {
      currentTarget: HTMLInputElement;
    },
  ) => {
    if (!e?.currentTarget?.files?.length) return;
    const f = e.currentTarget.files[0];
    setFile(f);

    const data = new FormData();
    data.append('file', f, 'blockstudio-import.zip');
    setFormData(data);
  };

  const onPrimary = async () => {
    setIsLoading(true);
    await createFolder()
      .then(onClose)
      .finally(() => setIsLoading(false));
  };

  return (
    <Modal
      show={newFolder}
      isLoading={isLoading}
      onRequestClose={onClose}
      onSecondary={onClose}
      onPrimary={onPrimary}
      disabled={name === '' || !isUnique || (isImport ? file === null : false)}
      title={__(isImport ? 'Import ZIP' : 'Create a new folder')}
      textPrimary={__(isImport ? 'Import' : 'Create folder')}
      textSecondary={__('Cancel')}
    >
      <VStack spacing={SPACING.SECTION} alignment="left">
        <VStack>
          <Text variant="muted">
            {__(
              isImport
                ? 'The data will be imported in the following path:'
                : 'The folder will be created in the following path:',
            )}
          </Text>
          <Text weight={500}>{newFolder}</Text>
        </VStack>
      </VStack>
      <TextControl label={__('Folder name')} value={name} onChange={onType} />
      {isImport && (
        <div
          css={css({
            maxWidth: 'max-content',
          })}
        >
          <HStack>
            <FormFileUpload
              // @ts-ignore
              variant="secondary"
              accept=".zip"
              onChange={onImport}
            >
              {__('Select .zip')}
            </FormFileUpload>
            <Text variant="muted">{file?.name || __('No file selected')}</Text>
          </HStack>
        </div>
      )}
      {!isUnique && (
        <VStack spacing={4}>
          {!isUnique && (
            <Text isDestructive>{__('Folder name already exists')}</Text>
          )}
        </VStack>
      )}
    </Modal>
  );
};
