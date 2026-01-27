import { InspectorControls } from '@wordpress/block-editor';
import { BlockEditProps } from '@wordpress/blocks';
import { Attributes } from '@/builder/Controls/controls/Attributes';
import { Classes } from '@/builder/Controls/controls/Classes';
import { Other } from '@/builder/Controls/controls/Other';
import { BuilderAttributes } from '@/type/builder';
import { css } from '@/utils/css';
import { Import } from './controls/Import';

export const Controls = (props: BlockEditProps<BuilderAttributes>) => {
  return (
    <InspectorControls>
      <div
        className="blockstudio-builder__controls"
        css={css({
          '.components-panel__body > div + div': {
            marginTop: '12px',
          },
        })}
      >
        <Classes {...props} />
        <Attributes {...props} />
        <Other {...props} />
        <Import {...props} />
      </div>
    </InspectorControls>
  );
};
