import {
  RichText,
  useBlockProps,
  useInnerBlocksProps,
} from '@wordpress/block-editor';
import { BlockEditProps } from '@wordpress/blocks';
import { createElement } from '@wordpress/element';
import styleToObject from 'style-to-object';
import { useMedia } from '@/blocks/hooks/useMedia';
import { Controls } from '@/builder/Controls';
import { screens } from '@/tailwind/data/screens';
import { BuilderAttributes, BuilderTextTags } from '@/type/builder';

export const Element = (props: BlockEditProps<BuilderAttributes>) => {
  const { attributes, name } = props as unknown as {
    attributes: BuilderAttributes;
    name: string;
  };
  const className = attributes?.blockstudio?.data?.className || [];
  let classNameTemporary =
    attributes?.blockstudio?.data?.className__temporary || '';
  let hasScreen = false;

  useMedia(
    attributes?.blockstudio?.data?.attributes
      ? attributes?.blockstudio?.data.attributes
          .filter((attribute) => attribute.data?.media)
          .map((attribute) => `${attribute.data?.media}`)
      : '',
  );

  Object.keys(screens).forEach((screen) => {
    if (hasScreen) return;
    if (classNameTemporary.includes(`${screen}:`)) {
      classNameTemporary = classNameTemporary.replace(
        `${screen}:`,
        `${screen}:!`,
      );
      hasScreen = true;
    }
  });

  if (!hasScreen && classNameTemporary) {
    classNameTemporary = `!${classNameTemporary}`;
  }

  const blockAttributes = {};
  attributes?.blockstudio?.data?.attributes?.forEach((attribute) => {
    if (attribute?.attribute === 'style') {
      blockAttributes[attribute.attribute] = styleToObject(
        attribute?.value || '',
      );
    } else {
      blockAttributes[attribute.attribute] = attribute.value;
    }
  });

  const blockProps = useBlockProps();
  let elementProps = {
    ...blockProps,
    ...blockAttributes,
    'data-blockstudio': name,
    className:
      blockProps.className + ' ' + className + ' ' + classNameTemporary,
  } as unknown as Record<string, unknown>;

  if (name === 'blockstudio/container') {
    elementProps = useInnerBlocksProps({ ...elementProps });
  }

  return (
    <>
      <Controls {...props} />
      {name === 'blockstudio/text' ? (
        <RichText
          {...elementProps}
          tagName={
            (attributes?.blockstudio?.data?.tag || 'p') as BuilderTextTags
          }
          value={attributes?.blockstudio?.data?.content || ''}
          onChange={(content) =>
            props.setAttributes({
              blockstudio: {
                data: { ...attributes?.blockstudio?.data, content },
              },
            })
          }
        />
      ) : (
        createElement(attributes?.blockstudio?.data?.tag || 'div', elementProps)
      )}
    </>
  );
};
