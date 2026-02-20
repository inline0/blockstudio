import { loader } from 'fumadocs-core/source';
import { docs, blog, guides } from '../../.source/server';

export const source = loader({
  baseUrl: '/docs',
  source: docs.toFumadocsSource(),
});

export { blog, guides };

export function blogSlug(path: string): string {
  return path.replace(/\.mdx?$/, '');
}

export function guideSlug(path: string): string {
  return path.replace(/\.mdx?$/, '');
}
