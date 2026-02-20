import { loader } from 'fumadocs-core/source';
import { docs, blog, guides } from '../../.source/server';

export const source = loader({
  baseUrl: '/docs',
  source: docs.toFumadocsSource(),
});

export const guidesSource = loader({
  baseUrl: '/guides',
  source: guides.toFumadocsSource(),
});

export { blog };

export function blogSlug(path: string): string {
  return path.replace(/\.mdx?$/, '');
}
