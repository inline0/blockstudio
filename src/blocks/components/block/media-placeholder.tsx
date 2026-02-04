import { MediaPlaceholder as M } from '@wordpress/block-editor';
import { BlockstudioAttribute } from '@/types/block';
import { BlockstudioBlock, BlockstudioBlockAttributes } from '@/types/types';

type MediaItem = { id: number; [k: string]: unknown };
type MediaValue = MediaItem | MediaItem[];

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
  const addToGallery = blockAttr?.[data.attribute]?.addToGallery;
  const multiple = blockAttr?.[data.attribute]?.multiple;
  const shouldRender = !attributes.blockstudio.attributes[data.attribute];

  if (!shouldRender) return null;

  return (
    // @ts-ignore
    <M
      {...data}
      {...{ addToGallery, multiple }}
      onSelect={(value: MediaValue) => {
        const currentAttributes = attributes.blockstudio
          .attributes as unknown as Record<string, unknown>;
        setAttributes({
          ...attributes,
          blockstudio: {
            ...attributes.blockstudio,
            attributes: {
              ...currentAttributes,
              [data.attribute]: !multiple
                ? (value as MediaItem).id
                : (value as MediaItem[]).map((v) => v.id),
            } as { [key: string]: unknown }[],
          },
        });
      }}
      onHTMLDrop={() => {}}
    />
  );
};
