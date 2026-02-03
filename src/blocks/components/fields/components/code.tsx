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
import { useEffect } from '@wordpress/element';
import { useGetCssVariables } from '@/blocks/hooks/use-get-css-variables';
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
  const settingsCssVariables = useGetCssVariables(
    window.blockstudioAdmin?.styles,
    window.blockstudioAdmin?.cssVariables ?? [],
  );

  const getExtensions = () => {
    const baseExtensions = [html(), javascript(), json(), php()];

    if (item.language === 'css') {
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
        ...baseExtensions,
      ];
    }

    return baseExtensions;
  };

  const replacedVal = rest.value?.replaceAll(
    '%selector%',
    `.is-root-container [data-block="${clientId}"]`,
  );

  useEffect(() => {
    if (item.language !== 'css' && item.language !== 'javascript') return;
    if (!item.asset && !extensions) return;

    const id = `blockstudio-${clientId}-${inRepeater ? repeaterId : item.id}`;
    const doc = getEditorDocument();

    let element = doc.getElementById(id);
    if (!element) {
      element = doc.createElement(item.language === 'css' ? 'style' : 'script');
      element.id = id;
      if (item.language === 'css') {
        doc.head.appendChild(element);
      } else {
        doc.body.appendChild(element);
      }
    }

    element.innerHTML = replacedVal;
  }, [replacedVal, clientId]);

  return (
    <div
      css={css({
        border: style.border,
        borderRadius: style.borderRadius,
        overflow: 'hidden',
      })}
    >
      <CodeMirror
        {...rest}
        height={item.height || '200px'}
        maxHeight={item.maxHeight}
        minHeight={item.minHeight}
        basicSetup={{
          autocompletion:
            item.autoCompletion !== undefined ? item.autoCompletion : true,
          lineNumbers: item.lineNumbers || false,
          foldGutter: item.lineNumbers || false,
        }}
        theme={item.theme === 'dark' ? githubDark : githubLight}
        extensions={getExtensions()}
      />
    </div>
  );
};
