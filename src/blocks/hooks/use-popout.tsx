import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import { createPortal } from '@wordpress/element';

type PopoutOptions = {
  title?: string;
  width?: number;
  height?: number;
};

export const usePopout = (options: PopoutOptions = {}) => {
  const { title = 'Blockstudio', width = 800, height = 600 } = options;
  const [isOpen, setIsOpen] = useState(false);
  const windowRef = useRef<Window | null>(null);
  const containerRef = useRef<HTMLDivElement | null>(null);

  const open = useCallback(() => {
    const left = window.screenX + (window.outerWidth - width) / 2;
    const top = window.screenY + (window.outerHeight - height) / 2;

    const win = window.open(
      '',
      '',
      `width=${width},height=${height},left=${left},top=${top},resizable=yes`,
    );

    if (win) {
      windowRef.current = win;
      win.document.title = title;

      const container = win.document.createElement('div');
      container.id = 'blockstudio-popout-root';
      win.document.body.appendChild(container);
      containerRef.current = container;

      win.document.body.style.margin = '0';
      win.document.body.style.padding = '0';
      win.document.body.style.boxSizing = 'border-box';
      win.document.body.style.height = '100vh';
      win.document.body.style.overflow = 'hidden';

      const style = win.document.createElement('style');
      style.textContent = `
        * { box-sizing: border-box; }
        #blockstudio-popout-root { height: 100%; }
        .cm-editor { height: 100% !important; }
        .cm-scroller { overflow: auto !important; }
      `;
      win.document.head.appendChild(style);

      win.addEventListener('beforeunload', () => {
        setIsOpen(false);
        windowRef.current = null;
        containerRef.current = null;
      });

      setIsOpen(true);
      win.focus();
    }
  }, [title, width, height]);

  const close = useCallback(() => {
    if (windowRef.current && !windowRef.current.closed) {
      windowRef.current.close();
    }
    setIsOpen(false);
    windowRef.current = null;
    containerRef.current = null;
  }, []);

  useEffect(() => {
    return () => {
      if (windowRef.current && !windowRef.current.closed) {
        windowRef.current.close();
      }
    };
  }, []);

  const Popout = useCallback(
    ({ children }: { children: React.ReactNode }) => {
      if (!isOpen || !containerRef.current) return null;
      return createPortal(children, containerRef.current);
    },
    [isOpen],
  );

  return { isOpen, open, close, Popout };
};
