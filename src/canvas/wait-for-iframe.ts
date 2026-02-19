const STABLE_MS = 500;
const STABLE_MAX_MS = 2500;
const MEDIA_MAX_MS = 2000;
const BLOCKSTUDIO_SETTLE_TIMEOUT_MS = 8000;

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

    const waitForMedia = (doc: Document): Promise<void> => {
      const mediaEls = Array.from(doc.querySelectorAll<HTMLElement>('img, video'));

      return new Promise<void>((resolve) => {
        let settled = false;
        const finish = (): void => {
          if (settled) return;
          settled = true;
          clearTimeout(timeout);
          resolve();
        };

        const timeout = window.setTimeout(finish, MEDIA_MAX_MS);
        cleanups.push(() => clearTimeout(timeout));

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
            return Promise.resolve();
          }),
        )
          .then(finish)
          .catch(finish);
      });
    };

    const waitForBlockstudioRender = (doc: Document): Promise<void> => {
      const hasPendingBlockstudioSpinner = (): boolean =>
        !!doc.querySelector('.blockstudio-block__inner-spinner');

      if (!hasPendingBlockstudioSpinner()) {
        return Promise.resolve();
      }

      return new Promise<void>((resolve) => {
        let settleTimer: number | null = null;

        const finish = (): void => {
          observer.disconnect();
          if (settleTimer !== null) {
            clearTimeout(settleTimer);
          }
          clearTimeout(maxTimer);
          resolve();
        };

        const scheduleSettle = (): void => {
          if (hasPendingBlockstudioSpinner()) {
            if (settleTimer !== null) {
              clearTimeout(settleTimer);
              settleTimer = null;
            }
            return;
          }

          if (settleTimer !== null) {
            clearTimeout(settleTimer);
          }
          settleTimer = window.setTimeout(finish, STABLE_MS);
        };

        const observer = new MutationObserver(scheduleSettle);
        observer.observe(doc.documentElement, {
          childList: true,
          subtree: true,
          attributes: true,
        });

        const maxTimer = window.setTimeout(
          finish,
          BLOCKSTUDIO_SETTLE_TIMEOUT_MS,
        );

        cleanups.push(() => {
          observer.disconnect();
          if (settleTimer !== null) {
            clearTimeout(settleTimer);
          }
          clearTimeout(maxTimer);
        });

        scheduleSettle();
      });
    };

    const waitForStable = (doc: Document): void => {
      let timer: number;
      let settled = false;

      const settle = (): void => {
        if (settled) return;
        settled = true;
        observer.disconnect();
        clearTimeout(timer);
        clearTimeout(maxTimer);
        waitForMedia(doc).then(() => {
          waitForBlockstudioRender(doc).then(done);
        });
      };

      const restart = (): void => {
        clearTimeout(timer);
        timer = window.setTimeout(settle, STABLE_MS);
      };

      const observer = new MutationObserver(restart);
      observer.observe(doc.documentElement, {
        childList: true,
        subtree: true,
        attributes: true,
      });
      const maxTimer = window.setTimeout(settle, STABLE_MAX_MS);
      cleanups.push(() => {
        observer.disconnect();
        clearTimeout(timer);
        clearTimeout(maxTimer);
      });

      restart();
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
        waitForStable(doc);
        return;
      }

      const observer = new MutationObserver(() => {
        if (hasContent()) {
          observer.disconnect();
          waitForStable(doc);
        }
      });
      observer.observe(doc.documentElement, {
        childList: true,
        subtree: true,
      });
      cleanups.push(() => observer.disconnect());
    };

    const onIframeFound = (iframe: HTMLIFrameElement): void => {
      const doc = iframe.contentDocument;
      if (doc && doc.readyState === 'complete') {
        onLoaded(iframe);

        // Some previews remain on about:blank and portal content in.
        // Others navigate after about:blank. Handle both paths.
        if (doc.URL === 'about:blank') {
          iframe.addEventListener('load', () => onLoaded(iframe), {
            once: true,
          });
        }
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
