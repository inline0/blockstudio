import { useRef, KeyboardEvent } from 'react';
import {
  TextControl,
  __experimentalScrollable as Scrollable,
} from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';
import { useEffect, useLayoutEffect, useState } from '@wordpress/element';
import { Icon, search as searchIcon } from '@wordpress/icons';
import { colors } from '@/admin/const/colors';
import { Tree } from '@/editor/components/List/Tree';
import { selectors } from '@/editor/store/selectors';
import { deepSearch } from '@/editor/utils/deepSearch';
import { findAllByKey } from '@/editor/utils/findAllByKey';
import { BlockstudioEditorFileStructure } from '@/type/types';
import { __ } from '@/utils/__';
import { css } from '@/utils/css';

export const Table = ({
  blocks,
  padding = '',
  searchInput = '',
}: {
  blocks: BlockstudioEditorFileStructure;
  padding?: string;
  searchInput?: string;
}) => {
  const itemRefs = useRef({});
  const [folders, setFolders] = useState([]);
  const [searchInputInner, setSearchInputInner] = useState('');
  const [searchedBlocks, setSearchedBlocks] = useState([]);
  const { setBlocks, setTreeOpen } = useDispatch('blockstudio/editor');
  const isEditor = useSelect(
    (select) => (select('blockstudio/editor') as typeof selectors).isEditor(),
    [],
  );
  const tree = useSelect(
    (select) => (select('blockstudio/editor') as typeof selectors).getTree(),
    [],
  );

  const addItemRef = (id: string, element: HTMLElement) => {
    itemRefs.current[id] = element;
  };

  const focusElementById = (id: string | number) => {
    const element = itemRefs.current[id];
    if (element) {
      element.focus();
    }
  };

  const handleKeyDown = (event: KeyboardEvent, currentId: string) => {
    const ids = Object.keys(itemRefs.current);
    const currentIndex = ids.indexOf(currentId.toString());

    if (currentIndex === -1) return;

    let nextId: string, prevId: string;

    switch (event.key) {
      case 'ArrowUp':
        prevId = ids[currentIndex - 1];
        if (prevId) focusElementById(prevId);
        break;
      case 'ArrowDown':
        nextId = ids[currentIndex + 1];
        if (nextId) focusElementById(nextId);
        break;
      default:
        break;
    }
  };

  const search = () => {
    const input = isEditor ? searchInputInner : searchInput;

    let search = deepSearch(blocks, folders, input);
    if ((search as []).length === 0 && input !== '') {
      search = false as unknown as [];
    }
    setSearchedBlocks(search as []);
  };

  useEffect(() => {
    set(blocks);

    // console.log('blocks:', blocks);
  }, [blocks]);

  useEffect(() => {
    if (searchInputInner === '' && searchInput === '') {
      setTreeOpen(false);
    } else {
      setTreeOpen(true);
    }

    search();
  }, [isEditor, searchInput, searchInputInner]);

  useLayoutEffect(() => {
    Object.keys(itemRefs.current).forEach((id) => {
      if (!document.body.contains(itemRefs.current[id])) {
        delete itemRefs.current[id];
      }
    });

    const allNodes = Array.from(
      document.querySelectorAll('#blockstudio-table [id]'),
    );
    const sortedIds = [];

    allNodes.forEach((node) => {
      const id = node.getAttribute('id');
      if (itemRefs.current[id]) {
        sortedIds.push(id);
      }
    });

    const sortedRefs = {};
    for (const id of sortedIds) {
      sortedRefs[id] = itemRefs.current[id];
    }

    itemRefs.current = sortedRefs;
  }, [tree]);

  const set = (b: object) => {
    setBlocks(b);
    const folders = [
      ...new Set([
        'children',
        ...[...new Set(findAllByKey(b, 'instance'))],
        ...[...new Set(findAllByKey(b, 'structureArray'))],
        ...[...new Set(findAllByKey(b, 'basename'))],
      ]),
    ].filter((n) => n) as string[];
    setFolders(folders);
    setSearchedBlocks(deepSearch(blocks, folders));
  };

  return (
    <div
      id={`blockstudio-table`}
      css={css({
        height: '100%',
        borderRight: isEditor && `1px solid ${colors.gray[600]}`,
        '*': {
          userSelect: 'none',
        },
        'li[id], [tabindex="0"]': {
          '&:focus-visible': {
            outline: 'none',
            boxShadow: `0 0 0 2px ${
              isEditor ? colors.highlight : colors.primary
            }`,
            borderRadius: '2px',
          },
        },
      })}
    >
      {isEditor && (
        <div
          css={css({
            position: 'relative',
            display: 'grid',
            alignItems: 'center',
            gridTemplateColumns: '1fr auto',
            gap: '8px',
            height: '32px',
            padding: '0 6px 0 32px',
            boxShadow: `inset 0 -1px 0 0 ${colors.gray[600]}`,

            '.components-base-control': {
              width: '100%',
            },

            '.components-base-control__field': {
              marginBottom: '0px',
            },

            '.components-text-control__input': {
              background: 'transparent',
              minHeight: '24px',
              padding: '2px 8px',
              border: '1px solid transparent',
              color: '#fff',
              width: '100%',

              '&::placeholder': {
                color: colors.gray[400],
              },

              '&:focus': {
                border: `1px solid ${colors.primary}`,
              },
            },
          })}
        >
          <Icon
            icon={searchIcon}
            size={16}
            css={css({
              position: 'absolute',
              left: '16px',
              top: '50%',
              transform: 'translateY(-50%)',
              fill: colors.gray[400],
            })}
          />
          <TextControl
            value={searchInputInner}
            onChange={setSearchInputInner}
            placeholder={__('File search')}
            autoComplete="off"
          />
        </div>
      )}
      <Scrollable
        css={css({
          height: 'calc(100% - 64px)',
          padding: isEditor ? '16px 0' : '8px 16px 32px 14px',
        })}
        scrollDirection={isEditor ? 'auto' : 'x'}
      >
        <div
          css={css({
            padding,
            display: 'grid',
            gap: '12px',
            paddingLeft: isEditor ? '12px' : '0',
            '&:empty': {
              paddingLeft: isEditor ? '12px' : '24px',

              '::after': {
                content: '"No files found"',
                color: isEditor ? '#fff' : 'inherit',
              },
            },
          })}
        >
          {blocks &&
            Object.values(blocks as unknown).map((item, index) => {
              return (
                <Tree
                  key={`tree-${index}`}
                  {...{
                    addItemRef,
                    folders,
                    handleKeyDown,
                    item,
                    searchedBlocks,
                  }}
                />
              );
            })}
        </div>
      </Scrollable>
    </div>
  );
};
