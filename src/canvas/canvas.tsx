import { memo, useCallback, useEffect, useRef, useState } from 'react';

import { BlockEditorProvider } from '@wordpress/block-editor';
import { getBlockType } from '@wordpress/blocks';
import { Button, DropdownMenu, MenuGroup, MenuItem } from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { check, closeSmall, moreHorizontal } from '@wordpress/icons';
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

interface PreloadEntry {
  rendered: string;
  blockName: string;
}

interface RefreshResponse {
  pages: Page[];
  blocks: BlockItem[];
  blockstudioBlocks: PreloadEntry[];
  changedBlocks: string[];
  blocksNative?: Record<string, unknown>;
  tailwindCss?: string;
}

interface SSEChangedData {
  fingerprint: string;
  changedBlocks?: string[];
  changedPages?: string[];
  pages?: Page[];
  blocks?: BlockItem[];
  blockstudioBlocks?: PreloadEntry[];
  blocksNative?: Record<string, unknown>;
  tailwindCss?: string;
}

const PAGE_ARTBOARD_WIDTH = 1440;
const BLOCK_ARTBOARD_WIDTH = 800;
const BLOCK_ARTBOARD_MIN_HEIGHT = 200;
const BATCH_SIZE = 8;
const GAP = 80;
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

  const [currentSettings, setCurrentSettings] = useState(settings);
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

  const [focusedSlug, setFocusedSlug] = useState<string | null>(null);
  const focusedRef = useRef<string | null>(null);
  focusedRef.current = focusedSlug;

  const liveMode = useSelect(
    (select) => select(store).isLiveMode(),
    [],
  );
  const view = useSelect(
    (select) => select(store).getView(),
    [],
  ) as CanvasView;
  const { setLiveMode, setView, setFingerprint } = useDispatch(STORE_NAME);

  const isBlocksView = view === 'blocks';
  const artboardWidth = isBlocksView ? BLOCK_ARTBOARD_WIDTH : PAGE_ARTBOARD_WIDTH;
  const activeItems: (Page | BlockItem)[] = isBlocksView ? currentBlocks : currentPages;
  const totalItems = activeItems.length;

  const [mountedCount, setMountedCount] = useState(
    Math.min(BATCH_SIZE, totalItems),
  );
  const visibleItems = activeItems.slice(0, mountedCount);

  const columns = isBlocksView
    ? Math.ceil(Math.sqrt(currentBlocks.length)) || 1
    : currentPages.length;

  const applyTransform = useCallback((): void => {
    const surface = surfaceRef.current;
    if (!transformRef.current || !surface) return;
    const t = transformRef.current;
    surface.style.transform = `translate(${t.x}px, ${t.y}px) scale(${t.scale})`;
    surface.style.setProperty('--canvas-label-scale', String(1 / t.scale));
    surface.style.opacity = '1';
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
      if (focusedRef.current) return;

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

  const [focusWidth, setFocusWidth] = useState(window.innerWidth);

  useEffect(() => {
    if (!focusedSlug) return;

    setFocusWidth(window.innerWidth);

    const handleKeyDown = (e: KeyboardEvent): void => {
      if (e.key === 'Escape') {
        setFocusedSlug(null);
      }
    };

    const handleResize = (): void => {
      setFocusWidth(window.innerWidth);
    };

    document.addEventListener('keydown', handleKeyDown);
    window.addEventListener('resize', handleResize);
    return () => {
      document.removeEventListener('keydown', handleKeyDown);
      window.removeEventListener('resize', handleResize);
    };
  }, [focusedSlug]);

  const prevViewRef = useRef(view);
  useEffect(() => {
    if (prevViewRef.current !== view) {
      prevViewRef.current = view;
      setFocusedSlug(null);
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

  const processRefreshData = useCallback(
    (
      data: RefreshResponse,
      newFingerprint: string,
      changedPages?: string[],
    ): void => {
      if (data.blocksNative) {
        const registerBlock = window.blockstudio?.registerBlock;
        if (registerBlock) {
          Object.values(data.blocksNative).forEach((blockData: any) => {
            if (blockData?.name && !getBlockType(blockData.name)) {
              registerBlock(blockData);
            }
          });
        }
      }

      if (data.blockstudioBlocks.length > 0) {
        window.blockstudio?.addPreloads?.(data.blockstudioBlocks);
      }

      const changedBlockNames = new Set<string>(
        data.changedBlocks || [],
      );

      const currentPreloaded: PreloadEntry[] =
        (window as any).blockstudio?.blockstudioBlocks || [];

      if (changedBlockNames.size > 0) {
        const kept = currentPreloaded.filter(
          (e) => !changedBlockNames.has(e.blockName),
        );
        (window as any).blockstudio.blockstudioBlocks = [
          ...kept,
          ...data.blockstudioBlocks,
        ];
      } else if (data.blockstudioBlocks.length > 0) {
        (window as any).blockstudio.blockstudioBlocks = [
          ...currentPreloaded,
          ...data.blockstudioBlocks,
        ];
      }

      if (data.pages.length > 0) {
        setCurrentPages((prevPages) => {
          const changedSlugs = new Set<string>();
          let nextPages: typeof prevPages;

          if (changedPages && changedPages.length > 0) {
            const updatedMap = new Map(
              data.pages.map((p) => [p.slug, p]),
            );
            const merged = prevPages.map((existing) => {
              const updated = updatedMap.get(existing.slug);
              if (updated) {
                updatedMap.delete(existing.slug);
                if (existing.content !== updated.content) {
                  changedSlugs.add(updated.slug);
                  return updated;
                }
                return existing;
              }
              return existing;
            });
            updatedMap.forEach((newPage) => {
              changedSlugs.add(newPage.slug);
              merged.push(newPage);
            });
            nextPages = merged;
          } else {
            nextPages = data.pages.map((newPage) => {
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
          }

          if (changedSlugs.size > 0) {
            setRevisions((prev) => {
              const next = { ...prev };
              changedSlugs.forEach((slug) => {
                next[slug] = (next[slug] || 0) + 1;
              });
              return next;
            });
          }

          return nextPages;
        });
      } else if (changedBlockNames.size > 0) {
        setCurrentPages((prevPages) => {
          const changedSlugs = new Set<string>();
          const nextPages = prevPages.map((page) => {
            const usesChangedBlock = Array.from(changedBlockNames).some(
              (name) => page.content.includes(name),
            );
            if (usesChangedBlock) {
              changedSlugs.add(page.slug);
              return { ...page };
            }
            return page;
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

          return nextPages;
        });
      }

      if (data.blocks.length > 0) {
        setCurrentBlocks((prevBlocks) => {
          const changedBlockItems = new Set<string>();

          const merged = prevBlocks.map((existing) => {
            const updated = data.blocks.find(
              (b) => b.name === existing.name,
            );
            if (!updated) return existing;

            if (existing.content !== updated.content) {
              changedBlockItems.add(updated.name);
              return updated;
            }

            if (changedBlockNames.has(existing.name)) {
              changedBlockItems.add(existing.name);
              return { ...updated };
            }

            return existing;
          });

          const newBlocks = data.blocks.filter(
            (b) => !prevBlocks.some((p) => p.name === b.name),
          );
          newBlocks.forEach((b) => changedBlockItems.add(b.name));

          if (changedBlockItems.size > 0) {
            setBlockRevisions((prev) => {
              const next = { ...prev };
              changedBlockItems.forEach((name) => {
                next[name] = (next[name] || 0) + 1;
              });
              return next;
            });
          }

          return [...merged, ...newBlocks];
        });
      }

      if (data.tailwindCss) {
        setCurrentSettings((prev) => {
          const assets = (prev as any).__unstableResolvedAssets;
          if (!assets?.styles) return prev;

          const tag = '<style id="blockstudio-tailwind-editor">';
          const existingIndex = assets.styles.indexOf(tag);
          let newStyles: string;

          if (existingIndex !== -1) {
            const endTag = '</style>';
            const endIndex = assets.styles.indexOf(
              endTag,
              existingIndex,
            );
            newStyles =
              assets.styles.substring(0, existingIndex) +
              tag +
              data.tailwindCss +
              endTag +
              assets.styles.substring(endIndex + endTag.length);
          } else {
            newStyles =
              assets.styles + tag + data.tailwindCss + '</style>';
          }

          return {
            ...prev,
            __unstableResolvedAssets: {
              ...assets,
              styles: newStyles,
            },
          };
        });
      }

      setFingerprint(newFingerprint);
    },
    [setFingerprint],
  );

  useEffect(() => {
    if (!liveMode || !ready) return;

    const canvasData = (window as any).blockstudioCanvas as
      | { restRoot?: string; restNonce?: string }
      | undefined;
    if (!canvasData?.restRoot || !canvasData?.restNonce) return;

    const url =
      canvasData.restRoot +
      'blockstudio/v1/canvas/stream?_wpnonce=' +
      encodeURIComponent(canvasData.restNonce);
    const eventSource = new EventSource(url);

    eventSource.addEventListener('fingerprint', (e: MessageEvent) => {
      try {
        const parsed = JSON.parse(e.data);
        if (parsed.fingerprint) {
          setFingerprint(parsed.fingerprint);
        }
      } catch {
        // Ignore parse errors.
      }
    });

    eventSource.addEventListener('changed', (e: MessageEvent) => {
      try {
        const parsed = JSON.parse(e.data) as SSEChangedData;
        if (parsed.fingerprint && parsed.blockstudioBlocks) {
          processRefreshData(
            {
              pages: parsed.pages || [],
              blocks: parsed.blocks || [],
              blockstudioBlocks: parsed.blockstudioBlocks,
              changedBlocks: parsed.changedBlocks || [],
              blocksNative: parsed.blocksNative,
              tailwindCss: parsed.tailwindCss,
            },
            parsed.fingerprint,
            parsed.changedPages,
          );
        }
      } catch {
        // Ignore parse errors.
      }
    });

    return () => {
      eventSource.close();
    };
  }, [liveMode, ready, setFingerprint, processRefreshData]);

  const hasContent =
    (view === 'pages' && currentPages.length > 0) ||
    (view === 'blocks' && currentBlocks.length > 0);

  useEffect(() => {
    if (!hasContent) {
      document.getElementById('blockstudio-canvas-loader')?.remove();
    }
  }, [hasContent]);

  const labelColor = isBlocksView ? '#a855f7' : '#999';

  return (
    <BlockEditorProvider settings={currentSettings}>
      <style>{`
        [data-canvas-label] { cursor: pointer; }
        [data-canvas-label] .canvas-focus-icon { opacity: 0; transition: opacity 0.15s; }
        [data-canvas-label]:hover .canvas-focus-icon { opacity: 1; }
      `}</style>
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
        {!hasContent && (
          <div
            style={{
              position: 'absolute',
              inset: 0,
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
        )}
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
              <div key={key} style={{ position: 'relative', minHeight: isBlocksView ? BLOCK_ARTBOARD_MIN_HEIGHT : undefined }}>
                <div
                  data-canvas-label=""
                  onClick={() => setFocusedSlug(key)}
                  style={{
                    position: 'absolute',
                    bottom: '100%',
                    left: 0,
                    paddingBottom: 8,
                    maxWidth: `calc(${artboardWidth}px / var(--canvas-label-scale, 1))`,
                    overflow: 'hidden',
                    textOverflow: 'ellipsis',
                    color: labelColor,
                    fontFamily:
                      '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
                    fontSize: 13,
                    fontWeight: 500,
                    userSelect: 'none',
                    whiteSpace: 'nowrap',
                    transform: 'scale(var(--canvas-label-scale, 1))',
                    transformOrigin: '0 100%',
                  }}
                >
                  {isBlocksView && BLOCK_ICON}
                  {item.title}
                  <svg
                    className="canvas-focus-icon"
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 24 24"
                    width="12"
                    height="12"
                    fill="currentColor"
                    style={{ marginLeft: 6, verticalAlign: -1 }}
                  >
                    <path d="M19.5 4.5h-7V6h4.44l-5.97 5.97 1.06 1.06L18 7.06V11.5h1.5v-7zM4.5 19.5h7V18H7.06l5.97-5.97-1.06-1.06L6 16.94V12.5H4.5v7z" />
                  </svg>
                </div>
                <MemoizedArtboard
                  page={{
                    title: item.title,
                    slug: key,
                    name: item.name,
                    content: item.content,
                  }}
                  revision={rev}
                  width={artboardWidth}
                />
              </div>
            );
          })}
        </div>

        {focusedSlug && (() => {
          const focusedItem = activeItems.find((item) =>
            'slug' in item ? item.slug === focusedSlug : item.name === focusedSlug,
          );
          if (!focusedItem) return null;
          const focusedKey = 'slug' in focusedItem ? focusedItem.slug : focusedItem.name;
          const focusedRev = 'slug' in focusedItem
            ? revisions[focusedItem.slug] || 0
            : blockRevisions[focusedItem.name] || 0;
          return (
            <div
              data-canvas-focus=""
              style={{
                position: 'absolute',
                inset: 0,
                zIndex: 10,
                overflowX: 'hidden',
                overflowY: 'auto',
                background: '#2c2c2c',
              }}
            >
              <MemoizedArtboard
                page={{
                  title: focusedItem.title,
                  slug: focusedKey,
                  name: focusedItem.name,
                  content: focusedItem.content,
                }}
                revision={focusedRev}
                width={focusWidth}
              />
            </div>
          );
        })()}

        <div
          style={{
            position: 'absolute',
            top: 12,
            right: 12,
            zIndex: 20,
            display: 'flex',
            alignItems: 'center',
            gap: 8,
          }}
          className="blockstudio-canvas-menu"
        >
          {focusedSlug ? (
            <Button
              icon={closeSmall}
              label="Close focus mode"
              onClick={() => setFocusedSlug(null)}
              style={{
                background: 'rgba(0, 0, 0, 0.6)',
                color: '#fff',
                borderRadius: '50%',
                width: 32,
                height: 32,
                minWidth: 32,
              }}
            />
          ) : (
            <>
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
            </>
          )}
        </div>
      </div>
    </BlockEditorProvider>
  );
};
