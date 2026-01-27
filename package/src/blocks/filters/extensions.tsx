import { InspectorControls } from '@wordpress/block-editor';
import { createHigherOrderComponent } from '@wordpress/compose';
import { useSelect } from '@wordpress/data';
import { createPortal, useEffect, useRef, useState } from '@wordpress/element';
import { addFilter } from '@wordpress/hooks';
import { cloneDeep, isArray, set as lodashSet } from 'lodash-es';
import { Fields } from '@/blocks/components/Fields';
import { getEditorDocument } from '@/blocks/utils/getEditorDocument';
import { parseTemplate } from '@/blocks/utils/parseTemplate';
import { selectors as selectorsTailwind } from '@/tailwind/store/selectors';
import {
  Any,
  BlockstudioBlock,
  BlockstudioBlockAttributes,
} from '@/type/types';
import { css } from '@/utils/css';

const extensions = window.blockstudioAdmin.data.extensions;

const matchFound = (name: string, string: string) => {
  if (typeof name === 'string' && name.endsWith('*')) {
    const prefix = name.slice(0, -1);
    return string.startsWith(prefix);
  } else {
    return name === string;
  }
};

const getMatchesWithWildcard = (string: string) => {
  return extensions
    .filter((e: { name: string | string[] }) => {
      if (Array.isArray(e.name)) {
        return e.name.some((name) => matchFound(name, string));
      } else {
        return matchFound(e.name, string);
      }
    })
    .sort((a, b) => {
      const prioA = a.blockstudio.extend?.priority || 0;
      const prioB = b.blockstudio.extend?.priority || 0;

      if (prioA > prioB) {
        return 1;
      } else if (prioA < prioB) {
        return -1;
      } else {
        return 0;
      }
    });
};

export const shouldExtend = (string: string): boolean => {
  return getMatchesWithWildcard(string).length > 0;
};

export const getMatches = (string: string) => {
  return getMatchesWithWildcard(string);
};

const getAttributes = (
  attributes: BlockstudioBlockAttributes,
  name: string,
  disabled: string[]
) => {
  const classNames = [];
  const styles = [];
  const dataAttributes = {};

  getMatches(name).forEach((block: BlockstudioBlock) => {
    Object.values(block.attributes).forEach((attribute) => {
      if (disabled.includes(attribute.id)) return;
      if (attribute.set?.length) {
        attribute.set.forEach((set: { attribute: string; value: string }) => {
          if (attributes?.[attribute.id]) {
            const copy = cloneDeep(attributes);

            const applyValue = (value) => {
              if (['select', 'radio', 'checkbox'].includes(attribute.field)) {
                if (
                  attribute.populate &&
                  attribute?.optionsPopulate?.includes(value.value)
                ) {
                  value.value = attribute.optionsPopulateFull[value.value];
                }

                if (
                  !attribute.returnFormat ||
                  attribute.returnFormat === 'value'
                ) {
                  lodashSet(copy, attribute.id, value.value);
                }
                if (attribute.returnFormat === 'label') {
                  lodashSet(copy, attribute.id, value.label);
                }
              }

              const val = set.value
                ? parseTemplate(set.value, {
                    attributes: copy,
                  })
                : value;

              if (set.attribute === 'class') {
                classNames.push(val);
              } else if (set.attribute === 'style') {
                styles.push(val.replace(/;+$/, ''));
              } else {
                dataAttributes[set.attribute] = val;
              }
            };

            if (isArray(copy[attribute.id])) {
              copy[attribute.id].forEach((value: string) => applyValue(value));
            } else {
              applyValue(copy[attribute.id]);
            }
          }
        });
      }
      if (attribute.field === 'attributes') {
        const copy = cloneDeep(attributes);
        (copy?.[attribute.id] || [])
          .filter((attribute) => attribute?.attribute)
          .forEach((attribute) => {
            dataAttributes[attribute.attribute] = attribute.value;
          });
      }
    });
  });

  return {
    classNames: [...new Set(classNames)],
    styles,
    dataAttributes,
  };
};

addFilter(
  'editor.BlockEdit',
  'blockstudio/extensions/set',
  createHigherOrderComponent((BlockEdit) => {
    return (props: Any) => {
      const ref = useRef<HTMLDivElement>(undefined);

      if (!shouldExtend(props.name)) return <BlockEdit {...props} />;

      const [isLoaded, setIsLoaded] = useState(false);
      const { attributes, setAttributes } = props;

      useEffect(() => {
        const html = ref?.current?.closest('html');
        if (
          html.classList.contains('block-editor-block-preview__content-iframe')
        ) {
          return;
        }
        setTimeout(() => setIsLoaded(true), 100);
      }, []);

      if (isLoaded) {
        return (
          <>
            <BlockEdit {...props} />
            {getMatches(props.name).map(
              (e: BlockstudioBlock, index: number) => {
                return (
                  <>
                    {createPortal(
                      <div css={css({ display: 'none' })}>
                        <Fields
                          {...{ attributes, setAttributes }}
                          clientId={props.clientId}
                          block={e}
                          extensions
                          portal
                        />
                        <style>{`.blockstudio-fields .components-form-toggle__track, .blockstudio-fields .components-form-toggle__thumb { transition: none !important; }`}</style>
                      </div>,
                      document.body
                    )}
                    <InspectorControls
                      key={index}
                      {...((
                        e.blockstudio as {
                          group: string;
                        }
                      ).group
                        ? {
                            group: (
                              e.blockstudio as {
                                group: string;
                              }
                            ).group,
                          }
                        : {})}
                    >
                      <Fields
                        {...{ attributes, setAttributes }}
                        clientId={props.clientId}
                        block={e}
                        extensions
                      />
                    </InspectorControls>
                  </>
                );
              }
            )}
          </>
        );
      }

      return (
        <div style={{ display: 'contents' }} ref={ref}>
          <BlockEdit {...props} />
        </div>
      );
    };
  }, 'addCustomControlsToEditorBlock')
);

addFilter(
  'editor.BlockListBlock',
  'blockstudio/extension/edit',
  createHigherOrderComponent((BlockListBlock) => {
    return (props: {
      attributes: {
        blockstudio: {
          attributes: BlockstudioBlockAttributes;
          disabled: string[];
        };
      };
      className: string;
      clientId: string;
      name: string;
    }) => {
      if (!shouldExtend(props.name)) return <BlockListBlock {...props} />;

      const temporaryClasses = useSelect(
        (select) =>
          (
            select('blockstudio/tailwind') as typeof selectorsTailwind
          ).getTemporaryClasses(),
        []
      );

      const currentTemporaryClasses = Object.entries(temporaryClasses)
        .filter(([key, _]) => key.startsWith(props.clientId))
        .map(([_, value]) => value)
        .join(' ')
        .trim();

      const { styles, classNames, dataAttributes } = getAttributes(
        props.attributes?.blockstudio?.attributes,
        props.name,
        props.attributes?.blockstudio?.disabled || []
      );

      const updatedClassNames = [props.className || '', ...classNames]
        .filter(Boolean)
        .join(' ')
        .trim();

      useEffect(() => {
        const id = `blockstudio-${props.clientId}`;
        const doc = getEditorDocument();

        let element = doc.getElementById(id);
        if (!element) {
          element = doc.createElement('style');
          element.id = id;
          doc.head.appendChild(element);
        }

        element.innerHTML = `.editor-styles-wrapper [data-block="${
          props.clientId
        }"] {${styles.join(';')}`;
      }, [styles]);

      return (
        <BlockListBlock
          {...props}
          wrapperProps={{ ...dataAttributes }}
          className={`${updatedClassNames}${
            currentTemporaryClasses ? ' ' + currentTemporaryClasses : ''
          }`}
        />
      );
    };
  }, 'addCustomClassNameToEditorBlock')
);
