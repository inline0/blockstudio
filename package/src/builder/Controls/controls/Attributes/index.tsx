// @ts-nocheck
import { PanelBody } from '@wordpress/components';
import { Control } from '@/blocks/components/Control';
import { Attributes as Attrs } from '@/blocks/components/Fields/components/Attributes';
import { __ } from '@/utils/__';

export const Attributes = (props) => {
  const { attributes, setAttributes } = props;

  return (
    <PanelBody title={__('Attributes')}>
      <Control enabled={false}>
        {/* @ts-ignore */}
        <Attrs
          {...{ attributes, setAttributes }}
          media
          link
          keyName="blockstudio.data.attributes"
        />
      </Control>
    </PanelBody>
  );
};
