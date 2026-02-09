import { useCallback, useEffect, useRef, useState } from 'react';

import { Artboard } from './artboard';

interface Page {
  title: string;
  url: string;
  slug: string;
  name: string;
}

interface CanvasSettings {
  adminBar: boolean;
}

interface CanvasProps {
  pages: Page[];
  settings: CanvasSettings;
}

const ARTBOARD_WIDTH = 1440;
const ARTBOARD_HEIGHT = 900;
const GAP = 80;
const LABEL_OFFSET = 28;
const PADDING = 120;
const MIN_SCALE = 0.02;
const MAX_SCALE = 2;

export const Canvas = ({ pages }: CanvasProps): JSX.Element => {
  const containerRef = useRef<HTMLDivElement>(null);
  const [transform, setTransform] = useState<{
    x: number;
    y: number;
    scale: number;
  } | null>(null);

  const columns = pages.length;
  const contentWidth = columns * ARTBOARD_WIDTH + (columns - 1) * GAP;
  const contentHeight = ARTBOARD_HEIGHT;

  const fitToView = useCallback((): { x: number; y: number; scale: number } => {
    const vw = window.innerWidth;
    const vh = window.innerHeight;
    const scaleX = (vw - PADDING * 2) / contentWidth;
    const scaleY = (vh - PADDING * 2) / contentHeight;
    const scale = Math.min(scaleX, scaleY, 1);
    return {
      x: (vw - contentWidth * scale) / 2,
      y: (vh - contentHeight * scale) / 2,
      scale,
    };
  }, [contentWidth, contentHeight]);

  useEffect(() => {
    if (!transform) {
      setTransform(fitToView());
    }
  }, [transform, fitToView]);

  useEffect(() => {
    const container = containerRef.current;
    if (!container) return;

    const handleWheel = (e: WheelEvent): void => {
      e.preventDefault();

      setTransform((prev) => {
        if (!prev) return prev;

        if (e.ctrlKey || e.metaKey) {
          const zoomFactor = 1 - e.deltaY * 0.01;
          const newScale = Math.min(
            MAX_SCALE,
            Math.max(MIN_SCALE, prev.scale * zoomFactor),
          );
          const ratio = newScale / prev.scale;
          const mouseX = e.clientX;
          const mouseY = e.clientY;

          return {
            x: mouseX - (mouseX - prev.x) * ratio,
            y: mouseY - (mouseY - prev.y) * ratio,
            scale: newScale,
          };
        }

        return {
          ...prev,
          x: prev.x - e.deltaX,
          y: prev.y - e.deltaY,
        };
      });
    };

    container.addEventListener('wheel', handleWheel, { passive: false });
    return () => container.removeEventListener('wheel', handleWheel);
  }, []);


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
    <div
      ref={containerRef}
      style={{
        position: 'fixed',
        inset: 0,
        background: '#2c2c2c',
        overflow: 'hidden',
      }}
    >
      {transform && (
        <>
          <div
            data-canvas-surface=""
            style={{
              transformOrigin: '0 0',
              transform: `translate(${transform.x}px, ${transform.y}px) scale(${transform.scale})`,
              willChange: 'transform',
              display: 'grid',
              gridTemplateColumns: `repeat(${columns}, ${ARTBOARD_WIDTH}px)`,
              gap: GAP,
              position: 'absolute',
              top: 0,
              left: 0,
            }}
          >
            {pages.map((page) => (
              <Artboard key={page.slug} page={page} />
            ))}
          </div>
          {pages.map((page, i) => {
            const x = transform.x + i * (ARTBOARD_WIDTH + GAP) * transform.scale;
            const y = transform.y - LABEL_OFFSET;
            const maxWidth = (ARTBOARD_WIDTH + GAP) * transform.scale - 16;

            return (
              <div
                key={page.slug}
                data-canvas-label=""
                style={{
                  position: 'absolute',
                  left: x,
                  top: y,
                  maxWidth,
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
            );
          })}
        </>
      )}
    </div>
  );
};
