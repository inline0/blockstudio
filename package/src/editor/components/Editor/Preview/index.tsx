import apiFetch from '@wordpress/api-fetch';
import { Spinner } from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { useEffect, useState, useRef } from '@wordpress/element';
import { colors } from '@/admin/const/colors';
import { useCollectErrors } from '@/editor/hooks/useCollectErrors';
import { useIsBlock } from '@/editor/hooks/useIsBlock';
import { selectors } from '@/editor/store/selectors';
import { isCss } from '@/editor/utils/isCss';
import { moduleMatcher } from '@/editor/utils/moduleMatcher';
import { moduleMatcherCss } from '@/editor/utils/moduleMatcherCss';
import { useTailwind } from '@/tailwind/useTailwind';
import { BlockstudioEditorBlock, BlockstudioRestResponse } from '@/type/types';
import { __ } from '@/utils/__';
import { css } from '@/utils/css';

const userMarkup = window.blockstudioAdmin.data.editorMarkup || '';

const Frame = ({ html }: { html: string }) => {
  const iframe = useRef(null);
  const parent = useRef(null);
  const settings = useSelect(
    (select) =>
      (select('blockstudio/editor') as typeof selectors).getSettings(),
    [],
  );

  const resize = () => {
    if (
      !settings?.previewSizeSelected ||
      settings.previewSizeSelected === 'None'
    ) {
      return;
    }

    const ratio = parent.current.offsetWidth / iframe.current.offsetWidth;
    iframe.current.style.transform = 'scale(calc((' + ratio + '))';
    iframe.current.style.height = parent.current.offsetHeight / ratio + 'px';
  };

  useEffect(() => {
    window.addEventListener('resize', resize);

    return () => window.removeEventListener('resize', resize);
  }, []);

  useEffect(() => {
    resize();
  }, [html]);

  useEffect(() => {
    resize();
    if (
      !settings?.previewSizeSelected ||
      settings.previewSizeSelected === 'None'
    ) {
      iframe.current.style.transform = 'scale(1)';
      iframe.current.style.height = '100%';
    }
  }, [settings.previewSizeSelected]);

  return (
    <div
      ref={parent}
      style={{
        position: 'absolute',
        left: 0,
        top: 0,
        height: '100%',
        width: '100%',
      }}
    >
      <iframe
        name="blockstudio-preview"
        sandbox="allow-scripts allow-same-origin allow-presentation"
        title={__('Blockstudio Preview')}
        ref={iframe}
        srcDoc={html}
        style={{
          width:
            !settings?.previewSizeSelected ||
            settings.previewSizeSelected === 'None'
              ? '100%'
              : `${settings.previewSizeSelected}px`,
          transformOrigin: 'left top',
        }}
      />
    </div>
  );
};

export const Preview = () => {
  const isBlock = useIsBlock();
  const { addError, removeError } = useCollectErrors();
  const { setConsole } = useDispatch('blockstudio/editor');
  const [isError, setIsError] = useState(false);
  const [isLoading, setIsLoading] = useState(true);
  const [preview, setPreview] = useState('');
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
  const options = useSelect(
    (select) => (select('blockstudio/editor') as typeof selectors).getOptions(),
    [],
  );
  const previewCount = useSelect(
    (select) => (select('blockstudio/editor') as typeof selectors).getPreview(),
    [],
  );
  const [assetsCss, setAssetsCss] = useState('');
  const [assetsJs, setAssetsJs] = useState('');
  const tailwind = useTailwind({ html: preview });

  const baseStyles = `<style>
    html {
      height: 100%;
      width: 100%;
    }
    body {
      margin: 0;
      height: 100%;
      width: 100%;
    }
  </style>`;

  useEffect(() => {
    let css = '';
    let js = '';

    Object.entries({
      ...window.blockstudioAdmin.data.scripts,
      ...(window?.blockstudioAdmin.data.styles || {}),
    }).forEach(
      ([k, v]: [string, BlockstudioEditorBlock['previewAssets'][0]]) => {
        const globalAssets = options?.editor?.assets || [];
        const blockAssets = block?.previewAssets || [];

        const assets = [...globalAssets, ...blockAssets];
        if (!assets.includes(k)) return;

        if (v?.src && v.type === 'style') {
          css = `${css}<link id="${k}" rel="stylesheet" type="text/css" href="${v.src}">`;
        }
        if (v?.inline && v.type === 'style') {
          css = `${css}<style id="${k}">${v.inline}</style>`;
        }
        if (v?.src && v.type === 'script') {
          js = `${js}<script type="module" id="${k}" src="${v.src}"></script>`;
        }
        if (v?.inline && v.type === 'script') {
          js = `${js}<script type="module" id="${k}">${v.inline}</script>`;
        }
      },
    );

    setAssetsCss(css);
    setAssetsJs(js);
  }, []);

  useEffect(() => {
    const updatePreview = async () => {
      let preview = '';

      const id = (path: string) =>
        path
          .replaceAll('/', '-')
          .replaceAll(' ', '-')
          .replaceAll('.', '-')
          .toLowerCase()
          .slice(1);

      const create = async (item: string) => {
        let file: {
          path: string;
          value: string;
        };

        if (filesChanged[item] || filesChanged[item] === '') {
          file = {
            path: item,
            value: filesChanged[item],
          };
        } else if (files[item]) {
          file = {
            path: item,
            value: files[item].value,
          };
        } else {
          return;
        }

        const path = file.path;
        let value = file.value;

        if (path.endsWith('.js')) {
          value = moduleMatcher(value) as string;

          const { updatedValue, finalUrls } = moduleMatcherCss(value);
          value = updatedValue;
          finalUrls.forEach((url) => {
            preview += `<link rel="stylesheet" type="text/css" href="${url}">`;
          });

          preview += `<script type="module" id="${id(path)}">${value}</script>`;
        } else if (path.endsWith('index.php') || path.endsWith('index.twig')) {
          await apiFetch({
            path: '/blockstudio/v1/editor/block/render',
            method: 'POST',
            data: {
              name: files[path].name,
              content: value === '' ? ' ' : encodeURIComponent(value),
            },
          })
            .then((res: BlockstudioRestResponse) => {
              setIsError(false);
              removeError(path);
              preview += res.data.content;
            })
            .catch(() => {
              setIsError(true);
              setConsole({ type: 'error' });
              addError(path);
            });
        }
      };

      const get = async (items: string[]) => {
        await Promise.all(
          items.map(async (item) => {
            await create(item);
          }),
        );
      };

      const css = async (items: string[]) => {
        const content = [
          ...items.map((item) => {
            return {
              path: item,
              content: filesChanged[item] || files[item]?.value,
            };
          }),
        ];

        return await apiFetch({
          path: '/blockstudio/v1/editor/processor/scss',
          method: 'POST',
          data: {
            content,
          },
        }).then((res: BlockstudioRestResponse) => {
          Object.entries(res.data.compiled).forEach(
            ([k, v]) => (preview += `<style id="${id(k)}">${v}</style>`),
          );
        });
      };

      const getAssets = Object.values(block?.assets || []).map(
        (asset) => asset.path,
      );

      await get(
        block.filesPaths.filter((e: string) => !e.endsWith('.js') && !isCss(e)),
      );
      await get(
        getAssets.filter(
          (e: string) => e.endsWith('.js') && !e.endsWith('-editor.js'),
        ),
      );
      await css(
        getAssets.filter(
          (e: string) =>
            isCss(e) &&
            !e.endsWith('-editor.css') &&
            !e.endsWith('-editor.scss'),
        ),
      );
      setPreview(preview);
      setIsLoading(false);
    };

    updatePreview();
  }, [block, files, filesChanged, isBlock, previewCount]);

  useEffect(() => setIsLoading(true), [block]);

  return (
    <div
      id="blockstudio-editor-preview"
      css={css({
        position: 'relative',
        background: colors.gray[50],
        height:
          'calc(100vh - var(--blockstudio-editor-admin-bar) - var(--blockstudio-editor-toolbar))',
        width: '100%',
        display: 'flex',

        iframe: {
          height:
            'calc(100vh - var(--blockstudio-editor-admin-bar) - var(--blockstudio-editor-toolbar))',
        },
      })}
    >
      {isLoading ? (
        <Spinner
          css={css({
            margin: '0',
            position: 'absolute',
            top: '50%',
            left: '50%',
            transform: 'translate(-50%, -50%)',
          })}
        />
      ) : isError ? (
        <div
          css={css({
            color: colors.error,
            margin: 'auto',
            textAlign: 'center',
            padding: '0.5rem',
            fontWeight: 'bold',
          })}
        >
          🚨 {__('There is an error in your block template file')}
        </div>
      ) : (
        <Frame
          html={`${
            tailwind ? `<style>${tailwind}</style>` : ``
          }${baseStyles}${assetsCss}${preview}${assetsJs}${userMarkup}`}
        />
      )}
    </div>
  );
};
