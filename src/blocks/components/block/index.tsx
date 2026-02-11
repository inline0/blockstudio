import { Global } from '@emotion/react';
import apiFetch from '@wordpress/api-fetch';
import { useBlockProps } from '@wordpress/block-editor';
import { Spinner } from '@wordpress/components';
import { useDebounce } from '@wordpress/compose';
import { useSelect } from '@wordpress/data';
import { useEffect, useRef, useState } from '@wordpress/element';
import parse from 'html-react-parser';
import { DomElement } from 'htmlparser2';
import { cloneDeep } from 'lodash-es';
import { MediaPlaceholder } from '@/blocks/components/block/media-placeholder';
import { checkForBlockProps } from '@/blocks/components/block/utils/check-for-block-props';
import { getAttributes } from '@/blocks/components/block/utils/get-attributes';
import { getInnerHTML as getFirstElementContent } from '@/blocks/components/block/utils/get-first-element-content';
import { getRegex } from '@/blocks/components/block/utils/get-regex';
import { Placeholder } from '@/blocks/components/placeholder';
import { dispatch } from '@/blocks/utils/dispatch';
import {
  Any,
  BlockstudioBlock,
  BlockstudioBlockAttributes,
} from '@/types/types';
import { css } from '@/utils/css';
import { batchFetcher } from './batch-fetcher';
import { BlockProps } from './block-props';
import { InnerBlocks } from './inner-blocks';
import { computeHash, renderCache } from './render-cache';
import { RichText } from './rich-text';

interface CustomEventDetail {
  filesChanged: { [key: string]: Any };
}

interface CustomEvent extends Event {
  detail?: CustomEventDetail;
}

interface RenderState {
  markup: Any;
  hasMarkup: boolean;
  hasBlockProps: boolean | null;
}

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
  function computeRender(rendered: string): RenderState {
    const transform = (input: string) => {
      input = input.replace(
        getRegex('InnerBlocks'),
        '<div id="blockstudio-replace-innerblocks"></div>',
      );
      input = input.replace(getRegex('RichText', 'gs'), (match) => {
        const innerAttributes = getAttributes(match, 'RichText');
        const attrKey = innerAttributes.attribute as string;
        attributeMap[attrKey] = innerAttributes;

        return `<div id="blockstudio-replace-richtext" class="${attrKey}"></div>`;
      });
      input = input.replace(getRegex('MediaPlaceholder', 'gs'), (match) => {
        const innerAttributes = getAttributes(match, 'MediaPlaceholder');
        const attrKey = innerAttributes.attribute as string;
        attributeMap[attrKey] = innerAttributes;

        return `<div id="blockstudio-replace-dropzone" class="${attrKey}"></div>`;
      });

      return input;
    };

    const parser = (input: string) => {
      return parse(input, {
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
    const blockResponse = rendered;
    const transformBlockResponse = transform(blockResponse);
    const blockPropsCheck = checkForBlockProps(blockResponse);
    const hasComponentBlockProps = checkForBlockProps(blockResponse, true);

    let m: NonNullable<unknown>;

    if (blockPropsCheck) {
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

    return {
      markup: m,
      hasMarkup: true,
      hasBlockProps: blockPropsCheck || hasComponentBlockProps || null,
    };
  }

  const firstRenderDone = useRef<boolean | null>(null);
  const ref = useRef<HTMLDivElement | null>(null);
  const attributesRef = useRef(attributes);
  const prevContextRef = useRef(JSON.stringify(context));
  const [disableLoading, setDisableLoading] = useState(
    block?.blockstudio?.blockEditor?.disableLoading,
  );
  const [isInPreview, setIsInPreview] = useState(false);
  const blockProps = useBlockProps();
  const postId =
    useSelect((select: Any) => select('core/editor')?.getCurrentPostId(), []) ||
    false;
  const postType =
    useSelect(
      (select: Any) => select('core/editor')?.getCurrentPostType(),
      [],
    ) || false;

  const [renderState, setRenderState] = useState<RenderState>(() => {
    if (block?.blockstudio?.blockEditor?.disableLoading) {
      return { markup: null, hasMarkup: false, hasBlockProps: null };
    }

    const hash = computeHash(block.name, attributes);

    const cached = renderCache.get(hash);
    if (cached) {
      firstRenderDone.current = true;
      return computeRender(cached);
    }

    const preloaded = renderCache.claimPreloaded(block.name);
    if (preloaded) {
      renderCache.set(hash, preloaded);
      firstRenderDone.current = true;
      return computeRender(preloaded);
    }

    return { markup: null, hasMarkup: false, hasBlockProps: null };
  });

  const loaded = () => {
    setTimeout(() => {
      dispatch(block, 'loaded');
      dispatch(false as unknown as BlockstudioBlock, 'loaded');
      if (!firstRenderDone.current) {
        firstRenderDone.current = true;
      }
    });
  };

  const updateRender = (rendered: string) => {
    setRenderState(computeRender(rendered));
  };

  const getPostParams = () => ({
    blockstudioMode: ref.current?.closest(
      '.block-editor-block-preview__content-iframe',
    )
      ? 'preview'
      : 'editor',
    postId,
    contextPostId: attributes.blockstudio?.contextBlock?.postId || postId,
    contextPostType:
      attributes.blockstudio?.contextBlock?.postType || postType,
  });

  const fetchSingle = (event: CustomEvent | false = false) => {
    if (event) {
    }
    const params = new URLSearchParams({
      ...getPostParams(),
    }).toString();

    apiFetch({
      path: `/blockstudio/v1/gutenberg/block/render/${block.name}?${params}`,
      method: 'POST',
      data: {
        attributes,
        context,
      },
    })
      .then((response) => {
        const res = response as { rendered: string };
        const hash = computeHash(block.name, attributes);
        renderCache.set(hash, res.rendered);
        updateRender(res.rendered);
      })
      .then(() => {
        loaded();
      });
  };

  const debouncedFetchSingle = useDebounce(fetchSingle, 500);

  useEffect(function onMount() {
    setIsInPreview(
      !!ref.current?.closest('.block-editor-block-preview__content-iframe'),
    );

    if (disableLoading) return;

    if (firstRenderDone.current && renderState.hasMarkup) {
      loaded();
      return;
    }

    batchFetcher
      .requestRender(
        clientId,
        block.name,
        attributes,
        context,
        getPostParams(),
      )
      .then((rendered) => {
        updateRender(rendered);
        loaded();
        firstRenderDone.current = true;
      })
      .catch(() => {
      });
  }, [disableLoading]);

  useEffect(
    function onAttributeOrContextChange() {
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

      if (!firstRenderDone.current) return;

      const hash = computeHash(block.name, newAttributes);
      const cached = renderCache.get(hash);

      if (cached) {
        updateRender(cached);
        loaded();
        return;
      }

      debouncedFetchSingle();
    },
    [attributes],
  );

  useEffect(
    function onContextChange() {
      const contextStr = JSON.stringify(context);
      if (prevContextRef.current === contextStr) {
        return;
      }
      prevContextRef.current = contextStr;
      if (firstRenderDone.current) {
        debouncedFetchSingle();
      }
    },
    [context],
  );

  useEffect(function onRefreshEvent() {
    document.addEventListener(
      `blockstudio/${block.name}/refresh`,
      debouncedFetchSingle,
    );

    return () =>
      document.removeEventListener(
        `blockstudio/${block.name}/refresh`,
        debouncedFetchSingle,
      );
  }, [disableLoading]);

  useEffect(
    function onMarkupChange() {
      setTimeout(() => {
        if (disableLoading) return;
        dispatch(block, 'rendered');
        dispatch(false as unknown as BlockstudioBlock, 'rendered');
      });
    },
    [renderState.markup],
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
      ) : renderState.hasMarkup ? (
        renderState.hasBlockProps ? (
          renderState.markup
        ) : (
          <div {...blockProps}>{renderState.markup}</div>
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
