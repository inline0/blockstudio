// @ts-nocheck
import { PanelBody } from '@wordpress/components';
import { Control } from '@/blocks/components/Control';
import { Classes as Tailwind } from '@/blocks/components/Fields/components/Classes';
import { __ } from '@/utils/__';

export const Classes = (props) => {
  const { attributes, setAttributes } = props;

  return (
    <PanelBody title={__('Classes')}>
      <Control enabled={false}>
        <Tailwind
          {...{ attributes, setAttributes }}
          label={__('Classes')}
          value={attributes.blockstudio?.data?.className || ''}
          keyName="blockstudio.data.className"
          only
          tailwind
        />
      </Control>
    </PanelBody>
  );
};
