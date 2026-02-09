import { useEffect, useMemo, useRef } from 'react';

// @ts-expect-error No types for BlockPreview
import { BlockPreview } from '@wordpress/block-editor';
import { parse } from '@wordpress/blocks';

interface Page {
  title: string;
  slug: string;
  name: string;
  content: string;
}

interface ArtboardProps {
  page: Page;
}

const ARTBOARD_WIDTH = 1440;

export const Artboard = ({ page }: ArtboardProps): JSX.Element => {
  const blocks = useMemo(() => parse(page.content), [page.content]);
  const wrapperRef = useRef<HTMLDivElement>(null);

  // BlockPreview hardcodes MAX_HEIGHT = 2000 on both the iframe and its
  // wrapper. Remove those limits so artboards show full page content.
  useEffect(() => {
    const wrapper = wrapperRef.current;
    if (!wrapper) return;

    const clearHeightLimits = (): void => {
      const iframe = wrapper.querySelector('iframe');
      if (iframe) {
        iframe.style.setProperty('max-height', 'none', 'important');
      }
      const preview = wrapper.querySelector(
        '.block-editor-block-preview__content',
      ) as HTMLElement | null;
      if (preview) {
        preview.style.setProperty('max-height', 'none', 'important');
        preview.style.setProperty('overflow', 'visible', 'important');
      }
    };

    const observer = new MutationObserver(clearHeightLimits);
    observer.observe(wrapper, { childList: true, subtree: true, attributes: true, attributeFilter: ['style'] });
    clearHeightLimits();

    return () => observer.disconnect();
  }, []);

  return (
    <div
      ref={wrapperRef}
      style={{
        width: ARTBOARD_WIDTH,
        background: '#fff',
        boxShadow: '0 2px 8px rgba(0,0,0,0.3)',
        backfaceVisibility: 'hidden',
      }}
    >
      <BlockPreview blocks={blocks} viewportWidth={ARTBOARD_WIDTH} />
    </div>
  );
};
