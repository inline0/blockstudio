import type { Bounds } from './store';
import { shortenPath } from './copy';

const VIEWPORT_MARGIN_PX = 8;
const ARROW_HEIGHT_PX = 8;
const ARROW_MIN_SIZE_PX = 4;
const ARROW_MAX_LABEL_WIDTH_RATIO = 0.2;
const ARROW_LABEL_MARGIN_PX = 16;
const LABEL_GAP_PX = 4;
const Z_INDEX_LABEL = 2147483647;

type ArrowPosition = 'top' | 'bottom';

interface LabelPosition {
  left: number;
  top: number;
  arrowLeftOffset: number;
  edgeOffsetX: number;
  arrowPosition: ArrowPosition;
}

let container: HTMLDivElement | null = null;
let panel: HTMLDivElement | null = null;
let arrow: HTMLDivElement | null = null;

let elementContainer: HTMLDivElement | null = null;
let elementPanel: HTMLDivElement | null = null;
let elementArrow: HTMLDivElement | null = null;

const getArrowSize = (labelWidth: number): number => {
  if (labelWidth <= 0) return ARROW_HEIGHT_PX;
  const scaledSize = labelWidth * ARROW_MAX_LABEL_WIDTH_RATIO;
  return Math.max(ARROW_MIN_SIZE_PX, Math.min(ARROW_HEIGHT_PX, scaledSize));
};

const createArrow = (): HTMLDivElement => {
  const el = document.createElement('div');
  el.setAttribute('data-blockstudio-devtools', 'arrow');
  el.style.position = 'absolute';
  el.style.width = '0';
  el.style.height = '0';
  el.style.zIndex = '10';
  return el;
};

const updateArrow = (
  arrowEl: HTMLDivElement,
  position: ArrowPosition,
  leftOffset: number,
  labelWidth: number,
): void => {
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
    arrowEl.style.filter =
      'drop-shadow(-1px -1px 0 rgba(0,0,0,0.06)) drop-shadow(1px -1px 0 rgba(0,0,0,0.06))';
  } else {
    arrowEl.style.top = '';
    arrowEl.style.bottom = '0';
    arrowEl.style.transform = 'translateX(-50%) translateY(100%)';
    arrowEl.style.borderTop = `${size}px solid white`;
    arrowEl.style.borderBottom = 'none';
    arrowEl.style.filter =
      'drop-shadow(-1px 1px 0 rgba(0,0,0,0.06)) drop-shadow(1px 1px 0 rgba(0,0,0,0.06))';
  }
};

const createPanel = (): HTMLDivElement => {
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
  el.style.fontFamily =
    '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
  el.style.fontSize = '13px';
  el.style.lineHeight = '16px';
  el.style.fontWeight = '500';
  el.style.color = '#000';
  el.style.whiteSpace = 'nowrap';
  (el.style as any).webkitFontSmoothing = 'antialiased';
  return el;
};

const createContainer = (): HTMLDivElement => {
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

const computePosition = (
  selectionBounds: Bounds,
  cursorX: number,
  labelWidth: number,
  labelHeight: number,
  preferTop: boolean = false,
): LabelPosition => {
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

  let positionTop: number;
  let arrowPosition: ArrowPosition;

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
    const fitsBelow =
      positionTop + labelHeight <= viewportHeight - VIEWPORT_MARGIN_PX;
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
  const arrowMaxPx = Math.max(
    labelWidth - ARROW_LABEL_MARGIN_PX,
    labelHalfWidth,
  );
  const clampedArrowCenterPx = Math.max(
    arrowMinPx,
    Math.min(arrowMaxPx, arrowCenterPx),
  );
  const arrowLeftOffset = clampedArrowCenterPx - labelHalfWidth;

  return {
    left: anchorX,
    top: positionTop,
    arrowLeftOffset,
    edgeOffsetX,
    arrowPosition,
  };
};

const showLabel = (
  path: string,
  selectionBounds: Bounds,
  cursorX: number,
  parentContainer: Element,
): void => {
  if (!container) {
    container = createContainer();
    parentContainer.appendChild(container);
  }

  if (panel) {
    panel.textContent = shortenPath(path);
  }

  container.style.display = 'block';
  container.style.opacity = '1';

  const labelRect = container.getBoundingClientRect();
  const panelRect = panel?.getBoundingClientRect();
  const panelWidth = panelRect?.width ?? 0;
  const position = computePosition(
    selectionBounds,
    cursorX,
    labelRect.width,
    labelRect.height,
  );

  container.style.left = `${position.left}px`;
  container.style.top = `${position.top}px`;
  container.style.transform = `translateX(calc(-50% + ${position.edgeOffsetX}px))`;

  if (arrow) {
    updateArrow(arrow, position.arrowPosition, position.arrowLeftOffset, panelWidth);
  }
};

const hideLabel = (): void => {
  if (container) {
    container.style.display = 'none';
  }
};

const showCopiedFeedback = (path: string): void => {
  if (panel) {
    panel.textContent = `Copied: ${shortenPath(path)}`;
  }
};

const cleanupLabel = (): void => {
  if (container) {
    container.remove();
    container = null;
    panel = null;
    arrow = null;
  }
};

const createElementPanel = (): HTMLDivElement => {
  const el = document.createElement('div');
  el.setAttribute('data-blockstudio-devtools', 'element-panel');
  el.style.display = 'flex';
  el.style.alignItems = 'center';
  el.style.borderRadius = '8px';
  el.style.backgroundColor = 'white';
  el.style.width = 'fit-content';
  el.style.height = 'fit-content';
  el.style.padding = '4px 7px';
  el.style.fontFamily =
    '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
  el.style.fontSize = '12px';
  el.style.lineHeight = '14px';
  el.style.fontWeight = '500';
  el.style.color = 'rgba(0, 0, 0, 0.5)';
  el.style.whiteSpace = 'nowrap';
  (el.style as any).webkitFontSmoothing = 'antialiased';
  return el;
};

const createElementContainer = (): HTMLDivElement => {
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

const showElementLabel = (
  tagName: string,
  elementBounds: Bounds,
  cursorX: number,
  parentContainer: Element,
): void => {
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
  const position = computePosition(
    elementBounds,
    cursorX,
    labelRect.width,
    labelRect.height,
    true,
  );

  elementContainer.style.left = `${position.left}px`;
  elementContainer.style.top = `${position.top}px`;
  elementContainer.style.transform = `translateX(calc(-50% + ${position.edgeOffsetX}px))`;

  if (elementArrow) {
    updateArrow(elementArrow, position.arrowPosition, position.arrowLeftOffset, panelWidth);
  }
};

const hideElementLabel = (): void => {
  if (elementContainer) {
    elementContainer.style.display = 'none';
  }
};

const cleanupElementLabel = (): void => {
  if (elementContainer) {
    elementContainer.remove();
    elementContainer = null;
    elementPanel = null;
    elementArrow = null;
  }
};

export {
  showLabel,
  hideLabel,
  showCopiedFeedback,
  cleanupLabel,
  showElementLabel,
  hideElementLabel,
  cleanupElementLabel,
};
