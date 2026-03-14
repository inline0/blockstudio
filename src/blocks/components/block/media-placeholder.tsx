import { MediaPlaceholder as M } from '@wordpress/block-editor';
import { cloneDeep, get, set } from 'lodash-es';
import { BlockstudioAttribute } from '@/types/block';
import { BlockstudioBlock, BlockstudioBlockAttributes } from '@/types/types';

type MediaItem = { id: number; [k: string]: unknown };
type MediaValue = MediaItem | MediaItem[];

const toPath = (key: string): string =>
  key.replace(/\[(\d+)\]/g, '.$1');

export const MediaPlaceholder = ({
  attributes,
  block,
  data,
  setAttributes,
}: {
  attributes: BlockstudioBlockAttributes;
  block: BlockstudioBlock;
  data: BlockstudioAttribute;
  setAttributes: (attributes: BlockstudioBlockAttributes) => void;
}) => {
  const blockAttr = block.attributes as Record<string, Record<string, unknown>>;
  const attrKey = data.attribute;
  const isNestedPath = attrKey.includes('[');

  const addToGallery = isNestedPath
    ? get(blockAttr, toPath(attrKey) + '.addToGallery')
    : blockAttr?.[attrKey]?.addToGallery;
  const multiple = isNestedPath
    ? get(blockAttr, toPath(attrKey) + '.multiple')
    : blockAttr?.[attrKey]?.multiple;

  const currentAttrs = attributes.blockstudio.attributes as unknown as Record<string, unknown>;
  const currentValue = isNestedPath
    ? get(currentAttrs, toPath(attrKey))
    : currentAttrs[attrKey];

  const shouldRender = !currentValue;

  if (!shouldRender) return null;

  return (
    // @ts-ignore
    <M
      {...data}
      {...{ addToGallery, multiple }}
      onSelect={(value: MediaValue) => {
        const newValue = !multiple
          ? (value as MediaItem).id
          : (value as MediaItem[]).map((v) => v.id);

        if (isNestedPath) {
          const cloned = cloneDeep(attributes.blockstudio.attributes);
          set(cloned as object, toPath(attrKey), newValue);
          setAttributes({
            ...attributes,
            blockstudio: {
              ...attributes.blockstudio,
              attributes: cloned,
            },
          });
        } else {
          const prevAttributes = attributes.blockstudio
            .attributes as unknown as Record<string, unknown>;
          setAttributes({
            ...attributes,
            blockstudio: {
              ...attributes.blockstudio,
              attributes: {
                ...prevAttributes,
                [attrKey]: newValue,
              } as unknown as { [key: string]: unknown }[],
            },
          });
        }
      }}
      onHTMLDrop={() => {}}
    />
  );
};
