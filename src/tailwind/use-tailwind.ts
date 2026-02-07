import { useSelect } from '@wordpress/data';
import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import { BlockstudioAdmin } from '@/types/types';

// Types for blockstudio/editor store selectors
type EditorSelectors = {
  getBlockstudio: () => BlockstudioAdmin | undefined;
  getOptions: () => BlockstudioAdmin['options'] | undefined;
};

export const useTailwind = ({
  id = 'default',
  doc = document,
  enabled = false,
  html,
  started = true,
}: {
  id?: string;
  doc?: Document;
  enabled?: boolean;
  html?: string;
  started?: boolean;
}) => {
  const templateId = `blockstudio-tailwind-template-${id}`;
  const scriptId = 'blockstudio-tailwind-script';
  const settingsId = 'blockstudio-tailwind-settings';

  const blockstudio = useSelect(
    (select) =>
      (
        select('blockstudio/editor') as EditorSelectors | undefined
      )?.getBlockstudio(),
    [],
  );
  const options = useSelect(
    (select) =>
      (
        select('blockstudio/editor') as EditorSelectors | undefined
      )?.getOptions(),
    [],
  );
  const hasStyle = useRef(false);
  const [styleElement, setStyleElement] = useState<HTMLStyleElement | null>(
    null,
  );
  const [css, setCss] = useState('');

  const getStyleElement = useCallback(() => {
    if (!doc || hasStyle.current) return;

    const styles = doc.head.querySelectorAll('style');
    styles.forEach((style) => {
      if (style.innerHTML.includes('--tw-border-spacing-x')) {
        setStyleElement(style);
        hasStyle.current = true;
      }
    });

    if (!hasStyle.current) setTimeout(getStyleElement, 100);
  }, [doc, started, styleElement]);

  useEffect(() => {
    if (!doc || !started || (!options?.tailwind?.enabled && !enabled)) {
      return;
    }

    let template = doc.getElementById(templateId);
    if (!template) {
      template = doc.createElement('div');
      template.id = templateId;
      template.style.display = 'none';
      doc.body.appendChild(template);
    } else {
      template.innerHTML = html || '';
    }

    const script = doc.getElementById(scriptId);
    if (!script) {
      const script = doc.createElement('script');
      script.id = scriptId;
      script.src =
        blockstudio?.tailwindUrl || window.blockstudioAdmin?.tailwindUrl;
      doc.head.appendChild(script);

      script.onload = () => {
        let settings = doc.getElementById(settingsId) as HTMLStyleElement;
        if (!settings) {
          settings = doc.createElement('style');
          settings.id = settingsId;
          settings.type = 'text/tailwindcss';
          doc.head.appendChild(settings);
        }
        const configCss = options?.tailwind?.config || '';
        settings.innerHTML = typeof configCss === 'string' ? configCss : '';
        template.innerHTML = html || '';
      };
    }

    getStyleElement();
  }, [doc, html, started, options]);

  useEffect(() => {
    if (!styleElement || !doc) return;

    setCss(styleElement.innerHTML);
    const observer = new MutationObserver((mutations) => {
      mutations.forEach((mutation) => {
        if (mutation.type === 'childList' && mutation.addedNodes.length) {
          const css = styleElement.innerHTML;
          setCss(css);
        }
      });
    });

    observer.observe(styleElement, { childList: true, subtree: true });

    return () => {
      observer.disconnect();
    };
  }, [doc, started, styleElement]);

  return css;
};
