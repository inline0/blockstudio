import { useDispatch } from '@wordpress/data';
import { useEffect, useMemo, useRef } from '@wordpress/element';
import { set, unset, result, isNumber, cloneDeep, get } from 'lodash-es';
import { Base } from '@/blocks/components/base';
import { Control } from '@/blocks/components/control';
import { El } from '@/blocks/components/el';
import { Attributes } from '@/blocks/components/fields/components/attributes';
import { Checkbox } from '@/blocks/components/fields/components/checkbox';
import { Classes } from '@/blocks/components/fields/components/classes';
import { Code, CodeActions } from '@/blocks/components/fields/components/code';
import { Color } from '@/blocks/components/fields/components/color';
import { Date } from '@/blocks/components/fields/components/date';
import { Datetime } from '@/blocks/components/fields/components/datetime';
import { Files } from '@/blocks/components/fields/components/files';
import { Gradient } from '@/blocks/components/fields/components/gradient';
import { Icon } from '@/blocks/components/fields/components/icon';
import { Link } from '@/blocks/components/fields/components/link';
import { Message } from '@/blocks/components/fields/components/message';
import { NumberField } from '@/blocks/components/fields/components/number';
import { Radio } from '@/blocks/components/fields/components/radio';
import { Range } from '@/blocks/components/fields/components/range';
import { Repeater } from '@/blocks/components/fields/components/repeater';
import { Select } from '@/blocks/components/fields/components/select';
import { Text } from '@/blocks/components/fields/components/text';
import { Textarea } from '@/blocks/components/fields/components/textarea';
import { Toggle } from '@/blocks/components/fields/components/toggle';
import { Token } from '@/blocks/components/fields/components/token';
import { Unit } from '@/blocks/components/fields/components/unit';
import { WYSIWYG } from '@/blocks/components/fields/components/wysiwyg';
import { LabelAction } from '@/blocks/components/label';
import { seen, unseen } from '@wordpress/icons';
import { Styles } from '@/blocks/components/styles';
import { createBlocks } from '@/blocks/utils/create-blocks';
import { dispatch } from '@/blocks/utils/dispatch';
import { getDefaults } from '@/blocks/utils/get-defaults';
import { isAllowedToRender } from '@/blocks/utils/is-allowed-to-render';
import { BlockstudioAttribute } from '@/types/block';
import {
  Any,
  BlockstudioBlock,
  BlockstudioBlockAttributes,
  BlockstudioFieldsChange,
  BlockstudioFieldsElement,
  BlockstudioFieldsRepeaterAddRemove,
  BlockstudioFieldsRepeaterSort,
} from '@/types/types';
import { css } from '@/utils/css';
import { isNumeric } from '@/utils/is-numeric';

let isDeleting = false;

export const Fields = ({
  attributes,
  block,
  clientId = '',
  config = false,
  extensions,
  portal,
  setAttributes,
}: {
  attributes: BlockstudioBlockAttributes;
  block: BlockstudioBlock;
  clientId?: string;
  config?: boolean;
  extensions?: boolean;
  portal?: boolean;
  setAttributes: (attributes: BlockstudioBlockAttributes) => void;
}) => {
  if (config && !block?.name) return null;

  const {
    __unstableMarkNextChangeAsNotPersistent: markNextChangeAsNotPersistent,
  } = useDispatch('core/block-editor') as unknown as {
    __unstableMarkNextChangeAsNotPersistent: () => void;
  };

  const defaultsRepeaters = useRef(false);
  const defaultValue = useRef(false);

  const defaults = useMemo(
    () => getDefaults(Object.values(block?.attributes || {}), attributes),
    [block?.attributes, attributes],
  );

  useEffect(() => {
    if (!config) return;
    if (defaultValue.current === false && Object.values(defaults).length) {
      setAttributes({
        ...attributes,
        blockstudio: {
          ...attributes.blockstudio,
          attributes: {
            ...attributes.blockstudio.attributes,
            ...defaults,
          },
        },
      });
    }

    defaultValue.current = true;
  }, []);

  const element: BlockstudioFieldsElement = (
    item,
    repeaterId = '',
    transform = false,
  ) => {
    if (item.type === 'message')
      return <Message {...{ attributes, block }} value={item.value ?? ''} />;

    const transformed = transform || (block.attributes as Any)?.[item.id ?? ''];

    if (item.type === 'group' || !item.id || !item.type) return false;

    const props: Any = { ...item };
    const transformedOptions =
      (block?.attributes as Any)?.[item.id]?.options || item.options;
    delete props.type;
    delete props.id;

    let v = (attributes.blockstudio?.attributes as Any)?.[item.id];
    let val = v?.value;
    const key = `blockstudio.attributes.${repeaterId}`;

    const getRepeaterKey = (id: string) => {
      return id.split('.')[0].split('[')[0];
    };

    if (repeaterId !== '' && item.type !== 'repeater') {
      const repeaterAttributes = result(
        attributes,
        `blockstudio.attributes.${repeaterId.slice(0, -item.id.length - 1)}`,
      );

      if (
        !isAllowedToRender(
          item,
          {
            blockstudio: { attributes: repeaterAttributes },
          } as BlockstudioBlockAttributes,
          attributes as unknown as boolean,
          defaults,
        )
      ) {
        return false;
      }
    }

    const getValue = (item: Any, value: string | number) => {
      const finder = (innerValue = value) => {
        return transformedOptions?.find(
          (e: { value: string | number }) =>
            String(e?.value || e) === String(innerValue),
        );
      };

      const optionValue =
        isNumeric(value) && isNumber(finder()?.value || finder())
          ? Number(value)
          : value;

      return (item.type === 'select' && !item?.multiple) ||
        item.type === 'radio'
        ? {
            value: optionValue,
            label: finder()?.label || value,
          }
        : item.type === 'checkbox' ||
            (item.type === 'select' && item?.multiple === true)
          ? value
            ? v?.length &&
              v.map((e: { value: string | number }) => e.value).includes(value)
              ? v.filter((e: { value: string | number }) => e.value !== value)
              : [
                  ...(v?.length ? v : []),
                  {
                    value: optionValue,
                    label: finder()?.label || value,
                  },
                ]
            : []
          : // @ts-ignore
            item.type === 'token'
            ? typeof value === 'string'
              ? [{ value, label: finder(value).label }]
              : (
                  value as unknown as {
                    value: string | number;
                    label: string;
                  }[]
                ).map((e) => {
                  return e?.value
                    ? e
                    : {
                        value:
                          finder(e as unknown as number | string)?.value || e,
                        label:
                          finder(e as unknown as number | string)?.label || e,
                      };
                })
            : item.type === 'color' || item.type === 'gradient'
              ? {
                  value,
                  name: value && finder()?.name,
                  slug: value && finder()?.slug,
                }
              : value;
    };

    const setRepeater = (
      newAttributes: BlockstudioBlockAttributes,
      key: string,
    ) => {
      setAttributes({
        ...attributes,
        blockstudio: {
          ...attributes.blockstudio,
          attributes: {
            ...attributes.blockstudio.attributes,
            [key]: (newAttributes.blockstudio.attributes as Any)?.[key]
              ? (newAttributes.blockstudio.attributes as Any)[key].filter(
                  (e: Any) => e,
                )
              : false,
          },
        },
      });
    };

    if (repeaterId !== '') {
      if (result(attributes, key)) {
        v = result(attributes, key);
        val = v?.value || v;
      } else {
        v = '';
      }
    }

    if (item.type === 'repeater' && !v && !isDeleting) {
      if (Array.isArray(item?.default) && item.default.length > 0) {
        v = item.default;
      } else if (item?.min && item.min >= 1) {
        const arr: Any[] = [];

        Array.from({ length: item.min }).forEach(() => {
          const obj = getDefaults(transformed?.attributes);
          arr.push(obj);
        });

        v = [...(v || []), ...arr];
      }

      if (v) {
        const key = `blockstudio.attributes.${repeaterId || item.id}`;
        const newAttributes = JSON.parse(JSON.stringify(attributes));
        const isNewRepeater = !attributes.blockstudio.attributes;

        if (!defaultsRepeaters.current)
          defaultsRepeaters.current = newAttributes;

        const updatedObj = isNewRepeater
          ? defaultsRepeaters.current
          : newAttributes;

        set(updatedObj, key, v);

        if (isNewRepeater) markNextChangeAsNotPersistent();
        setRepeater(
          updatedObj,
          repeaterId ? getRepeaterKey(repeaterId) : item.id,
        );
      }
    }

    const change: BlockstudioFieldsChange = (
      value,
      direct = false,
      suffix = '',
    ) => {
      let updatedAttributes: Any;

      const newValue = direct ? value : getValue(item, value);

      if (repeaterId !== '') {
        const newAttributes = JSON.parse(JSON.stringify(attributes));
        const objectKey = repeaterId.split('[')[0];
        set(newAttributes, key + suffix, newValue);

        if (
          value === undefined &&
          (item.type === 'color' || item.type === 'gradient')
        ) {
          unset(newAttributes, key);
        }

        updatedAttributes = newAttributes;

        // markNextChangeAsNotPersistent();
        setRepeater(newAttributes, objectKey);
      } else {
        updatedAttributes = {
          ...attributes.blockstudio.attributes,
          [item.id + suffix]: newValue,
        };

        // markNextChangeAsNotPersistent();
        setAttributes({
          ...attributes,
          blockstudio: {
            ...attributes.blockstudio,
            attributes: updatedAttributes,
          },
        });
      }

      if (
        (item.type === 'select' && !item?.multiple) ||
        item.type === 'radio'
      ) {
        const innerBlocks = transformedOptions?.find(
          (e: Any) => String(e?.value || e) === String(value),
        )?.innerBlocks;

        if (innerBlocks) {
          createBlocks(innerBlocks, clientId);
        }
      }

      const eventData = {
        block: {
          name: block.name,
          category: block?.category,
          blockstudio: block?.blockstudio,
        },
        attribute: { ...item },
        attributes: { ...updatedAttributes },
        value: newValue,
        clientId,
        repeaterId: repeaterId === '' ? false : repeaterId,
        update() {
          dispatch(block, 'loaded');
        },
      };
      dispatch(block, `attributes/${item.id}/update`, eventData);
      if (item.key) {
        dispatch(block, `attributes/${item.key}/update`, eventData);
      }
      dispatch(
        false as unknown as BlockstudioBlock,
        `attributes/update`,
        eventData,
      );
    };

    const disable: BlockstudioFieldsRepeaterAddRemove = (id) => {
      const disable = !attributes?.blockstudio?.disabled?.includes(id);

      setAttributes({
        ...attributes,
        blockstudio: {
          ...attributes.blockstudio,
          disabled: disable
            ? [id, ...(attributes?.blockstudio?.disabled ?? [])]
            : attributes?.blockstudio?.disabled?.filter(
                (e: string) => e !== id,
              ),
        },
      });
    };

    const sort: BlockstudioFieldsRepeaterSort = (order, id) => {
      const key = `blockstudio.attributes.${id}`;
      const newAttributes = JSON.parse(JSON.stringify(attributes));
      const values = ((result(newAttributes, key) || []) as Any[]).map(
        (e: Any, index: number) => {
          return {
            ...e,
            __BLOCKSTUDIO_INDEX: index,
          };
        },
      );
      const sortedValues = values.sort(
        (a, b) =>
          order.indexOf(
            (
              a as unknown as {
                __BLOCKSTUDIO_INDEX: string;
              }
            ).__BLOCKSTUDIO_INDEX,
          ) -
          order.indexOf(
            (
              b as unknown as {
                __BLOCKSTUDIO_INDEX: string;
              }
            ).__BLOCKSTUDIO_INDEX,
          ),
      );

      set(
        newAttributes,
        key,
        sortedValues.map((obj) => ({
          ...obj,
          __BLOCKSTUDIO_INDEX: undefined,
        })),
      );

      setRepeater(newAttributes, getRepeaterKey(id));
    };

    const add: BlockstudioFieldsRepeaterAddRemove = (id) => {
      isDeleting = false;
      const key = `blockstudio.attributes.${id}`;
      const newAttributes = JSON.parse(JSON.stringify(attributes));
      const values = result(
        newAttributes,
        key,
      ) as BlockstudioBlock['blockstudio']['attributes'];

      const obj: Record<string | number, Any> = {};
      transformed.attributes.forEach(
        (e: { id: string | number; default: boolean }) => {
          obj[e.id] = e?.default || false;
        },
      );

      set(newAttributes, key, [...(values || []), obj]);
      setRepeater(newAttributes, getRepeaterKey(id));
    };

    const remove: BlockstudioFieldsRepeaterAddRemove = (id) => {
      isDeleting = true;
      const key = `blockstudio.attributes.${id}`;
      const newAttributes = JSON.parse(JSON.stringify(attributes));
      unset(newAttributes, key);

      const outerRepeater = key.replace(/\[\d+\]$/, '');

      const filteredAttributes = (
        (result(newAttributes, outerRepeater) || []) as Any[]
      ).filter((e: Any) => e);

      set(newAttributes, outerRepeater, filteredAttributes);

      if (filteredAttributes.length === 0) {
        set(newAttributes, outerRepeater, false);
      }

      setRepeater(newAttributes, getRepeaterKey(id));
    };

    const duplicate: BlockstudioFieldsRepeaterAddRemove = (id) => {
      const key = `blockstudio.attributes.${id}`;
      const newAttributes = cloneDeep(attributes);
      const attributeToDuplicate = get(newAttributes, key);
      const duplicatedAttribute = cloneDeep(attributeToDuplicate);
      const outerRepeater = key.replace(/\[\d+\]$/, '');
      const repeaterAttributes = (result(newAttributes, outerRepeater) ||
        []) as Any[];
      const attributeIndex = repeaterAttributes.findIndex(
        (attr: Any) => attr.id === id,
      );
      repeaterAttributes.splice(attributeIndex + 1, 0, duplicatedAttribute);
      set(newAttributes, outerRepeater, repeaterAttributes);
      const repeaterKey = getRepeaterKey(id);
      setRepeater(newAttributes, repeaterKey);
    };

    const allProps = {
      ...props,
      value: v,
      onChange: (value: string) => change(value),
    } as Any;

    const textProps = () => {
      const { min: _min, max: _max, ...rest } = allProps;

      return rest;
    };

    const optionSetter = () => {
      const transform = transformedOptions?.map((e: Any) => {
        return {
          value: e?.value || e,
          label: e?.label || e,
          disabled: e?.disabled || false,
          innerBlocks: e?.innerBlocks || false,
        };
      });

      return {
        options:
          item.type === 'select' && item?.allowNull
            ? [...[{ value: '', label: item?.allowNull || '' }], ...transform]
            : transform,
      };
    };

    const optionProps = () => {
      return {
        ...allProps,
        ...optionSetter(),
      };
    };

    if (extensions && portal) {
      if (item.type === 'code') {
        return (
          <Code
            {...textProps()}
            {...{ clientId, extensions, repeaterId }}
            properties={props}
            item={item}
            inRepeater={repeaterId !== ''}
          />
        );
      }
      return null;
    }

    // Get actions wrapper for field types that need it
    const ActionsWrapper = item.type === 'code' ? CodeActions : null;

    const controlContent = (existingActions?: LabelAction[]) => {
      const isDisabled = attributes.blockstudio?.disabled?.includes(
        item.id ?? '',
      );

      const switchAction: LabelAction[] =
        item?.switch === true && repeaterId === ''
          ? [
              {
                icon: isDisabled ? unseen : seen,
                onClick: () => disable(item.id ?? ''),
                label: isDisabled ? 'Enable field' : 'Disable field',
              },
            ]
          : [];

      const actions = [...(existingActions || []), ...switchAction];

      return (
        <Control
          active={!isDisabled}
          actions={actions.length > 0 ? actions : undefined}
          className={`blockstudio-fields__field blockstudio-fields__field--${item.type}`}
          enabled={false}
          help={item.help}
          inRepeater={repeaterId !== ''}
          isRepeater={item.type === 'repeater'}
          label={item.label}
          name={item.id}
          description={item.description}
          onClick={() => disable(item.id ?? '')}
          remove={() => remove(repeaterId)}
          type={item.type}
        >
        {item.type === 'text' ? (
          <Text {...textProps()} properties={props} />
        ) : item.type === 'textarea' ? (
          <Textarea {...textProps()} properties={props} />
        ) : item.type === 'number' ? (
          <NumberField {...allProps} />
        ) : item.type === 'unit' ? (
          <Unit {...allProps} />
        ) : item.type === 'toggle' ? (
          <Toggle {...allProps} checked={v} />
        ) : item.type === 'range' ? (
          <Range {...allProps} />
        ) : item.type === 'select' ? (
          <Select
            {...{
              ...optionProps(),
              attributes,
              change,
              disable,
              item,
              v,
            }}
            value={val}
            inRepeater={repeaterId !== ''}
          />
        ) : item.type === 'checkbox' ? (
          <Checkbox {...{ ...optionSetter(), item, change, v }} />
        ) : item.type === 'radio' ? (
          <Radio {...{ ...props, ...optionSetter(), item, val, change }} />
        ) : // @ts-ignore
        item.type === 'token' ? (
          <Token {...{ ...allProps, item, transformedOptions }} />
        ) : item.type === 'color' ? (
          <Color {...{ ...allProps, item, transformedOptions }} value={val} />
        ) : item.type === 'gradient' ? (
          <Gradient
            {...{ ...allProps, item, transformedOptions }}
            value={val}
          />
        ) : item.type === 'files' ? (
          <Files
            {...{
              ...props,
              attributes,
              change,
              config,
              disable,
              item,
              repeaterId,
              transformed,
              v,
            }}
            inRepeater={repeaterId !== ''}
          />
        ) : item.type === 'date' ? (
          <Date {...{ item, v, change }} />
        ) : item.type === 'datetime' ? (
          <Datetime {...{ ...props, item, v, change }} />
        ) : item.type === 'link' ? (
          <Base>
            <Link {...{ ...props, item, change }} value={v} />
          </Base>
        ) : item.type === 'icon' ? (
          <Icon {...({ item, change } as Any)} value={v} />
        ) : item.type === 'repeater' ? (
          <Repeater
            {...{
              add,
              attributes,
              block,
              duplicate,
              element,
              item,
              remove,
              sort,
              transformed,
              v,
            }}
            id={repeaterId}
            context={repeaterId === ''}
          />
        ) : item.type === 'wysiwyg' ? (
          <WYSIWYG {...textProps()} properties={props} item={item} />
        ) : item.type === 'code' ? (
          <Code
            {...textProps()}
            {...{ clientId, extensions, repeaterId }}
            item={item}
            inRepeater={repeaterId !== ''}
          />
        ) : item.type === 'classes' ? (
          <Classes
            {...{ attributes, setAttributes, clientId }}
            attributeId={item.id}
            keyName={`${key}${item.id}`}
            label=""
            tailwind={item.tailwind}
            value={v || ''}
          />
        ) : item.type === 'attributes' ? (
          <Attributes
            {...{ attributes, setAttributes }}
            keyName={`${key}${item.id}`}
            link={item.link ?? false}
            media={item.media ?? false}
          />
        ) : null}
      </Control>
      );
    };

    if (ActionsWrapper) {
      return (
        <ActionsWrapper item={item}>
          {({ actions }) => controlContent(actions)}
        </ActionsWrapper>
      );
    }

    return controlContent();
  };

  return (
    <>
      {!portal && <Styles />}
      <div
        className={`blockstudio-fields blockstudios-space`}
        css={css({
          background: '#fff',
          '.components-panel__body:empty': {
            display: 'none',
          },
        })}
      >
        {block.blockstudio?.attributes?.map(
          (item: BlockstudioAttribute, index: number) => {
            if (!isAllowedToRender(item, attributes, false, defaults))
              return false;
            if (item.type === 'tabs' && index !== 0) return null;

            return (
              <El
                key={`field-${index}`}
                {...{ item, element, attributes, block, defaults, portal }}
              />
            );
          },
        )}
      </div>
    </>
  );
};
