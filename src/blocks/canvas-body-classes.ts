import { select, subscribe } from '@wordpress/data';
import domReady from '@wordpress/dom-ready';

type EditorSettingsStore = {
  getEditorSettings: () => { blockstudioCanvasBodyClasses?: string[] };
};

const canvasBodyTarget = (): HTMLElement | null => {
  const frame = document.querySelector(
    'iframe[name="editor-canvas"]',
  ) as HTMLIFrameElement | null;

  if (frame) {
    return frame.contentDocument?.body ?? null;
  }

  return document.querySelector('.editor-styles-wrapper');
};

export const initializeCanvasBodyClasses = () => {
  domReady(() => {
    subscribe(() => {
      const editor = select('core/editor') as EditorSettingsStore | undefined;

      if (!editor) {
        return;
      }

      const classes = editor.getEditorSettings().blockstudioCanvasBodyClasses;

      if (!classes || classes.length === 0) {
        return;
      }

      const target = canvasBodyTarget();

      if (!target) {
        return;
      }

      classes.forEach((value) => {
        if (value && !target.classList.contains(value)) {
          target.classList.add(value);
        }
      });
    });
  });
};
