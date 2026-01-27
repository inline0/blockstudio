import { SetStateAction } from 'react';
import {
  __experimentalText as Text,
  __experimentalVStack as VStack,
} from '@wordpress/components';
import { useState } from '@wordpress/element';
import { Modal } from '@/editor/components/Modal';
import { useDeleteFile } from '@/editor/hooks/useDeleteFile';
import { getFilename } from '@/editor/utils/getFilename';
import { BlockstudioEditorBlock } from '@/type/types';
import { __ } from '@/utils/__';

export const ModalDeleteFile = ({
  setIsModalDelete,
  path,
  block,
  show,
}: {
  setIsModalDelete: (value: SetStateAction<boolean>) => void;
  path: string;
  block: BlockstudioEditorBlock;
  show: boolean;
}) => {
  const [isLoading, setIsLoading] = useState(false);
  const { deleteFile } = useDeleteFile({
    block,
    path,
  });

  if (!block && !path) {
    return null;
  }

  const onPrimary = async () => {
    setIsLoading(true);
    await deleteFile()
      .then(() => setIsModalDelete(false))
      .finally(() => setIsLoading(false));
  };

  return (
    <Modal
      show={show}
      isLoading={isLoading}
      onRequestClose={() => setIsModalDelete(false)}
      onSecondary={() => setIsModalDelete(false)}
      onPrimary={onPrimary}
      title={
        getFilename(path).endsWith('block.json') ||
        getFilename(path).endsWith('index.php') ||
        getFilename(path).endsWith('index.twig')
          ? __('Delete block')
          : block.directory
            ? __('Delete folder')
            : __('Delete file')
      }
      textPrimary={
        getFilename(path).endsWith('block.json') ||
        getFilename(path).endsWith('index.php') ||
        getFilename(path).endsWith('index.twig')
          ? __('Delete block')
          : block.directory
            ? __('Delete folder')
            : __('Delete file')
      }
      isDestructive
      text={
        (getFilename(path).endsWith('block.json') ||
          getFilename(path).endsWith('index.php') ||
          getFilename(path).endsWith('index.twig')) &&
        block.files.length >= 2 &&
        `${__(
          'Warning: deleting the block will also delete the following files in this folder:',
        )} ${block.files
          .filter((e: string) => e !== getFilename(path))
          .map((item: string) => getFilename(item))
          .join(', ')}`
      }
    >
      <VStack>
        <Text variant="muted">{__('Following file will be deleted:')}</Text>
        <Text weight={500}>{path}</Text>
      </VStack>
    </Modal>
  );
};
