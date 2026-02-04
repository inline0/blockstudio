import { ReactNode } from 'react';
import { PanelBody, TabPanel } from '@wordpress/components';
import { Panel } from '@/blocks/components/panel';
import { dispatch } from '@/blocks/utils/dispatch';
import { isAllowedToRender } from '@/blocks/utils/is-allowed-to-render';
import { BlockstudioAttribute } from '@/types/block';
import {
  Any,
  BlockstudioBlock,
  BlockstudioBlockAttributes,
} from '@/types/types';

export const El = ({
  attributes,
  block,
  defaults = {},
  element,
  item,
  portal,
}: {
  attributes: BlockstudioBlockAttributes;
  block?: BlockstudioBlock | null;
  defaults?: Record<string, unknown>;
  element: (item: BlockstudioAttribute) => ReactNode;
  item: NonNullable<BlockstudioBlock['blockstudio']['attributes']>[0];
  portal?: boolean;
}) => {
  const isAllowedToRenderWithDefaults = (
    item: BlockstudioAttribute,
    attrs: BlockstudioBlockAttributes,
    outerAttrs: BlockstudioBlockAttributes | boolean = false,
  ) => isAllowedToRender(item, attrs, outerAttrs, defaults);

  return item.type === 'group' ? (
    <Panel
      {...{
        item,
        element,
        isAllowedToRender: isAllowedToRenderWithDefaults,
        attributes,
        portal,
      }}
    />
  ) : item.type === 'tabs' ? (
    <div className="blockstudio-fields__field--tabs">
      <PanelBody opened={portal}>
        <div>
          <TabPanel
            onSelect={() => {
              dispatch(block as BlockstudioBlock, `tabs/${item.id}/change`);
              if (item.key) {
                dispatch(block as BlockstudioBlock, `tabs/${item.key}/change`);
              }
            }}
            tabs={(item.tabs || []).map((e: Any, i: number) => {
              return {
                name: `tab-${i}`,
                title: e.title,
                attributes: e.attributes,
              };
            })}
          >
            {(tab) =>
              tab.attributes.map(
                (item: BlockstudioAttribute, index: number) => {
                  if (!isAllowedToRender(item, attributes, false, defaults)) {
                    return false;
                  }

                  return (
                    <El
                      key={`tab-child-${index}`}
                      {...{ item, element, attributes, defaults }}
                    />
                  );
                },
              )
            }
          </TabPanel>
        </div>
      </PanelBody>
    </div>
  ) : (
    isAllowedToRender(item, attributes, false, defaults) && (
      <PanelBody>{element(item)}</PanelBody>
    )
  );
};
