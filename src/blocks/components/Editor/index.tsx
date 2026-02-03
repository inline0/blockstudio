import apiFetch from '@wordpress/api-fetch';
import { useDispatch, useSelect } from '@wordpress/data';
import {
  unmountComponentAtNode,
  useEffect,
  useRef,
  useState,
} from '@wordpress/element';
import { debounce } from 'lodash-es';
import { selectors } from '@/blocks/store/selectors';
import { getAssetId } from '@/blocks/utils/getAssetId';
import { isCss } from '@/utils/isCss';
import { BlockstudioEditorBlock } from '@/types/types';

type EditorState = Record<string, string> & { name?: string };

export const Editor = () => {
  const { setEditor } = useDispatch('blockstudio/blocks');
  const editor = useSelect(
    (select) => (select('blockstudio/blocks') as typeof selectors).getEditor(),
    [],
  ) as EditorState | undefined;
  const editorFocus = useSelect(
    (select) =>
      (select('blockstudio/blocks') as typeof selectors).getEditorFocus(),
    [],
  );
  const [currentEditor, setCurrentEditor] = useState<EditorState | null>(null);
  const [newWindowRoot, setNewWindowRoot] = useState<HTMLDivElement | null>(
    null,
  );
  const winRef = useRef<Window | null>(null);
  const assetsRef = useRef<Record<string, string>>({});

  useEffect(() => {
    handleParentWindowUnload();
    winRef.current = null;
    assetsRef.current = {};
    setNewWindowRoot(null);

    if (!editor?.name) return;
    setCurrentEditor(editor);

    const width = Math.min(window.screen.width * 0.75, 1000);
    const height = Math.min(window.screen.height * 0.75, 1000);
    const win = window.open(
      '',
      '',
      `width=${width},height=${height},resizable=yes`,
    );
    if (win) {
      winRef.current = win;
      const root = win.document.createElement('iframe');
      win.document.title = `Blockstudio: ${editor.name}`;
      win.document.body.style.margin = '0';
      root.style.width = '100%';
      root.style.height = '100%';
      root.style.border = 'none';

      win.addEventListener('beforeunload', handleChildWindowUnload);
      const encodedBlock = encodeURIComponent(editor.name);
      root.src = `${window.blockstudioAdmin.adminUrl}admin.php?page=blockstudio&block=${encodedBlock}#/editor`;
      win.document.body.appendChild(root);
      setNewWindowRoot(root);

      win.addEventListener('message', iframeListener);
      window.addEventListener('beforeunload', handleParentWindowUnload);
    }

    return () => {
      window.removeEventListener('beforeunload', handleParentWindowUnload);
      if (winRef.current) {
        winRef.current.removeEventListener(
          'beforeunload',
          handleChildWindowUnload,
        );
        winRef.current.removeEventListener('message', iframeListener);
      }
    };
  }, [editor]);

  useEffect(() => {
    if (winRef.current) {
      winRef.current.focus();
    }
  }, [editorFocus]);

  useEffect(() => {
    if (currentEditor?.name && editor?.name !== currentEditor.name) {
      reset(currentEditor);
    }
  }, [editor]);

  const debounced = debounce(async (data: { filesChanged: Record<string, string> }) => {
    const response = await apiFetch({
      method: 'POST',
      path: '/blockstudio/v1/gutenberg/block/update',
      data: {
        block: editor,
        filesChanged: data.filesChanged,
      },
    }) as { filesChanged: Record<string, string> };

    const event = new CustomEvent(`blockstudio/${editor?.name}/refresh`, {
      detail: response,
    });
    document.dispatchEvent(event);
    Object.entries(response?.filesChanged || {}).forEach(([key, value]) => {
      if (key.endsWith('-css') || key.endsWith('-js')) {
        const assetElement = document.querySelector(`#${key}`);
        if (assetElement) {
          assetElement.textContent = value as string;
        } else {
          const newAssetElement = document.createElement(
            key.endsWith('-css') ? 'style' : 'script',
          );
          newAssetElement.id = key;
          newAssetElement.textContent = value as string;
          document.body.appendChild(newAssetElement);
        }
      }
    });
  }, 500);

  const reset = (obj: EditorState | undefined = editor) => {
    const event = new CustomEvent(`blockstudio/${obj?.name}/refresh`);
    document.dispatchEvent(event);
    Object.entries(assetsRef.current || {}).forEach(([key, value]) => {
      console.log(key, value);
      const el = document.querySelector(`#${key}`);
      if (el) el.textContent = value as string;
    });
  };

  const iframeListener = (event: {
    data: {
      deletedFile?: string;
      filesChanged: {
        [key: string]: string;
      };
      files: {
        [key: string]: string;
      };
    };
  }) => {
    if (event?.data?.deletedFile) {
      const element = document.querySelector(`${event.data.deletedFile}`);
      if (element) {
        element.remove();
      }
    }

    const { filesChanged, files } = event.data;
    if (files) {
      Object.keys(files)
        .filter((key) => isCss(key) || key.endsWith('.js'))
        .forEach((key) => {
          const id = getAssetId(editor as unknown as BlockstudioEditorBlock, key);
          const el = document.querySelector(`#${id}`);
          assetsRef.current[id] = el?.textContent || '';
        });
    }

    debounced({ filesChanged });
  };

  const handleChildWindowUnload = () => {
    reset();
    setEditor({});
    if (newWindowRoot) unmountComponentAtNode(newWindowRoot);
  };

  const handleParentWindowUnload = () => {
    if (winRef.current && !winRef.current.closed) {
      reset();
      winRef.current.close();
    }
  };

  return null;
};
