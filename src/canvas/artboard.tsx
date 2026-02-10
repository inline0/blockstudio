import { useEffect, useMemo, useRef, useState } from 'react';

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
  revision: number;
}

interface Layer {
  id: number;
}

const ARTBOARD_WIDTH = 1440;

export const Artboard = ({ page, revision }: ArtboardProps): JSX.Element => {
  const blocks = useMemo(() => parse(page.content), [page.content]);
  const wrapperRef = useRef<HTMLDivElement>(null);
  const pendingRef = useRef<HTMLDivElement>(null);

  const [layers, setLayers] = useState<Layer[]>([{ id: revision }]);
  const [activeId, setActiveId] = useState(revision);

  useEffect(() => {
    if (revision === activeId) return;

    setLayers((prev) => {
      const active = prev.find((l) => l.id === activeId);
      return active ? [active, { id: revision }] : [{ id: revision }];
    });
  }, [revision, activeId]);

  useEffect(() => {
    const pending = layers.find((l) => l.id !== activeId);
    if (!pending) return;

    const container = pendingRef.current;
    if (!container) return;

    let cancelled = false;

    const swap = (): void => {
      if (cancelled) return;
      const newId = pending.id;
      setActiveId(newId);
      setLayers([{ id: newId }]);
    };

    const checkIframe = (): boolean => {
      const iframe = container.querySelector('iframe');
      if (!iframe) return false;
      if (iframe.contentDocument?.readyState === 'complete') {
        swap();
        return true;
      }
      iframe.addEventListener('load', swap, { once: true });
      return true;
    };

    if (checkIframe()) return () => { cancelled = true; };

    const observer = new MutationObserver(() => {
      if (checkIframe()) observer.disconnect();
    });
    observer.observe(container, { childList: true, subtree: true });

    return () => {
      cancelled = true;
      observer.disconnect();
    };
  }, [layers, activeId]);

  // BlockPreview hardcodes MAX_HEIGHT = 2000 on both the iframe and its
  // wrapper. Remove those limits so artboards show full page content.
  useEffect(() => {
    const wrapper = wrapperRef.current;
    if (!wrapper) return;

    const clearHeightLimits = (): void => {
      wrapper.querySelectorAll('iframe').forEach((iframe) => {
        iframe.style.setProperty('max-height', 'none', 'important');
      });
      wrapper
        .querySelectorAll('.block-editor-block-preview__content')
        .forEach((el) => {
          (el as HTMLElement).style.setProperty(
            'max-height',
            'none',
            'important',
          );
          (el as HTMLElement).style.setProperty(
            'overflow',
            'visible',
            'important',
          );
        });
    };

    const observer = new MutationObserver(clearHeightLimits);
    observer.observe(wrapper, {
      childList: true,
      subtree: true,
      attributes: true,
      attributeFilter: ['style'],
    });
    clearHeightLimits();

    return () => observer.disconnect();
  }, []);

  return (
    <div
      ref={wrapperRef}
      data-canvas-slug={page.slug}
      data-canvas-revision={revision}
      style={{
        position: 'relative',
        width: ARTBOARD_WIDTH,
        background: '#fff',
        boxShadow: '0 2px 8px rgba(0,0,0,0.3)',
        backfaceVisibility: 'hidden',
      }}
    >
      {layers.map((layer) => {
        const isActive = layer.id === activeId;
        return (
          <div
            key={layer.id}
            ref={isActive ? undefined : pendingRef}
            style={
              isActive
                ? undefined
                : {
                    position: 'absolute' as const,
                    top: 0,
                    left: 0,
                    width: '100%',
                    visibility: 'hidden' as const,
                  }
            }
          >
            <BlockPreview blocks={blocks} viewportWidth={ARTBOARD_WIDTH} />
          </div>
        );
      })}
    </div>
  );
};
