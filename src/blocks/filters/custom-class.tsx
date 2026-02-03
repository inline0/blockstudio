import { createHigherOrderComponent } from '@wordpress/compose';
import { addFilter } from '@wordpress/hooks';
import { BlockstudioBlock } from '@/types/types';

const blocks = window.blockstudioAdmin.data.blocksNative;

const customProps = createHigherOrderComponent((BlockListBlock) => {
  return (props) => {
    const { name } = props;

    if (
      !Object.values(blocks)
        .map((e) => (e as BlockstudioBlock).name)
        .includes(name)
    ) {
      return <BlockListBlock {...props} />;
    }

    return <BlockListBlock {...props} className="blockstudio-block" />;
  };
}, 'addCustomClassNameToEditorBlock');

addFilter('editor.BlockListBlock', 'blockstudio/props', customProps);
