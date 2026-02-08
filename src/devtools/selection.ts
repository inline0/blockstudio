import type { Bounds } from './store';

const CACHE_DISTANCE_THRESHOLD = 2;
const CACHE_THROTTLE_MS = 16;
const VISIBILITY_CACHE_TTL_MS = 500;
const VIEWPORT_COVERAGE_THRESHOLD = 0.9;
const DEVTOOLS_OVERLAY_Z_INDEX_THRESHOLD = 2147483640;

interface BlockAncestor {
  element: Element;
  path: string;
}

interface SelectionResult extends BlockAncestor {
  targetElement: Element;
  tagName: string;
}

interface PositionCache {
  x: number;
  y: number;
  result: SelectionResult | null;
  timestamp: number;
}

let cache: PositionCache | null = null;
let visibilityCache = new WeakMap<Element, { isVisible: boolean; timestamp: number }>();

const isDevtoolsElement = (element: Element): boolean => {
  if (element.hasAttribute('data-blockstudio-devtools')) return true;
  const rootNode = element.getRootNode();
  return (
    rootNode instanceof ShadowRoot &&
    rootNode.host.hasAttribute('data-blockstudio-devtools')
  );
};

const isElementVisible = (element: Element, computedStyle?: CSSStyleDeclaration): boolean => {
  const style = computedStyle || window.getComputedStyle(element);
  return (
    style.display !== 'none' &&
    style.visibility !== 'hidden' &&
    parseFloat(style.opacity) > 0
  );
};

const isDevToolsOverlay = (computedStyle: CSSStyleDeclaration): boolean => {
  const zIndex = parseInt(computedStyle.zIndex, 10);
  return (
    computedStyle.pointerEvents === 'none' &&
    computedStyle.position === 'fixed' &&
    !isNaN(zIndex) &&
    zIndex >= DEVTOOLS_OVERLAY_Z_INDEX_THRESHOLD
  );
};

const isFullViewportOverlay = (element: Element, computedStyle: CSSStyleDeclaration): boolean => {
  const position = computedStyle.position;
  if (position !== 'fixed' && position !== 'absolute') return false;

  const rect = element.getBoundingClientRect();
  const coversViewport =
    rect.width / window.innerWidth >= VIEWPORT_COVERAGE_THRESHOLD &&
    rect.height / window.innerHeight >= VIEWPORT_COVERAGE_THRESHOLD;

  if (!coversViewport) return false;

  const backgroundColor = computedStyle.backgroundColor;
  const hasInvisibleBackground =
    backgroundColor === 'transparent' ||
    backgroundColor === 'rgba(0, 0, 0, 0)' ||
    parseFloat(computedStyle.opacity) < 0.1;

  return hasInvisibleBackground;
};

const isValidGrabbableElement = (element: Element): boolean => {
  if (isDevtoolsElement(element)) return false;

  const now = performance.now();
  const cached = visibilityCache.get(element);
  if (cached && now - cached.timestamp < VISIBILITY_CACHE_TTL_MS) {
    return cached.isVisible;
  }

  const computedStyle = window.getComputedStyle(element);
  if (isDevToolsOverlay(computedStyle)) return false;
  if (isFullViewportOverlay(element, computedStyle)) return false;

  const visible = isElementVisible(element, computedStyle);
  visibilityCache.set(element, { isVisible: visible, timestamp: now });
  return visible;
};

const findBlockstudioAncestor = (element: Element): BlockAncestor | null => {
  let current: Element | null = element;
  while (current) {
    if (isDevtoolsElement(current)) return null;
    const path = current.getAttribute('data-blockstudio-path');
    if (path) {
      return { element: current, path };
    }
    current = current.parentElement;
  }
  return null;
};

const getSelectionAtPoint = (x: number, y: number): SelectionResult | null => {
  const now = performance.now();

  if (cache) {
    const dx = Math.abs(x - cache.x);
    const dy = Math.abs(y - cache.y);
    const isPositionClose = dx <= CACHE_DISTANCE_THRESHOLD && dy <= CACHE_DISTANCE_THRESHOLD;
    const isWithinThrottle = now - cache.timestamp < CACHE_THROTTLE_MS;
    if (isPositionClose || isWithinThrottle) {
      return cache.result;
    }
  }

  const elements = document.elementsFromPoint(x, y);
  let result: SelectionResult | null = null;

  for (const el of elements) {
    if (!isValidGrabbableElement(el)) continue;

    const found = findBlockstudioAncestor(el);
    if (found) {
      result = {
        ...found,
        targetElement: el,
        tagName: (el.tagName || '').toLowerCase(),
      };
      break;
    }
  }

  cache = { x, y, result, timestamp: now };
  return result;
};

const createElementBounds = (element: Element): Bounds => {
  const rect = element.getBoundingClientRect();
  const computedStyle = window.getComputedStyle(element);
  const borderRadius = parseFloat(computedStyle.borderRadius) || 0;
  return {
    x: rect.x,
    y: rect.y,
    width: rect.width,
    height: rect.height,
    borderRadius,
  };
};

const clearSelectionCache = (): void => {
  cache = null;
  visibilityCache = new WeakMap();
};

export { getSelectionAtPoint, clearSelectionCache, createElementBounds };
export type { SelectionResult };
