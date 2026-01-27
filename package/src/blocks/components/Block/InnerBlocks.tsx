import {
  useBlockProps,
  useInnerBlocksProps,
  InnerBlocks as WordPressInnerBlocks,
  EditorTemplateLock,
} from '@wordpress/block-editor';
import { TemplateArray } from '@wordpress/blocks';
import { createElement } from '@wordpress/element';
import styleToObject from 'style-to-object';
import { getDefaultsFromTemplate } from '@/blocks/utils/getDefaultsFromTemplate';
import { Block } from '@/type/block';
import { Any } from '@/type/types';

const blocks = window.blockstudioAdmin.data.blocksNative;

export const InnerBlocks = ({
  blockResponse,
  getAttributes,
  hasOwnBlockProps,
}: {
  blockResponse: string;
  getAttributes: (value: string, elementName: string) => NonNullable<unknown>;
  hasOwnBlockProps: boolean;
}) => {
  const attributes = getAttributes(blockResponse, 'InnerBlocks');
  const {
    allowedBlocks,
    orientation,
    prioritizedInserterBlocks,
    renderAppender,
    tag,
    template,
    templateInsertUpdatesSelection,
    templateLock,
    ...rest
  } = attributes as {
    allowedBlocks: string[];
    defaultBlock: string;
    directInsert: boolean;
    orientation: 'horizontal' | 'vertical';
    prioritizedInserterBlocks: string[];
    renderAppender: string;
    tag: string;
    template: TemplateArray;
    templateInsertUpdatesSelection: boolean;
    templateLock: EditorTemplateLock;
    [key: string]: Any;
  };
  const blockProps = hasOwnBlockProps
    ? useBlockProps()
    : ({} as {
        className: string;
        style: Record<string, string>;
      });

  const innerBlocksProps = useInnerBlocksProps(
    {
      ...blockProps,
      className:
        (rest?.class || '') +
        (hasOwnBlockProps ? ` ${blockProps.className}` : ''),
      style: styleToObject(rest?.style || ''),
    },
    {
      allowedBlocks,
      orientation,
      prioritizedInserterBlocks,
      template: (template || []).map((item: Any) => {
        try {
          const defaults = getDefaultsFromTemplate(blocks[item[0]], item);
          return [item[0], defaults];
        } catch (e) {
          const templateBlock = {
            name: item[0],
            attributes: item[1],
          } as Block;
          const defaults = getDefaultsFromTemplate(templateBlock, item);
          return [item[0], defaults];
        }
      }),
      templateInsertUpdatesSelection,
      templateLock,
      ...(renderAppender === 'false' || renderAppender === 'button'
        ? {
            renderAppender: () => {
              if (renderAppender === 'false') {
                return false;
              }
              if (renderAppender === 'button') {
                return <WordPressInnerBlocks.ButtonBlockAppender />;
              }
            },
          }
        : {}),
    }
  );

  if (rest.class) delete rest.class;
  if (rest.style) delete rest.style;

  const props = { ...innerBlocksProps, ...rest };

  return createElement(tag || 'div', props);
};
