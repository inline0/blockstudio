import { parse as nodeParser } from 'node-html-parser';
import { getRegex } from '@/blocks/components/Block/utils/getRegex';

export const getAttributes = (value: string, elementName: string) => {
  const regex = getRegex(elementName);
  const match = value.match(regex);
  const attributesString = match?.[1] || '';
  const element = `<div ${attributesString}></div>`;

  const root = nodeParser(element);
  const div = root.querySelector('div');
  const attributes: Record<string, unknown> = (div?.attributes as unknown as Record<string, unknown>) || {};

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
      attributes[key] = JSON.parse(attributes[key] as string);
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

  return attributes;
};
