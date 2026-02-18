import { useEffect, useRef, useState } from 'react';

// @ts-expect-error No types for BlockPreview
import { BlockPreview } from '@wordpress/block-editor';
import { parse } from '@wordpress/blocks';

import { waitForIframeReady } from './wait-for-iframe';

interface Page {
  title: string;
  slug: string;
  name: string;
  content: string;
}

interface ArtboardProps {
  page: Page;
  revision: number;
  width?: number;
  onSwapComplete?: (slug: string) => void;
}

interface Layer {
  id: number;
  blocks: ReturnType<typeof parse>;
}

const DEFAULT_WIDTH = 1440;

export const Artboard = ({ page, revision, width = DEFAULT_WIDTH, onSwapComplete }: ArtboardProps): JSX.Element => {
  const wrapperRef = useRef<HTMLDivElement>(null);
  const pendingRef = useRef<HTMLDivElement>(null);
  const prevRevisionRef = useRef(revision);
  const onSwapCompleteRef = useRef(onSwapComplete);
  onSwapCompleteRef.current = onSwapComplete;

  const [layers, setLayers] = useState<Layer[]>(() => [
    { id: revision, blocks: parse(page.content) },
  ]);
  const [activeId, setActiveId] = useState(revision);
  const [readyId, setReadyId] = useState<number | null>(null);

  useEffect(() => {
    if (revision === activeId) {
      if (prevRevisionRef.current !== revision) {
        prevRevisionRef.current = revision;
        onSwapCompleteRef.current?.(page.slug);
      }
      return;
    }
    prevRevisionRef.current = revision;

    const newBlocks = parse(page.content);
    setLayers((prev) => {
      const active = prev.find((l) => l.id === activeId);
      return active
        ? [active, { id: revision, blocks: newBlocks }]
        : [{ id: revision, blocks: newBlocks }];
    });
  }, [revision, activeId, page.content, page.slug]);

  useEffect(() => {
    const pending = layers.find((l) => l.id !== activeId);
    if (!pending) return;

    const container = pendingRef.current;
    if (!container) return;

    let resolved = false;
    const promote = (): void => {
      if (resolved) return;
      resolved = true;
      setReadyId(pending.id);
    };

    const controller = new AbortController();
    waitForIframeReady(container, controller.signal)
      .then(promote)
      .catch(() => {});

    // Fallback: swap even if content detection hangs.
    const timeout = setTimeout(promote, 5000);

    return () => {
      controller.abort();
      clearTimeout(timeout);
    };
  }, [layers, activeId]);

  useEffect(() => {
    if (readyId === null) return;
    const ready = layers.find((l) => l.id === readyId);
    if (!ready) return;

    setActiveId(readyId);
    setLayers([ready]);
    setReadyId(null);
    onSwapCompleteRef.current?.(page.slug);
  }, [readyId, layers, page.slug]);

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
        width,
        background: '#fff',
        boxShadow: '0 2px 8px rgba(0,0,0,0.3)',
        backfaceVisibility: 'hidden',
      }}
    >
      {layers.map((layer) => {
        const isActive = layer.id === activeId;
        const isReady = layer.id === readyId;
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
                    zIndex: isReady ? 1 : undefined,
                    visibility: isReady
                      ? ('visible' as const)
                      : ('hidden' as const),
                  }
            }
          >
            <BlockPreview blocks={layer.blocks} viewportWidth={width} />
          </div>
        );
      })}
    </div>
  );
};
