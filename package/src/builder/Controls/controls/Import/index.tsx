// @ts-nocheck
import { BlockEditProps, createBlock } from '@wordpress/blocks';
import { Button, PanelBody } from '@wordpress/components';
import { dispatch, select } from '@wordpress/data';
import { Control } from '@/blocks/components/Control';
import { BuilderAttributes } from '@/type/builder';
import { Any } from '@/type/types';
import { __ } from '@/utils/__';

export const Import = (props: BlockEditProps<BuilderAttributes>) => {
  const { name } = props as unknown as {
    name: string;
  };

  if (name !== 'blockstudio/container') return null;

  const handleImportClick = async () => {
    await insertBlocks();
  };

  return (
    <PanelBody title="Import" initialOpen={false}>
      <Control enabled={false}>
        <Button variant="secondary" onClick={handleImportClick}>
          {__('Import HTML from clipboard')}
        </Button>
      </Control>
    </PanelBody>
  );
};

const importFromClipboard = async () => {
  try {
    const text = await navigator.clipboard.readText();
    const parser = new DOMParser();
    const doc = parser.parseFromString(text, 'text/html');
    return Array.from(doc.body.childNodes) as HTMLElement[];
  } catch (error) {
    console.error('Failed to read from clipboard:', error);
    return [];
  }
};

const mapElementToBlock = (element: HTMLElement) => {
  const tagName = element.tagName?.toLowerCase();
  if (!tagName) return;
  const className = element.className || '';
  const attributes = Array.from(element.attributes)
    .filter((attr) => attr.name !== 'class')
    .map((attr) => ({
      attribute: attr.name,
      value: attr.value,
    }));

  const isSelfClosing = [
    'img',
    'input',
    'br',
    'hr',
    'area',
    'base',
    'col',
    'command',
    'embed',
    'keygen',
    'link',
    'meta',
    'param',
    'source',
    'track',
    'wbr',
  ].includes(tagName);

  if (isSelfClosing) {
    return {
      name: 'blockstudio/element',
      attributes: {
        data: {
          className,
          attributes,
          tag: tagName,
        },
      },
    };
  }

  const textTags = ['p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'span'];
  if (!textTags.includes(tagName)) {
    element.childNodes.forEach((node) => {
      if (node.nodeType === Node.TEXT_NODE && node.textContent.trim() !== '') {
        const span = document.createElement('span');
        span.textContent = node.textContent;
        element.replaceChild(span, node);
      }
    });
  }

  if (element.children.length > 0) {
    return {
      name: 'blockstudio/container',
      attributes: {
        data: {
          className,
          attributes,
          tag: tagName,
        },
      },
      innerBlocks: Array.from(element.children)
        .map(mapElementToBlock)
        .filter(Boolean),
    };
  }

  if (
    element.childNodes.length === 1 &&
    element.childNodes[0].nodeType === Node.TEXT_NODE
  ) {
    return {
      name: 'blockstudio/text',
      attributes: {
        data: {
          className,
          attributes,
          tag: tagName,
          content: element.innerText,
        },
      },
    };
  }

  return {
    name: 'blockstudio/container',
    attributes: {
      data: {
        className,
        attributes,
        tag: tagName,
      },
    },
    innerBlocks: Array.from(element.childNodes)
      .map(mapElementToBlock)
      .filter(Boolean),
  };
};

const insertBlocks = async () => {
  const elements = await importFromClipboard();
  const blocks = elements.map(mapElementToBlock).filter(Boolean);

  const { getSelectedBlockClientId } = select('core/block-editor');
  const selectedClientId = getSelectedBlockClientId();

  const removeInnerBlocks = (clientId: string) => {
    const { getBlocks } = select('core/block-editor');
    const { removeBlocks } = dispatch('core/block-editor');
    const innerBlocks = getBlocks(clientId).map((block) => block.clientId);
    if (innerBlocks.length > 0) {
      removeBlocks(innerBlocks, !!clientId);
    }
  };

  const createAndInsertBlock = (
    block: (typeof blocks)[0],
    index = 0,
    clientId = selectedClientId,
    first = true,
  ) => {
    let newBlock = { clientId: '' } as Any;
    if (!first) {
      newBlock = createBlock(block.name, block.attributes);
      dispatch('core/block-editor').insertBlock(newBlock, index, clientId);
    }
    if (block.innerBlocks) {
      block.innerBlocks.forEach((innerBlock: (typeof blocks)[0], i: number) => {
        setTimeout(
          () =>
            createAndInsertBlock(
              innerBlock,
              i,
              newBlock?.clientId || clientId,
              false,
            ),
          0,
        );
      });
    }
  };

  removeInnerBlocks(selectedClientId);
  blocks.forEach((block, index) => {
    createAndInsertBlock(block, index, selectedClientId, true);
  });
};
