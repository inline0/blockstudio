import { MouseEvent, KeyboardEvent, ReactNode } from 'react';
import { __experimentalText as Text } from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { useRef } from '@wordpress/element';
import { file, Icon } from '@wordpress/icons';
import { colors } from '@/admin/const/colors';
import { Add } from '@/editor/components/List/Add';
import { Chevron } from '@/editor/components/List/Chevron';
import { FILE_TYPES } from '@/editor/const/fileTypes';
import { useOpen } from '@/editor/hooks/useOpen';
import { selectors } from '@/editor/store/selectors';
import { findAllByKey } from '@/editor/utils/findAllByKey';
import { isAllowedToRender } from '@/editor/utils/isAllowedToRender';
import { BlockstudioEditorBlock } from '@/type/types';
import { css } from '@/utils/css';

const id = (
  type: string,
  data: BlockstudioEditorBlock | string,
  absolute = false,
) => {
  const replacer = (str: string) =>
    str.replaceAll('/', '-').replaceAll('.', '-').toLowerCase();

  if (absolute) {
    return replacer(`${type}-${data}`);
  }

  const arr = [
    (data as BlockstudioEditorBlock).instance,
    ...(data as BlockstudioEditorBlock).structureArray,
    (data as BlockstudioEditorBlock).file.basename,
  ].filter((e) => e);

  return replacer(`${type}-${arr.join('-')}`);
};

const TreeItem = ({
  addItemRef,
  block = null,
  handleKeyDown,
  index,
  instance,
  item,
  ml,
  path,
  type = 'file',
}: {
  addItemRef: (key: string, element: HTMLElement) => void;
  block: BlockstudioEditorBlock;
  handleKeyDown: (e: KeyboardEvent, id: string) => void;
  index: number;
  instance: string;
  item: {
    text: string;
    icon: ReactNode;
  };
  ml: boolean;
  path: string;
  type?: string;
}) => {
  const isEmptyDirectory =
    Object.values(block).filter((e) => e?.[0] !== '.').length === 0;

  const { setContextMenu } = useDispatch('blockstudio/editor');
  const { setTree } = useDispatch('blockstudio/editor');
  const contextMenu = useSelect(
    (select) =>
      (select('blockstudio/editor') as typeof selectors).getContextMenu(),
    [],
  );
  const errors = useSelect(
    (select) => (select('blockstudio/editor') as typeof selectors).getErrors(),
    [],
  );
  const isEditor = useSelect(
    (select) => (select('blockstudio/editor') as typeof selectors).isEditor(),
    [],
  );
  const filesChanged = useSelect(
    (select) =>
      (select('blockstudio/editor') as typeof selectors).getFilesChanged(),
    [],
  );
  const filePath = useSelect(
    (select) => (select('blockstudio/editor') as typeof selectors).getPath(),
    [],
  );
  const tree = useSelect(
    (select) => (select('blockstudio/editor') as typeof selectors).getTree(),
    [],
  );

  const identifier = block?.name
    ? id(type, block)
    : id(type, instance + path.split(instance)[1], true);

  const onRightClick = (e: MouseEvent, right = false) => {
    e.preventDefault();
    e.stopPropagation();
    setContextMenu({
      allowDelete: type === 'file' || isEmptyDirectory,
      block: block?.[0]?.[0] === '.' ? block[0][1] : block,
      path,
      right,
      type,
      x: e.pageX - window.scrollX,
      y: e.pageY - window.scrollY,
    });
  };

  return (
    <span
      onContextMenu={(e) => onRightClick(e)}
      css={css({
        cursor: isEmptyDirectory ? 'pointer' : 'default',
        position: 'relative',
        display: 'flex',
        alignItems: 'center',
        paddingLeft: type === 'file' && '16px',
        marginLeft: ml && `calc(16px * ${index})`,
        '&:hover > .blockstudio-add': {
          display: 'block',
        },
        '&:hover *:not(.blockstudio-add *)': {
          fill: colors.highlight,
          color: colors.highlight,
        },
      })}
    >
      <Add onClick={(e: MouseEvent) => onRightClick(e, true)} />
      <span
        ref={
          type === 'folder'
            ? (element) => addItemRef(identifier, element)
            : null
        }
        id={identifier}
        role={type === 'folder' && 'button'}
        onClick={
          type === 'folder' && !isEmptyDirectory
            ? () => {
                setTree(path);
              }
            : undefined
        }
        css={css({
          padding: '2px 0',
          paddingLeft: type !== 'folder' && '6px',
          cursor: 'pointer',
          position: 'relative',
          display: 'flex',
          alignItems: 'center',
          width: '100%',
          '& > span:first-of-type': {
            visibility: !isEmptyDirectory ? 'visible' : 'hidden',
          },
        })}
        tabIndex={type === 'folder' ? 0 : undefined}
        onKeyDown={(e) => {
          switch (e.key) {
            case 'ArrowLeft':
              tree.includes(path) && setTree(path);
              break;
            case 'ArrowRight':
              !tree.includes(path) && setTree(path);
              break;
          }

          handleKeyDown(e, identifier);
        }}
      >
        {type === 'folder' && <Chevron path={path} />}
        <span
          css={css({
            flexShrink: 0,
            display: 'flex',
            background: isEditor ? '#1D2327' : '#fff',
            zIndex: 1,
            svg: {
              fill:
                filePath === path && isEditor
                  ? colors.highlight
                  : isEditor
                    ? '#fff'
                    : '',
            },
          })}
        >
          {item.icon}
        </span>
        {item.text && (
          <Text
            title={item.text}
            truncate
            css={css({
              marginLeft: type === 'folder' ? '2px' : ' 6px',
              textDecoration:
                (contextMenu.path === path &&
                  contextMenu.x !== 0 &&
                  contextMenu.y !== 0) ||
                (isEditor && filePath === path)
                  ? 'underline'
                  : 'none',
              textDecorationColor:
                isEditor && filePath === path && colors.highlight,
              textDecorationThickness: '2px',
              fontStyle:
                contextMenu.path === path &&
                contextMenu.x !== 0 &&
                contextMenu.y !== 0
                  ? 'italic'
                  : 'normal',
            })}
            weight={type === 'folder' && 600}
            color={
              errors.includes(path) && isEditor
                ? colors.error
                : filesChanged[path] || filesChanged[path] === ''
                  ? colors.changed
                  : isEditor
                    ? type === 'folder'
                      ? colors.gray[400]
                      : colors.gray[100]
                    : 'rgb(30, 30, 30)'
            }
          >
            {item.text}
          </Text>
        )}
      </span>
    </span>
  );
};

export const Item = ({
  addItemRef,
  folders,
  handleKeyDown,
  index,
  instance,
  item,
  objectKey,
  path,
  searchedBlocks = [],
}: {
  addItemRef: (key: string, element: HTMLElement) => void;
  folders: string[];
  handleKeyDown: (e: KeyboardEvent, id: string) => void;
  index: number;
  instance: string;
  item: BlockstudioEditorBlock;
  objectKey: string;
  path: string;
  searchedBlocks: string[];
}) => {
  if (objectKey === '.') {
    return null;
  }
  const open = useOpen();
  const files = useSelect(
    (select) => (select('blockstudio/editor') as typeof selectors).getFiles(),
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

  const identifier = files[`${path}/${objectKey}`]?.name
    ? id('block', item)
    : id('folder', `${path}/${objectKey}`, true);

  const pathsRef = useRef(null);
  if (!pathsRef.current) {
    pathsRef.current = findAllByKey(item, 'path');
  }
  const paths = pathsRef.current;

  const obj = Object.entries(
    Object.fromEntries(
      Object.entries(item).sort(
        (a: [string, unknown], b: [string, unknown]) =>
          a[0] && a[0].localeCompare(b[0]),
      ),
    ),
  );

  const onClick = () => open(item);

  if (!isAllowedToRender(searchedBlocks, paths)) return null;
  if (item?.['.']?.file?.dirname.endsWith('_dist')) return null;

  return (
    <>
      {!item.name ? (
        <li
          css={css({
            margin: 0,
            position: 'relative',
          })}
        >
          <TreeItem
            block={obj as unknown as BlockstudioEditorBlock}
            item={{
              text: objectKey,
              icon: <Icon css={css({ flexShrink: 0 })} size={20} icon={file} />,
            }}
            ml
            path={`${path}/${objectKey}`}
            type="folder"
            {...{ index, instance, addItemRef, handleKeyDown }}
          />
          <ul css={css({ position: 'relative' })}>
            {obj.map(([k, v], indexInner) => {
              if (k === '_dist') {
                return null;
              }

              return (
                (treeOpen ||
                  tree.includes((v as BlockstudioEditorBlock)?.file?.dirname) ||
                  tree.includes(path + '/' + objectKey)) && (
                  <Item
                    index={index + 1}
                    item={v as BlockstudioEditorBlock}
                    key={`recursive-${
                      (
                        v as {
                          path: string;
                        }
                      )?.path || k
                    }-${indexInner}`}
                    objectKey={k}
                    path={`${path}/${objectKey}`}
                    {...{
                      addItemRef,
                      folders,
                      handleKeyDown,
                      instance,
                      searchedBlocks,
                    }}
                  />
                )
              );
            })}
          </ul>
        </li>
      ) : !searchedBlocks.length || searchedBlocks?.includes(item.structure) ? (
        <li
          ref={(element) => addItemRef(identifier, element)}
          id={identifier}
          onClick={onClick}
          onKeyDown={(e) => {
            if (e.key === 'Enter') {
              onClick();
            }

            handleKeyDown(e, identifier);
          }}
          role="button"
          css={css({
            display: 'grid',
            margin: 0,
            cursor: 'pointer',
          })}
          key={item.path}
          tabIndex={0}
        >
          {[
            {
              icon: (
                <div
                  css={css({
                    position: 'relative',
                    top: '2px',

                    '.file-icon': {
                      fontFamily: 'sans-serif',
                      fontWeight: 300,
                      display: 'inline-block',
                      width: '24px',
                      height: '32px',
                      position: 'relative',
                      borderRadius: '2px',
                      textAlign: 'left',
                      WebkitFontSmoothing: 'antialiased',
                      background: FILE_TYPES.base,
                    },
                    '.file-icon::before': {
                      display: 'block',
                      content: '""',
                      position: 'absolute',
                      top: '0',
                      right: '0',
                      width: '0',
                      height: '0',
                      borderBottomLeftRadius: '2px',
                      borderWidth: '5px',
                      borderStyle: 'solid',
                      borderColor: isEditor
                        ? '#1D2327 #1D2327 rgba(255,255,255,.35) rgba(255,255,255,.35)'
                        : '#fff #fff rgba(255,255,255,.35) rgba(255,255,255,.35)',
                    },
                    '.file-icon::after': {
                      display: 'block',
                      content: 'attr(data-type)',
                      position: 'absolute',
                      bottom: '0',
                      left: '0',
                      fontSize: '10px',
                      color: '#fff',
                      textTransform: 'lowercase',
                      width: '100%',
                      padding: '2px',
                      whiteSpace: 'nowrap',
                      overflow: 'hidden',
                    },
                    '.file-icon-xs': {
                      width: '12px',
                      height: '16px',
                      borderRadius: '2px',
                    },
                    '.file-icon-xs::before': {
                      borderBottomLeftRadius: '1px',
                      borderWidth: '3px',
                    },
                    '.file-icon-xs::after': {
                      content: '""',
                      borderBottom: '2px solid rgba(255,255,255,.45)',
                      width: 'auto',
                      left: '2px',
                      right: '2px',
                      bottom: '3px',
                    },
                    '.file-icon-json': {
                      background: FILE_TYPES['json'],
                    },
                    '.file-icon-php': {
                      background: FILE_TYPES['php'],
                    },
                    '.file-icon-twig': {
                      background: FILE_TYPES['twig'],
                    },
                    '.file-icon-js': {
                      background: FILE_TYPES['js'],
                    },
                    '.file-icon-css': {
                      background: FILE_TYPES['css'],
                    },
                    '.file-icon-scss': {
                      background: FILE_TYPES['scss'],
                    },
                  })}
                >
                  <span
                    className={`file-icon file-icon-xs file-icon-${item?.file?.extension?.replace(
                      '.',
                      '',
                    )}`}
                  />
                </div>
              ),
              text: item.file.basename,
              condition: true,
              type: 'name',
            },
          ].map((itemInner, indexInner) => {
            return (
              itemInner.condition && (
                <TreeItem
                  key={`tree-${item.path}-${indexInner}`}
                  item={itemInner}
                  ml={indexInner === 0}
                  path={item.path}
                  block={item}
                  {...{ index, instance, addItemRef, handleKeyDown }}
                />
              )
            );
          })}
        </li>
      ) : null}
    </>
  );
};
