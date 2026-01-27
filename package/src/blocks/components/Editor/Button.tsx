import { Panel, PanelBody, Button as B } from '@wordpress/components';
import { useDispatch } from '@wordpress/data';
import { useEffect, useState } from '@wordpress/element';
import { BlockstudioBlock } from '@/type/types';
import { __ } from '@/utils/__';
import { css } from '@/utils/css';

export const Button = ({ block }: { block: BlockstudioBlock }) => {
  const { setEditor, setEditorFocus } = useDispatch('blockstudio/blocks');
  const [canEdit, setCanEdit] = useState(false);

  useEffect(() => {
    setCanEdit(window.blockstudioAdmin?.canEdit === 'true');
  }, []);

  if (!canEdit) return null;

  return (
    <Panel
      css={css({
        borderBottom: '1px solid #e0e0e0',
      })}
    >
      <PanelBody title={__('Edit')} initialOpen={false}>
        <B
          variant="secondary"
          onClick={() => {
            if (block?.name) {
              setEditorFocus();
            }
            setEditor(block);
          }}
        >
          {__('Edit block with Blockstudio')}
        </B>
      </PanelBody>
    </Panel>
  );
};
