import { parseFragment, serialize } from 'parse5';
import { Any } from '@/types/types';

export const getInnerHTML = (htmlString: string) => {
  htmlString = htmlString.replace(/<(\w+)([^>]*)\s?\/>/g, '<$1$2></$1>');

  const fragment = parseFragment(htmlString).childNodes.filter(
    (node) => node.nodeName !== '#text' && node.nodeName !== '#comment',
  )[0];

  let element = serialize(fragment as Any);

  element = element
    .replaceAll(/<innerblocks([^>]*)>\s*<\/innerblocks>/g, '<InnerBlocks$1 />')
    .replaceAll(/<richtext([^>]*)>\s*<\/richtext>/g, '<RichText$1 />')
    .replaceAll(
      /<mediaplaceholder([^>]*)>\s*<\/mediaplaceholder>/g,
      '<MediaPlaceholder$1 />',
    );

  return element;
};
