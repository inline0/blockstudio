import { parse as nodeParser } from 'node-html-parser';
import { getRegex } from '@/blocks/components/Block/utils/getRegex';
import { Any } from '@/types/types';

export const getAttributes = (value: string, elementName: string) => {
  const regex = getRegex(elementName);
  const attributesString = value.match(regex)[1];
  const element = `<div ${attributesString}></div>`;

  const root = nodeParser(element);
  const div = root.querySelector('div');
  const attributes = div.attributes as unknown as object[];

  [
    'template',
    'allowedBlocks',
    'allowedFormats',
    'autocompleters',
    'allowedTypes',
    'labels',
  ].forEach((key) => {
    if (!attributes[key]) {
      return;
    }

    try {
      attributes[key] = JSON.parse(attributes[key]);
    } catch {
      attributes[key] = [];
    }
  });

  [
    'multiline',
    'withoutInteractiveFormatting',
    'preserveWhiteSpace',
    'addToGallery',
    'autoOpenMediaUpload',
    'disableDropZone',
    'dropZoneUIOnly',
    'isAppender',
    'disableMediaButtons',
  ].forEach((key) => {
    if (!attributes[key]) {
      return;
    }

    if (attributes[key] === 'false') {
      attributes[key] = false;
    } else if (attributes[key]) {
      attributes[key] = true;
    }
  });

  return attributes as Any;
};
