import { memo, useCallback, useEffect, useRef, useState } from 'react';

import { BlockEditorProvider } from '@wordpress/block-editor';
import { DropdownMenu, MenuGroup, MenuItem } from '@wordpress/components';
import { moreHorizontal } from '@wordpress/icons';

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
  const fittedRef = useRef(false);

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
    let cancelled = false;

    const waitForMedia = async (
      iframes: NodeListOf<HTMLIFrameElement>,
    ): Promise<void> => {
      // Wait for all iframe documents to finish loading.
      await Promise.all(
        Array.from(iframes).map((iframe) => {
          if (iframe.contentDocument?.readyState === 'complete') {
            return Promise.resolve();
          }
          return new Promise<void>((resolve) =>
            iframe.addEventListener('load', () => resolve(), { once: true }),
          );
        }),
      );

      const mediaElements: HTMLElement[] = [];
      iframes.forEach((iframe) => {
        const doc = iframe.contentDocument;
        if (!doc) return;
        mediaElements.push(
          ...Array.from(
            doc.querySelectorAll<HTMLElement>('img, video, iframe'),
          ),
        );
      });

      await Promise.all(
        mediaElements.map((el) => {
          if (el instanceof HTMLImageElement) {
            if (el.complete) return Promise.resolve();
            return new Promise<void>((resolve) => {
              el.addEventListener('load', () => resolve(), { once: true });
              el.addEventListener('error', () => resolve(), { once: true });
            });
          }
          if (el instanceof HTMLVideoElement) {
            if (el.readyState >= 1) return Promise.resolve();
            return new Promise<void>((resolve) => {
              el.addEventListener('loadedmetadata', () => resolve(), {
                once: true,
              });
              el.addEventListener('error', () => resolve(), { once: true });
            });
          }
          if (el instanceof HTMLIFrameElement) {
            return new Promise<void>((resolve) => {
              el.addEventListener('load', () => resolve(), { once: true });
              el.addEventListener('error', () => resolve(), { once: true });
            });
          }
          return Promise.resolve();
        }),
      );
    };

    const check = (): void => {
      const iframes = surface.querySelectorAll('iframe');
      if (iframes.length < pages.length) return;

      observer.disconnect();
      waitForMedia(iframes).then(() => {
        if (!cancelled) setReady(true);
      });
    };

    const observer = new MutationObserver(check);
    observer.observe(surface, { childList: true, subtree: true });
    check();

    return () => {
      cancelled = true;
      observer.disconnect();
    };
  }, [pages.length, ready]);

  // Fallback timeout in case iframes never appear.
  useEffect(() => {
    if (ready || pages.length === 0) return;
    const timeout = setTimeout(() => setReady(true), 10000);
    return () => clearTimeout(timeout);
  }, [ready, pages.length]);

  // Wait for surface dimensions to stabilize before fitting to view.
  // The max-height removal in artboards causes async resizing.
  useEffect(() => {
    if (!ready || !surfaceRef.current) return;

    const surface = surfaceRef.current;
    let timeout: number;
    let done = false;

    const fitToView = (): void => {
      if (done) return;
      done = true;
      resizeObserver.disconnect();

      const contentWidth = surface.offsetWidth;
      const contentHeight = surface.offsetHeight;
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

      document.getElementById('blockstudio-canvas-loader')?.remove();
      applyTransform();
      fittedRef.current = true;
    };

    const resizeObserver = new ResizeObserver(() => {
      clearTimeout(timeout);
      timeout = window.setTimeout(fitToView, 500);
    });

    resizeObserver.observe(surface);
    timeout = window.setTimeout(fitToView, 500);

    return () => {
      resizeObserver.disconnect();
      clearTimeout(timeout);
    };
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

        <div
          style={{ position: 'absolute', top: 12, right: 12 }}
          className="blockstudio-canvas-menu"
        >
          <DropdownMenu icon={moreHorizontal} label="Canvas options">
            {() => (
              <>
                <MenuGroup>
                  <MenuItem onClick={() => {}}>Fit to view</MenuItem>
                  <MenuItem onClick={() => {}}>Zoom to 100%</MenuItem>
                </MenuGroup>
                <MenuGroup>
                  <MenuItem onClick={() => {}}>Export as image</MenuItem>
                  <MenuItem onClick={() => {}}>Settings</MenuItem>
                </MenuGroup>
              </>
            )}
          </DropdownMenu>
        </div>
      </div>
    </BlockEditorProvider>
  );
};
