import { useEffect, useState, useRef } from '@wordpress/element';
import { useTailwind } from '@/tailwind/use-tailwind';

const TIMEOUT = 1000;

export const useTailwindCompile = ({ enabled }: { enabled?: boolean }) => {
  const [doc, setDoc] = useState<Document | undefined>(undefined);
  const [htmlInner, setHtmlInner] = useState('');
  const [started, setStarted] = useState(false);
  const tailwind = useTailwind({
    doc,
    enabled,
    html: htmlInner,
    id: 'compiled',
    started,
  });

  const stableTimeoutRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const lastCSSRef = useRef('');
  const resolveRef = useRef<((value: unknown) => void) | null>(null);

  useEffect(() => {
    if (!started) return;

    const iframe = document.createElement('iframe');
    iframe.style.display = 'none';
    iframe.id = 'blockstudio-tailwind-iframe';
    document.body.appendChild(iframe);

    setDoc(iframe.contentDocument || undefined);
  }, [started]);

  useEffect(() => {
    const checkStability = () => {
      if (tailwind === lastCSSRef.current && tailwind !== '') {
        if (resolveRef.current) {
          resolveRef.current(tailwind);
        }
      } else {
        lastCSSRef.current = tailwind;
        if (stableTimeoutRef.current) clearTimeout(stableTimeoutRef.current);
        stableTimeoutRef.current = setTimeout(checkStability, TIMEOUT);
      }
    };

    if (doc) checkStability();
  }, [doc, tailwind]);

  return async (html: string) => {
    setStarted(true);
    setHtmlInner(html);

    return new Promise((resolve) => {
      resolveRef.current = resolve;
      lastCSSRef.current = '';
      if (stableTimeoutRef.current) clearTimeout(stableTimeoutRef.current);
      stableTimeoutRef.current = setTimeout(() => {
        if (tailwind !== '') resolve(tailwind);
      }, TIMEOUT);
    });
  };
};
