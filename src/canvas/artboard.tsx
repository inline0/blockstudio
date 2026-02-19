import { useCallback, useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';

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

interface StagingLayer {
  id: number;
  revision: number;
  width: number;
  blocks: ReturnType<typeof parse>;
}

interface DisplayLayer {
  id: number;
  revision: number;
  width: number;
  src: string;
  height: number;
}

const DEFAULT_WIDTH = 1440;
const SNAPSHOT_FALLBACK_MS = 8000;
const INITIAL_SPINNER_GRACE_MS = 2500;

const getDisplayHeight = (iframe: HTMLIFrameElement): number => {
  const explicitHeight = Number.parseFloat(iframe.style.height || '');
  if (Number.isFinite(explicitHeight) && explicitHeight > 0) {
    return Math.max(1, Math.ceil(explicitHeight));
  }

  const measuredHeight = iframe.getBoundingClientRect().height;
  if (Number.isFinite(measuredHeight) && measuredHeight > 0) {
    return Math.max(1, Math.ceil(measuredHeight));
  }

  return 1;
};

export const Artboard = ({
  page,
  revision,
  width = DEFAULT_WIDTH,
  onSwapComplete,
}: ArtboardProps): JSX.Element => {
  const stagingRef = useRef<HTMLDivElement>(null);
  const [stagingHost, setStagingHost] = useState<HTMLDivElement | null>(null);
  const stagingIdRef = useRef(1);
  const onSwapCompleteRef = useRef(onSwapComplete);
  const acknowledgedRevisionRef = useRef(revision);
  const renderedTargetRef = useRef<{ revision: number; width: number } | null>(
    null,
  );
  const activeDisplayRef = useRef<DisplayLayer | null>(null);
  const pendingDisplayIdRef = useRef<number | null>(null);
  const blobUrlsRef = useRef<Set<string>>(new Set());
  onSwapCompleteRef.current = onSwapComplete;

  const [stagingLayer, setStagingLayer] = useState<StagingLayer | null>(() => ({
    id: stagingIdRef.current,
    revision,
    width,
    blocks: parse(page.content),
  }));

  const [displayLayers, setDisplayLayers] = useState<DisplayLayer[]>([]);
  const [activeDisplayId, setActiveDisplayId] = useState<number | null>(null);
  const [pendingDisplayId, setPendingDisplayId] = useState<number | null>(null);
  const [readyDisplayId, setReadyDisplayId] = useState<number | null>(null);

  const activeDisplay = activeDisplayId
    ? displayLayers.find((layer) => layer.id === activeDisplayId) || null
    : null;

  activeDisplayRef.current = activeDisplay;
  pendingDisplayIdRef.current = pendingDisplayId;

  const setDisplayIframeRef = useCallback((node: HTMLIFrameElement | null): void => {
    if (!node) return;
    (node as HTMLIFrameElement & { _load?: () => void })._load = () => {};
  }, []);

  const handlePendingLoad = useCallback((id: number): void => {
    if (pendingDisplayIdRef.current !== id) return;
    setReadyDisplayId(id);
  }, []);

  useEffect(() => {
    const host = document.createElement('div');
    host.dataset.canvasStagingHost = page.slug;
    host.style.position = 'fixed';
    host.style.left = '0';
    host.style.top = '0';
    host.style.opacity = '0';
    host.style.pointerEvents = 'none';
    host.style.zIndex = '-1';
    host.style.width = `${width}px`;
    document.body.appendChild(host);
    setStagingHost(host);

    return () => {
      setStagingHost(null);
      host.remove();
    };
  }, [page.slug]);

  useEffect(() => {
    stagingHost?.style.setProperty('width', `${width}px`);
  }, [stagingHost, width]);

  useEffect(() => {
    const rendered = renderedTargetRef.current;
    if (rendered && rendered.revision === revision && rendered.width === width) {
      return;
    }

    setStagingLayer((current) => {
      if (current && current.revision === revision && current.width === width) {
        return current;
      }

      return {
        id: ++stagingIdRef.current,
        revision,
        width,
        blocks: parse(page.content),
      };
    });
  }, [revision, width, page.content]);

  useEffect(() => {
    if (!stagingLayer) return;

    const staging = stagingRef.current;
    if (!staging) return;

    const clearHeightLimits = (): void => {
      staging.querySelectorAll('iframe').forEach((iframe) => {
        iframe.style.setProperty('max-height', 'none', 'important');
      });

      staging
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
    observer.observe(staging, {
      childList: true,
      subtree: true,
      attributes: true,
      attributeFilter: ['style'],
    });
    clearHeightLimits();

    return () => observer.disconnect();
  }, [stagingLayer?.id, stagingHost]);

  useEffect(() => {
    if (!stagingLayer) return;

    const staging = stagingRef.current;
    if (!staging) return;

    let cancelled = false;
    let captured = false;
    const controller = new AbortController();
    const startedAt = performance.now();

    const captureSnapshot = (force = false): void => {
      if (cancelled || captured) return;

      const iframe = staging.querySelector('iframe') as HTMLIFrameElement | null;
      const doc = iframe?.contentDocument;
      const html = iframe?.contentDocument?.documentElement?.outerHTML;
      if (!iframe || !html) return;
      const hasSpinner = !!doc?.querySelector('.blockstudio-block__inner-spinner');
      const hasUnsupportedBlock = !!doc?.querySelector('.wp-block-missing');
      const hasActiveDisplay = !!activeDisplayRef.current;
      const spinnerGraceElapsed =
        performance.now() - startedAt >= INITIAL_SPINNER_GRACE_MS;
      const bodyText = (doc?.body?.textContent || '').trim();
      const hasMeaningfulText =
        bodyText.length > 0 && !/^loading(?:\.\.\.)?$/i.test(bodyText);

      if (!force && !hasActiveDisplay && hasSpinner && !hasUnsupportedBlock) {
        if (!spinnerGraceElapsed) {
          return;
        }
        // Avoid freezing the first visible frame as "spinner only".
        if (!hasMeaningfulText) {
          return;
        }
      }
      captured = true;

      const src = URL.createObjectURL(
        new window.Blob([html], { type: 'text/html' }),
      );
      blobUrlsRef.current.add(src);

      const snapshot: DisplayLayer = {
        id: stagingLayer.id,
        revision: stagingLayer.revision,
        width: stagingLayer.width,
        src,
        height: getDisplayHeight(iframe),
      };

      renderedTargetRef.current = {
        revision: stagingLayer.revision,
        width: stagingLayer.width,
      };

      const active = activeDisplayRef.current;
      if (!active) {
        setDisplayLayers([snapshot]);
        setPendingDisplayId(snapshot.id);
        setReadyDisplayId(null);
        return;
      }

      setDisplayLayers([active, snapshot]);
      setPendingDisplayId(snapshot.id);
      setReadyDisplayId(null);
    };

    waitForIframeReady(staging, controller.signal)
      .then(() => {
        captureSnapshot();
      })
      .catch(() => {});

    const pollTimer =
      activeDisplayRef.current !== null
        ? window.setInterval(() => {
            captureSnapshot();
          }, 500)
        : null;

    const fallbackTimer = window.setTimeout(
      () => captureSnapshot(true),
      SNAPSHOT_FALLBACK_MS,
    );

    return () => {
      cancelled = true;
      if (pollTimer !== null) {
        clearInterval(pollTimer);
      }
      clearTimeout(fallbackTimer);
      controller.abort();
    };
  }, [stagingLayer, stagingHost]);

  useEffect(() => {
    const currentSrc = new Set(displayLayers.map((layer) => layer.src));
    blobUrlsRef.current.forEach((url) => {
      if (!currentSrc.has(url)) {
        URL.revokeObjectURL(url);
        blobUrlsRef.current.delete(url);
      }
    });
  }, [displayLayers]);

  useEffect(() => {
    return () => {
      blobUrlsRef.current.forEach((url) => URL.revokeObjectURL(url));
      blobUrlsRef.current.clear();
    };
  }, []);

  useEffect(() => {
    if (readyDisplayId === null) return;

    const readyLayer = displayLayers.find((layer) => layer.id === readyDisplayId);
    if (!readyLayer) return;

    const previousRevision =
      activeDisplayRef.current?.revision ?? acknowledgedRevisionRef.current;

    setActiveDisplayId(readyLayer.id);
    setDisplayLayers([readyLayer]);
    setPendingDisplayId(null);
    setReadyDisplayId(null);
    setStagingLayer(null);

    if (readyLayer.revision !== previousRevision) {
      acknowledgedRevisionRef.current = readyLayer.revision;
      onSwapCompleteRef.current?.(page.slug);
    }
  }, [readyDisplayId, displayLayers, page.slug]);

  return (
    <div
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
      {displayLayers.map((layer) => {
        const isActive = layer.id === activeDisplayId;
        const isPending = layer.id === pendingDisplayId;
        const isReady = layer.id === readyDisplayId;

        return (
          <div
            key={layer.id}
            className="components-disabled"
            style={
              isActive
                ? {
                    display: 'block',
                    width: '100%',
                    height: layer.height,
                    pointerEvents: 'none',
                    background: '#fff',
                  }
                : {
                    position: 'absolute',
                    top: 0,
                    left: 0,
                    width: '100%',
                    height: layer.height,
                    zIndex: isReady ? 2 : 1,
                    visibility: isReady ? ('visible' as const) : ('hidden' as const),
                    pointerEvents: 'none',
                    background: '#fff',
                  }
            }
          >
            <iframe
              ref={setDisplayIframeRef}
              data-canvas-display-layer={isActive ? 'active' : 'pending'}
              src={layer.src}
              aria-hidden
              tabIndex={-1}
              onLoad={isPending ? () => handlePendingLoad(layer.id) : undefined}
              style={{
                display: 'block',
                width: '100%',
                height: '100%',
                border: 0,
                pointerEvents: 'none',
                background: '#fff',
              }}
            />
          </div>
        );
      })}

      {stagingLayer &&
        stagingHost &&
        createPortal(
          <div ref={stagingRef} data-canvas-staging="" style={{ width }}>
            <div data-canvas-layer-active="true">
              <BlockPreview blocks={stagingLayer.blocks} viewportWidth={width} />
            </div>
          </div>,
          stagingHost,
        )}
    </div>
  );
};
