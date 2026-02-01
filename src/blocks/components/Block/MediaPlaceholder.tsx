import { MediaPlaceholder as M } from '@wordpress/block-editor';
import { BlockstudioAttribute } from '@/types/block';
import { Any, BlockstudioBlock, BlockstudioBlockAttributes } from '@/types/types';

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
  const addToGallery = (block.attributes as Record<string, Any>)?.[data.attribute]?.addToGallery;
  const multiple = (block.attributes as Record<string, Any>)?.[data.attribute]?.multiple;
  const shouldRender = !attributes.blockstudio.attributes[data.attribute];

  if (!shouldRender) return null;

  return (
    // @ts-ignore
    <M
      {...data}
      {...{ addToGallery, multiple }}
      onSelect={(value: Any) => {
        const currentAttributes = attributes.blockstudio.attributes as unknown as Record<string, Any>;
        setAttributes({
          ...attributes,
          blockstudio: {
            ...attributes.blockstudio,
            attributes: {
              ...currentAttributes,
              [data.attribute]: !multiple
                ? value.id
                : value.map((v: { id: string }) => v.id),
            } as Any,
          },
        });
      }}
      onHTMLDrop={() => {}}
    />
  );
};
