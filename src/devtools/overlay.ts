import type { Bounds, GrabbedBox } from './store';
import { state } from './store';

const GRAB_PURPLE_RGB = '210, 57, 192';
const OVERLAY_CROSSHAIR_COLOR = `rgba(${GRAB_PURPLE_RGB}, 1)`;
const OVERLAY_BORDER_COLOR = `rgba(${GRAB_PURPLE_RGB}, 0.5)`;
const OVERLAY_FILL_COLOR = `rgba(${GRAB_PURPLE_RGB}, 0.08)`;

const SELECTION_LERP_FACTOR = 0.95;
const LERP_CONVERGENCE_THRESHOLD_PX = 0.5;
const FEEDBACK_DURATION_MS = 1500;
const FADE_OUT_BUFFER_MS = 100;
const MIN_DEVICE_PIXEL_RATIO = 2;
const Z_INDEX_OVERLAY_CANVAS = 2147483645;

const OVERLAY_ELEMENT_BORDER_COLOR = `rgba(${GRAB_PURPLE_RGB}, 0.8)`;

type LayerName = 'crosshair' | 'selection' | 'element' | 'grabbed';

interface OffscreenLayer {
  canvas: OffscreenCanvas | null;
  context: OffscreenCanvasRenderingContext2D | null;
}

interface AnimatedBounds {
  id: string;
  current: { x: number; y: number; width: number; height: number };
  target: { x: number; y: number; width: number; height: number };
  borderRadius: number;
  opacity: number;
  createdAt?: number;
  isInitialized: boolean;
}

const lerp = (start: number, end: number, factor: number): number => {
  return start + (end - start) * factor;
};

let canvas: HTMLCanvasElement | null = null;
let mainContext: CanvasRenderingContext2D | null = null;
let canvasWidth = 0;
let canvasHeight = 0;
let devicePixelRatio = 1;
let animationFrameId: number | null = null;

const layers: Record<LayerName, OffscreenLayer> = {
  crosshair: { canvas: null, context: null },
  selection: { canvas: null, context: null },
  element: { canvas: null, context: null },
  grabbed: { canvas: null, context: null },
};

let selectionAnimation: AnimatedBounds | null = null;
let elementBoundsState: Bounds | null = null;
let grabbedAnimations: AnimatedBounds[] = [];

const createOffscreenLayer = (
  layerWidth: number,
  layerHeight: number,
  scaleFactor: number,
): OffscreenLayer => {
  const offscreen = new OffscreenCanvas(
    layerWidth * scaleFactor,
    layerHeight * scaleFactor,
  );
  const context = offscreen.getContext('2d');
  if (context) {
    context.scale(scaleFactor, scaleFactor);
  }
  return { canvas: offscreen, context };
};

const initializeCanvas = (): void => {
  if (!canvas) return;

  devicePixelRatio = Math.max(
    window.devicePixelRatio || 1,
    MIN_DEVICE_PIXEL_RATIO,
  );
  canvasWidth = window.innerWidth;
  canvasHeight = window.innerHeight;

  canvas.width = canvasWidth * devicePixelRatio;
  canvas.height = canvasHeight * devicePixelRatio;
  canvas.style.width = `${canvasWidth}px`;
  canvas.style.height = `${canvasHeight}px`;

  mainContext = canvas.getContext('2d');
  if (mainContext) {
    mainContext.scale(devicePixelRatio, devicePixelRatio);
  }

  for (const layerName of Object.keys(layers) as LayerName[]) {
    layers[layerName] = createOffscreenLayer(
      canvasWidth,
      canvasHeight,
      devicePixelRatio,
    );
  }
};

const drawRoundedRectangle = (
  context: OffscreenCanvasRenderingContext2D,
  rectX: number,
  rectY: number,
  rectWidth: number,
  rectHeight: number,
  cornerRadius: number,
  fillColor: string,
  strokeColor: string,
  opacity: number = 1,
): void => {
  if (rectWidth <= 0 || rectHeight <= 0) return;

  const maxCornerRadius = Math.min(rectWidth / 2, rectHeight / 2);
  const clampedCornerRadius = Math.min(cornerRadius, maxCornerRadius);

  context.globalAlpha = opacity;
  context.beginPath();
  if (clampedCornerRadius > 0) {
    context.roundRect(
      rectX,
      rectY,
      rectWidth,
      rectHeight,
      clampedCornerRadius,
    );
  } else {
    context.rect(rectX, rectY, rectWidth, rectHeight);
  }
  context.fillStyle = fillColor;
  context.fill();
  context.strokeStyle = strokeColor;
  context.lineWidth = 1;
  context.stroke();
  context.globalAlpha = 1;
};

const renderCrosshairLayer = (): void => {
  const layer = layers.crosshair;
  if (!layer.context) return;

  const context = layer.context;
  context.clearRect(0, 0, canvasWidth, canvasHeight);

  if (state.phase !== 'active') return;

  context.strokeStyle = OVERLAY_CROSSHAIR_COLOR;
  context.lineWidth = 1;

  context.beginPath();
  context.moveTo(state.pointer.x, 0);
  context.lineTo(state.pointer.x, canvasHeight);
  context.moveTo(0, state.pointer.y);
  context.lineTo(canvasWidth, state.pointer.y);
  context.stroke();
};

const renderSelectionLayer = (): void => {
  const layer = layers.selection;
  if (!layer.context) return;

  const context = layer.context;
  context.clearRect(0, 0, canvasWidth, canvasHeight);

  if (!selectionAnimation || state.phase !== 'active') return;

  drawRoundedRectangle(
    context,
    selectionAnimation.current.x,
    selectionAnimation.current.y,
    selectionAnimation.current.width,
    selectionAnimation.current.height,
    selectionAnimation.borderRadius,
    OVERLAY_FILL_COLOR,
    OVERLAY_BORDER_COLOR,
    selectionAnimation.opacity,
  );
};

const renderElementLayer = (): void => {
  const layer = layers.element;
  if (!layer.context) return;

  const context = layer.context;
  context.clearRect(0, 0, canvasWidth, canvasHeight);

  if (!elementBoundsState || state.phase !== 'active') return;

  const { x, y, width, height, borderRadius } = elementBoundsState;
  if (width <= 0 || height <= 0) return;

  const maxRadius = Math.min(width / 2, height / 2);
  const radius = Math.min(borderRadius, maxRadius);

  context.globalAlpha = 1;
  context.setLineDash([4, 4]);
  context.strokeStyle = OVERLAY_ELEMENT_BORDER_COLOR;
  context.lineWidth = 1;
  context.beginPath();
  if (radius > 0) {
    context.roundRect(x, y, width, height, radius);
  } else {
    context.rect(x, y, width, height);
  }
  context.stroke();
  context.setLineDash([]);
  context.globalAlpha = 1;
};

const renderGrabbedLayer = (): void => {
  const layer = layers.grabbed;
  if (!layer.context) return;

  const context = layer.context;
  context.clearRect(0, 0, canvasWidth, canvasHeight);

  for (const animation of grabbedAnimations) {
    drawRoundedRectangle(
      context,
      animation.current.x,
      animation.current.y,
      animation.current.width,
      animation.current.height,
      animation.borderRadius,
      OVERLAY_FILL_COLOR,
      OVERLAY_BORDER_COLOR,
      animation.opacity,
    );
  }
};

const compositeAllLayers = (): void => {
  if (!mainContext || !canvas) return;

  mainContext.setTransform(1, 0, 0, 1, 0, 0);
  mainContext.clearRect(0, 0, canvas.width, canvas.height);
  mainContext.setTransform(devicePixelRatio, 0, 0, devicePixelRatio, 0, 0);

  renderCrosshairLayer();
  renderSelectionLayer();
  renderElementLayer();
  renderGrabbedLayer();

  const layerRenderOrder: LayerName[] = ['crosshair', 'selection', 'element', 'grabbed'];
  for (const layerName of layerRenderOrder) {
    const layer = layers[layerName];
    if (layer.canvas) {
      mainContext.drawImage(layer.canvas, 0, 0, canvasWidth, canvasHeight);
    }
  }
};

const interpolateBounds = (
  animation: AnimatedBounds,
  lerpFactor: number,
): boolean => {
  const lerpedX = lerp(animation.current.x, animation.target.x, lerpFactor);
  const lerpedY = lerp(animation.current.y, animation.target.y, lerpFactor);
  const lerpedWidth = lerp(
    animation.current.width,
    animation.target.width,
    lerpFactor,
  );
  const lerpedHeight = lerp(
    animation.current.height,
    animation.target.height,
    lerpFactor,
  );

  const hasBoundsConverged =
    Math.abs(lerpedX - animation.target.x) < LERP_CONVERGENCE_THRESHOLD_PX &&
    Math.abs(lerpedY - animation.target.y) < LERP_CONVERGENCE_THRESHOLD_PX &&
    Math.abs(lerpedWidth - animation.target.width) <
      LERP_CONVERGENCE_THRESHOLD_PX &&
    Math.abs(lerpedHeight - animation.target.height) <
      LERP_CONVERGENCE_THRESHOLD_PX;

  animation.current.x = hasBoundsConverged ? animation.target.x : lerpedX;
  animation.current.y = hasBoundsConverged ? animation.target.y : lerpedY;
  animation.current.width = hasBoundsConverged
    ? animation.target.width
    : lerpedWidth;
  animation.current.height = hasBoundsConverged
    ? animation.target.height
    : lerpedHeight;

  return !hasBoundsConverged;
};

const runAnimationFrame = (): void => {
  let shouldContinueAnimating = false;

  if (selectionAnimation?.isInitialized) {
    if (interpolateBounds(selectionAnimation, SELECTION_LERP_FACTOR)) {
      shouldContinueAnimating = true;
    }
  }

  const currentTimestamp = Date.now();
  grabbedAnimations = grabbedAnimations.filter((animation) => {
    if (animation.isInitialized) {
      if (interpolateBounds(animation, SELECTION_LERP_FACTOR)) {
        shouldContinueAnimating = true;
      }
    }

    if (animation.createdAt) {
      const elapsed = currentTimestamp - animation.createdAt;
      const fadeOutDeadline = FEEDBACK_DURATION_MS + FADE_OUT_BUFFER_MS;

      if (elapsed >= fadeOutDeadline) {
        return false;
      }

      if (elapsed > FEEDBACK_DURATION_MS) {
        const fadeProgress =
          (elapsed - FEEDBACK_DURATION_MS) / FADE_OUT_BUFFER_MS;
        animation.opacity = 1 - fadeProgress;
        shouldContinueAnimating = true;
      }

      return true;
    }

    return animation.opacity > 0;
  });

  compositeAllLayers();

  if (shouldContinueAnimating) {
    animationFrameId = requestAnimationFrame(runAnimationFrame);
  } else {
    animationFrameId = null;
  }
};

const scheduleAnimationFrame = (): void => {
  if (animationFrameId !== null) return;
  animationFrameId = requestAnimationFrame(runAnimationFrame);
};

const updateSelectionBounds = (bounds: Bounds | null): void => {
  if (!bounds) {
    selectionAnimation = null;
    scheduleAnimationFrame();
    return;
  }

  if (selectionAnimation) {
    selectionAnimation.target = {
      x: bounds.x,
      y: bounds.y,
      width: bounds.width,
      height: bounds.height,
    };
    selectionAnimation.borderRadius = bounds.borderRadius;
  } else {
    selectionAnimation = {
      id: 'selection',
      current: {
        x: bounds.x,
        y: bounds.y,
        width: bounds.width,
        height: bounds.height,
      },
      target: {
        x: bounds.x,
        y: bounds.y,
        width: bounds.width,
        height: bounds.height,
      },
      borderRadius: bounds.borderRadius,
      opacity: 1,
      isInitialized: true,
    };
  }

  scheduleAnimationFrame();
};

const addGrabbedBox = (box: GrabbedBox): void => {
  grabbedAnimations.push({
    id: box.id,
    current: {
      x: box.bounds.x,
      y: box.bounds.y,
      width: box.bounds.width,
      height: box.bounds.height,
    },
    target: {
      x: box.bounds.x,
      y: box.bounds.y,
      width: box.bounds.width,
      height: box.bounds.height,
    },
    borderRadius: box.bounds.borderRadius,
    opacity: 1,
    createdAt: box.createdAt,
    isInitialized: true,
  });
  scheduleAnimationFrame();
};

const createCanvas = (): HTMLCanvasElement => {
  const el = document.createElement('canvas');
  el.setAttribute('data-blockstudio-devtools', 'overlay');
  el.style.position = 'fixed';
  el.style.top = '0';
  el.style.left = '0';
  el.style.pointerEvents = 'none';
  el.style.zIndex = String(Z_INDEX_OVERLAY_CANVAS);
  return el;
};

const showOverlay = (container: Element): void => {
  if (!canvas) {
    canvas = createCanvas();
    container.appendChild(canvas);
  }
  initializeCanvas();
  scheduleAnimationFrame();
};

const updateElementBounds = (bounds: Bounds | null): void => {
  elementBoundsState = bounds;
  scheduleAnimationFrame();
};

const hideOverlay = (): void => {
  if (animationFrameId) {
    cancelAnimationFrame(animationFrameId);
    animationFrameId = null;
  }
  selectionAnimation = null;
  elementBoundsState = null;
  if (canvas && mainContext) {
    mainContext.setTransform(1, 0, 0, 1, 0, 0);
    mainContext.clearRect(0, 0, canvas.width, canvas.height);
  }
};

const handleResize = (): void => {
  initializeCanvas();
  scheduleAnimationFrame();
};

const cleanupOverlay = (): void => {
  hideOverlay();
  if (canvas) {
    canvas.remove();
    canvas = null;
    mainContext = null;
  }
  grabbedAnimations = [];
};

export {
  showOverlay,
  hideOverlay,
  cleanupOverlay,
  handleResize,
  updateSelectionBounds,
  updateElementBounds,
  addGrabbedBox,
  scheduleAnimationFrame,
};
