import { ReactNode } from 'react';
import { useBlockProps } from '@wordpress/block-editor';
import { createElement } from '@wordpress/element';
import { DOMNode, domToReact } from 'html-react-parser';
import { DomElement } from 'htmlparser2';
import styleToObject from 'style-to-object';

const voidElements = [
  'area',
  'base',
  'br',
  'col',
  'command',
  'embed',
  'hr',
  'img',
  'input',
  'keygen',
  'link',
  'meta',
  'param',
  'source',
  'track',
  'wbr',
];

export const BlockProps = ({
  node,
  children = null,
}: {
  node: DomElement;
  children?: ReactNode;
}) => {
  const blockProps = useBlockProps({
    ...node.attribs,
    ...{
      style: styleToObject(node.attribs?.style || ''),
    },
  });
  blockProps.className = node.attribs.class
    ? node.attribs.class + ' ' + blockProps.className
    : blockProps.className;

  delete (
    blockProps as unknown as {
      class?: string;
    }
  ).class;

  const isVoidElement = voidElements.includes(node.name);
  if (isVoidElement) {
    return createElement(node.name, blockProps);
  }

  return createElement(
    node.name,
    blockProps,
    children || domToReact(node.children as DOMNode[])
  );
};
