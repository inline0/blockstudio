import { InspectorControls, InnerBlocks } from '@wordpress/block-editor';
import { registerBlockType } from '@wordpress/blocks';
import { register, useDispatch, useSelect } from '@wordpress/data';
import { useEffect, useState, createPortal } from '@wordpress/element';
import { registerPlugin } from '@wordpress/plugins';
import parse from 'html-react-parser';
import { Block } from '@/blocks/components/block';
import { Fields } from '@/blocks/components/fields';
import '@/blocks/filters/custom-class';
import '@/blocks/filters/default';
import '@/blocks/filters/extensions';
import { mediaModal } from '@/blocks/functions/media-modal';
import { store } from '@/blocks/store';
import { selectors } from '@/blocks/store/selectors';
import { transforms } from '@/blocks/transforms';
import { isAllowedToRender } from '@/blocks/utils/is-allowed-to-render';
import { sendEvents } from '@/blocks/utils/send-events';
import { store as tailwindStore } from '@/tailwind/store';
import { useTailwind } from '@/tailwind/use-tailwind';
import { useTailwindSaveBlockEditor } from '@/tailwind/use-tailwind-save-block-editor';
import { BlockstudioAttribute } from '@/types/block';
import { BlockstudioBlock, BlockstudioBlockAttributes } from '@/types/types';
import { css } from '@/utils/css';
import { onSavePost } from '@/utils/on-save-post';

(window as unknown as Record<string, boolean>)[
  '__@hello-pangea/dnd-disable-dev-warnings'
] = true;
register(store);
register(tailwindStore);
mediaModal();

const blocks = window.blockstudioAdmin.data.blocksNative;

const renderedIds: string[] = [];

Object.values(blocks).forEach((block: BlockstudioBlock) => {
  if (!isAllowedToRender(block.blockstudio as BlockstudioAttribute)) return;

  // @ts-ignore
  registerBlockType(block, {
    ...(block?.blockstudio?.icon
      ? {
          icon: {
            src: parse(block?.blockstudio?.icon),
          },
        }
      : {}),
    apiVersion: 2,
    providesContext: { [block.name]: 'blockstudio' },
    transforms: transforms(
      block,
      blocks as unknown as Record<string, BlockstudioBlock>,
    ),
    usesContext: ['postId', 'postType', ...(block?.usesContext || [])],
    save: () => <InnerBlocks.Content />,
    edit: ({
      attributes,
      setAttributes,
      context,
      clientId,
    }: {
      attributes: BlockstudioBlockAttributes;
      setAttributes: (attributes: BlockstudioBlockAttributes) => void;
      context: Record<string, unknown>;
      clientId: string;
    }) => {
      const { setRichText } = useDispatch('blockstudio/blocks');
      const {
        __unstableMarkNextChangeAsNotPersistent: markNextChangeAsNotPersistent,
      } = useDispatch('core/block-editor') as unknown as {
        __unstableMarkNextChangeAsNotPersistent: () => void;
      };
      const [isLoaded, setIsLoaded] = useState(false);
      const richText = useSelect(
        (select) =>
          (select('blockstudio/blocks') as typeof selectors).getRichText(),
        [],
      );

      useEffect(() => {
        const clickSave = () => {
          if (
            !richText?.[clientId] ||
            Object.values(richText?.[clientId] || {}).length === 0
          ) {
            return;
          }

          markNextChangeAsNotPersistent();
          setAttributes({
            ...attributes,
            blockstudio: {
              ...attributes.blockstudio,
              attributes: {
                ...attributes.blockstudio?.attributes,
                ...((richText?.[clientId] ? richText[clientId] : {}) as object),
              },
            },
          });
        };

        const publishButtons = document.querySelectorAll(
          '.editor-header__settings .components-button',
        );

        const keydown = (e: KeyboardEvent) => {
          if (
            navigator.userAgent.indexOf('Mac OS X') !== -1
              ? e.metaKey
              : e.ctrlKey
          ) {
            clickSave();
          }
        };

        publishButtons.forEach((item) =>
          item?.addEventListener('click', clickSave),
        );
        document.addEventListener('keydown', keydown);

        return () => {
          publishButtons.forEach((item) =>
            item?.removeEventListener('click', clickSave),
          );
          document.removeEventListener('keydown', keydown);
        };
      }, [richText, attributes]);

      useEffect(() => {
        if (renderedIds.includes(clientId)) return;

        const richTexts: Record<string, string> = {};
        Object.values(block.attributes as Record<string, BlockstudioAttribute>)
          .filter((item) => item.field === 'richtext')
          .forEach((attribute) => {
            richTexts[attribute.id ?? ''] = (
              attributes?.blockstudio?.attributes as unknown as Record<
                string,
                string
              >
            )?.[attribute.id ?? ''];
          });

        setRichText({
          ...richText,
          [clientId]: richTexts,
        });

        renderedIds.push(clientId);
      }, []);

      useEffect(() => {
        setTimeout(() => setIsLoaded(true), 100);
      }, []);

      // useEffect(() => {
      //   console.log('attributes', attributes);
      // }, [attributes]);

      return (
        <>
          {!isLoaded &&
            createPortal(
              <div css={css({ display: 'hidden' })}>
                <Fields
                  {...{
                    attributes,
                    setAttributes,
                    block,
                    clientId,
                  }}
                  portal
                />
                <style>{`.blockstudio-fields .components-form-toggle__track, .blockstudio-fields .components-form-toggle__thumb { transition: none !important; }`}</style>
              </div>,
              document.body,
            )}
          <InspectorControls>
            <Fields
              {...{
                attributes,
                block,
                clientId,
                setAttributes,
              }}
            />
          </InspectorControls>
          {isLoaded && (
            <Block
              {...{
                attributes,
                block,
                clientId,
                context,
                setAttributes,
              }}
            />
          )}
        </>
      );
    },
  });
});

registerPlugin('blockstudio-tailwind', {
  render: () => {
    if (window.blockstudioAdmin.isTailwindActive === 'false') return;

    const compile = useTailwindSaveBlockEditor();

    useEffect(() => onSavePost(compile), []);
    useTailwind({
      enabled: true,
    });

    return <></>;
  },
});

onSavePost(sendEvents);
