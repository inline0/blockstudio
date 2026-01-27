import { InnerBlocks } from '@wordpress/block-editor';
import { BlockEditProps, registerBlockType } from '@wordpress/blocks';
import { group, typography, mediaAndText } from '@wordpress/icons';
import { Element } from '@/builder/Element';
import { attributes } from '@/builder/attributes';
import { BuilderAttributes } from '@/type/builder';

const settings = {
  apiVersion: 2,
};

registerBlockType('blockstudio/container', {
  title: 'Container',
  description:
    'A block for grouping multiple blocks. Use it to section off content within your pages.',
  icon: group,
  category: 'blockstudio',
  attributes,
  ...settings,
  edit: (props) => {
    return <Element {...(props as BlockEditProps<BuilderAttributes>)} />;
  },
  save: () => <InnerBlocks.Content />,
});

registerBlockType('blockstudio/element', {
  title: 'Element',
  description: 'Self closing element for form inputs or images.',
  icon: mediaAndText,
  category: 'blockstudio',
  attributes,
  ...settings,
  edit: (props) => {
    return <Element {...(props as BlockEditProps<BuilderAttributes>)} />;
  },
  save: () => null,
});

registerBlockType('blockstudio/text', {
  title: 'Text',
  description:
    'Add and format text. Suitable for individual paragraphs or headings.',
  icon: typography,
  category: 'blockstudio',
  attributes,
  ...settings,
  edit: (props) => {
    return <Element {...(props as BlockEditProps<BuilderAttributes>)} />;
  },
  save: () => null,
});
