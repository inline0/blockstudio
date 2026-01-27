import {
  __experimentalText as Text,
  Flex,
  CardBody,
  Button,
} from '@wordpress/components';
import { register, useDispatch, useSelect } from '@wordpress/data';
import { useEffect } from '@wordpress/element';
import { useParams } from 'react-router-dom';
import { Card } from '@/admin/components/Card';
import { BlockList } from '@/editor/components/BlockList';
import { ContextMenu } from '@/editor/components/ContextMenu';
import { Editor as E } from '@/editor/components/Editor/index';
import { ModalNewBlock } from '@/editor/components/Modal/NewBlock';
import { ModalNewInstance } from '@/editor/components/Modal/NewInstance';
import { ModalRename } from '@/editor/components/Modal/Rename';
import { useOpen } from '@/editor/hooks/useOpen';
import { store } from '@/editor/store';
import { selectors } from '@/editor/store/selectors';

register(store);

let hasRendered = false;

export const Editor = () => {
  const open = useOpen();
  const { id } = useParams();
  const { closeEditor, setIsGutenberg } = useDispatch('blockstudio/editor');
  const blocks = useSelect(
    (select) =>
      (select('blockstudio/editor') as typeof selectors).getBlocksData(),
    [],
  );
  const blockstudio = useSelect(
    (select) =>
      (select('blockstudio/editor') as typeof selectors).getBlockstudio(),
    [],
  );
  const files = useSelect(
    (select) => (select('blockstudio/editor') as typeof selectors).getFiles(),
    [],
  );
  const isEditor = useSelect(
    (select) => (select('blockstudio/editor') as typeof selectors).isEditor(),
    [],
  );
  const isGutenberg = useSelect(
    (select) =>
      (select('blockstudio/editor') as typeof selectors).isGutenberg(),
    [],
  );

  const block = files?.[(id as string)?.replaceAll('--', '/')];

  useEffect(() => {
    if (!hasRendered) {
      const params = new URLSearchParams(window.location.search);
      const blockParam = params.get('block');
      const isDemo = params.get('demo');
      if (blockParam && blocks?.[blockParam]) {
        setIsGutenberg(true);
        open(blocks[blockParam]);
      } else if (isDemo) {
        open(blocks?.['demo/import']);
      } else if (block?.path) {
        open(block);
      }
      hasRendered = true;
      (
        document.querySelector('#blockstudio-admin') as HTMLElement
      ).style.opacity = '100';
    }

    window.scrollTo(0, 0);
  }, []);

  useEffect(() => {
    if (!id && isEditor && !isGutenberg) closeEditor();
  }, [id, isEditor, isGutenberg]);

  return blockstudio?.allowEditor === 'true' ? (
    <div
      id="blockstudio-editor"
      onContextMenu={(e) => {
        e.preventDefault();
      }}
    >
      {isEditor ? <E /> : <BlockList />}
      <ModalNewBlock />
      <ModalNewInstance />
      <ModalRename />
      <ContextMenu />
    </div>
  ) : (
    <Card>
      <CardBody>
        <Flex direction="column">
          <Text>The editor is disabled for the current user.</Text>
          <Text>
            Visit{' '}
            <Button
              target="_blank"
              variant="link"
              href="https://blockstudio.dev/documentation/editor/general/#setup"
            >
              the docs
            </Button>{' '}
            to learn more about how to enable the editor.
          </Text>
        </Flex>
      </CardBody>
    </Card>
  );
};
