import apiFetch from '@wordpress/api-fetch';
import { computeHash, renderCache } from './render-cache';
import { Any } from '@/types/types';

interface RenderRequest {
  clientId: string;
  name: string;
  attributes: Any;
  context: Any;
  post: {
    blockstudioMode: string;
    postId: string | number | false;
    contextPostId: string | number | false;
    contextPostType: string | false;
  };
  resolve: (rendered: string) => void;
  reject: (error: unknown) => void;
}

const queue: RenderRequest[] = [];
let debounceTimer: ReturnType<typeof setTimeout> | null = null;

const flush = () => {
  if (queue.length === 0) return;

  const batch = queue.splice(0, queue.length);
  const data: Record<string, Any> = {};

  batch.forEach((req) => {
    data[req.clientId] = {
      clientId: req.clientId,
      attributes: req.attributes,
      context: req.context,
      name: req.name,
      post: req.post,
    };
  });

  apiFetch({
    path: `/blockstudio/v1/gutenberg/block/render/all`,
    method: 'POST',
    data: { data },
  })
    .then((response) => {
      const rendered = response as Record<string, string>;

      batch.forEach((req) => {
        const html = rendered[req.clientId];
        if (html) {
          const hash = computeHash(
            req.name,
            req.attributes?.blockstudio?.attributes || {},
          );
          renderCache.set(hash, html);
          req.resolve(html);
        } else {
          req.reject(new Error(`No render for ${req.clientId}`));
        }
      });
    })
    .catch((error) => {
      batch.forEach((req) => req.reject(error));
    });
};

export const batchFetcher = {
  requestRender(
    clientId: string,
    blockName: string,
    attributes: Any,
    context: Any,
    post: RenderRequest['post'],
  ): Promise<string> {
    return new Promise((resolve, reject) => {
      queue.push({
        clientId,
        name: blockName,
        attributes,
        context,
        post,
        resolve,
        reject,
      });

      if (debounceTimer) {
        clearTimeout(debounceTimer);
      }

      debounceTimer = setTimeout(flush, 500);
    });
  },
};
