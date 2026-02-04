import { ReactNode } from 'react';
import { Panel as P, PanelBody } from '@wordpress/components';
import { BlockstudioAttribute } from '@/types/block';
import { BlockstudioBlockAttributes } from '@/types/types';

export const Panel = ({
  attributes,
  element,
  isAllowedToRender,
  item,
  portal,
}: {
  attributes: BlockstudioBlockAttributes;
  element: (props: BlockstudioAttribute) => ReactNode;
  isAllowedToRender: (
    item: BlockstudioAttribute,
    attributes: BlockstudioBlockAttributes,
    outerBlock?: boolean,
  ) => boolean;
  item: BlockstudioAttribute;
  portal?: boolean;
}) => {
  const props = { ...item } as Partial<BlockstudioAttribute>;
  delete props.icon;
  delete props.id;
  delete props.type;

  if (!isAllowedToRender(item, attributes)) {
    return null;
  }

  return item?.attributes?.length ? (
    <P
      className={`blockstudio-fields__field blockstudio-fields__field--${item.type}`}
    >
      <PanelBody {...props} initialOpen={portal ? true : props.initialOpen}>
        <div
          style={item.style}
          className={`blockstudio-space${item.class ? ` ${item.class}` : ''} `}
        >
          {item.attributes.map((itemInner) => {
            const itemInnerProps = {
              ...itemInner,
            } as unknown as BlockstudioAttribute;
            if (item?.id) {
              itemInnerProps.id = `${item.id}_${itemInner.id}`;
            }

            if (
              !isAllowedToRender(
                itemInner as unknown as BlockstudioAttribute,
                attributes,
                false,
              )
            ) {
              return null;
            }

            return element(itemInnerProps);
          })}
        </div>
      </PanelBody>
    </P>
  ) : null;
};
