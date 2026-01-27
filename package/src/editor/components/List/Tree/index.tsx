import { KeyboardEvent } from 'react';
import { __experimentalText as Text } from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { useRef } from '@wordpress/element';
import { colors } from '@/admin/const/colors';
import { Add } from '@/editor/components/List/Add';
import { Chevron } from '@/editor/components/List/Chevron';
import { Item } from '@/editor/components/List/Item';
import { selectors } from '@/editor/store/selectors';
import { findAllByKey } from '@/editor/utils/findAllByKey';
import { isAllowedToRender } from '@/editor/utils/isAllowedToRender';
import { BlockstudioEditorBlock } from '@/type/types';
import { css } from '@/utils/css';

export const Tree = ({
  addItemRef,
  folders,
  handleKeyDown,
  item,
  last,
  searchedBlocks,
}: {
  addItemRef: (key: string, element: HTMLElement) => void;
  folders: string[];
  handleKeyDown: (e: KeyboardEvent, identifier: string) => void;
  item: {
    instance: string;
    prefix?: string;
    children: object[];
    path: string;
  };
  last?: boolean;
  searchedBlocks: string[];
}) => {
  const { setTree, setContextMenu } = useDispatch('blockstudio/editor');
  const contextMenu = useSelect(
    (select) =>
      (select('blockstudio/editor') as typeof selectors).getContextMenu(),
    [],
  );
  const isEditor = useSelect(
    (select) => (select('blockstudio/editor') as typeof selectors).isEditor(),
    [],
  );
  const tree = useSelect(
    (select) => (select('blockstudio/editor') as typeof selectors).getTree(),
    [],
  );
  const treeOpen = useSelect(
    (select) => (select('blockstudio/editor') as typeof selectors).isTreeOpen(),
    [],
  );

  const identifier = 'instance-' + item.instance.replaceAll('/', '-');

  const pathsRef = useRef(null);
  if (!pathsRef.current) {
    pathsRef.current = findAllByKey(item, 'path');
  }
  const paths = pathsRef.current;

  const hasChildren =
    Object.keys(item.children).filter((k) => k !== '.').length >= 1;

  const onRightClick = (e, right = false) => {
    e.preventDefault();
    e.stopPropagation();
    setContextMenu({
      allowDelete: false,
      block: false,
      path: item.path,
      right,
      type: false,
      x: e.pageX - window.scrollX,
      y: e.pageY - window.scrollY,
    });
  };

  if (!isAllowedToRender(searchedBlocks, paths)) return null;

  return (
    <div
      id={`tree-${item.instance.replaceAll('/', '-')}`}
      key={`tree-${item.instance}`}
    >
      <div
        css={css({
          position: 'relative',
        })}
      >
        <span
          ref={(element) => addItemRef(identifier, element)}
          id={identifier}
          css={css({
            display: 'grid',
            alignItems: 'center',
            gridTemplateColumns: '18px 1fr',
            cursor: 'pointer',
            '&:hover span span:last-child': {
              color:
                (isEditor ? colors.highlight : colors.primary) + '!important',
            },
            '&:hover > .blockstudio-add': {
              display: 'block',
            },
            '&:hover *:not(.blockstudio-add *)': {
              fill: isEditor ? colors.highlight : colors.primary,
              color: isEditor ? colors.highlight : colors.primary,
            },
          })}
          role="button"
          onClick={() => hasChildren && setTree(item.instance)}
          onContextMenu={onRightClick}
          onKeyDown={(e) => {
            switch (e.key) {
              case 'ArrowLeft':
                tree.includes(item.instance) && setTree(item.instance);
                break;
              case 'ArrowRight':
                !tree.includes(item.instance) && setTree(item.instance);
                break;
            }

            handleKeyDown(e, identifier);
          }}
          tabIndex={0}
        >
          <Add onClick={(e) => onRightClick(e, true)} />
          {hasChildren ? <Chevron path={item.instance} /> : <div />}
          <Text
            weight={700}
            css={css({
              color: isEditor ? '#fff' : '#00',
              whiteSpace: 'nowrap',
              paddingRight: '16px',
              display: 'flex',
            })}
          >
            <span
              css={css({
                display: 'flex',
                alignItems: 'center',
                textDecoration:
                  contextMenu.path === item.path &&
                  contextMenu.x !== 0 &&
                  contextMenu.y !== 0
                    ? 'underline'
                    : 'none',
                fontStyle:
                  contextMenu.path === item.path &&
                  contextMenu.x !== 0 &&
                  contextMenu.y !== 0
                    ? 'italic'
                    : 'normal',
              })}
            >
              {item.instance}
              {item.prefix ? ` (prefix: ${item.prefix})` : ''}
            </span>
          </Text>
        </span>
      </div>
      {hasChildren && (treeOpen || tree.includes(item.instance)) && (
        <ul
          css={css({
            marginTop: '12px',
            margin: last ? '8px 0 0 0' : '8px 0',
          })}
        >
          {Object.entries(item.children)
            .filter(([k]) => k !== '.')
            .map(([k, v], index) => {
              return (
                <Item
                  path={item.path}
                  key={`item-${
                    (v as BlockstudioEditorBlock)?.path || k
                  }-${index}`}
                  item={v as BlockstudioEditorBlock}
                  objectKey={k}
                  index={0}
                  searchedBlocks={searchedBlocks}
                  instance={item.instance}
                  {...{ folders, addItemRef, handleKeyDown }}
                />
              );
            })}
        </ul>
      )}
    </div>
  );
};
