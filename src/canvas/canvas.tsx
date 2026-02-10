import { memo, useCallback, useEffect, useRef, useState } from 'react';

import { BlockEditorProvider } from '@wordpress/block-editor';
import { DropdownMenu, MenuGroup, MenuItem } from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { check, moreHorizontal } from '@wordpress/icons';
import apiFetch from '@wordpress/api-fetch';

import { Artboard } from './artboard';
import { STORE_NAME, store } from './store';
import type { CanvasView } from './store';

interface Page {
  title: string;
  slug: string;
  name: string;
  content: string;
}

interface BlockItem {
  title: string;
  name: string;
  content: string;
}

interface CanvasProps {
  pages: Page[];
  blocks: BlockItem[];
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
  blocks: BlockItem[];
  blockstudioBlocks: Record<string, unknown>;
}

const PAGE_ARTBOARD_WIDTH = 1440;
const BLOCK_ARTBOARD_WIDTH = 800;
const BATCH_SIZE = 8;
const GAP = 80;
const LABEL_OFFSET = 28;
const PADDING = 120;
const MIN_SCALE = 0.02;
const MAX_SCALE = 2;

const BLOCK_ICON = (
  <svg
    xmlns="http://www.w3.org/2000/svg"
    viewBox="0 0 24 24"
    width="12"
    height="12"
    fill="currentColor"
    style={{ marginRight: 4, verticalAlign: -1 }}
  >
    <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5" />
  </svg>
);

const MemoizedArtboard = memo(Artboard);

export const Canvas = ({
  pages: initialPages,
  blocks: initialBlocks,
  settings,
}: CanvasProps): JSX.Element => {
  const containerRef = useRef<HTMLDivElement>(null);
  const surfaceRef = useRef<HTMLDivElement>(null);
  const transformRef = useRef<Transform | null>(null);
  const [ready, setReady] = useState(false);
  const fittedRef = useRef(false);

  const [currentPages, setCurrentPages] = useState(initialPages);
  const [currentBlocks, setCurrentBlocks] = useState(initialBlocks);
  const [revisions, setRevisions] = useState<Record<string, number>>(() => {
    const initial: Record<string, number> = {};
    initialPages.forEach((p) => {
      initial[p.slug] = 0;
    });
    return initial;
  });
  const [blockRevisions, setBlockRevisions] = useState<
    Record<string, number>
  >(() => {
    const initial: Record<string, number> = {};
    initialBlocks.forEach((b) => {
      initial[b.name] = 0;
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
  const view = useSelect(
    (select) => select(store).getView(),
    [],
  ) as CanvasView;
  const { setLiveMode, setView, setFingerprint } = useDispatch(STORE_NAME);
  const fingerprintRef = useRef('');

  const fingerprint = useSelect(
    (select) => select(store).getFingerprint(),
    [],
  );
  fingerprintRef.current = fingerprint;

  const isBlocksView = view === 'blocks';
  const artboardWidth = isBlocksView ? BLOCK_ARTBOARD_WIDTH : PAGE_ARTBOARD_WIDTH;
  const activeItems = isBlocksView ? currentBlocks : currentPages;
  const totalItems = activeItems.length;

  const [mountedCount, setMountedCount] = useState(
    Math.min(BATCH_SIZE, totalItems),
  );
  const visibleItems = activeItems.slice(0, mountedCount);

  const columns = isBlocksView
    ? Math.ceil(Math.sqrt(currentBlocks.length)) || 1
    : currentPages.length;

  const applyTransform = useCallback((): void => {
    const t = transformRef.current;
    const surface = surfaceRef.current;
    const container = containerRef.current;
    if (!t || !surface || !container) return;

    surface.style.transform = `translate(${t.x}px, ${t.y}px) scale(${t.scale})`;
    surface.style.opacity = '1';

    const width = isBlocksView ? BLOCK_ARTBOARD_WIDTH : PAGE_ARTBOARD_WIDTH;
    const cols = isBlocksView
      ? Math.ceil(Math.sqrt(currentBlocks.length)) || 1
      : currentPages.length;

    const labels = container.querySelectorAll<HTMLElement>(
      '[data-canvas-label]',
    );
    labels.forEach((label, i) => {
      const col = i % cols;
      const row = Math.floor(i / cols);
      const rowOffset = label.closest('[data-canvas-surface]')
        ? 0
        : (() => {
            const items = container.querySelectorAll('[data-canvas-slug]');
            let offset = 0;
            for (let r = 0; r < row; r++) {
              let maxH = 0;
              for (let c = 0; c < cols; c++) {
                const idx = r * cols + c;
                if (idx < items.length) {
                  maxH = Math.max(maxH, items[idx].getBoundingClientRect().height / t.scale);
                }
              }
              offset += maxH + GAP;
            }
            return offset;
          })();

      label.style.left = `${t.x + col * (width + GAP) * t.scale}px`;
      label.style.top = isBlocksView
        ? `${t.y + rowOffset * t.scale - LABEL_OFFSET}px`
        : `${t.y - LABEL_OFFSET}px`;
      label.style.maxWidth = `${(width + GAP) * t.scale - 16}px`;
      label.style.opacity = '1';
    });
  }, [isBlocksView, currentBlocks.length, currentPages.length]);

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

  const expectedIframeCount = Math.min(BATCH_SIZE, totalItems);

  useEffect(() => {
    const surface = surfaceRef.current;
    if (!surface || ready || expectedIframeCount === 0) return;
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
      if (iframes.length < expectedIframeCount) return;

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
  }, [expectedIframeCount, ready]);

  // Fallback timeout in case iframes never appear.
  useEffect(() => {
    if (ready || expectedIframeCount === 0) return;
    const timeout = setTimeout(() => setReady(true), 10000);
    return () => clearTimeout(timeout);
  }, [ready, expectedIframeCount]);

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

  // Re-apply label positions when items change (new labels start at opacity 0).
  useEffect(() => {
    if (fittedRef.current) {
      applyTransform();
    }
  }, [currentPages, currentBlocks, applyTransform]);

  const prevViewRef = useRef(view);
  useEffect(() => {
    if (prevViewRef.current !== view) {
      prevViewRef.current = view;
      setMountedCount(Math.min(BATCH_SIZE, activeItems.length));
      if (fittedRef.current) {
        requestAnimationFrame(() => fitToView());
      }
    }
  }, [view, fitToView, activeItems.length]);

  useEffect(() => {
    if (!ready) return;
    if (mountedCount >= totalItems) return;

    const timer = setTimeout(() => {
      setMountedCount((prev) => Math.min(prev + BATCH_SIZE, totalItems));
    }, 200);

    return () => clearTimeout(timer);
  }, [ready, mountedCount, totalItems]);

  useEffect(() => {
    if (fittedRef.current) {
      applyTransform();
    }
  }, [mountedCount, applyTransform]);

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

        const currentPreloaded = (window as any).blockstudio
          ?.blockstudioBlocks || {};
        const newPreloaded = data.blockstudioBlocks as Record<
          string,
          { rendered: string; block: { blockName: string } }
        >;

        // Compare individual block entries to find which block types changed.
        const changedBlockNames = new Set<string>();
        for (const [key, entry] of Object.entries(newPreloaded)) {
          const old = currentPreloaded[key] as
            | { rendered: string }
            | undefined;
          if (!old || old.rendered !== entry.rendered) {
            changedBlockNames.add(entry.block.blockName);
          }
        }
        for (const [key, entry] of Object.entries(
          currentPreloaded as Record<
            string,
            { block: { blockName: string } }
          >,
        )) {
          if (!(key in newPreloaded)) {
            changedBlockNames.add(entry.block.blockName);
          }
        }

        (window as any).blockstudio.blockstudioBlocks =
          data.blockstudioBlocks;

        // Update pages.
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

        // Update blocks.
        const changedBlockItems = new Set<string>();
        setCurrentBlocks((prevBlocks) => {
          return data.blocks.map((newBlock) => {
            const existing = prevBlocks.find(
              (b) => b.name === newBlock.name,
            );

            if (!existing || existing.content !== newBlock.content) {
              changedBlockItems.add(newBlock.name);
              return newBlock;
            }

            if (changedBlockNames.has(newBlock.name)) {
              changedBlockItems.add(newBlock.name);
              return { ...newBlock };
            }

            return existing;
          });
        });

        if (changedBlockItems.size > 0) {
          setBlockRevisions((prev) => {
            const next = { ...prev };
            changedBlockItems.forEach((name) => {
              next[name] = (next[name] || 0) + 1;
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

  const hasContent =
    (view === 'pages' && currentPages.length > 0) ||
    (view === 'blocks' && currentBlocks.length > 0);

  if (!hasContent) {
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
        {view === 'pages'
          ? 'No Blockstudio pages found.'
          : 'No Blockstudio blocks found.'}
      </div>
    );
  }

  const labelColor = isBlocksView ? '#a855f7' : '#999';

  return (
    <BlockEditorProvider settings={settings}>
      <div
        ref={containerRef}
        data-canvas-view={view}
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
            gridTemplateColumns: `repeat(${columns}, ${artboardWidth}px)`,
            alignItems: 'start',
            gap: GAP,
            position: 'absolute',
            top: 0,
            left: 0,
          }}
        >
          {visibleItems.map((item) => {
            const key = 'slug' in item ? item.slug : item.name;
            const rev = 'slug' in item
              ? revisions[item.slug] || 0
              : blockRevisions[item.name] || 0;
            return (
              <MemoizedArtboard
                key={key}
                page={{
                  title: item.title,
                  slug: key,
                  name: item.name,
                  content: item.content,
                }}
                revision={rev}
                width={artboardWidth}
              />
            );
          })}
        </div>

        {visibleItems.map((item) => (
          <div
            key={'slug' in item ? item.slug : item.name}
            data-canvas-label=""
            style={{
              position: 'absolute',
              overflow: 'hidden',
              textOverflow: 'ellipsis',
              color: labelColor,
              fontFamily:
                '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
              fontSize: 13,
              fontWeight: 500,
              userSelect: 'none',
              pointerEvents: 'none',
              whiteSpace: 'nowrap',
              display: 'flex',
              alignItems: 'center',
            }}
          >
            {isBlocksView && BLOCK_ICON}
            {item.title}
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
                  <MenuItem
                    icon={view === 'pages' ? check : undefined}
                    onClick={() => setView('pages')}
                  >
                    Pages
                  </MenuItem>
                  <MenuItem
                    icon={view === 'blocks' ? check : undefined}
                    onClick={() => setView('blocks')}
                  >
                    Blocks
                  </MenuItem>
                </MenuGroup>
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
