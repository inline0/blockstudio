/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

/***/ "./src/devtools/copy.ts"
/*!******************************!*\
  !*** ./src/devtools/copy.ts ***!
  \******************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   buildCopyContent: () => (/* binding */ buildCopyContent),
/* harmony export */   copyToClipboard: () => (/* binding */ copyToClipboard),
/* harmony export */   shortenPath: () => (/* binding */ shortenPath)
/* harmony export */ });
const PREVIEW_ATTR_VALUE_MAX_LENGTH = 15;
const PREVIEW_TEXT_MAX_LENGTH = 100;
const shortenPath = fullPath => {
  const parts = fullPath.replace(/\\/g, '/').split('/');
  const themeOrPluginIdx = parts.findIndex(p => p === 'themes' || p === 'plugins');
  if (themeOrPluginIdx >= 0 && themeOrPluginIdx + 1 < parts.length) {
    return parts.slice(themeOrPluginIdx + 1).join('/');
  }
  return parts.slice(-3).join('/');
};
const truncateValue = (value, maxLength) => {
  return value.length > maxLength ? `${value.slice(0, maxLength)}...` : value;
};
const getTagName = element => {
  return (element.tagName || '').toLowerCase();
};
const formatChildElements = elements => {
  if (elements.length === 0) return '';
  if (elements.length <= 2) {
    return elements.map(el => `<${getTagName(el)} ...>`).join('\n  ');
  }
  return `(${elements.length} elements)`;
};
const getHTMLPreview = (element, tagName) => {
  if (!(element instanceof HTMLElement)) {
    return `<${tagName} />`;
  }
  let attrsText = '';
  for (const {
    name,
    value
  } of element.attributes) {
    if (name.startsWith('data-blockstudio')) continue;
    attrsText += ` ${name}="${truncateValue(value, PREVIEW_ATTR_VALUE_MAX_LENGTH)}"`;
  }
  const topElements = [];
  const bottomElements = [];
  let foundFirstText = false;
  for (const node of element.childNodes) {
    if (node.nodeType === Node.COMMENT_NODE) continue;
    if (node.nodeType === Node.TEXT_NODE) {
      if (node.textContent && node.textContent.trim().length > 0) {
        foundFirstText = true;
      }
    } else if (node instanceof Element) {
      if (!foundFirstText) {
        topElements.push(node);
      } else {
        bottomElements.push(node);
      }
    }
  }
  const text = element.innerText?.trim() ?? element.textContent?.trim() ?? '';
  const truncatedText = truncateValue(text, PREVIEW_TEXT_MAX_LENGTH);
  let content = '';
  const topStr = formatChildElements(topElements);
  if (topStr) content += `\n  ${topStr}`;
  if (truncatedText.length > 0) content += `\n  ${truncatedText}`;
  const bottomStr = formatChildElements(bottomElements);
  if (bottomStr) content += `\n  ${bottomStr}`;
  if (content.length > 0) {
    return `<${tagName}${attrsText}>${content}\n</${tagName}>`;
  }
  return `<${tagName}${attrsText} />`;
};
const buildCopyContent = (targetElement, tagName, path) => {
  const htmlPreview = getHTMLPreview(targetElement, tagName);
  const shortPath = shortenPath(path);
  return `@<${tagName}>\n\n${htmlPreview}\n  in ${shortPath}`;
};
const copyToClipboard = async text => {
  try {
    await navigator.clipboard.writeText(text);
    return true;
  } catch {
    return false;
  }
};


/***/ },

/***/ "./src/devtools/keyboard.ts"
/*!**********************************!*\
  !*** ./src/devtools/keyboard.ts ***!
  \**********************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   isActivationKeyDown: () => (/* binding */ isActivationKeyDown),
/* harmony export */   isActivationKeyUp: () => (/* binding */ isActivationKeyUp),
/* harmony export */   isKeyboardEventTriggeredByInput: () => (/* binding */ isKeyboardEventTriggeredByInput),
/* harmony export */   isMac: () => (/* binding */ isMac)
/* harmony export */ });
const C_LIKE_CHARACTERS = new Set(['c', 'C', '\u0441', '\u0421', '\u023c', '\u023b', '\u2184', '\u2183', '\u1d04', '\u1d9c', '\u217d', '\u216d', 'ç', 'Ç', 'ć', 'Ć', 'č', 'Č', 'ĉ', 'Ĉ', 'ċ', 'Ċ']);
const isCLikeKey = (key, code) => {
  if (code === 'KeyC') return true;
  if (!key || key.length !== 1) return false;
  return C_LIKE_CHARACTERS.has(key);
};
let cachedIsMac = null;
const isMac = () => {
  if (cachedIsMac !== null) return cachedIsMac;
  cachedIsMac = navigator.platform?.toUpperCase().indexOf('MAC') >= 0 || /Mac|iPhone|iPad|iPod/.test(navigator.userAgent);
  return cachedIsMac;
};
const isModifierHeld = e => {
  return isMac() ? e.metaKey : e.ctrlKey;
};
const isActivationKeyDown = e => {
  return isCLikeKey(e.key, e.code) && isModifierHeld(e);
};
const isActivationKeyUp = e => {
  return isCLikeKey(e.key, e.code) || (isMac() ? e.key === 'Meta' : e.key === 'Control');
};
const isKeyboardEventTriggeredByInput = event => {
  const target = event.target;
  if (!target) return false;
  if (target.isContentEditable) return true;
  const tagName = target.tagName?.toLowerCase();
  if (tagName === 'input' || tagName === 'textarea' || tagName === 'select') return true;
  const role = target.getAttribute('role');
  if (role === 'textbox' || role === 'searchbox') return true;
  return false;
};


/***/ },

/***/ "./src/devtools/overlay.ts"
/*!*********************************!*\
  !*** ./src/devtools/overlay.ts ***!
  \*********************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   addGrabbedBox: () => (/* binding */ addGrabbedBox),
/* harmony export */   cleanupOverlay: () => (/* binding */ cleanupOverlay),
/* harmony export */   handleResize: () => (/* binding */ handleResize),
/* harmony export */   hideOverlay: () => (/* binding */ hideOverlay),
/* harmony export */   scheduleAnimationFrame: () => (/* binding */ scheduleAnimationFrame),
/* harmony export */   showOverlay: () => (/* binding */ showOverlay),
/* harmony export */   updateElementBounds: () => (/* binding */ updateElementBounds),
/* harmony export */   updateSelectionBounds: () => (/* binding */ updateSelectionBounds)
/* harmony export */ });
/* harmony import */ var _store__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./store */ "./src/devtools/store.ts");

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
const lerp = (start, end, factor) => {
  return start + (end - start) * factor;
};
let canvas = null;
let mainContext = null;
let canvasWidth = 0;
let canvasHeight = 0;
let devicePixelRatio = 1;
let animationFrameId = null;
const layers = {
  crosshair: {
    canvas: null,
    context: null
  },
  selection: {
    canvas: null,
    context: null
  },
  element: {
    canvas: null,
    context: null
  },
  grabbed: {
    canvas: null,
    context: null
  }
};
let selectionAnimation = null;
let elementBoundsState = null;
let grabbedAnimations = [];
const createOffscreenLayer = (layerWidth, layerHeight, scaleFactor) => {
  const offscreen = new OffscreenCanvas(layerWidth * scaleFactor, layerHeight * scaleFactor);
  const context = offscreen.getContext('2d');
  if (context) {
    context.scale(scaleFactor, scaleFactor);
  }
  return {
    canvas: offscreen,
    context
  };
};
const initializeCanvas = () => {
  if (!canvas) return;
  devicePixelRatio = Math.max(window.devicePixelRatio || 1, MIN_DEVICE_PIXEL_RATIO);
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
  for (const layerName of Object.keys(layers)) {
    layers[layerName] = createOffscreenLayer(canvasWidth, canvasHeight, devicePixelRatio);
  }
};
const drawRoundedRectangle = (context, rectX, rectY, rectWidth, rectHeight, cornerRadius, fillColor, strokeColor, opacity = 1) => {
  if (rectWidth <= 0 || rectHeight <= 0) return;
  const maxCornerRadius = Math.min(rectWidth / 2, rectHeight / 2);
  const clampedCornerRadius = Math.min(cornerRadius, maxCornerRadius);
  context.globalAlpha = opacity;
  context.beginPath();
  if (clampedCornerRadius > 0) {
    context.roundRect(rectX, rectY, rectWidth, rectHeight, clampedCornerRadius);
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
const renderCrosshairLayer = () => {
  const layer = layers.crosshair;
  if (!layer.context) return;
  const context = layer.context;
  context.clearRect(0, 0, canvasWidth, canvasHeight);
  if (_store__WEBPACK_IMPORTED_MODULE_0__.state.phase !== 'active') return;
  context.strokeStyle = OVERLAY_CROSSHAIR_COLOR;
  context.lineWidth = 1;
  context.beginPath();
  context.moveTo(_store__WEBPACK_IMPORTED_MODULE_0__.state.pointer.x, 0);
  context.lineTo(_store__WEBPACK_IMPORTED_MODULE_0__.state.pointer.x, canvasHeight);
  context.moveTo(0, _store__WEBPACK_IMPORTED_MODULE_0__.state.pointer.y);
  context.lineTo(canvasWidth, _store__WEBPACK_IMPORTED_MODULE_0__.state.pointer.y);
  context.stroke();
};
const renderSelectionLayer = () => {
  const layer = layers.selection;
  if (!layer.context) return;
  const context = layer.context;
  context.clearRect(0, 0, canvasWidth, canvasHeight);
  if (!selectionAnimation || _store__WEBPACK_IMPORTED_MODULE_0__.state.phase !== 'active') return;
  drawRoundedRectangle(context, selectionAnimation.current.x, selectionAnimation.current.y, selectionAnimation.current.width, selectionAnimation.current.height, selectionAnimation.borderRadius, OVERLAY_FILL_COLOR, OVERLAY_BORDER_COLOR, selectionAnimation.opacity);
};
const renderElementLayer = () => {
  const layer = layers.element;
  if (!layer.context) return;
  const context = layer.context;
  context.clearRect(0, 0, canvasWidth, canvasHeight);
  if (!elementBoundsState || _store__WEBPACK_IMPORTED_MODULE_0__.state.phase !== 'active') return;
  const {
    x,
    y,
    width,
    height,
    borderRadius
  } = elementBoundsState;
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
const renderGrabbedLayer = () => {
  const layer = layers.grabbed;
  if (!layer.context) return;
  const context = layer.context;
  context.clearRect(0, 0, canvasWidth, canvasHeight);
  for (const animation of grabbedAnimations) {
    drawRoundedRectangle(context, animation.current.x, animation.current.y, animation.current.width, animation.current.height, animation.borderRadius, OVERLAY_FILL_COLOR, OVERLAY_BORDER_COLOR, animation.opacity);
  }
};
const compositeAllLayers = () => {
  if (!mainContext || !canvas) return;
  mainContext.setTransform(1, 0, 0, 1, 0, 0);
  mainContext.clearRect(0, 0, canvas.width, canvas.height);
  mainContext.setTransform(devicePixelRatio, 0, 0, devicePixelRatio, 0, 0);
  renderCrosshairLayer();
  renderSelectionLayer();
  renderElementLayer();
  renderGrabbedLayer();
  const layerRenderOrder = ['crosshair', 'selection', 'element', 'grabbed'];
  for (const layerName of layerRenderOrder) {
    const layer = layers[layerName];
    if (layer.canvas) {
      mainContext.drawImage(layer.canvas, 0, 0, canvasWidth, canvasHeight);
    }
  }
};
const interpolateBounds = (animation, lerpFactor) => {
  const lerpedX = lerp(animation.current.x, animation.target.x, lerpFactor);
  const lerpedY = lerp(animation.current.y, animation.target.y, lerpFactor);
  const lerpedWidth = lerp(animation.current.width, animation.target.width, lerpFactor);
  const lerpedHeight = lerp(animation.current.height, animation.target.height, lerpFactor);
  const hasBoundsConverged = Math.abs(lerpedX - animation.target.x) < LERP_CONVERGENCE_THRESHOLD_PX && Math.abs(lerpedY - animation.target.y) < LERP_CONVERGENCE_THRESHOLD_PX && Math.abs(lerpedWidth - animation.target.width) < LERP_CONVERGENCE_THRESHOLD_PX && Math.abs(lerpedHeight - animation.target.height) < LERP_CONVERGENCE_THRESHOLD_PX;
  animation.current.x = hasBoundsConverged ? animation.target.x : lerpedX;
  animation.current.y = hasBoundsConverged ? animation.target.y : lerpedY;
  animation.current.width = hasBoundsConverged ? animation.target.width : lerpedWidth;
  animation.current.height = hasBoundsConverged ? animation.target.height : lerpedHeight;
  return !hasBoundsConverged;
};
const runAnimationFrame = () => {
  let shouldContinueAnimating = false;
  if (selectionAnimation?.isInitialized) {
    if (interpolateBounds(selectionAnimation, SELECTION_LERP_FACTOR)) {
      shouldContinueAnimating = true;
    }
  }
  const currentTimestamp = Date.now();
  grabbedAnimations = grabbedAnimations.filter(animation => {
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
        const fadeProgress = (elapsed - FEEDBACK_DURATION_MS) / FADE_OUT_BUFFER_MS;
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
const scheduleAnimationFrame = () => {
  if (animationFrameId !== null) return;
  animationFrameId = requestAnimationFrame(runAnimationFrame);
};
const updateSelectionBounds = bounds => {
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
      height: bounds.height
    };
    selectionAnimation.borderRadius = bounds.borderRadius;
  } else {
    selectionAnimation = {
      id: 'selection',
      current: {
        x: bounds.x,
        y: bounds.y,
        width: bounds.width,
        height: bounds.height
      },
      target: {
        x: bounds.x,
        y: bounds.y,
        width: bounds.width,
        height: bounds.height
      },
      borderRadius: bounds.borderRadius,
      opacity: 1,
      isInitialized: true
    };
  }
  scheduleAnimationFrame();
};
const addGrabbedBox = box => {
  grabbedAnimations.push({
    id: box.id,
    current: {
      x: box.bounds.x,
      y: box.bounds.y,
      width: box.bounds.width,
      height: box.bounds.height
    },
    target: {
      x: box.bounds.x,
      y: box.bounds.y,
      width: box.bounds.width,
      height: box.bounds.height
    },
    borderRadius: box.bounds.borderRadius,
    opacity: 1,
    createdAt: box.createdAt,
    isInitialized: true
  });
  scheduleAnimationFrame();
};
const createCanvas = () => {
  const el = document.createElement('canvas');
  el.setAttribute('data-blockstudio-devtools', 'overlay');
  el.style.position = 'fixed';
  el.style.top = '0';
  el.style.left = '0';
  el.style.pointerEvents = 'none';
  el.style.zIndex = String(Z_INDEX_OVERLAY_CANVAS);
  return el;
};
const showOverlay = container => {
  if (!canvas) {
    canvas = createCanvas();
    container.appendChild(canvas);
  }
  initializeCanvas();
  scheduleAnimationFrame();
};
const updateElementBounds = bounds => {
  elementBoundsState = bounds;
  scheduleAnimationFrame();
};
const hideOverlay = () => {
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
const handleResize = () => {
  initializeCanvas();
  scheduleAnimationFrame();
};
const cleanupOverlay = () => {
  hideOverlay();
  if (canvas) {
    canvas.remove();
    canvas = null;
    mainContext = null;
  }
  grabbedAnimations = [];
};


/***/ },

/***/ "./src/devtools/selection.ts"
/*!***********************************!*\
  !*** ./src/devtools/selection.ts ***!
  \***********************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   clearSelectionCache: () => (/* binding */ clearSelectionCache),
/* harmony export */   createElementBounds: () => (/* binding */ createElementBounds),
/* harmony export */   getSelectionAtPoint: () => (/* binding */ getSelectionAtPoint)
/* harmony export */ });
const CACHE_DISTANCE_THRESHOLD = 2;
const CACHE_THROTTLE_MS = 16;
const VISIBILITY_CACHE_TTL_MS = 500;
const VIEWPORT_COVERAGE_THRESHOLD = 0.9;
const DEVTOOLS_OVERLAY_Z_INDEX_THRESHOLD = 2147483640;
let cache = null;
let visibilityCache = new WeakMap();
const isDevtoolsElement = element => {
  if (element.hasAttribute('data-blockstudio-devtools')) return true;
  const rootNode = element.getRootNode();
  return rootNode instanceof ShadowRoot && rootNode.host.hasAttribute('data-blockstudio-devtools');
};
const isElementVisible = (element, computedStyle) => {
  const style = computedStyle || window.getComputedStyle(element);
  return style.display !== 'none' && style.visibility !== 'hidden' && parseFloat(style.opacity) > 0;
};
const isDevToolsOverlay = computedStyle => {
  const zIndex = parseInt(computedStyle.zIndex, 10);
  return computedStyle.pointerEvents === 'none' && computedStyle.position === 'fixed' && !isNaN(zIndex) && zIndex >= DEVTOOLS_OVERLAY_Z_INDEX_THRESHOLD;
};
const isFullViewportOverlay = (element, computedStyle) => {
  const position = computedStyle.position;
  if (position !== 'fixed' && position !== 'absolute') return false;
  const rect = element.getBoundingClientRect();
  const coversViewport = rect.width / window.innerWidth >= VIEWPORT_COVERAGE_THRESHOLD && rect.height / window.innerHeight >= VIEWPORT_COVERAGE_THRESHOLD;
  if (!coversViewport) return false;
  const backgroundColor = computedStyle.backgroundColor;
  const hasInvisibleBackground = backgroundColor === 'transparent' || backgroundColor === 'rgba(0, 0, 0, 0)' || parseFloat(computedStyle.opacity) < 0.1;
  return hasInvisibleBackground;
};
const isValidGrabbableElement = element => {
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
  visibilityCache.set(element, {
    isVisible: visible,
    timestamp: now
  });
  return visible;
};
const findBlockstudioAncestor = element => {
  let current = element;
  while (current) {
    if (isDevtoolsElement(current)) return null;
    const path = current.getAttribute('data-blockstudio-path');
    if (path) {
      return {
        element: current,
        path
      };
    }
    current = current.parentElement;
  }
  return null;
};
const getSelectionAtPoint = (x, y) => {
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
  let result = null;
  for (const el of elements) {
    if (!isValidGrabbableElement(el)) continue;
    const found = findBlockstudioAncestor(el);
    if (found) {
      result = {
        ...found,
        targetElement: el,
        tagName: (el.tagName || '').toLowerCase()
      };
      break;
    }
  }
  cache = {
    x,
    y,
    result,
    timestamp: now
  };
  return result;
};
const createElementBounds = element => {
  const rect = element.getBoundingClientRect();
  const computedStyle = window.getComputedStyle(element);
  const borderRadius = parseFloat(computedStyle.borderRadius) || 0;
  return {
    x: rect.x,
    y: rect.y,
    width: rect.width,
    height: rect.height,
    borderRadius
  };
};
const clearSelectionCache = () => {
  cache = null;
  visibilityCache = new WeakMap();
};


/***/ },

/***/ "./src/devtools/store.ts"
/*!*******************************!*\
  !*** ./src/devtools/store.ts ***!
  \*******************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   OFFSCREEN: () => (/* binding */ OFFSCREEN),
/* harmony export */   state: () => (/* binding */ state)
/* harmony export */ });
const OFFSCREEN = -10000;
const state = {
  phase: 'idle',
  holdStartedAt: 0,
  pointer: {
    x: OFFSCREEN,
    y: OFFSCREEN
  },
  detectedElement: null,
  detectedPath: null,
  detectedTargetElement: null,
  detectedTagName: null,
  elementBounds: null,
  animatedBounds: null,
  grabbedBoxes: []
};


/***/ },

/***/ "./src/devtools/styles.ts"
/*!********************************!*\
  !*** ./src/devtools/styles.ts ***!
  \********************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   cleanupElementLabel: () => (/* binding */ cleanupElementLabel),
/* harmony export */   cleanupLabel: () => (/* binding */ cleanupLabel),
/* harmony export */   hideElementLabel: () => (/* binding */ hideElementLabel),
/* harmony export */   hideLabel: () => (/* binding */ hideLabel),
/* harmony export */   showCopiedFeedback: () => (/* binding */ showCopiedFeedback),
/* harmony export */   showElementLabel: () => (/* binding */ showElementLabel),
/* harmony export */   showLabel: () => (/* binding */ showLabel)
/* harmony export */ });
/* harmony import */ var _copy__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./copy */ "./src/devtools/copy.ts");

const VIEWPORT_MARGIN_PX = 8;
const ARROW_HEIGHT_PX = 8;
const ARROW_MIN_SIZE_PX = 4;
const ARROW_MAX_LABEL_WIDTH_RATIO = 0.2;
const ARROW_LABEL_MARGIN_PX = 16;
const LABEL_GAP_PX = 4;
const Z_INDEX_LABEL = 2147483647;
let container = null;
let panel = null;
let arrow = null;
let elementContainer = null;
let elementPanel = null;
let elementArrow = null;
const getArrowSize = labelWidth => {
  if (labelWidth <= 0) return ARROW_HEIGHT_PX;
  const scaledSize = labelWidth * ARROW_MAX_LABEL_WIDTH_RATIO;
  return Math.max(ARROW_MIN_SIZE_PX, Math.min(ARROW_HEIGHT_PX, scaledSize));
};
const createArrow = () => {
  const el = document.createElement('div');
  el.setAttribute('data-blockstudio-devtools', 'arrow');
  el.style.position = 'absolute';
  el.style.width = '0';
  el.style.height = '0';
  el.style.zIndex = '10';
  return el;
};
const updateArrow = (arrowEl, position, leftOffset, labelWidth) => {
  const size = getArrowSize(labelWidth);
  arrowEl.style.left = `calc(50% + ${leftOffset}px)`;
  arrowEl.style.borderLeft = `${size}px solid transparent`;
  arrowEl.style.borderRight = `${size}px solid transparent`;
  if (position === 'bottom') {
    arrowEl.style.top = '0';
    arrowEl.style.bottom = '';
    arrowEl.style.transform = 'translateX(-50%) translateY(-100%)';
    arrowEl.style.borderBottom = `${size}px solid white`;
    arrowEl.style.borderTop = 'none';
    arrowEl.style.filter = 'drop-shadow(-1px -1px 0 rgba(0,0,0,0.06)) drop-shadow(1px -1px 0 rgba(0,0,0,0.06))';
  } else {
    arrowEl.style.top = '';
    arrowEl.style.bottom = '0';
    arrowEl.style.transform = 'translateX(-50%) translateY(100%)';
    arrowEl.style.borderTop = `${size}px solid white`;
    arrowEl.style.borderBottom = 'none';
    arrowEl.style.filter = 'drop-shadow(-1px 1px 0 rgba(0,0,0,0.06)) drop-shadow(1px 1px 0 rgba(0,0,0,0.06))';
  }
};
const createPanel = () => {
  const el = document.createElement('div');
  el.setAttribute('data-blockstudio-devtools', 'panel');
  el.style.display = 'flex';
  el.style.alignItems = 'center';
  el.style.gap = '5px';
  el.style.borderRadius = '10px';
  el.style.backgroundColor = 'white';
  el.style.width = 'fit-content';
  el.style.height = 'fit-content';
  el.style.padding = '6px 8px';
  el.style.fontFamily = '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
  el.style.fontSize = '13px';
  el.style.lineHeight = '16px';
  el.style.fontWeight = '500';
  el.style.color = '#000';
  el.style.whiteSpace = 'nowrap';
  el.style.webkitFontSmoothing = 'antialiased';
  return el;
};
const createContainer = () => {
  const el = document.createElement('div');
  el.setAttribute('data-blockstudio-devtools', 'label');
  el.style.position = 'fixed';
  el.style.zIndex = String(Z_INDEX_LABEL);
  el.style.pointerEvents = 'none';
  el.style.filter = 'drop-shadow(0px 1px 2px rgba(81, 81, 81, 0.25))';
  el.style.userSelect = 'none';
  el.style.transition = 'opacity 100ms ease-out';
  el.style.opacity = '1';
  arrow = createArrow();
  panel = createPanel();
  el.appendChild(arrow);
  el.appendChild(panel);
  return el;
};
const computePosition = (selectionBounds, cursorX, labelWidth, labelHeight, preferTop = false) => {
  const viewportWidth = window.innerWidth;
  const viewportHeight = window.innerHeight;
  const panelW = labelWidth > 0 ? labelWidth : 100;
  const actualArrowHeight = getArrowSize(panelW);
  const anchorX = cursorX;
  let edgeOffsetX = 0;
  const selectionBottom = selectionBounds.y + selectionBounds.height;
  const selectionTop = selectionBounds.y;
  if (labelWidth > 0) {
    const labelLeft = anchorX - labelWidth / 2;
    const labelRight = anchorX + labelWidth / 2;
    if (labelRight > viewportWidth - VIEWPORT_MARGIN_PX) {
      edgeOffsetX = viewportWidth - VIEWPORT_MARGIN_PX - labelRight;
    }
    if (labelLeft + edgeOffsetX < VIEWPORT_MARGIN_PX) {
      edgeOffsetX = VIEWPORT_MARGIN_PX - labelLeft;
    }
  }
  const totalHeightNeeded = labelHeight + actualArrowHeight + LABEL_GAP_PX;
  let positionTop;
  let arrowPosition;
  if (preferTop) {
    const topPosition = selectionTop - totalHeightNeeded;
    if (topPosition >= VIEWPORT_MARGIN_PX) {
      positionTop = topPosition;
      arrowPosition = 'top';
    } else {
      positionTop = selectionBottom + actualArrowHeight + LABEL_GAP_PX;
      arrowPosition = 'bottom';
    }
  } else {
    positionTop = selectionBottom + actualArrowHeight + LABEL_GAP_PX;
    const fitsBelow = positionTop + labelHeight <= viewportHeight - VIEWPORT_MARGIN_PX;
    if (fitsBelow) {
      arrowPosition = 'bottom';
    } else {
      positionTop = selectionTop - totalHeightNeeded;
      arrowPosition = 'top';
    }
  }
  if (positionTop < VIEWPORT_MARGIN_PX) {
    positionTop = VIEWPORT_MARGIN_PX;
  }
  const labelHalfWidth = labelWidth / 2;
  const arrowCenterPx = labelHalfWidth - edgeOffsetX;
  const arrowMinPx = Math.min(ARROW_LABEL_MARGIN_PX, labelHalfWidth);
  const arrowMaxPx = Math.max(labelWidth - ARROW_LABEL_MARGIN_PX, labelHalfWidth);
  const clampedArrowCenterPx = Math.max(arrowMinPx, Math.min(arrowMaxPx, arrowCenterPx));
  const arrowLeftOffset = clampedArrowCenterPx - labelHalfWidth;
  return {
    left: anchorX,
    top: positionTop,
    arrowLeftOffset,
    edgeOffsetX,
    arrowPosition
  };
};
const showLabel = (path, selectionBounds, cursorX, parentContainer) => {
  if (!container) {
    container = createContainer();
    parentContainer.appendChild(container);
  }
  if (panel) {
    panel.textContent = (0,_copy__WEBPACK_IMPORTED_MODULE_0__.shortenPath)(path);
  }
  container.style.display = 'block';
  container.style.opacity = '1';
  const labelRect = container.getBoundingClientRect();
  const panelRect = panel?.getBoundingClientRect();
  const panelWidth = panelRect?.width ?? 0;
  const position = computePosition(selectionBounds, cursorX, labelRect.width, labelRect.height);
  container.style.left = `${position.left}px`;
  container.style.top = `${position.top}px`;
  container.style.transform = `translateX(calc(-50% + ${position.edgeOffsetX}px))`;
  if (arrow) {
    updateArrow(arrow, position.arrowPosition, position.arrowLeftOffset, panelWidth);
  }
};
const hideLabel = () => {
  if (container) {
    container.style.display = 'none';
  }
};
const showCopiedFeedback = path => {
  if (panel) {
    panel.textContent = `Copied: ${(0,_copy__WEBPACK_IMPORTED_MODULE_0__.shortenPath)(path)}`;
  }
};
const cleanupLabel = () => {
  if (container) {
    container.remove();
    container = null;
    panel = null;
    arrow = null;
  }
};
const createElementPanel = () => {
  const el = document.createElement('div');
  el.setAttribute('data-blockstudio-devtools', 'element-panel');
  el.style.display = 'flex';
  el.style.alignItems = 'center';
  el.style.borderRadius = '8px';
  el.style.backgroundColor = 'white';
  el.style.width = 'fit-content';
  el.style.height = 'fit-content';
  el.style.padding = '4px 7px';
  el.style.fontFamily = '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
  el.style.fontSize = '12px';
  el.style.lineHeight = '14px';
  el.style.fontWeight = '500';
  el.style.color = 'rgba(0, 0, 0, 0.5)';
  el.style.whiteSpace = 'nowrap';
  el.style.webkitFontSmoothing = 'antialiased';
  return el;
};
const createElementContainer = () => {
  const el = document.createElement('div');
  el.setAttribute('data-blockstudio-devtools', 'element-label');
  el.style.position = 'fixed';
  el.style.zIndex = String(Z_INDEX_LABEL);
  el.style.pointerEvents = 'none';
  el.style.filter = 'drop-shadow(0px 1px 2px rgba(81, 81, 81, 0.25))';
  el.style.userSelect = 'none';
  el.style.transition = 'opacity 100ms ease-out';
  el.style.opacity = '1';
  elementArrow = createArrow();
  elementPanel = createElementPanel();
  el.appendChild(elementArrow);
  el.appendChild(elementPanel);
  return el;
};
const showElementLabel = (tagName, elementBounds, cursorX, parentContainer) => {
  if (!elementContainer) {
    elementContainer = createElementContainer();
    parentContainer.appendChild(elementContainer);
  }
  if (elementPanel) {
    elementPanel.textContent = `<${tagName}>`;
  }
  elementContainer.style.display = 'block';
  elementContainer.style.opacity = '1';
  const labelRect = elementContainer.getBoundingClientRect();
  const panelRect = elementPanel?.getBoundingClientRect();
  const panelWidth = panelRect?.width ?? 0;
  const position = computePosition(elementBounds, cursorX, labelRect.width, labelRect.height, true);
  elementContainer.style.left = `${position.left}px`;
  elementContainer.style.top = `${position.top}px`;
  elementContainer.style.transform = `translateX(calc(-50% + ${position.edgeOffsetX}px))`;
  if (elementArrow) {
    updateArrow(elementArrow, position.arrowPosition, position.arrowLeftOffset, panelWidth);
  }
};
const hideElementLabel = () => {
  if (elementContainer) {
    elementContainer.style.display = 'none';
  }
};
const cleanupElementLabel = () => {
  if (elementContainer) {
    elementContainer.remove();
    elementContainer = null;
    elementPanel = null;
    elementArrow = null;
  }
};


/***/ }

/******/ 	});
/************************************************************************/
/******/ 	// The module cache
/******/ 	var __webpack_module_cache__ = {};
/******/ 	
/******/ 	// The require function
/******/ 	function __webpack_require__(moduleId) {
/******/ 		// Check if module is in cache
/******/ 		var cachedModule = __webpack_module_cache__[moduleId];
/******/ 		if (cachedModule !== undefined) {
/******/ 			return cachedModule.exports;
/******/ 		}
/******/ 		// Check if module exists (development only)
/******/ 		if (__webpack_modules__[moduleId] === undefined) {
/******/ 			var e = new Error("Cannot find module '" + moduleId + "'");
/******/ 			e.code = 'MODULE_NOT_FOUND';
/******/ 			throw e;
/******/ 		}
/******/ 		// Create a new module (and put it into the cache)
/******/ 		var module = __webpack_module_cache__[moduleId] = {
/******/ 			// no module.id needed
/******/ 			// no module.loaded needed
/******/ 			exports: {}
/******/ 		};
/******/ 	
/******/ 		// Execute the module function
/******/ 		__webpack_modules__[moduleId](module, module.exports, __webpack_require__);
/******/ 	
/******/ 		// Return the exports of the module
/******/ 		return module.exports;
/******/ 	}
/******/ 	
/************************************************************************/
/******/ 	/* webpack/runtime/define property getters */
/******/ 	(() => {
/******/ 		// define getter functions for harmony exports
/******/ 		__webpack_require__.d = (exports, definition) => {
/******/ 			for(var key in definition) {
/******/ 				if(__webpack_require__.o(definition, key) && !__webpack_require__.o(exports, key)) {
/******/ 					Object.defineProperty(exports, key, { enumerable: true, get: definition[key] });
/******/ 				}
/******/ 			}
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/hasOwnProperty shorthand */
/******/ 	(() => {
/******/ 		__webpack_require__.o = (obj, prop) => (Object.prototype.hasOwnProperty.call(obj, prop))
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/make namespace object */
/******/ 	(() => {
/******/ 		// define __esModule on exports
/******/ 		__webpack_require__.r = (exports) => {
/******/ 			if(typeof Symbol !== 'undefined' && Symbol.toStringTag) {
/******/ 				Object.defineProperty(exports, Symbol.toStringTag, { value: 'Module' });
/******/ 			}
/******/ 			Object.defineProperty(exports, '__esModule', { value: true });
/******/ 		};
/******/ 	})();
/******/ 	
/************************************************************************/
var __webpack_exports__ = {};
// This entry needs to be wrapped in an IIFE because it needs to be isolated against other modules in the chunk.
(() => {
/*!*******************************!*\
  !*** ./src/devtools/index.ts ***!
  \*******************************/
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   cleanup: () => (/* binding */ cleanup)
/* harmony export */ });
/* harmony import */ var _store__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./store */ "./src/devtools/store.ts");
/* harmony import */ var _keyboard__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./keyboard */ "./src/devtools/keyboard.ts");
/* harmony import */ var _selection__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./selection */ "./src/devtools/selection.ts");
/* harmony import */ var _overlay__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ./overlay */ "./src/devtools/overlay.ts");
/* harmony import */ var _styles__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ./styles */ "./src/devtools/styles.ts");
/* harmony import */ var _copy__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ./copy */ "./src/devtools/copy.ts");






const HOLD_THRESHOLD_MS = 100;
const FEEDBACK_DURATION_MS = 1500;
const DEVTOOLS_ATTRIBUTE = 'data-blockstudio-devtools';
const FROZEN_GLOW_COLOR = 'rgba(210, 57, 192, 0.15)';
const FROZEN_GLOW_EDGE_PX = 50;
const Z_INDEX_OVERLAY_CANVAS = 2147483645;
let holdTimer = null;
let shadowHost = null;
let shadowContainer = null;
let glowOverlay = null;
let pointerOverrideStyle = null;
let abortController = null;
let inToggleFeedbackPeriod = false;
let toggleFeedbackTimerId = null;
const enablePointerEventsOverride = () => {
  if (pointerOverrideStyle) return;
  pointerOverrideStyle = document.createElement('style');
  pointerOverrideStyle.setAttribute('data-blockstudio-pointer-override', '');
  pointerOverrideStyle.textContent = '* { pointer-events: auto !important; }';
  document.head.appendChild(pointerOverrideStyle);
};
const disablePointerEventsOverride = () => {
  pointerOverrideStyle?.remove();
  pointerOverrideStyle = null;
};
const createGlowOverlay = () => {
  const el = document.createElement('div');
  el.setAttribute(DEVTOOLS_ATTRIBUTE, 'glow');
  el.style.position = 'fixed';
  el.style.top = '0';
  el.style.right = '0';
  el.style.bottom = '0';
  el.style.left = '0';
  el.style.pointerEvents = 'none';
  el.style.zIndex = String(Z_INDEX_OVERLAY_CANVAS);
  el.style.opacity = '0';
  el.style.transition = 'opacity 100ms ease-out';
  el.style.willChange = 'opacity';
  el.style.contain = 'strict';
  el.style.transform = 'translateZ(0)';
  el.style.boxShadow = `inset 0 0 ${FROZEN_GLOW_EDGE_PX}px ${FROZEN_GLOW_COLOR}`;
  return el;
};
const showGlow = () => {
  if (glowOverlay) {
    glowOverlay.style.opacity = '1';
  }
};
const hideGlow = () => {
  if (glowOverlay) {
    glowOverlay.style.opacity = '0';
  }
};
const mountRoot = () => {
  const existingHost = document.querySelector(`[${DEVTOOLS_ATTRIBUTE}="host"]`);
  if (existingHost?.shadowRoot) {
    const existingContainer = existingHost.shadowRoot.querySelector(`[${DEVTOOLS_ATTRIBUTE}="root"]`);
    if (existingContainer instanceof HTMLDivElement) {
      shadowHost = existingHost;
      glowOverlay = existingHost.shadowRoot.querySelector(`[${DEVTOOLS_ATTRIBUTE}="glow"]`) ?? null;
      return existingContainer;
    }
  }
  const host = document.createElement('div');
  host.setAttribute(DEVTOOLS_ATTRIBUTE, 'host');
  host.style.zIndex = '2147483646';
  host.style.position = 'fixed';
  host.style.inset = '0';
  host.style.pointerEvents = 'none';
  const shadow = host.attachShadow({
    mode: 'open'
  });
  const root = document.createElement('div');
  root.setAttribute(DEVTOOLS_ATTRIBUTE, 'root');
  glowOverlay = createGlowOverlay();
  root.appendChild(glowOverlay);
  shadow.appendChild(root);
  const doc = document.body ?? document.documentElement;
  doc.appendChild(host);
  shadowHost = host;
  return root;
};
const activate = () => {
  _store__WEBPACK_IMPORTED_MODULE_0__.state.phase = 'active';
  document.documentElement.style.cursor = 'crosshair';
  if (!shadowContainer) {
    shadowContainer = mountRoot();
  }
  (0,_overlay__WEBPACK_IMPORTED_MODULE_3__.showOverlay)(shadowContainer);
  showGlow();
  enablePointerEventsOverride();
  (0,_overlay__WEBPACK_IMPORTED_MODULE_3__.scheduleAnimationFrame)();
};
const deactivate = () => {
  _store__WEBPACK_IMPORTED_MODULE_0__.state.phase = 'idle';
  _store__WEBPACK_IMPORTED_MODULE_0__.state.detectedElement = null;
  _store__WEBPACK_IMPORTED_MODULE_0__.state.detectedPath = null;
  _store__WEBPACK_IMPORTED_MODULE_0__.state.detectedTargetElement = null;
  _store__WEBPACK_IMPORTED_MODULE_0__.state.detectedTagName = null;
  _store__WEBPACK_IMPORTED_MODULE_0__.state.elementBounds = null;
  _store__WEBPACK_IMPORTED_MODULE_0__.state.animatedBounds = null;
  document.documentElement.style.cursor = '';
  (0,_overlay__WEBPACK_IMPORTED_MODULE_3__.hideOverlay)();
  (0,_styles__WEBPACK_IMPORTED_MODULE_4__.hideLabel)();
  (0,_styles__WEBPACK_IMPORTED_MODULE_4__.hideElementLabel)();
  hideGlow();
  (0,_selection__WEBPACK_IMPORTED_MODULE_2__.clearSelectionCache)();
  disablePointerEventsOverride();
  inToggleFeedbackPeriod = false;
  if (toggleFeedbackTimerId !== null) {
    window.clearTimeout(toggleFeedbackTimerId);
    toggleFeedbackTimerId = null;
  }
};
const performCopy = () => {
  if (!_store__WEBPACK_IMPORTED_MODULE_0__.state.detectedPath || !_store__WEBPACK_IMPORTED_MODULE_0__.state.animatedBounds) return;
  _store__WEBPACK_IMPORTED_MODULE_0__.state.phase = 'copying';
  (0,_styles__WEBPACK_IMPORTED_MODULE_4__.hideElementLabel)();
  (0,_overlay__WEBPACK_IMPORTED_MODULE_3__.updateElementBounds)(null);
  const grabbedBox = {
    id: `grabbed-${Date.now()}`,
    bounds: {
      ..._store__WEBPACK_IMPORTED_MODULE_0__.state.animatedBounds
    },
    createdAt: Date.now()
  };
  _store__WEBPACK_IMPORTED_MODULE_0__.state.grabbedBoxes.push(grabbedBox);
  (0,_overlay__WEBPACK_IMPORTED_MODULE_3__.addGrabbedBox)(grabbedBox);
  (0,_styles__WEBPACK_IMPORTED_MODULE_4__.showCopiedFeedback)(_store__WEBPACK_IMPORTED_MODULE_0__.state.detectedPath);
  const targetElement = _store__WEBPACK_IMPORTED_MODULE_0__.state.detectedTargetElement ?? _store__WEBPACK_IMPORTED_MODULE_0__.state.detectedElement;
  const tagName = _store__WEBPACK_IMPORTED_MODULE_0__.state.detectedTagName ?? 'div';
  const content = (0,_copy__WEBPACK_IMPORTED_MODULE_5__.buildCopyContent)(targetElement, tagName, _store__WEBPACK_IMPORTED_MODULE_0__.state.detectedPath);
  (0,_copy__WEBPACK_IMPORTED_MODULE_5__.copyToClipboard)(content).then(() => {
    _store__WEBPACK_IMPORTED_MODULE_0__.state.phase = 'justCopied';
    setTimeout(() => {
      _store__WEBPACK_IMPORTED_MODULE_0__.state.grabbedBoxes = _store__WEBPACK_IMPORTED_MODULE_0__.state.grabbedBoxes.filter(box => box.id !== grabbedBox.id);
      deactivate();
    }, FEEDBACK_DURATION_MS);
  });
};
const handlePointerMove = e => {
  _store__WEBPACK_IMPORTED_MODULE_0__.state.pointer.x = e.clientX;
  _store__WEBPACK_IMPORTED_MODULE_0__.state.pointer.y = e.clientY;
  if (_store__WEBPACK_IMPORTED_MODULE_0__.state.phase !== 'active') return;
  const result = (0,_selection__WEBPACK_IMPORTED_MODULE_2__.getSelectionAtPoint)(e.clientX, e.clientY);
  if (result) {
    _store__WEBPACK_IMPORTED_MODULE_0__.state.detectedElement = result.element;
    _store__WEBPACK_IMPORTED_MODULE_0__.state.detectedPath = result.path;
    _store__WEBPACK_IMPORTED_MODULE_0__.state.detectedTargetElement = result.targetElement;
    _store__WEBPACK_IMPORTED_MODULE_0__.state.detectedTagName = result.tagName;
    const blockBounds = (0,_selection__WEBPACK_IMPORTED_MODULE_2__.createElementBounds)(result.element);
    _store__WEBPACK_IMPORTED_MODULE_0__.state.animatedBounds = blockBounds;
    (0,_overlay__WEBPACK_IMPORTED_MODULE_3__.updateSelectionBounds)(blockBounds);
    const isSameElement = result.targetElement === result.element;
    if (!isSameElement) {
      const elBounds = (0,_selection__WEBPACK_IMPORTED_MODULE_2__.createElementBounds)(result.targetElement);
      _store__WEBPACK_IMPORTED_MODULE_0__.state.elementBounds = elBounds;
      (0,_overlay__WEBPACK_IMPORTED_MODULE_3__.updateElementBounds)(elBounds);
      if (shadowContainer) {
        (0,_styles__WEBPACK_IMPORTED_MODULE_4__.showElementLabel)(result.tagName, elBounds, e.clientX, shadowContainer);
      }
    } else {
      _store__WEBPACK_IMPORTED_MODULE_0__.state.elementBounds = null;
      (0,_overlay__WEBPACK_IMPORTED_MODULE_3__.updateElementBounds)(null);
      (0,_styles__WEBPACK_IMPORTED_MODULE_4__.hideElementLabel)();
    }
    if (shadowContainer) {
      (0,_styles__WEBPACK_IMPORTED_MODULE_4__.showLabel)(result.path, blockBounds, e.clientX, shadowContainer);
    }
  } else {
    _store__WEBPACK_IMPORTED_MODULE_0__.state.detectedElement = null;
    _store__WEBPACK_IMPORTED_MODULE_0__.state.detectedPath = null;
    _store__WEBPACK_IMPORTED_MODULE_0__.state.detectedTargetElement = null;
    _store__WEBPACK_IMPORTED_MODULE_0__.state.detectedTagName = null;
    _store__WEBPACK_IMPORTED_MODULE_0__.state.elementBounds = null;
    _store__WEBPACK_IMPORTED_MODULE_0__.state.animatedBounds = null;
    (0,_overlay__WEBPACK_IMPORTED_MODULE_3__.updateSelectionBounds)(null);
    (0,_overlay__WEBPACK_IMPORTED_MODULE_3__.updateElementBounds)(null);
    (0,_styles__WEBPACK_IMPORTED_MODULE_4__.hideLabel)();
    (0,_styles__WEBPACK_IMPORTED_MODULE_4__.hideElementLabel)();
  }
  (0,_overlay__WEBPACK_IMPORTED_MODULE_3__.scheduleAnimationFrame)();
};
const handleKeyDown = e => {
  if ((0,_keyboard__WEBPACK_IMPORTED_MODULE_1__.isKeyboardEventTriggeredByInput)(e)) return;
  if (_store__WEBPACK_IMPORTED_MODULE_0__.state.phase === 'active') {
    if (e.key === 'Escape') {
      deactivate();
      return;
    }
    if ((0,_keyboard__WEBPACK_IMPORTED_MODULE_1__.isActivationKeyDown)(e) && !e.repeat) {
      e.preventDefault();
      deactivate();
      return;
    }
    return;
  }
  if (_store__WEBPACK_IMPORTED_MODULE_0__.state.phase !== 'idle') return;
  if (!(0,_keyboard__WEBPACK_IMPORTED_MODULE_1__.isActivationKeyDown)(e)) return;
  _store__WEBPACK_IMPORTED_MODULE_0__.state.phase = 'holding';
  _store__WEBPACK_IMPORTED_MODULE_0__.state.holdStartedAt = performance.now();
  holdTimer = setTimeout(() => {
    if (_store__WEBPACK_IMPORTED_MODULE_0__.state.phase === 'holding') {
      activate();
    }
  }, HOLD_THRESHOLD_MS);
};
const handleKeyUp = e => {
  if (_store__WEBPACK_IMPORTED_MODULE_0__.state.phase === 'holding') {
    if (holdTimer) {
      clearTimeout(holdTimer);
      holdTimer = null;
    }
    _store__WEBPACK_IMPORTED_MODULE_0__.state.phase = 'idle';
    return;
  }
  if (inToggleFeedbackPeriod) {
    if ((0,_keyboard__WEBPACK_IMPORTED_MODULE_1__.isActivationKeyUp)(e)) {
      inToggleFeedbackPeriod = false;
    }
    return;
  }
};
const handlePointerDown = e => {
  if (e.button !== 0) return;
  if (_store__WEBPACK_IMPORTED_MODULE_0__.state.phase !== 'active') return;
  e.preventDefault();
  e.stopPropagation();
  e.stopImmediatePropagation();
  if (_store__WEBPACK_IMPORTED_MODULE_0__.state.detectedPath && _store__WEBPACK_IMPORTED_MODULE_0__.state.animatedBounds) {
    performCopy();
  } else {
    deactivate();
  }
};
const handleClick = e => {
  if (_store__WEBPACK_IMPORTED_MODULE_0__.state.phase === 'active' || _store__WEBPACK_IMPORTED_MODULE_0__.state.phase === 'copying' || _store__WEBPACK_IMPORTED_MODULE_0__.state.phase === 'justCopied') {
    e.preventDefault();
    e.stopPropagation();
    e.stopImmediatePropagation();
  }
};
const handleWindowResize = () => {
  (0,_overlay__WEBPACK_IMPORTED_MODULE_3__.handleResize)();
};
const init = () => {
  abortController = new AbortController();
  const signal = abortController.signal;
  document.addEventListener('keydown', handleKeyDown, {
    capture: true,
    signal
  });
  document.addEventListener('keyup', handleKeyUp, {
    capture: true,
    signal
  });
  window.addEventListener('pointermove', handlePointerMove, {
    passive: true,
    signal
  });
  window.addEventListener('pointerdown', handlePointerDown, {
    capture: true,
    signal
  });
  window.addEventListener('click', handleClick, {
    capture: true,
    signal
  });
  window.addEventListener('resize', handleWindowResize, {
    passive: true,
    signal
  });
};
const cleanup = () => {
  if (abortController) {
    abortController.abort();
    abortController = null;
  }
  (0,_overlay__WEBPACK_IMPORTED_MODULE_3__.cleanupOverlay)();
  (0,_styles__WEBPACK_IMPORTED_MODULE_4__.cleanupLabel)();
  (0,_styles__WEBPACK_IMPORTED_MODULE_4__.cleanupElementLabel)();
  disablePointerEventsOverride();
  if (shadowHost) {
    shadowHost.remove();
    shadowHost = null;
    shadowContainer = null;
    glowOverlay = null;
  }
};
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', init);
} else {
  init();
}

})();

/******/ })()
;
//# sourceMappingURL=index.js.map