import { ReactNode, createContext, useContext } from 'react';
import {
  autocompletion,
  CompletionContext,
  CompletionResult,
} from '@codemirror/autocomplete';
import { css as cssLang, cssCompletionSource } from '@codemirror/lang-css';
import { html } from '@codemirror/lang-html';
import { javascript } from '@codemirror/lang-javascript';
import { json } from '@codemirror/lang-json';
import { php } from '@codemirror/lang-php';
import { githubDark, githubLight } from '@uiw/codemirror-theme-github';
import CodeMirror from '@uiw/react-codemirror';
import { useEffect, useRef, useState } from '@wordpress/element';
import { external } from '@wordpress/icons';
import { LabelAction } from '@/blocks/components/label';
import { useGetCssVariables } from '@/blocks/hooks/use-get-css-variables';
import { usePopout } from '@/blocks/hooks/use-popout';
import { getEditorDocument } from '@/blocks/utils/get-editor-document';
import { style } from '@/const/index';
import { BlockstudioAttribute } from '@/types/block';
import { css } from '@/utils/css';

const cssVariablesCompletion = (
  cssVariables: string[],
): ((context: CompletionContext) => CompletionResult | null) => {
  const variables = cssVariables.map((v) => `--${v}`); // Format variables

  return (context: CompletionContext) => {
    const word = context.matchBefore(/--[\w-]*$/);
    if (!word || (word.from === word.to && !context.explicit)) {
      return null;
    }
    return {
      from: word.from,
      to: word.to,
      options: variables.map((variable) => ({
        label: variable,
        type: 'variable',
        apply: variable,
      })),
      validFor: /^[\w-]*$/,
    };
  };
};

const CodePopoutContext = createContext<{
  isOpen: boolean;
  Popout: ({ children }: { children: ReactNode }) => ReactNode;
} | null>(null);

type CleanupElement = HTMLElement & {
  __blockstudioCleanupUnsubscribe?: () => void;
};

export const CodeActions = ({
  item,
  children,
}: {
  item: BlockstudioAttribute;
  children: (props: { actions: LabelAction[] }) => ReactNode;
}) => {
  const { isOpen, open, close, Popout } = usePopout({
    title: item.label || 'Code Editor',
  });
  const showPopout = item.popout === true;

  const actions: LabelAction[] = showPopout
    ? [
        {
          icon: external,
          onClick: isOpen ? close : open,
          label: 'Open in popup',
        },
      ]
    : [];

  return (
    <CodePopoutContext.Provider value={showPopout ? { isOpen, Popout } : null}>
      {children({ actions })}
    </CodePopoutContext.Provider>
  );
};

export const Code = ({
  clientId,
  extensions,
  inRepeater,
  item,
  repeaterId,
  ...rest
}: {
  clientId?: string;
  extensions?: boolean;
  inRepeater?: boolean;
  item: BlockstudioAttribute;
  onChange: (value: string) => void;
  repeaterId?: string;
  value: string;
}) => {
  const popoutState = useContext(CodePopoutContext);
  const settingsCssVariables = useGetCssVariables(
    window.blockstudioAdmin?.styles,
    window.blockstudioAdmin?.cssVariables ?? [],
  );

  const getExtensions = () => {
    switch (item.language) {
      case 'css':
      case 'scss':
        return [
          cssLang(),
          autocompletion({
            override: [
              (context: CompletionContext) => {
                const cssVarResult =
                  cssVariablesCompletion(settingsCssVariables)(context);
                if (cssVarResult) return cssVarResult;

                return cssCompletionSource(context);
              },
            ],
          }),
        ];
      case 'javascript':
        return [javascript()];
      case 'json':
        return [json()];
      case 'html':
      default:
        return [html(), javascript(), json(), php()];
    }
  };

  const replacedVal = rest.value?.replaceAll(
    '%selector%',
    `.is-root-container [data-block="${clientId}"]`,
  );

  const scssEnabled = item.language === 'scss';
  const [compiledCss, setCompiledCss] = useState<string>(replacedVal);
  const debounceRef = useRef<ReturnType<typeof setTimeout>>();
  const cleanupReadyRef = useRef(false);

  useEffect(() => {
    if (!scssEnabled) {
      setCompiledCss(replacedVal);
      return;
    }

    if (!replacedVal) {
      setCompiledCss('');
      return;
    }

    if (debounceRef.current) clearTimeout(debounceRef.current);

    debounceRef.current = setTimeout(async () => {
      try {
        const res = await window.fetch(
          `${window.blockstudioAdmin.rest}blockstudio/v1/scss/compile`,
          {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-WP-Nonce': window.blockstudioAdmin.nonceRest,
            },
            body: JSON.stringify({ scss: replacedVal }),
          },
        );
        const data = await res.json();
        if (data.css !== undefined) setCompiledCss(data.css);
      } catch {
        setCompiledCss(replacedVal);
      }
    }, 400);

    return () => {
      if (debounceRef.current) clearTimeout(debounceRef.current);
    };
  }, [replacedVal, scssEnabled]);

  const isCssLike = item.language === 'css' || item.language === 'scss';

  useEffect(() => {
    if (!isCssLike && item.language !== 'javascript') return;
    if (!item.asset && !extensions) return;

    const id = `blockstudio-${clientId}-${inRepeater ? repeaterId : item.id}`;
    const doc = getEditorDocument();

    let element = doc.getElementById(id);
    if (!element) {
      element = doc.createElement(isCssLike ? 'style' : 'script');
      element.id = id;
      if (isCssLike) {
        doc.head.appendChild(element);
      } else {
        doc.body.appendChild(element);
      }
    }

    const injectedValue = scssEnabled ? compiledCss : replacedVal;
    element.innerHTML = injectedValue;
  }, [compiledCss, replacedVal, clientId]);

  useEffect(() => {
    if (!isCssLike && item.language !== 'javascript') return;
    if (!item.asset && !extensions) return;

    const id = `blockstudio-${clientId}-${inRepeater ? repeaterId : item.id}`;
    const doc = getEditorDocument();
    const win = doc.defaultView;
    const element = doc.getElementById(id) as CleanupElement | null;

    if (element?.__blockstudioCleanupUnsubscribe) {
      element.__blockstudioCleanupUnsubscribe();
      element.__blockstudioCleanupUnsubscribe = undefined;
    }

    cleanupReadyRef.current = false;
    const readyTimer = win?.setTimeout(() => {
      cleanupReadyRef.current = true;
    }, 0);

    return () => {
      if (readyTimer) {
        win?.clearTimeout(readyTimer);
      }

      if (!cleanupReadyRef.current) {
        return;
      }

      const removeIfBlockGone = () => {
        const blockEditor = (window as any).wp?.data?.select?.(
          'core/block-editor',
        );
        const blockExists =
          clientId && typeof blockEditor?.getBlock === 'function'
            ? !!blockEditor.getBlock(clientId)
            : !!doc.querySelector(`[data-block="${clientId}"]`);

        if (!blockExists) {
          const element = doc.getElementById(id) as CleanupElement | null;
          element?.__blockstudioCleanupUnsubscribe?.();
          element?.remove();
          return true;
        }

        return false;
      };

      if (!win) {
        removeIfBlockGone();
        return;
      }

      win.setTimeout(() => {
        if (removeIfBlockGone()) {
          return;
        }

        const subscribe = (window as any).wp?.data?.subscribe;
        if (typeof subscribe === 'function') {
          const unsubscribe = subscribe(() => {
            if (removeIfBlockGone()) {
              unsubscribe();
            }
          });
          const element = doc.getElementById(id) as CleanupElement | null;
          if (element) {
            element.__blockstudioCleanupUnsubscribe = unsubscribe;
          }
          return;
        }

        const observer = new win.MutationObserver(() => {
          if (removeIfBlockGone()) {
            observer.disconnect();
          }
        });
        observer.observe(doc.body, {
          childList: true,
          subtree: true,
        });
        const element = doc.getElementById(id) as CleanupElement | null;
        if (element) {
          element.__blockstudioCleanupUnsubscribe = () => observer.disconnect();
        }
      }, 0);
    };
  }, [
    clientId,
    extensions,
    inRepeater,
    isCssLike,
    item.asset,
    item.id,
    item.language,
    repeaterId,
  ]);

  const editorProps = {
    ...rest,
    basicSetup: {
      autocompletion:
        item.autoCompletion !== undefined ? item.autoCompletion : true,
      lineNumbers: item.lineNumbers || false,
      foldGutter: item.lineNumbers || false,
    },
    theme: item.theme === 'dark' ? githubDark : githubLight,
    extensions: getExtensions(),
  };

  // Stop undo/redo from bubbling to Gutenberg so CodeMirror handles its own history
  const handleKeyDown = (e: React.KeyboardEvent) => {
    const isMac = navigator.platform.toUpperCase().indexOf('MAC') >= 0;
    const isUndo =
      (isMac ? e.metaKey : e.ctrlKey) && e.key === 'z' && !e.shiftKey;
    const isRedo =
      (isMac ? e.metaKey : e.ctrlKey) &&
      (e.key === 'y' || (e.key === 'z' && e.shiftKey));

    if (isUndo || isRedo) {
      e.stopPropagation();
    }
  };

  return (
    <>
      <div
        onKeyDown={handleKeyDown}
        css={css({
          border: style.border,
          borderRadius: style.borderRadius,
          overflow: 'hidden',
          position: 'relative',
        })}
      >
        <CodeMirror
          {...editorProps}
          height={item.height || '200px'}
          maxHeight={item.maxHeight}
          minHeight={item.minHeight}
        />
      </div>
      {popoutState && (
        <popoutState.Popout>
          <CodeMirror {...editorProps} height="100%" />
        </popoutState.Popout>
      )}
    </>
  );
};
