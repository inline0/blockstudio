import { ReactNode } from 'react';
import { DragDropContext, Droppable } from '@hello-pangea/dnd';
import { ConditionalWrapper } from '@/components/conditional-wrapper';
import { BlockstudioFieldsListMove } from '@/types/types';
import { css } from '@/utils/css';

export const List = ({
  children,
  context = true,
  id = 'droppable',
  ids,
  onChange,
  repeaters = {},
  style = {},
}: {
  children:
    | ReactNode
    | (({
        moveUp,
        moveDown,
      }: {
        moveUp: (index: number) => void;
        moveDown: (index: number) => void;
      }) => ReactNode);
  context?: boolean;
  id?: string;
  ids?: string[];
  onChange?: (ids: string[], droppableId: string) => void;
  repeaters?: { [key: string]: string[] };
  style?: Record<string, string>;
}) => {
  const repeaterData = (droppableId: string) => {
    return repeaters?.[droppableId]
      ? repeaters[droppableId].map((_: string, i: number) => i)
      : [...(ids || [])];
  };

  const reorder = (
    list: Iterable<unknown> | ArrayLike<unknown>,
    startIndex: number,
    endIndex: number,
  ) => {
    const result = Array.from(list);
    const [removed] = result.splice(startIndex, 1);
    result.splice(endIndex, 0, removed);

    return result;
  };

  const onDragStart = () => {
    (document.activeElement as HTMLButtonElement)?.blur();
  };

  const onDragEnd = (result: {
    destination: { droppableId: string; index: number } | null;
    source: { index: number };
  }) => {
    const droppableId = result?.destination?.droppableId || '';

    if (!result.destination) {
      return;
    }

    const data = repeaterData(droppableId);
    const newItems = reorder(
      data,
      result.source.index,
      result.destination.index,
    ) as string[];

    onChange?.(newItems, droppableId || '');
  };

  const reorderAndFocus = (
    index: number,
    moveDirection: 'up' | 'down',
    droppableId = '',
    ref: string | null = null,
  ) => {
    const offset = moveDirection === 'up' ? -1 : 1;
    if (
      (moveDirection === 'up' && index <= 0) ||
      (moveDirection === 'down' && index >= (ids?.length || 0) - 1)
    ) {
      return;
    }

    const data = droppableId ? repeaterData(droppableId) : ids || [];
    const newItems = reorder(data, index, index + offset);
    onChange?.(newItems as string[], droppableId);

    const focusIndex = index + offset;
    (
      document.querySelector(
        `#${ref?.slice(0, -1)}${focusIndex}`,
      ) as HTMLElement
    )?.focus();
  };

  const moveUp: BlockstudioFieldsListMove = (
    index,
    droppableId = '',
    ref = null,
  ) => {
    reorderAndFocus(index, 'up', droppableId, ref);
  };

  const moveDown: BlockstudioFieldsListMove = (
    index: number,
    droppableId = '',
    ref: string | null = null,
  ) => {
    reorderAndFocus(index, 'down', droppableId, ref);
  };

  return ids?.length || ids ? (
    <ConditionalWrapper
      condition={context}
      wrapper={(children) => (
        <DragDropContext onDragEnd={onDragEnd} onDragStart={onDragStart}>
          {children}
        </DragDropContext>
      )}
    >
      <Droppable droppableId={id} type={id}>
        {(provided) => (
          <div
            {...provided.droppableProps}
            ref={provided.innerRef}
            css={css({
              ...style,
            })}
          >
            {typeof children === 'function'
              ? children({ moveUp, moveDown })
              : children}
            {provided.placeholder}
          </div>
        )}
      </Droppable>
    </ConditionalWrapper>
  ) : null;
};
