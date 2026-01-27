import { register } from '@wordpress/data';
import { render, useState, useEffect } from '@wordpress/element';
import { Fields } from '@/blocks/components/Fields';
import { store } from '@/blocks/store';
import { store as tailwindStore } from '@/tailwind/store';
import { BlockstudioBlock, BlockstudioBlockAttributes } from '@/type/types';
import { css } from '@/utils/css';

register(store);
register(tailwindStore);

export const Configurator = ({
  site,
  outerBlock = null,
}: {
  site: string | boolean;
  outerBlock?: BlockstudioBlock;
}) => {
  document.body.classList.add(
    'blockstudio-configurator',
    'fabrikat-scrollbar',
    'is-visible',
  );

  const [block, setBlock] = useState<BlockstudioBlock>(null);
  const [attributes, setAttributes] = useState({
    blockstudio: {
      name: 'blockstudio/type-repeater',
    },
  } as BlockstudioBlockAttributes);

  useEffect(() => {
    if (outerBlock) return;

    const messageHandler = (event: { data: string }) => {
      try {
        const block = JSON.parse(event.data);
        if (typeof block === 'object' && block?.blockstudio) {
          block.name = 'blockstudio/configurator';
          setBlock(block);
        }
        parent.postMessage('loaded', site as string);
      } catch (e) {}
    };

    window.addEventListener('message', messageHandler, false);

    return () => window.removeEventListener('message', messageHandler);
  }, []);

  useEffect(() => {
    if (!outerBlock) return;
    setBlock(outerBlock);

    if (!outerBlock?.blockstudio?.attributes) return;
    setAttributes({
      blockstudio: {
        attributes: outerBlock.blockstudio.attributes,
      },
    } as unknown as BlockstudioBlockAttributes);
  }, [outerBlock]);

  return outerBlock ? (
    <div
      css={css({
        '.blockstudio-fields__field--group.components-panel': {
          border: 'none',
        },
      })}
    >
      <Fields {...{ attributes, setAttributes, block }} config />
    </div>
  ) : (
    <div
      className={`interface-interface-skeleton__sidebar`}
      style={{
        width: '100%',
        borderTop: 'none',
      }}
    >
      <div className={`interface-complementary-area edit-post-sidebar`}>
        <div className={`components-panel`}>
          <div className={`block-editor-block-inspector`}>
            <div className="block-editor-block-card">
              <span className="block-editor-block-icon has-colors">
                <span
                  className={`dashicon dashicons dashicons-${
                    block?.icon || 'star-filled'
                  }`}
                ></span>
              </span>
              <div className="block-editor-block-card__content">
                <h2 className="block-editor-block-card__title">
                  {block?.title || 'Custom block'}
                </h2>
                {block?.description && (
                  <span className="block-editor-block-card__description">
                    {block?.description}
                  </span>
                )}
              </div>
            </div>
            <div>
              <Fields {...{ attributes, setAttributes, block }} config />
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.configurator').forEach((item) => {
    render(<Configurator site={item.getAttribute('data-site')} />, item);
  });
});
