import { memo, useCallback, useEffect, useRef, useState } from 'react';

import { BlockEditorProvider } from '@wordpress/block-editor';

import { Artboard } from './artboard';

interface Page {
  title: string;
  slug: string;
  name: string;
  content: string;
}

interface CanvasProps {
  pages: Page[];
  settings: Record<string, unknown>;
}

interface Transform {
  x: number;
  y: number;
  scale: number;
}

const ARTBOARD_WIDTH = 1440;
const GAP = 80;
const LABEL_OFFSET = 28;
const PADDING = 120;
const MIN_SCALE = 0.02;
const MAX_SCALE = 2;

const MemoizedArtboard = memo(Artboard);

export const Canvas = ({ pages, settings }: CanvasProps): JSX.Element => {
  const containerRef = useRef<HTMLDivElement>(null);
  const surfaceRef = useRef<HTMLDivElement>(null);
  const transformRef = useRef<Transform | null>(null);
  const [ready, setReady] = useState(false);

  const columns = pages.length;

  // Bypass React to avoid re-rendering BlockPreview iframes during pan/zoom.
  const applyTransform = useCallback((): void => {
    const t = transformRef.current;
    const surface = surfaceRef.current;
    const container = containerRef.current;
    if (!t || !surface || !container) return;

    surface.style.transform = `translate(${t.x}px, ${t.y}px) scale(${t.scale})`;
    surface.style.opacity = '1';

    const labels = container.querySelectorAll<HTMLElement>(
      '[data-canvas-label]',
    );
    labels.forEach((label, i) => {
      label.style.left = `${t.x + i * (ARTBOARD_WIDTH + GAP) * t.scale}px`;
      label.style.top = `${t.y - LABEL_OFFSET}px`;
      label.style.maxWidth = `${(ARTBOARD_WIDTH + GAP) * t.scale - 16}px`;
      label.style.opacity = '1';
    });
  }, []);

  useEffect(() => {
    const surface = surfaceRef.current;
    if (!surface || ready || pages.length === 0) return;

    const check = (): void => {
      const iframes = surface.querySelectorAll('iframe');
      if (iframes.length >= pages.length) {
        observer.disconnect();
        setTimeout(() => setReady(true), 300);
      }
    };

    const observer = new MutationObserver(check);
    observer.observe(surface, { childList: true, subtree: true });
    check();

    return () => observer.disconnect();
  }, [pages.length, ready]);

  // Fallback timeout in case iframes never appear.
  useEffect(() => {
    if (ready || pages.length === 0) return;
    const timeout = setTimeout(() => setReady(true), 10000);
    return () => clearTimeout(timeout);
  }, [ready, pages.length]);

  useEffect(() => {
    if (!ready || !surfaceRef.current) return;

    const contentWidth = surfaceRef.current.offsetWidth;
    const contentHeight = surfaceRef.current.offsetHeight;
    const vw = window.innerWidth;
    const vh = window.innerHeight;
    const scaleX = (vw - PADDING * 2) / contentWidth;
    const scaleY = (vh - PADDING * 2) / contentHeight;
    const scale = Math.min(scaleX, scaleY, 1);

    transformRef.current = {
      x: (vw - contentWidth * scale) / 2,
      y: (vh - contentHeight * scale) / 2,
      scale,
    };

    applyTransform();
  }, [ready, applyTransform]);

  useEffect(() => {
    const container = containerRef.current;
    if (!container) return;

    const handleWheel = (e: WheelEvent): void => {
      e.preventDefault();

      const prev = transformRef.current;
      if (!prev) return;

      if (e.ctrlKey || e.metaKey) {
        const zoomFactor = 1 - e.deltaY * 0.01;
        const newScale = Math.min(
          MAX_SCALE,
          Math.max(MIN_SCALE, prev.scale * zoomFactor),
        );
        const ratio = newScale / prev.scale;

        transformRef.current = {
          x: e.clientX - (e.clientX - prev.x) * ratio,
          y: e.clientY - (e.clientY - prev.y) * ratio,
          scale: newScale,
        };
      } else {
        transformRef.current = {
          ...prev,
          x: prev.x - e.deltaX,
          y: prev.y - e.deltaY,
        };
      }

      applyTransform();
    };

    container.addEventListener('wheel', handleWheel, { passive: false });
    return () => container.removeEventListener('wheel', handleWheel);
  }, [applyTransform]);

  if (pages.length === 0) {
    return (
      <div
        style={{
          position: 'fixed',
          inset: 0,
          background: '#2c2c2c',
          display: 'flex',
          alignItems: 'center',
          justifyContent: 'center',
          color: '#999',
          fontFamily:
            '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
          fontSize: 16,
        }}
      >
        No Blockstudio pages found.
      </div>
    );
  }

  return (
    <BlockEditorProvider settings={settings}>
      <div
        ref={containerRef}
        style={{
          position: 'fixed',
          inset: 0,
          background: '#2c2c2c',
          overflow: 'hidden',
        }}
      >
        {!ready && (
          <div
            style={{
              position: 'absolute',
              inset: 0,
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
            }}
          >
            <div
              style={{
                width: 32,
                height: 32,
                border: '3px solid rgba(255,255,255,0.1)',
                borderTopColor: 'rgba(255,255,255,0.4)',
                borderRadius: '50%',
                animation: 'blockstudio-canvas-spin 0.8s linear infinite',
              }}
            />
          </div>
        )}

        <div
          ref={surfaceRef}
          data-canvas-surface=""
          style={{
            transformOrigin: '0 0',
            willChange: 'transform',
            display: 'grid',
            gridTemplateColumns: `repeat(${columns}, ${ARTBOARD_WIDTH}px)`,
            alignItems: 'start',
            gap: GAP,
            position: 'absolute',
            top: 0,
            left: 0,
          }}
        >
          {pages.map((page) => (
            <MemoizedArtboard key={page.slug} page={page} />
          ))}
        </div>

        {pages.map((page) => (
          <div
            key={page.slug}
            data-canvas-label=""
            style={{
              position: 'absolute',
              overflow: 'hidden',
              textOverflow: 'ellipsis',
              color: '#999',
              fontFamily:
                '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
              fontSize: 13,
              fontWeight: 500,
              userSelect: 'none',
              pointerEvents: 'none',
              whiteSpace: 'nowrap',
            }}
          >
            {page.title}
          </div>
        ))}
      </div>
    </BlockEditorProvider>
  );
};
