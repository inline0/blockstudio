import apiFetch from '@wordpress/api-fetch';
import { InnerBlocks, useBlockProps } from '@wordpress/block-editor';
import { getBlockType, registerBlockType } from '@wordpress/blocks';
import { useSelect } from '@wordpress/data';
import type { ComponentType } from 'react';
import {
  createElement,
  Fragment,
  RawHTML,
  useEffect,
  useState,
} from '@wordpress/element';
import { addQueryArgs } from '@wordpress/url';

export const PAGE_BLOCK = 'blockstudio/page';

interface RendererResponse {
  rendered?: string;
}

interface EditorSelectors {
  getCurrentPostId: () => number | null;
}

type Preview =
  | { state: 'loading' }
  | { state: 'ready'; html: string }
  | { state: 'error'; message: string };

export function previewPath(postId: number): string {
  return addQueryArgs(`/wp/v2/block-renderer/${PAGE_BLOCK}`, {
    context: 'edit',
    post_id: postId,
  });
}

function usePreview(postId: number | null): Preview {
  const [preview, setPreview] = useState<Preview>({ state: 'loading' });

  useEffect(() => {
    if (!postId) {
      setPreview({ state: 'error', message: 'Save the page to preview it.' });
      return undefined;
    }

    let active = true;
    setPreview({ state: 'loading' });

    apiFetch<RendererResponse>({ path: previewPath(postId) })
      .then((response) => {
        if (active) {
          setPreview({ state: 'ready', html: response.rendered ?? '' });
        }
      })
      .catch((error: { message?: string }) => {
        if (active) {
          setPreview({
            state: 'error',
            message: error.message ?? 'The page preview could not be loaded.',
          });
        }
      });

    return () => {
      active = false;
    };
  }, [postId]);

  return preview;
}

function Edit() {
  const postId = useSelect(
    (select) =>
      (
        select('core/editor') as EditorSelectors | undefined
      )?.getCurrentPostId?.() ?? null,
    [],
  );
  const preview = usePreview(postId);
  const blockProps = useBlockProps({ className: 'blockstudio-page' });

  let body;

  if ('ready' === preview.state) {
    body = createElement(
      RawHTML as unknown as ComponentType<{ className: string }>,
      { className: 'blockstudio-page__preview' },
      preview.html,
    );
  } else {
    body = createElement(
      'p',
      {
        className: `blockstudio-page__status blockstudio-page__status--${preview.state}`,
      },
      'loading' === preview.state ? 'Loading page preview…' : preview.message,
    );
  }

  return createElement('div', blockProps, body, createElement(InnerBlocks, {}));
}

function Save() {
  return createElement(Fragment, {}, createElement(InnerBlocks.Content, {}));
}

export function registerPageBlock(): void {
  if (getBlockType(PAGE_BLOCK)) {
    return;
  }

  registerBlockType(PAGE_BLOCK, {
    apiVersion: 3,
    title: 'Page',
    category: 'design',
    description: 'The component that renders this file-backed page.',
    attributes: {},
    supports: {
      inserter: false,
      html: false,
      reusable: false,
      lock: false,
      multiple: false,
    },
    usesContext: ['postId', 'postType'],
    edit: Edit,
    save: Save,
  } as Parameters<typeof registerBlockType>[1]);
}
