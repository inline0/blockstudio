import { InspectorControls, InnerBlocks } from '@wordpress/block-editor';
import { registerBlockType } from '@wordpress/blocks';
import { register, useDispatch, useSelect } from '@wordpress/data';
import { useEffect, useState, createPortal } from '@wordpress/element';
import { registerPlugin } from '@wordpress/plugins';
import parse from 'html-react-parser';
import { cloneDeep, get, set } from 'lodash-es';
import { Block } from '@/blocks/components/block';
import { renderCache } from '@/blocks/components/block/render-cache';
import { ExpandedEditor } from '@/blocks/components/expanded-editor';
import { Fields } from '@/blocks/components/fields';
import { initializeEditorReadinessGate } from '@/blocks/editor-readiness';
import { initializeCanvasBodyClasses } from '@/blocks/canvas-body-classes';
import '@/blocks/filters/custom-class';
import '@/blocks/filters/default';
import { getMatches } from '@/blocks/filters/extensions';
import { mediaModal } from '@/blocks/functions/media-modal';
import '@/blocks/integrations/seo';
import { store } from '@/blocks/store';
import { selectors } from '@/blocks/store/selectors';
import { transforms } from '@/blocks/transforms';
import { isAllowedToRender } from '@/blocks/utils/is-allowed-to-render';
import { sendEvents } from '@/blocks/utils/send-events';
import { store as tailwindStore } from '@/tailwind/store';
import { useTailwind } from '@/tailwind/use-tailwind';
import type { PreloadEntry } from '../canvas/types';
import { BlockstudioAttribute } from '@/types/block';
import { BlockstudioBlock, BlockstudioBlockAttributes } from '@/types/types';
import { css } from '@/utils/css';
import { onSavePost } from '@/utils/on-save-post';

(window as unknown as Record<string, boolean>)[
  '__@hello-pangea/dnd-disable-dev-warnings'
] = true;
register(store);
register(tailwindStore);
renderCache.initFromPreload();
mediaModal();
initializeEditorReadinessGate();
initializeCanvasBodyClasses();

const blocks = window.blockstudioAdmin.data.blocksNative;

const renderedIds: string[] = [];

const richTextPathExists = (
  attributes: Record<string, unknown>,
  key: string,
): boolean => {
  if (!key.includes('[')) {
    return true;
  }

  const path = key.replace(/\[(\d+)\]/g, '.$1');
  const parentPath = path.replace(/\.[^.]+$/, '');

  return get(attributes, parentPath) !== undefined;
};

const registerSingleBlock = (block: BlockstudioBlock) => {
  if (!isAllowedToRender(block.blockstudio as BlockstudioAttribute)) return;

  const blockstudioVariations = (
    block.blockstudio as BlockstudioBlock['blockstudio'] & {
      variations?: BlockstudioBlock['variations'];
    }
  ).variations;

  // @ts-ignore
  registerBlockType(block, {
    ...(block?.blockstudio?.icon
      ? {
          icon: {
            src: parse(block?.blockstudio?.icon),
          },
        }
      : {}),
    apiVersion: 3,
    variations: block.variations ?? blockstudioVariations ?? [],
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
      const [isExpandedEditorOpen, setIsExpandedEditorOpen] = useState(false);
      const richTextEntries = useSelect(
        (select) =>
          (select('blockstudio/blocks') as typeof selectors).getRichText()?.[
            clientId
          ] as Record<string, string> | undefined,
        [clientId],
      );

      useEffect(() => {
        const clickSave = () => {
          if (
            !richTextEntries ||
            Object.values(richTextEntries || {}).length === 0
          ) {
            return;
          }

          const clonedAttrs = cloneDeep(
            attributes.blockstudio?.attributes ?? {},
          ) as unknown as Record<string, unknown>;

          Object.entries(richTextEntries).forEach(([key, value]) => {
            if (key.includes('[')) {
              if (!richTextPathExists(clonedAttrs, key)) {
                return;
              }

              const path = key.replace(/\[(\d+)\]/g, '.$1');
              set(clonedAttrs as object, path, value);
            } else {
              (clonedAttrs as unknown as Record<string, unknown>)[key] = value;
            }
          });

          markNextChangeAsNotPersistent();
          setAttributes({
            ...attributes,
            blockstudio: {
              ...attributes.blockstudio,
              attributes:
                clonedAttrs as unknown as BlockstudioBlockAttributes['blockstudio']['attributes'],
            },
          });
        };

        const publishButtons = document.querySelectorAll(
          '.editor-header__settings .components-button',
        );

        const keydown = (e: KeyboardEvent) => {
          if (e.key.toLowerCase() !== 's') {
            return;
          }

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
      }, [richTextEntries, attributes]);

      useEffect(() => {
        if (renderedIds.includes(clientId)) return;

        const richTexts: Record<string, string> = {};
        const blockAttrs = block.attributes as Record<
          string,
          BlockstudioAttribute
        >;
        const savedAttrs = (attributes?.blockstudio?.attributes ??
          {}) as unknown as Record<string, unknown>;

        Object.values(blockAttrs)
          .filter((item) => item.field === 'richtext')
          .forEach((attribute) => {
            richTexts[attribute.id ?? ''] = (
              savedAttrs as Record<string, string>
            )?.[attribute.id ?? ''];
          });

        Object.values(blockAttrs)
          .filter((item) => item.field === 'repeater')
          .forEach((repeaterAttr) => {
            const repeaterId = repeaterAttr.id ?? '';
            const rows = (savedAttrs?.[repeaterId] as unknown[]) ?? [];
            const innerAttrs =
              (
                repeaterAttr as unknown as {
                  attributes: BlockstudioAttribute[];
                }
              ).attributes ?? [];
            const richTextInner = innerAttrs.filter(
              (a) => a.field === 'richtext',
            );

            if (richTextInner.length > 0 && Array.isArray(rows)) {
              rows.forEach((row, index) => {
                richTextInner.forEach((rtAttr) => {
                  const path = `${repeaterId}[${index}].${rtAttr.id}`;
                  richTexts[path] =
                    (row as Record<string, string>)?.[rtAttr.id ?? ''] ?? '';
                });
              });
            }
          });

        setRichText({
          [clientId]: richTexts,
        });

        renderedIds.push(clientId);
      }, []);

      useEffect(() => {
        setTimeout(() => setIsLoaded(true), 100);
      }, []);

      const expandedEditorBlocks = [block, ...getMatches(block.name)];

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
            <ExpandedEditor
              {...{
                attributes,
                block,
                blocks: expandedEditorBlocks,
                clientId,
                setAttributes,
              }}
              onOpenChange={setIsExpandedEditorOpen}
            />
            {!isExpandedEditorOpen && (
              <Fields
                {...{
                  attributes,
                  block,
                  clientId,
                  setAttributes,
                }}
              />
            )}
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
};

Object.values(blocks).forEach((block: BlockstudioBlock) => {
  registerSingleBlock(block);
});

window.blockstudio.registerBlock = (block: BlockstudioBlock) => {
  (blocks as any)[block.name] = block;
  registerSingleBlock(block);
};

window.blockstudio.addPreloads = (entries: PreloadEntry[]) => {
  renderCache.addPreloads(entries);
};

window.blockstudio.replacePreloads = (entries: PreloadEntry[]) => {
  renderCache.replacePreloads(entries);
};

registerPlugin('blockstudio-tailwind', {
  render: () => {
    if (window.blockstudioAdmin.isTailwindActive === 'false') return;

    useTailwind({
      enabled: true,
    });

    return <></>;
  },
});

onSavePost(sendEvents);
