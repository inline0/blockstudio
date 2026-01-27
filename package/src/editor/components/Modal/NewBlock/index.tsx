import {
  TextControl,
  CheckboxControl,
  __experimentalText as Text,
  __experimentalVStack as VStack,
  __experimentalHStack as HStack,
  __experimentalSpacer as Spacer,
} from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';
import { useState, useEffect } from '@wordpress/element';
import { Modal } from '@/editor/components/Modal';
import { SPACING } from '@/editor/const/spacings';
import { useCreateBlock } from '@/editor/hooks/useCreateBlock';
import { selectors } from '@/editor/store/selectors';
import { limitName } from '@/editor/utils/limitName';
import { __ } from '@/utils/__';
import { css } from '@/utils/css';

export const ModalNewBlock = () => {
  const [file, setFile] = useState('');
  const [isLoading, setIsLoading] = useState(false);
  const [isTwig, setIsTwig] = useState(false);
  const [isUnique, setIsUnique] = useState(true);
  const [isValidName, setIsValidName] = useState(false);
  const [name, setName] = useState('My new block');
  const { createBlock } = useCreateBlock({
    name,
    file,
    isTwig,
    setIsLoading,
  });
  const { setNewBlock } = useDispatch('blockstudio/editor');
  const files = useSelect(
    (select) => (select('blockstudio/editor') as typeof selectors).getFiles(),
    [],
  );
  const newBlock = useSelect(
    (select) => (select('blockstudio/editor') as typeof selectors).newBlock(),
    [],
  );

  useEffect(() => {
    setFile('');
    setName(__('My new block'));
    setIsTwig(false);
  }, [newBlock]);

  const setFileName = (value: string) => {
    setFile(value);
    setIsValidName(/^[a-z][a-z0-9-]*\/[a-z][a-z0-9-]*$/.test(value as string));
    setIsUnique(
      !Object.values(files)
        .map((e) => e.name)
        .includes(value),
    );
  };

  return (
    <Modal
      disabled={!isValidName || !isUnique || isLoading}
      id="blockstudio-new-block"
      isLoading={isLoading}
      onPrimary={createBlock}
      onRequestClose={() => setNewBlock(false)}
      textPrimary={__('Create block')}
      show={newBlock as unknown as boolean}
      title={__('Create a new block')}
    >
      <VStack spacing={SPACING.SECTION} alignment="left">
        <VStack>
          <Text variant="muted">
            {__('The block will be created in the following path:')}
          </Text>
          <Text weight={500}>{newBlock}</Text>
        </VStack>
        <VStack spacing={SPACING.INPUT} css={css({ width: '100%' })}>
          <TextControl
            label={__('Block name')}
            help={__('Must be in the following format: namespace/block-name')}
            value={file}
            onChange={(value) => setFileName(limitName(value))}
          />
          <TextControl
            label={__('Block title')}
            value={name}
            onChange={(value) => setName(value)}
          />
          <Spacer />
          <CheckboxControl
            label={__('Twig?')}
            checked={isTwig}
            onChange={setIsTwig}
          />
        </VStack>
        {!isUnique && (
          <HStack spacing={4} justify="flex-start">
            <Text isDestructive>{__('Block name already exists')}</Text>
          </HStack>
        )}
      </VStack>
    </Modal>
  );
};
