import {
  useBlockProps,
  useInnerBlocksProps,
  InnerBlocks as WordPressInnerBlocks,
  EditorTemplateLock,
} from '@wordpress/block-editor';
import { TemplateArray } from '@wordpress/blocks';
import { createElement } from '@wordpress/element';
import styleToObject from 'style-to-object';
import { getDefaultsFromTemplate } from '@/blocks/utils/get-defaults-from-template';
import { Block } from '@/types/block';

const blocks = window.blockstudioAdmin.data.blocksNative as unknown as Record<string, Block>;

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
    class?: string;
    style?: string;
    [key: string]: unknown;
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
      template: (template || []).map((item) => {
        const blockName = typeof item === 'string' ? item : item[0];
        const blockAttrs = typeof item === 'string' ? undefined : item[1];
        try {
          const defaults = getDefaultsFromTemplate(blocks[blockName], item);
          return [blockName, defaults];
        } catch {
          const templateBlock = {
            name: blockName,
            attributes: blockAttrs,
          } as Block;
          const defaults = getDefaultsFromTemplate(templateBlock, item);
          return [blockName, defaults];
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
