import { Global } from '@emotion/react';
import apiFetch from '@wordpress/api-fetch';
import { useBlockProps } from '@wordpress/block-editor';
import { Spinner } from '@wordpress/components';
import { useDebounce } from '@wordpress/compose';
import { useDispatch, useSelect } from '@wordpress/data';
import { useEffect, useRef, useState } from '@wordpress/element';
import parse from 'html-react-parser';
import { DomElement } from 'htmlparser2';
import { cloneDeep } from 'lodash-es';
import { MediaPlaceholder } from '@/blocks/components/Block/MediaPlaceholder';
import { checkForBlockProps } from '@/blocks/components/Block/utils/checkForBlockProps';
import { getAttributes } from '@/blocks/components/Block/utils/getAttributes';
import { getInnerHTML as getFirstElementContent } from '@/blocks/components/Block/utils/getFirstElementContent';
import { getRegex } from '@/blocks/components/Block/utils/getRegex';
import { Placeholder } from '@/blocks/components/Placeholder';
import { selectors } from '@/blocks/store/selectors';
import { dispatch } from '@/blocks/utils/dispatch';
import { replaceEmptyStringsWithFalse } from '@/blocks/utils/replaceEmptyStringsWithFalse';
import {
  Any,
  BlockstudioBlock,
  BlockstudioBlockAttributes,
} from '@/types/types';
import { css } from '@/utils/css';
import { BlockProps } from './BlockProps';
import { InnerBlocks } from './InnerBlocks';
import { RichText } from './RichText';

interface CustomEventDetail {
  filesChanged: { [key: string]: Any };
}

interface CustomEvent extends Event {
  detail?: CustomEventDetail;
}

let GLOBAL_INITIAL_LOAD_TIMEOUT: NodeJS.Timeout | null = null;
let LAST_BATCH_SIZE = 0;

export const Block = ({
  attributes,
  block,
  clientId,
  context,
  setAttributes,
}: {
  attributes: BlockstudioBlockAttributes;
  block: BlockstudioBlock;
  clientId: string;
  context: {
    [key: string]: Any;
  };
  setAttributes: (attributes: BlockstudioBlockAttributes) => void;
  response?: {
    rendered: string;
  } | null;
}) => {
  const editor = useSelect(
    (select) => (select('blockstudio/blocks') as typeof selectors).getEditor(),
    []
  );
  const initialLoad = useSelect(
    (select) =>
      (select('blockstudio/blocks') as typeof selectors).getInitialLoad(),
    []
  );
  const initialLoadRendered = useSelect(
    (select) =>
      (
        select('blockstudio/blocks') as typeof selectors
      ).getInitialLoadRendered(),
    []
  );
  const isLoaded = useSelect(
    (select) => (select('blockstudio/blocks') as typeof selectors).isLoaded(),
    []
  );
  const { setInitialLoad, setInitialLoadRendered } =
    useDispatch('blockstudio/blocks');
  const editorRef = useRef(editor);
  const firstRenderDone = useRef<boolean | null>(null);
  const ref = useRef<HTMLDivElement | null>(null);
  const attributesRef = useRef(attributes);
  const [disableLoading, setDisableLoading] = useState(
    block?.blockstudio?.blockEditor?.disableLoading
  );
  const [isInPreview, setIsInPreview] = useState(false);
  const [hasBlockProps, setHasBlockProps] = useState<boolean | null>(false);
  const [hasMarkup, setHasMarkup] = useState(false);
  const [markup, setMarkup] = useState<Any>(null);
  const blockProps = useBlockProps();
  const postId =
    useSelect((select: Any) => select('core/editor')?.getCurrentPostId(), []) ||
    false;
  const postType =
    useSelect(
      (select: Any) => select('core/editor')?.getCurrentPostType(),
      []
    ) || false;

  const loaded = () => {
    setTimeout(() => {
      dispatch(block, 'loaded');
      dispatch(false as unknown as BlockstudioBlock, 'loaded');
      if (!firstRenderDone.current) {
        firstRenderDone.current = true;
      }
    });
  };

  const parseBlock = (response: { rendered: string }) => {
    const transform = (response: string) => {
      response = response.replace(
        getRegex('InnerBlocks'),
        '<div id="blockstudio-replace-innerblocks"></div>'
      );
      response = response.replace(getRegex('RichText', 'gs'), (match) => {
        const innerAttributes = getAttributes(match, 'RichText');
        attributeMap[innerAttributes.attribute] = innerAttributes;

        return `<div id="blockstudio-replace-richtext" class="${innerAttributes.attribute}"></div>`;
      });
      response = response.replace(
        getRegex('MediaPlaceholder', 'gs'),
        (match) => {
          const innerAttributes = getAttributes(match, 'MediaPlaceholder');
          attributeMap[innerAttributes.attribute] = innerAttributes;

          return `<div id="blockstudio-replace-dropzone" class="${innerAttributes.attribute}"></div>`;
        }
      );

      return response;
    };

    const parser = (response: string) => {
      return parse(response, {
        replace: (domNode) => {
          const node = domNode as {
            attribs: Record<string, string>;
          };

          if (
            node.attribs &&
            node.attribs.id === 'blockstudio-replace-innerblocks'
          ) {
            return (
              <InnerBlocks
                {...{
                  getAttributes,
                  blockResponse,
                  clientId,
                }}
                hasOwnBlockProps={hasComponentBlockProps ?? false}
              />
            );
          }
          if (
            node.attribs &&
            node.attribs.id === 'blockstudio-replace-richtext'
          ) {
            return (
              <RichText
                {...{
                  attributes,
                  setAttributes,
                  block,
                  clientId,
                }}
                data={attributeMap[node.attribs.class]}
                hasOwnBlockProps={hasComponentBlockProps ?? false}
              />
            );
          }

          if (
            node.attribs &&
            node.attribs.id === 'blockstudio-replace-dropzone'
          ) {
            return (
              <MediaPlaceholder
                {...{ attributes, block, setAttributes }}
                data={attributeMap[node.attribs.class]}
              />
            );
          }
        },
      });
    };

    const attributeMap: Record<string, Any> = {};
    const blockResponse = (
      response as unknown as {
        rendered: string;
      }
    ).rendered;
    const transformBlockResponse = transform(blockResponse);
    const hasBlockProps = checkForBlockProps(blockResponse);
    const hasComponentBlockProps = checkForBlockProps(blockResponse, true);

    let m: NonNullable<unknown>;

    if (hasBlockProps) {
      m = parse(blockResponse, {
        replace: (domNode) => {
          const node = domNode as DomElement;

          if (node.attribs?.useblockprops === 'true') {
            const content = getFirstElementContent(blockResponse);

            return (
              <BlockProps {...{ node }}>
                {parser(transform(content))}
              </BlockProps>
            );
          }
        },
      });
    } else {
      m = parser(transformBlockResponse);
    }

    setMarkup(m);
    setHasMarkup(true);
    setHasBlockProps(hasBlockProps || hasComponentBlockProps || null);
  };

  const fetchData = (event: CustomEvent | false = false) => {
    const parameters = {
      blockstudioMode: ref.current?.closest(
        '.block-editor-block-preview__content-iframe'
      )
        ? 'preview'
        : 'editor',
      blockstudioEditor:
        editorRef.current?.name &&
        event &&
        Object.keys(event?.detail?.filesChanged || {}).some(
          (e) => e.endsWith('.php') || e.endsWith('.twig')
        )
          ? 'true'
          : 'false',
      postId,
      contextPostId: attributes.blockstudio?.contextBlock?.postId || postId,
      contextPostType:
        attributes.blockstudio?.contextBlock?.postType || postType,
    };

    if (
      !initialLoadRendered?.[clientId] &&
      !event &&
      !block.blockstudio.blockEditor?.disableLoading
    ) {
      setInitialLoad({
        [clientId]: {
          clientId,
          attributes,
          context,
          name: block.name,
          post: parameters,
        },
      });
      return;
    }

    const params = new URLSearchParams({
      ...parameters,
    }).toString();

    apiFetch({
      path: `/blockstudio/v1/gutenberg/block/render/${block.name}?${params}`,
      method: 'POST',
      data: {
        attributes,
        context,
      },
    })
      .then((response) => parseBlock(response as { rendered: string }))
      .then(() => {
        loaded();
      });
  };
  const debouncedFetchData = useDebounce(fetchData, 500);

  useEffect(() => {
    const keysRendered = Object.keys(initialLoadRendered || {});
    const keysLoaded = Object.keys(initialLoad || {});
    const currentBatchSize = keysRendered.length;

    if (keysLoaded.length === keysRendered.length) {
      return;
    }

    if (LAST_BATCH_SIZE === currentBatchSize && LAST_BATCH_SIZE !== 0) {
      return;
    }

    LAST_BATCH_SIZE = currentBatchSize;

    if (GLOBAL_INITIAL_LOAD_TIMEOUT) {
      clearTimeout(GLOBAL_INITIAL_LOAD_TIMEOUT);
    }

    GLOBAL_INITIAL_LOAD_TIMEOUT = setTimeout(() => {
      const unloadedBlocks: Record<string, Any> = {};

      Object.entries(initialLoad || {}).forEach(([key, value]) => {
        if (!initialLoadRendered?.[key]) {
          unloadedBlocks[key] = value;
        }
      });

      if (Object.keys(unloadedBlocks).length === 0) {
        return;
      }

      apiFetch({
        path: `/blockstudio/v1/gutenberg/block/render/all`,
        method: 'POST',
        data: {
          data: unloadedBlocks,
        },
      })
        .then((response) => {
          setInitialLoadRendered(response as { [key: string]: string });
        })
        .finally(() => {
          GLOBAL_INITIAL_LOAD_TIMEOUT = null;
        });
    }, 500);
  }, [initialLoad, initialLoadRendered]);

  useEffect(() => {
    const blockAttributes = replaceEmptyStringsWithFalse(
      cloneDeep(attributes.blockstudio.attributes)
    );

    const blockHash = JSON.stringify({
      blockName: block.name,
      attrs: blockAttributes,
    })
      .replaceAll('{', '_')
      .replaceAll('}', '_')
      .replaceAll('[', '_')
      .replaceAll(']', '_')
      .replaceAll('"', '_')
      .replaceAll('/', '__')
      .replaceAll(' ', '_')
      .replaceAll(',', '_')
      .replaceAll(':', '_');
    const renderedData = window.blockstudio.blockstudioBlocks?.[blockHash];
    if (renderedData) {
      parseBlock({
        rendered: renderedData.rendered,
      });
      loaded();
      firstRenderDone.current = true;
      return;
    }
    if (firstRenderDone.current) return;
    if (initialLoadRendered?.[clientId]) {
      parseBlock({
        rendered: initialLoadRendered[clientId],
      });
      loaded();
      firstRenderDone.current = true;
    }
  }, [initialLoadRendered, isLoaded, clientId]);

  useEffect(() => {
    setIsInPreview(
      !!ref.current?.closest('.block-editor-block-preview__content-iframe')
    );

    fetchData();

    document.addEventListener(
      `blockstudio/${block.name}/refresh`,
      debouncedFetchData
    );

    return () =>
      document.removeEventListener(
        `blockstudio/${block.name}/refresh`,
        debouncedFetchData
      );
  }, [disableLoading]);

  useEffect(() => {
    editorRef.current = editor;
  }, [editor]);

  useEffect(
    function onAttributeChange() {
      const newAttributes = cloneDeep(attributes) as Record<string, Any>;
      Object.keys(attributes).forEach((key) => {
        if (key.startsWith('BLOCKSTUDIO_RICH_TEXT')) {
          delete newAttributes[key];
        }
      });

      if (
        JSON.stringify(attributesRef.current) === JSON.stringify(newAttributes)
      ) {
        return;
      }
      attributesRef.current = newAttributes as BlockstudioBlockAttributes;
      if (firstRenderDone.current) {
        debouncedFetchData();
      }
    },
    [attributes]
  );

  useEffect(
    function onContextChange() {
      if (firstRenderDone.current) {
        debouncedFetchData();
      }
    },
    [context]
  );

  useEffect(
    function onMarkupChange() {
      setTimeout(() => {
        if (disableLoading) return;
        dispatch(block, 'rendered');
        dispatch(false as unknown as BlockstudioBlock, 'rendered');
      });
    },
    [markup]
  );

  useEffect(
    function onClientIdChange() {
      fetchData();
    },
    [clientId]
  );

  return (
    <>
      {disableLoading ? (
        <div
          {...blockProps}
          onClick={() => {
            setDisableLoading(false);
            dispatch(block, 'rendered');
            dispatch(false as unknown as BlockstudioBlock, 'rendered');
          }}
        >
          <Placeholder name={block.name} />
        </div>
      ) : hasMarkup ? (
        hasBlockProps ? (
          markup
        ) : (
          <div {...blockProps}>{markup}</div>
        )
      ) : (
        <div
          ref={ref}
          css={css({
            display: 'flex',
            width: '100%',
            height: '200px',
            justifyContent: 'center',
            alignItems: 'center',
          })}
        >
          <Spinner className={`blockstudio-block__inner-spinner`} />
        </div>
      )}

      <Global
        styles={{
          '.blockstudio-element__placeholder': {
            display: 'flex',
            height: '200px',
            justifyContent: 'center',
            alignItems: 'center',
            fontFamily: 'sans-serif',
            fontWeight: 600,
          },
        }}
      />
      {isInPreview && (
        <style>
          {`
            .blockstudio-element__preview {
              display: flex;
              height: 200px;
              justify-content: center;
              align-items: center;
              font-family: sans-serif;
              font-weight: 600;
              background-image: radial-gradient(
                  18% 28% at 24% 50%,
                  #cefaffff 7%,
                  #073aff00 100%
                ),
                radial-gradient(18% 28% at 18% 71%, #ffffff59 6%, #073aff00 100%),
                radial-gradient(70% 53% at 36% 76%, #73f2ffff 0, #073aff00 100%),
                radial-gradient(42% 53% at 15% 94%, #ffffffff 7%, #073aff00 100%),
                radial-gradient(42% 53% at 34% 72%, #ffffffff 7%, #073aff00 100%),
                radial-gradient(18% 28% at 35% 87%, #ffffffff 7%, #073aff00 100%),
                radial-gradient(31% 43% at 7% 98%, #ffffffff 24%, #073aff00 100%),
                radial-gradient(21% 37% at 72% 23%, #d3ff6d9c 24%, #073aff00 100%),
                radial-gradient(35% 56% at 91% 74%, #8a4ffff5 9%, #073aff00 100%),
                radial-gradient(74% 86% at 67% 38%, #6dffaef5 24%, #073aff00 100%),
                linear-gradient(125deg, #4eb5ffff 1%, #4c00fcff 100%);
              border-radius: 16px;
              font-size: 32px;
            }`}
        </style>
      )}
    </>
  );
};
