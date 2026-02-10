export const waitForIframeReady = (
  container: HTMLElement,
  signal?: AbortSignal,
): Promise<void> => {
  return new Promise<void>((resolve, reject) => {
    if (signal?.aborted) {
      reject(new DOMException('Aborted', 'AbortError'));
      return;
    }

    const cleanups: (() => void)[] = [];

    const done = (): void => {
      cleanups.forEach((fn) => fn());
      resolve();
    };

    const abort = (): void => {
      cleanups.forEach((fn) => fn());
      reject(new DOMException('Aborted', 'AbortError'));
    };

    if (signal) {
      signal.addEventListener('abort', abort, { once: true });
      cleanups.push(() => signal.removeEventListener('abort', abort));
    }

    const onContent = (doc: Document): void => {
      const mediaEls = Array.from(
        doc.querySelectorAll<HTMLElement>('img, video, iframe'),
      );

      Promise.all(
        mediaEls.map((el) => {
          if (el instanceof HTMLImageElement) {
            if (el.complete) return Promise.resolve();
            return new Promise<void>((r) => {
              el.addEventListener('load', () => r(), { once: true });
              el.addEventListener('error', () => r(), { once: true });
            });
          }
          if (el instanceof HTMLVideoElement) {
            if (el.readyState >= 1) return Promise.resolve();
            return new Promise<void>((r) => {
              el.addEventListener('loadedmetadata', () => r(), { once: true });
              el.addEventListener('error', () => r(), { once: true });
            });
          }
          if (el instanceof HTMLIFrameElement) {
            try {
              if (el.contentDocument?.readyState === 'complete') {
                return Promise.resolve();
              }
            } catch {
              return Promise.resolve();
            }
            return new Promise<void>((r) => {
              el.addEventListener('load', () => r(), { once: true });
              el.addEventListener('error', () => r(), { once: true });
            });
          }
          return Promise.resolve();
        }),
      ).then(done);
    };

    // BlockPreview renders content into the iframe via React portal after load.
    const onLoaded = (iframe: HTMLIFrameElement): void => {
      const doc = iframe.contentDocument;
      if (!doc) {
        done();
        return;
      }

      const hasContent = (): boolean => {
        const wrapper = doc.querySelector('.editor-styles-wrapper');
        return !!wrapper && wrapper.children.length > 0;
      };

      if (hasContent()) {
        onContent(doc);
        return;
      }

      const observer = new MutationObserver(() => {
        if (hasContent()) {
          observer.disconnect();
          onContent(doc);
        }
      });
      observer.observe(doc.documentElement, {
        childList: true,
        subtree: true,
      });
      cleanups.push(() => observer.disconnect());
    };

    // The iframe starts on about:blank (readyState 'complete') before the
    // blob URL loads. Always wait for the load event unless the real
    // document is already present.
    const onIframeFound = (iframe: HTMLIFrameElement): void => {
      const doc = iframe.contentDocument;
      const isRealDoc = doc && doc.URL !== 'about:blank';

      if (isRealDoc && doc.readyState === 'complete') {
        onLoaded(iframe);
      } else {
        iframe.addEventListener('load', () => onLoaded(iframe), {
          once: true,
        });
      }
    };

    const existing = container.querySelector('iframe');
    if (existing) {
      onIframeFound(existing);
      return;
    }

    const observer = new MutationObserver(() => {
      const iframe = container.querySelector('iframe');
      if (iframe) {
        observer.disconnect();
        onIframeFound(iframe);
      }
    });
    observer.observe(container, { childList: true, subtree: true });
    cleanups.push(() => observer.disconnect());
  });
};
