import { state } from './store';
import type { GrabbedBox } from './store';
import {
  isActivationKeyDown,
  isActivationKeyUp,
  isKeyboardEventTriggeredByInput,
} from './keyboard';
import {
  getSelectionAtPoint,
  clearSelectionCache,
  createElementBounds,
} from './selection';
import {
  showOverlay,
  hideOverlay,
  cleanupOverlay,
  handleResize,
  updateSelectionBounds,
  updateElementBounds,
  addGrabbedBox,
  scheduleAnimationFrame,
} from './overlay';
import {
  showLabel,
  hideLabel,
  showCopiedFeedback,
  cleanupLabel,
  showElementLabel,
  hideElementLabel,
  cleanupElementLabel,
} from './styles';
import { copyToClipboard, buildCopyContent } from './copy';

const HOLD_THRESHOLD_MS = 100;
const FEEDBACK_DURATION_MS = 1500;
const DEVTOOLS_ATTRIBUTE = 'data-blockstudio-devtools';
const FROZEN_GLOW_COLOR = 'rgba(210, 57, 192, 0.15)';
const FROZEN_GLOW_EDGE_PX = 50;
const Z_INDEX_OVERLAY_CANVAS = 2147483645;

let holdTimer: ReturnType<typeof setTimeout> | null = null;
let shadowHost: HTMLDivElement | null = null;
let shadowContainer: HTMLDivElement | null = null;
let glowOverlay: HTMLDivElement | null = null;
let pointerOverrideStyle: HTMLStyleElement | null = null;
let abortController: AbortController | null = null;
let inToggleFeedbackPeriod = false;
let toggleFeedbackTimerId: number | null = null;

const enablePointerEventsOverride = (): void => {
  if (pointerOverrideStyle) return;
  pointerOverrideStyle = document.createElement('style');
  pointerOverrideStyle.setAttribute('data-blockstudio-pointer-override', '');
  pointerOverrideStyle.textContent = '* { pointer-events: auto !important; }';
  document.head.appendChild(pointerOverrideStyle);
};

const disablePointerEventsOverride = (): void => {
  pointerOverrideStyle?.remove();
  pointerOverrideStyle = null;
};

const createGlowOverlay = (): HTMLDivElement => {
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

const showGlow = (): void => {
  if (glowOverlay) {
    glowOverlay.style.opacity = '1';
  }
};

const hideGlow = (): void => {
  if (glowOverlay) {
    glowOverlay.style.opacity = '0';
  }
};

const mountRoot = (): HTMLDivElement => {
  const existingHost = document.querySelector(`[${DEVTOOLS_ATTRIBUTE}="host"]`);
  if (existingHost?.shadowRoot) {
    const existingContainer = existingHost.shadowRoot.querySelector(
      `[${DEVTOOLS_ATTRIBUTE}="root"]`,
    );
    if (existingContainer instanceof HTMLDivElement) {
      shadowHost = existingHost as HTMLDivElement;
      glowOverlay =
        (existingHost.shadowRoot.querySelector(
          `[${DEVTOOLS_ATTRIBUTE}="glow"]`,
        ) as HTMLDivElement) ?? null;
      return existingContainer;
    }
  }

  const host = document.createElement('div');
  host.setAttribute(DEVTOOLS_ATTRIBUTE, 'host');
  host.style.zIndex = '2147483646';
  host.style.position = 'fixed';
  host.style.inset = '0';
  host.style.pointerEvents = 'none';

  const shadow = host.attachShadow({ mode: 'open' });

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

const activate = (): void => {
  state.phase = 'active';
  document.documentElement.style.cursor = 'crosshair';

  if (!shadowContainer) {
    shadowContainer = mountRoot();
  }

  showOverlay(shadowContainer);
  showGlow();
  enablePointerEventsOverride();
  scheduleAnimationFrame();
};

const deactivate = (): void => {
  state.phase = 'idle';
  state.detectedElement = null;
  state.detectedPath = null;
  state.detectedTargetElement = null;
  state.detectedTagName = null;
  state.elementBounds = null;
  state.animatedBounds = null;
  document.documentElement.style.cursor = '';
  hideOverlay();
  hideLabel();
  hideElementLabel();
  hideGlow();
  clearSelectionCache();
  disablePointerEventsOverride();
  inToggleFeedbackPeriod = false;
  if (toggleFeedbackTimerId !== null) {
    window.clearTimeout(toggleFeedbackTimerId);
    toggleFeedbackTimerId = null;
  }
};

const performCopy = (): void => {
  if (!state.detectedPath || !state.animatedBounds) return;

  state.phase = 'copying';

  hideElementLabel();
  updateElementBounds(null);

  const grabbedBox: GrabbedBox = {
    id: `grabbed-${Date.now()}`,
    bounds: { ...state.animatedBounds },
    createdAt: Date.now(),
  };
  state.grabbedBoxes.push(grabbedBox);
  addGrabbedBox(grabbedBox);

  showCopiedFeedback(state.detectedPath);

  const targetElement = state.detectedTargetElement ?? state.detectedElement;
  const tagName = state.detectedTagName ?? 'div';
  const content = buildCopyContent(targetElement!, tagName, state.detectedPath);

  copyToClipboard(content).then(() => {
    state.phase = 'justCopied';

    setTimeout(() => {
      state.grabbedBoxes = state.grabbedBoxes.filter(
        (box) => box.id !== grabbedBox.id,
      );
      deactivate();
    }, FEEDBACK_DURATION_MS);
  });
};

const handlePointerMove = (e: PointerEvent): void => {
  state.pointer.x = e.clientX;
  state.pointer.y = e.clientY;

  if (state.phase !== 'active') return;

  const result = getSelectionAtPoint(e.clientX, e.clientY);

  if (result) {
    state.detectedElement = result.element;
    state.detectedPath = result.path;
    state.detectedTargetElement = result.targetElement;
    state.detectedTagName = result.tagName;

    const blockBounds = createElementBounds(result.element);
    state.animatedBounds = blockBounds;
    updateSelectionBounds(blockBounds);

    const isSameElement = result.targetElement === result.element;
    if (!isSameElement) {
      const elBounds = createElementBounds(result.targetElement);
      state.elementBounds = elBounds;
      updateElementBounds(elBounds);
      if (shadowContainer) {
        showElementLabel(result.tagName, elBounds, e.clientX, shadowContainer);
      }
    } else {
      state.elementBounds = null;
      updateElementBounds(null);
      hideElementLabel();
    }

    if (shadowContainer) {
      showLabel(result.path, blockBounds, e.clientX, shadowContainer);
    }
  } else {
    state.detectedElement = null;
    state.detectedPath = null;
    state.detectedTargetElement = null;
    state.detectedTagName = null;
    state.elementBounds = null;
    state.animatedBounds = null;
    updateSelectionBounds(null);
    updateElementBounds(null);
    hideLabel();
    hideElementLabel();
  }

  scheduleAnimationFrame();
};

const handleKeyDown = (e: KeyboardEvent): void => {
  if (isKeyboardEventTriggeredByInput(e)) return;

  if (state.phase === 'active') {
    if (e.key === 'Escape') {
      deactivate();
      return;
    }

    if (isActivationKeyDown(e) && !e.repeat) {
      e.preventDefault();
      deactivate();
      return;
    }

    return;
  }

  if (state.phase !== 'idle') return;
  if (!isActivationKeyDown(e)) return;

  state.phase = 'holding';
  state.holdStartedAt = performance.now();

  holdTimer = setTimeout(() => {
    if (state.phase === 'holding') {
      activate();
    }
  }, HOLD_THRESHOLD_MS);
};

const handleKeyUp = (e: KeyboardEvent): void => {
  if (state.phase === 'holding') {
    if (holdTimer) {
      clearTimeout(holdTimer);
      holdTimer = null;
    }
    state.phase = 'idle';
    return;
  }

  if (inToggleFeedbackPeriod) {
    if (isActivationKeyUp(e)) {
      inToggleFeedbackPeriod = false;
    }
    return;
  }
};

const handlePointerDown = (e: PointerEvent): void => {
  if (e.button !== 0) return;
  if (state.phase !== 'active') return;

  e.preventDefault();
  e.stopPropagation();
  e.stopImmediatePropagation();

  if (state.detectedPath && state.animatedBounds) {
    performCopy();
  } else {
    deactivate();
  }
};

const handleClick = (e: MouseEvent): void => {
  if (
    state.phase === 'active' ||
    state.phase === 'copying' ||
    state.phase === 'justCopied'
  ) {
    e.preventDefault();
    e.stopPropagation();
    e.stopImmediatePropagation();
  }
};

const handleWindowResize = (): void => {
  handleResize();
};

const init = (): void => {
  abortController = new AbortController();
  const signal = abortController.signal;

  document.addEventListener('keydown', handleKeyDown, { capture: true, signal });
  document.addEventListener('keyup', handleKeyUp, { capture: true, signal });
  window.addEventListener('pointermove', handlePointerMove, {
    passive: true,
    signal,
  });
  window.addEventListener('pointerdown', handlePointerDown, {
    capture: true,
    signal,
  });
  window.addEventListener('click', handleClick, { capture: true, signal });
  window.addEventListener('resize', handleWindowResize, {
    passive: true,
    signal,
  });
};

const cleanup = (): void => {
  if (abortController) {
    abortController.abort();
    abortController = null;
  }
  cleanupOverlay();
  cleanupLabel();
  cleanupElementLabel();
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

export { cleanup };
