import { Draggable } from '@hello-pangea/dnd';
import { Button } from '@wordpress/components';
import {
  Fragment,
  createContext,
  useContext,
  useEffect,
  useRef,
  useState,
} from '@wordpress/element';
import { Icon, closeSmall, chevronDown, create } from '@wordpress/icons';
import { result } from 'lodash-es';
import { Base } from '@/blocks/components/base';
import { List } from '@/blocks/components/list';
import { isAllowedToRender } from '@/blocks/utils/is-allowed-to-render';
import { BlockstudioAttribute } from '@/types/block';
import {
  Any,
  BlockstudioBlock,
  BlockstudioBlockAttributes,
  BlockstudioFieldsElement,
  BlockstudioFieldsListMove,
  BlockstudioFieldsRepeaterAddRemove,
  BlockstudioFieldsRepeaterSort,
} from '@/types/types';
import { __ } from '@/utils/__';
import { css } from '@/utils/css';

const border = '1px dashed #b9b9b9';
const postId = window.blockstudioAdmin?.postId;

// Shared per-tree registry of each list's rows, keyed by droppableId. Nested
// repeaters render without their own DragDropContext, so the top-level list's
// onDragEnd must resolve any descendant list. Scoping the registry to the
// top-level repeater's React subtree keeps separate block instances isolated.
const RepeaterRegistryContext = createContext<Record<string, string[]> | null>(
  null,
);

const getItemStyle = (
  isDragging: boolean,
  draggableStyle: object,
  index: number,
) => ({
  userSelect: 'none',
  margin: `0 -16px`,
  marginTop: index !== 0 && '-1px',
  padding: '16px 16px 16px 16px',
  background: '#fff',
  border,
  borderRight: 'none',
  boxShadow: isDragging ? '0 2px 8px 0 rgba(0,0,0,0.25)' : '',
  height: 'max-content',
  ...draggableStyle,
});

const DragElement = ({
  attributes,
  block,
  draggableId,
  duplicate,
  element,
  getId,
  group,
  index,
  innerId,
  item,
  length,
  moveDown,
  moveUp,
  remove,
  transformed,
}: {
  attributes: BlockstudioBlockAttributes;
  block: BlockstudioBlock;
  draggableId: string;
  duplicate: BlockstudioFieldsRepeaterAddRemove;
  element: BlockstudioFieldsElement;
  getId: (index: number) => string;
  group: string[];
  index: number;
  innerId: string;
  item: Any;
  length: number;
  moveDown?: BlockstudioFieldsListMove;
  moveUp?: BlockstudioFieldsListMove;
  remove: BlockstudioFieldsRepeaterAddRemove;
  transformed: BlockstudioBlock['blockstudio'];
}) => {
  let textMinimized = __('Repeater element') as string;

  if (typeof item?.textMinimized === 'string') {
    textMinimized = item.textMinimized;
  }

  if (item?.textMinimized?.id) {
    let minimizedValue = (
      result(attributes, `blockstudio.attributes.${draggableId}`) as Any
    )?.[item.textMinimized?.id];

    if (minimizedValue && typeof minimizedValue === 'object') {
      const defaultKeys: Record<string, string> = {
        link: 'title',
        icon: 'icon',
        color: 'value',
        gradient: 'value',
      };

      const fieldType = item.attributes?.find(
        (a: Any) => a.id === item.textMinimized.id,
      )?.type;

      const key = item.textMinimized.key || defaultKeys[fieldType] || '';

      if (key) {
        minimizedValue = minimizedValue[key];
      }
    }

    textMinimized = `${item?.textMinimized?.prefix || ''}${
      minimizedValue || item?.textMinimized?.fallback || textMinimized
    }${item?.textMinimized?.suffix || ''}`;
  }

  let storage = localStorage.getItem('blockstudioRepeater')
    ? JSON.parse(localStorage.getItem('blockstudioRepeater') || '')
    : [];
  const id = `${postId}-${block.name}-${getId(index)}`;
  const [isHover, setIsHover] = useState(false);
  const [isMinimized, setIsMinimized] = useState(!!storage.includes(id));

  const repeaterId = draggableId
    .replaceAll('[', '')
    .replaceAll(']', '')
    .replaceAll('.', '');

  const rowAttributes = result(
    attributes,
    `blockstudio.attributes.${draggableId}`,
  );
  const rowBlockAttributes = {
    blockstudio: { attributes: rowAttributes || {} },
  } as BlockstudioBlockAttributes;

  const renderGroup = (item: Any, index: number) => {
    if (!isAllowedToRender(item, rowBlockAttributes, attributes)) {
      return null;
    }

    const transformedAttributes = (transformed?.attributes || []) as Any[];
    const groupAttributes = item?.attributes || [];
    const getGroupTransform = (
      itemInner: BlockstudioAttribute,
      childIndex: number,
      fallback?: Any,
    ) => {
      if (itemInner?.id) {
        return (
          transformedAttributes.find(
            (attribute: BlockstudioAttribute) => attribute.id === itemInner.id,
          ) ||
          fallback ||
          transformedAttributes[childIndex]
        );
      }

      return fallback || transformedAttributes[childIndex];
    };
    const renderGroupAttribute = (
      itemInner: BlockstudioAttribute,
      childIndex: number,
      transform: Any,
    ) => {
      const itemInnerProps = { ...itemInner };

      if (itemInnerProps.type === 'group') {
        if (itemInnerProps.id || !itemInnerProps.attributes?.length) {
          return null;
        }

        if (
          !isAllowedToRender(itemInnerProps, rowBlockAttributes, attributes)
        ) {
          return null;
        }

        return (
          <div
            key={`${draggableId}.group.${index}.${childIndex}`}
            style={itemInnerProps.style}
            className={`blockstudio-space${
              itemInnerProps.class ? ` ${itemInnerProps.class}` : ''
            } `}
          >
            {itemInnerProps.attributes.map((nestedItem, nestedIndex) =>
              renderGroupAttribute(
                nestedItem as unknown as BlockstudioAttribute,
                nestedIndex,
                getGroupTransform(
                  nestedItem as unknown as BlockstudioAttribute,
                  nestedIndex,
                  transform?.attributes?.[nestedIndex],
                ),
              ),
            )}
          </div>
        );
      }

      if (item?.id && itemInnerProps.id) {
        itemInnerProps.id = `${item.id}_${itemInnerProps.id}`;
      }

      if (!isAllowedToRender(itemInnerProps, rowBlockAttributes, attributes)) {
        return null;
      }

      return (
        <Fragment key={`${draggableId}.${itemInnerProps.id}`}>
          {element(
            itemInnerProps,
            `${draggableId}.${itemInnerProps.id}`,
            (transform || itemInnerProps) as unknown as boolean,
          )}
        </Fragment>
      );
    };

    return (
      <Base
        key={`${draggableId}.group.${index}`}
        className="blockstudio-fields__field blockstudio-fields__field--group"
      >
        <div
          style={item.style}
          className={`blockstudio-space${item.class ? ` ${item.class}` : ''} `}
        >
          {groupAttributes.map(
            (itemInner: BlockstudioAttribute, childIndex: number) => {
              return renderGroupAttribute(
                itemInner,
                childIndex,
                getGroupTransform(itemInner, childIndex),
              );
            },
          )}
        </div>
      </Base>
    );
  };

  const getTransformedAttribute = (item: Any, index: number) => {
    const transformedAttributes = transformed?.attributes || [];

    if (item?.id) {
      return (
        transformedAttributes.find(
          (attribute: BlockstudioAttribute) => attribute.id === item.id,
        ) || transformedAttributes[index]
      );
    }

    return transformedAttributes[index];
  };

  return (
    <Draggable
      draggableId={draggableId}
      index={index}
      isDragDisabled={length === 1}
    >
      {(provided, snapshot) => (
        <div
          ref={provided.innerRef}
          {...provided.draggableProps}
          {...provided.dragHandleProps}
          id={repeaterId}
          style={{
            ...{ position: 'relative' },
            ...(getItemStyle(
              snapshot.isDragging,
              provided.draggableProps.style || {},
              index,
            ) as { [key: string]: string }),
          }}
          css={css({
            '&:hover > div, &:focus > div, &:focus-within > div': {
              opacity: 1,
            },
            '&:focus': {
              borderColor: 'var(--wp-admin-theme-color)',
              zIndex: 10,
            },
          })}
          className={`blockstudio-space blockstudio-repeater__item blockstudio-repeater__item--${
            index + 1
          }`}
          onMouseEnter={() => setIsHover(true)}
          onMouseLeave={() => setIsHover(false)}
          onFocus={() => setIsHover(true)}
          onBlur={() => setIsHover(false)}
          onKeyDown={(e) => {
            if (e.key === 'ArrowUp' || e.key === 'ArrowDown') {
              e.preventDefault();
              e.stopPropagation();
            }
            if (e.key === 'ArrowUp') moveUp?.(index, innerId, repeaterId);
            if (e.key === 'ArrowDown') moveDown?.(index, innerId, repeaterId);
          }}
        >
          <div
            css={css({
              position: 'absolute',
              right: '1px',
              top: 0,
              opacity: 0,
              display: snapshot.isDragging && !isHover ? 'none' : 'flex',
              borderBottom: border,
            })}
          >
            {[
              {
                icon: chevronDown,
                className: 'blockstudio-repeater__minimize',
                onClick: () => {
                  if (storage.includes(id)) {
                    storage = storage.filter((item: string) => item !== id);
                  } else {
                    storage.push(id);
                  }
                  localStorage.setItem(
                    'blockstudioRepeater',
                    JSON.stringify(storage),
                  );
                  setIsMinimized(!isMinimized);
                },
                reverse: isMinimized,
                condition: true,
              },
              {
                icon: create,
                className: 'blockstudio-repeater__duplicate',
                onClick: () => {
                  duplicate(getId(index));
                },
                condition: item.max ? length <= item.max : true,
              },
              {
                icon: closeSmall,
                className: 'blockstudio-repeater__remove',
                onClick: () => {
                  if (item?.textRemove === false) {
                    remove(getId(index));
                    return;
                  }

                  if (
                    window.confirm(
                      __(
                        item?.textRemove || 'Do you want to delete this row?',
                        item?.textRemove,
                      ),
                    )
                  ) {
                    remove(getId(index));
                  }
                },
                condition: item?.min ? item.min < length : true,
              },
            ]
              .filter((e) => e.condition)
              .map((item, index) => {
                return (
                  <div
                    tabIndex={0}
                    className={item.className}
                    key={`icon-${index}`}
                    role="button"
                    onClick={item.onClick}
                    css={css({
                      width: '24px',
                      height: '24px',
                      cursor: 'pointer',
                      alignItems: 'center',
                      justifyContent: 'center',
                      zIndex: 50,
                      borderLeft: border,

                      '&:hover, &:focus': {
                        fill: 'var(--wp-admin-theme-color)',
                      },
                    })}
                    onKeyDown={(e) => {
                      if (e.key === 'Enter') item.onClick();
                    }}
                  >
                    <Icon
                      icon={item.icon}
                      css={css({
                        transform: item.reverse ? 'rotate(180deg)' : undefined,
                        position: 'relative',
                        top: item.reverse ? '1px' : undefined,
                      })}
                    />
                  </div>
                );
              })}
          </div>
          <div
            css={css({
              display: isMinimized ? 'none' : 'contents',
            })}
          >
            {group.map((e: Any, index: number) => {
              const transformedAttribute = getTransformedAttribute(e, index);

              if (e.options || transformedAttribute?.options) {
                e = {
                  ...e,
                  options: transformedAttribute?.options || e.options,
                };
              }

              if (e.type === 'group') {
                return renderGroup(e, index);
              }

              return (
                <Fragment key={`${draggableId}.${e.id}`}>
                  {element(
                    e,
                    `${draggableId}.${e.id}`,
                    transformedAttribute as unknown as boolean,
                  )}
                </Fragment>
              );
            })}
          </div>
          <div
            css={css({
              display: !isMinimized ? 'none' : 'contents',
            })}
          >
            {textMinimized.replace('{index}', String(index + 1))}
          </div>
        </div>
      )}
    </Draggable>
  );
};

export const Repeater = ({
  add,
  attributes,
  block,
  context,
  duplicate,
  element,
  id,
  item,
  remove,
  sort,
  transformed,
  v,
}: {
  add: BlockstudioFieldsRepeaterAddRemove;
  attributes: BlockstudioBlockAttributes;
  block: BlockstudioBlock;
  context: boolean;
  duplicate: BlockstudioFieldsRepeaterAddRemove;
  element: BlockstudioFieldsElement;
  id: string;
  item: BlockstudioAttribute;
  remove: BlockstudioFieldsRepeaterAddRemove;
  sort: BlockstudioFieldsRepeaterSort;
  transformed: BlockstudioBlock['blockstudio'];
  v: string[];
}) => {
  const [groups, setGroups] = useState<Any[]>([]);
  const innerId = id === '' ? item.id : id;
  const listId = id || item.id || '';

  // Inherit the ancestor registry when nested; the top-level repeater owns one.
  const parentRegistry = useContext(RepeaterRegistryContext);
  const ownRegistry = useRef<Record<string, string[]>>({});
  const registry = parentRegistry ?? ownRegistry.current;

  useEffect(() => {
    registry[listId] = groups as string[];
    return () => {
      delete registry[listId];
    };
  }, [groups, listId, registry]);

  useEffect(() => {
    if (v?.length) {
      createGroup();
    }
  }, [attributes]);

  const createGroup = () => {
    let newGroups: Any[] = [];
    const attributes = ((item.attributes || []) as Any[]).filter(
      (attribute) => attribute.type !== 'group' || !attribute.id,
    );

    v.forEach(() => {
      newGroups = [...newGroups, attributes];
    });
    setGroups(newGroups);
  };

  const getId = (index: number) => {
    return `${innerId}[${index}]`;
  };

  const ids = groups.map((_, index) => getId(index));

  return (
    <RepeaterRegistryContext.Provider value={registry}>
      <Base
        css={css({
          '& > div > .components-base-control__label': {
            marginBottom: '12px',
          },
        })}
      >
        <List
          {...{ ids, context }}
          repeaters={registry}
          id={listId}
          style={{
            marginLeft: '8px',
          }}
          onChange={(items, repeaterId: string) => sort(items, repeaterId)}
        >
          {({ moveUp, moveDown }) =>
            v &&
            groups
              .filter((e: Any) => e.type !== 'group')
              .map((group: Any, index: number) => {
                const draggableId = getId(index);
                return (
                  <DragElement
                    attributes={attributes}
                    block={block}
                    draggableId={draggableId}
                    duplicate={duplicate}
                    element={element}
                    getId={getId}
                    group={group}
                    index={index}
                    innerId={innerId ?? ''}
                    item={item}
                    length={
                      groups.filter((e: Any) => e.type !== 'group').length
                    }
                    moveDown={moveDown}
                    moveUp={moveUp}
                    remove={remove}
                    transformed={transformed as Any}
                    key={draggableId}
                  />
                );
              })
          }
        </List>
        <Button
          variant="secondary"
          onClick={() => add(innerId ?? '')}
          css={css({
            marginTop: '16px',
            marginLeft: '-4px',
          })}
          disabled={!!(item?.max && item.max <= v?.length)}
        >
          {__(
            item?.textButton || 'Add row',
            item?.textButton as unknown as boolean,
          )}
        </Button>
      </Base>
    </RepeaterRegistryContext.Provider>
  );
};
