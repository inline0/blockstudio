import { Draggable } from '@hello-pangea/dnd';
import {
  __experimentalText as Text,
  Icon,
  Spinner,
} from '@wordpress/components';
import { forwardRef, useState } from '@wordpress/element';
import { closeSmall, file } from '@wordpress/icons';
import { isArray } from 'lodash-es';
import { Control } from '@/blocks/components/control';
import { Label } from '@/blocks/components/list/label';
import { List } from '@/blocks/components/list/index';
import {
  Any,
  BlockstudioBlockAttributes,
  BlockstudioFieldsListMove,
  BlockstudioFieldsRepeaterAddRemove,
} from '@/types/types';
import { css } from '@/utils/css';

const grid = 8;

const getItemStyle = (isDragging: boolean, draggableStyle: object) => ({
  background: '#fff',
  border: 'var(--blockstudio-border)',
  borderRadius: 'var(--blockstudio-border-radius)',
  boxShadow: isDragging ? '0 2px 8px 0 rgba(0,0,0,0.25)' : '',
  margin: `0 0 ${grid}px 0`,
  overflow: 'hidden',
  userSelect: 'none',
  ...draggableStyle,
});

export const Card = forwardRef<
  HTMLDivElement,
  {
    attributes: BlockstudioBlockAttributes;
    change: (val: string[], media: boolean) => void;
    data: {
      id?: string;
      label?: string;
      media_type?: string;
      mime_type?: string;
      slug?: string;
      source_url?: string;
      value?: string;
      alt_text?: string;
    };
    id: string;
    inRepeater: boolean;
    index?: number;
    loaded: boolean;
    moveDown?: BlockstudioFieldsListMove;
    moveUp?: BlockstudioFieldsListMove;
    onClick: (name: string) => void;
    onClickDelete?: () => void;
    style?: Record<string, string>;
    v: {
      [key: string]: Any;
    };
  }
>(
  (
    {
      attributes,
      change,
      data,
      id,
      inRepeater,
      index,
      loaded,
      moveDown,
      moveUp,
      onClick,
      onClickDelete,
      v,
      ...rest
    },
    ref,
  ) => {
    const [showDelete, setShowDelete] = useState<boolean>(false);
    const media = data?.media_type;
    const isImage = media === 'image';
    const name = loaded ? `${id}_${data.id || data.value}` : '';

    const handleClick = () => {
      if (!loaded) return;
      if (onClickDelete) {
        onClickDelete();
        return;
      }

      if (showDelete) {
        const filteredIds = isArray(v)
          ? v.filter(
              (e) =>
                e !== data?.id && (media ? true : e?.value !== data?.value),
            )
          : [];
        const transformedIds = (
          filteredIds.length === 0 ? false : filteredIds
        ) as string[];
        change(transformedIds, !media);
      }
    };

    return (
      <div
        {...rest}
        ref={ref}
        css={css({
          '&:focus': {
            borderColor: 'var(--wp-admin-theme-color)',
            boxShadow: '0 0 0 1px var(--wp-admin-theme-color)',
          },
        })}
        onKeyDown={(e) => {
          e.preventDefault();
          if (e.key === 'ArrowUp') moveUp?.(index ?? 0);
          if (e.key === 'ArrowDown') moveDown?.(index ?? 0);
        }}
      >
        <Control
          active={!attributes.blockstudio?.disabled?.includes(name)}
          onClick={
            loaded
              ? () => {
                  onClick(name);
                }
              : () => {}
          }
          margin={false}
          customCss={{
            padding: `6px 8px 6px ${media ? `10px` : `8px`}`,
          }}
          customCssInner={{
            display: 'flex',
            alignItems: 'center',
          }}
          enabled={!!media}
          {...{ inRepeater }}
        >
          <div
            tabIndex={0}
            role="button"
            onMouseEnter={() => setShowDelete(true)}
            onMouseLeave={() => setShowDelete(false)}
            onFocus={() => setShowDelete(true)}
            onBlur={() => setShowDelete(false)}
            onKeyDown={(e) => {
              if (!showDelete) return;
              if (e.key === 'Enter') handleClick();
            }}
            onClick={handleClick}
            css={css({
              width: `${grid * 3}px`,
              height: `${grid * 3}px`,
              borderRadius: 'var(--blockstudio-border-radius)',
              marginRight: `${grid * 1.5}px`,
              overflow: 'hidden',
              border: loaded && (isImage || !media) ? '1px solid #eeeeee' : undefined,
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
              flexShrink: '0',
              zIndex: 50,
              position: 'relative',
              cursor: showDelete ? 'pointer' : undefined,

              '&:focus': {
                borderColor: 'var(--wp-admin-theme-color)',
                boxShadow: '0 0 0 1px var(--wp-admin-theme-color)',
              },
            })}
            className={`blockstudio-fields__field--files-toggle`}
          >
            {loaded ? (
              <div
                css={css({
                  display: 'flex',
                  alignItems: 'center',
                  justifyContent: 'center',
                  opacity: showDelete ? 1 : !media ? 0.25 : undefined,
                })}
              >
                {media ? (
                  showDelete ? (
                    <Icon icon={closeSmall} />
                  ) : isImage ? (
                    <img
                      src={data.source_url}
                      alt={data.alt_text || ''}
                      css={css({
                        objectFit: 'cover',
                        height: '100%',
                        width: '100%',
                      })}
                    />
                  ) : (
                    <Icon icon={file} />
                  )
                ) : (
                  <Icon icon={closeSmall} />
                )}
              </div>
            ) : (
              <Spinner
                css={css({
                  margin: '0',
                })}
              />
            )}
          </div>
          {loaded ? (
            <div
              css={css({
                display: 'grid',
                gridTemplateColumns: 'minmax(0, 1fr) auto',
                justifyContent: 'space-between',
                alignItems: 'center',
                width: '100%',
              })}
            >
              <Text truncate numberOfLines={1}>
                {data?.slug || data?.label || data?.value}
              </Text>
              {media && (
                <div
                  css={css({
                    textTransform: 'uppercase',
                    padding: '4px 6px',
                    background: 'rgba(0,0,0,0.1)',
                    marginLeft: '8px',
                    lineHeight: 0,
                  })}
                >
                  <Label>{media}</Label>
                </div>
              )}
            </div>
          ) : (
            !data?.value && (data as unknown as string)
          )}
        </Control>
      </div>
    );
  },
);

export const Cards = ({
  attributes,
  change,
  disable,
  ids,
  inRepeater,
  item,
  media = false,
  style = {},
  v,
}: {
  attributes: BlockstudioBlockAttributes;
  change: (val: string[], media: boolean) => void;
  disable: BlockstudioFieldsRepeaterAddRemove;
  ids: string[];
  inRepeater: boolean;
  item: {
    id?: string;
    value?: string;
  };
  media?:
    | Record<
        string,
        {
          [key: string]: string;
        }
      >
    | boolean;
  style?: Record<string, string>;
  v: {
    [key: string]: Any;
  };
}) => {
  return v?.length || v ? (
    <List {...{ style, ids }} onChange={(val) => change(val, !media)}>
      {({ moveUp, moveDown }) =>
        (v.length ? v : [v]).map(
          (itemInner: string | number, index: number) => {
            const el = (media as Record<string, Any>)?.[itemInner] ?? itemInner;
            const id = `${v[index]?.value || v[index]}`;

            return (
              <Draggable
                {...{ index }}
                key={id}
                draggableId={id}
                isDragDisabled={!v?.length}
              >
                {(provided, snapshot) => (
                  <Card
                    ref={provided.innerRef}
                    {...provided.draggableProps}
                    {...provided.dragHandleProps}
                    {...{
                      attributes,
                      change,
                      inRepeater,
                      index,
                      moveDown,
                      moveUp,
                      v,
                    }}
                    data={el}
                    id={item.id || item.value || ''}
                    loaded={media ? el?.mime_type : true}
                    onClick={(id) => disable(id)}
                    style={getItemStyle(
                      snapshot.isDragging,
                      provided.draggableProps.style || {},
                    )}
                  />
                )}
              </Draggable>
            );
          },
        )
      }
    </List>
  ) : null;
};
