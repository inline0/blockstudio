// @ts-nocheck
import { BlockEditProps } from '@wordpress/blocks';
import {
  PanelBody,
  SelectControl,
  TextControl,
  ToggleControl,
} from '@wordpress/components';
import { cloneDeep, set } from 'lodash-es';
import { Control } from '@/blocks/components/Control';
import { BuilderAttributes } from '@/type/builder';
import { css } from '@/utils/css';

export const Other = (props: BlockEditProps<BuilderAttributes>) => {
  const { attributes, setAttributes, name } = props as unknown as {
    attributes: BuilderAttributes;
    name: string;
    setAttributes: (val: BuilderAttributes) => void;
  };

  const setter = (val: string | boolean, key: string) => {
    const clone = cloneDeep(attributes);
    set(clone, key, val);
    setAttributes(clone);
  };

  const options =
    name === 'blockstudio/text'
      ? [
          { label: 'p', value: 'p' },
          { label: 'h1', value: 'h1' },
          { label: 'h2', value: 'h2' },
          { label: 'h3', value: 'h3' },
          { label: 'h4', value: 'h4' },
          { label: 'h5', value: 'h5' },
          { label: 'h6', value: 'h6' },
          { label: 'span', value: 'span' },
        ]
      : name === 'blockstudio/element'
        ? [
            { label: 'img', value: 'img' },
            { label: 'input', value: 'input' },
            { label: 'textarea', value: 'textarea' },
            { label: 'select', value: 'select' },
            { label: 'hr', value: 'hr' },
          ]
        : [
            { label: 'div', value: 'div' },
            { label: 'section', value: 'section' },
            { label: 'article', value: 'article' },
            { label: 'aside', value: 'aside' },
            { label: 'header', value: 'header' },
            { label: 'footer', value: 'footer' },
            { label: 'nav', value: 'nav' },
            { label: 'main', value: 'main' },
            { label: 'a', value: 'a' },
          ];

  const defaultTag =
    name === 'blockstudio/text'
      ? 'p'
      : name === 'blockstudio/element'
        ? 'img'
        : 'div';

  return (
    <PanelBody title="Other" initialOpen={false}>
      {!attributes.data?.tagCustom && (
        <Control enabled={false}>
          <SelectControl
            {...{ options }}
            label="Tag"
            value={attributes.data?.tag || defaultTag}
            onChange={(val: string) => setter(val, 'data.tag')}
          />
        </Control>
      )}
      {attributes.data?.tagCustom && (
        <TextControl
          css={css({
            marginBottom: '0',

            '.components-text-control__input': {
              marginLeft: '0',
            },
          })}
          label="Custom tag"
          value={attributes.data?.tag || defaultTag}
          onChange={(val: string) => setter(val, 'data.tag')}
          __nextHasNoMarginBottom
        />
      )}
      <Control enabled={false}>
        <ToggleControl
          checked={attributes.data?.tagCustom}
          onChange={(val: boolean) => setter(val, 'data.tagCustom')}
          className={`components-base-control`}
          label="Use custom tag"
        />
      </Control>
    </PanelBody>
  );
};
