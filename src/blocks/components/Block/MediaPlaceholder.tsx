import { MediaPlaceholder as M } from '@wordpress/block-editor';
import { BlockstudioAttribute } from '@/types/block';
import { BlockstudioBlock, BlockstudioBlockAttributes } from '@/types/types';

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
  const addToGallery = block.attributes[data.attribute].addToGallery;
  const multiple = block.attributes[data.attribute].multiple;
  const shouldRender = !attributes.blockstudio.attributes[data.attribute];

  if (!shouldRender) return null;

  return (
    // @ts-ignore
    <M
      {...data}
      {...{ addToGallery, multiple }}
      onSelect={(value) => {
        setAttributes({
          ...attributes,
          blockstudio: {
            ...attributes.blockstudio,
            attributes: {
              ...attributes.blockstudio.attributes,
              [data.attribute]: !multiple
                ? value.id
                : value.map((v: { id: string }) => v.id),
            },
          },
        });
      }}
      onHTMLDrop={() => {}}
    />
  );
};
