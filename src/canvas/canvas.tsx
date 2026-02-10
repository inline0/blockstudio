import { memo, useCallback, useEffect, useRef, useState } from 'react';

import { BlockEditorProvider } from '@wordpress/block-editor';
import { DropdownMenu, MenuGroup, MenuItem } from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { check, moreHorizontal } from '@wordpress/icons';
import apiFetch from '@wordpress/api-fetch';

import { Artboard } from './artboard';
import { STORE_NAME, store } from './store';

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

interface PollResponse {
  fingerprint: string;
}

interface RefreshResponse {
  pages: Page[];
  blockstudioBlocks: Record<string, unknown>;
}

const ARTBOARD_WIDTH = 1440;
const GAP = 80;
const LABEL_OFFSET = 28;
const PADDING = 120;
const MIN_SCALE = 0.02;
const MAX_SCALE = 2;

const MemoizedArtboard = memo(Artboard);

export const Canvas = ({
  pages: initialPages,
  settings,
}: CanvasProps): JSX.Element => {
  const containerRef = useRef<HTMLDivElement>(null);
  const surfaceRef = useRef<HTMLDivElement>(null);
  const transformRef = useRef<Transform | null>(null);
  const [ready, setReady] = useState(false);
  const fittedRef = useRef(false);

  const [currentPages, setCurrentPages] = useState(initialPages);
  const [revisions, setRevisions] = useState<Record<string, number>>(() => {
    const initial: Record<string, number> = {};
    initialPages.forEach((p) => {
      initial[p.slug] = 0;
    });
    return initial;
  });

  const liveMode = useSelect(
    (select) => select(store).isLiveMode(),
    [],
  );
  const pollInterval = useSelect(
    (select) => select(store).getPollInterval(),
    [],
  );
  const { setLiveMode, setFingerprint } = useDispatch(STORE_NAME);
  const fingerprintRef = useRef('');

  const fingerprint = useSelect(
    (select) => select(store).getFingerprint(),
    [],
  );
  fingerprintRef.current = fingerprint;

  const columns = currentPages.length;

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

  const fitToView = useCallback((): void => {
    const surface = surfaceRef.current;
    if (!surface) return;

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

    applyTransform();
  }, [applyTransform]);

  const zoomTo100 = useCallback((): void => {
    const surface = surfaceRef.current;
    if (!surface) return;

    const contentWidth = surface.offsetWidth;
    const contentHeight = surface.offsetHeight;
    const vw = window.innerWidth;
    const vh = window.innerHeight;

    transformRef.current = {
      x: (vw - contentWidth) / 2,
      y: (vh - contentHeight) / 2,
      scale: 1,
    };

    applyTransform();
  }, [applyTransform]);

  useEffect(() => {
    const surface = surfaceRef.current;
    if (!surface || ready || currentPages.length === 0) return;
    let cancelled = false;

    const waitForMedia = async (
      iframes: NodeListOf<HTMLIFrameElement>,
    ): Promise<void> => {
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
      if (iframes.length < currentPages.length) return;

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
  }, [currentPages.length, ready]);

  // Fallback timeout in case iframes never appear.
  useEffect(() => {
    if (ready || currentPages.length === 0) return;
    const timeout = setTimeout(() => setReady(true), 10000);
    return () => clearTimeout(timeout);
  }, [ready, currentPages.length]);

  // Wait for surface dimensions to stabilize before fitting to view.
  // The max-height removal in artboards causes async resizing.
  useEffect(() => {
    if (!ready || !surfaceRef.current) return;

    const surface = surfaceRef.current;
    let timeout: number;
    let done = false;

    const initialFit = (): void => {
      if (done) return;
      done = true;
      resizeObserver.disconnect();

      fitToView();
      document.getElementById('blockstudio-canvas-loader')?.remove();
      fittedRef.current = true;
    };

    const resizeObserver = new ResizeObserver(() => {
      clearTimeout(timeout);
      timeout = window.setTimeout(initialFit, 500);
    });

    resizeObserver.observe(surface);
    timeout = window.setTimeout(initialFit, 500);

    return () => {
      resizeObserver.disconnect();
      clearTimeout(timeout);
    };
  }, [ready, fitToView]);

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

  // Re-apply label positions when pages change (new labels start at opacity 0).
  useEffect(() => {
    if (fittedRef.current) {
      applyTransform();
    }
  }, [currentPages, applyTransform]);

  useEffect(() => {
    if (!liveMode || !ready) return;

    let active = true;

    const poll = async (): Promise<void> => {
      try {
        const result = await apiFetch<PollResponse>({
          path: '/blockstudio/v1/canvas/poll',
        });

        if (!active || !result.fingerprint) return;

        if (fingerprintRef.current === '') {
          setFingerprint(result.fingerprint);
          return;
        }

        if (result.fingerprint === fingerprintRef.current) return;

        const data = await apiFetch<RefreshResponse>({
          path: '/blockstudio/v1/canvas/refresh',
        });

        if (!active) return;

        const currentBlocks = (window as any).blockstudio
          ?.blockstudioBlocks || {};
        const newBlocks = data.blockstudioBlocks as Record<
          string,
          { rendered: string; block: { blockName: string } }
        >;

        // Compare individual block entries to find which block types changed.
        const changedBlockNames = new Set<string>();
        for (const [key, entry] of Object.entries(newBlocks)) {
          const old = currentBlocks[key] as
            | { rendered: string }
            | undefined;
          if (!old || old.rendered !== entry.rendered) {
            changedBlockNames.add(entry.block.blockName);
          }
        }
        for (const [key, entry] of Object.entries(
          currentBlocks as Record<
            string,
            { block: { blockName: string } }
          >,
        )) {
          if (!(key in newBlocks)) {
            changedBlockNames.add(entry.block.blockName);
          }
        }

        (window as any).blockstudio.blockstudioBlocks =
          data.blockstudioBlocks;

        const changedSlugs = new Set<string>();
        setCurrentPages((prevPages) => {
          return data.pages.map((newPage) => {
            const existing = prevPages.find(
              (p) => p.slug === newPage.slug,
            );

            if (!existing || existing.content !== newPage.content) {
              changedSlugs.add(newPage.slug);
              return newPage;
            }

            if (changedBlockNames.size > 0) {
              const usesChangedBlock = Array.from(
                changedBlockNames,
              ).some((name) => newPage.content.includes(name));
              if (usesChangedBlock) {
                changedSlugs.add(newPage.slug);
                return { ...newPage };
              }
            }

            return existing;
          });
        });

        if (changedSlugs.size > 0) {
          setRevisions((prev) => {
            const next = { ...prev };
            changedSlugs.forEach((slug) => {
              next[slug] = (next[slug] || 0) + 1;
            });
            return next;
          });
        }

        setFingerprint(result.fingerprint);
      } catch {
        // Silently ignore poll errors.
      }
    };

    poll();
    const interval = setInterval(poll, pollInterval * 1000);

    return () => {
      active = false;
      clearInterval(interval);
    };
  }, [liveMode, pollInterval, ready, setFingerprint]);

  if (currentPages.length === 0) {
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
          {currentPages.map((page) => (
            <MemoizedArtboard
              key={page.slug}
              page={page}
              revision={revisions[page.slug] || 0}
            />
          ))}
        </div>

        {currentPages.map((page) => (
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
          style={{
            position: 'absolute',
            top: 12,
            right: 12,
            display: 'flex',
            alignItems: 'center',
            gap: 8,
          }}
          className="blockstudio-canvas-menu"
        >
          {liveMode && (
            <div
              style={{
                width: 8,
                height: 8,
                borderRadius: '50%',
                background: '#4ade80',
                animation:
                  'blockstudio-canvas-pulse 2s ease-in-out infinite',
              }}
            />
          )}
          <DropdownMenu icon={moreHorizontal} label="Canvas options">
            {() => (
              <>
                <MenuGroup>
                  <MenuItem onClick={fitToView}>Fit to view</MenuItem>
                  <MenuItem onClick={zoomTo100}>Zoom to 100%</MenuItem>
                </MenuGroup>
                <MenuGroup>
                  <MenuItem
                    icon={liveMode ? check : undefined}
                    onClick={() => setLiveMode(!liveMode)}
                  >
                    Live mode
                  </MenuItem>
                </MenuGroup>
              </>
            )}
          </DropdownMenu>
        </div>
      </div>
    </BlockEditorProvider>
  );
};
