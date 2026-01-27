import Editor, { loader, useMonaco } from '@monaco-editor/react';
import { useSelect, useDispatch } from '@wordpress/data';
import { useEffect, useRef, useCallback } from '@wordpress/element';
import { debounce } from 'lodash-es';
import parserBabel from 'prettier/esm/parser-babel.mjs';
import parserPostCss from 'prettier/esm/parser-postcss.mjs';
import parserTypescript from 'prettier/esm/parser-typescript.mjs';
import prettier from 'prettier/esm/standalone.mjs';
import { useGetCssClasses } from '@/blocks/hooks/useGetCssClasses';
import { getCssClasses } from '@/blocks/utils/getCssClasses';
import { MONACO_THEMES } from '@/editor/const/themes';
import { useCheckForPhpErrors } from '@/editor/hooks/useCheckForPhpErrors';
import { useIsBlock } from '@/editor/hooks/useIsBlock';
import { selectors } from '@/editor/store/selectors';
import { getCurrentBlockFileValues } from '@/editor/utils/getCurrentBlockFileValues';
import { getFilename } from '@/editor/utils/getFilename';
import { isCss } from '@/editor/utils/isCss';
import { moduleMatcher } from '@/editor/utils/moduleMatcher';
import { BlockstudioBlock, BlockstudioEditorBlock } from '@/type/types';

loader.config({
  paths: {
    vs: `${window.blockstudioAdmin.loader}`,
  },
});

const LANGUAGE_MAP = {
  css: 'scss',
  js: 'javascript',
  json: 'json',
  php: 'php',
  twig: 'twig',
};

const templates = window.blockstudioAdmin.data.templates;

export const Code = () => {
  const editorRef = useRef(null);
  const isPreview = useIsBlock();
  const monaco = useMonaco();
  const { checkForPhpErrors } = useCheckForPhpErrors();
  const { setFilesChanged, setEditorMounted, setBlockResets } =
    useDispatch('blockstudio/editor');
  const block = useSelect(
    (select) => (select('blockstudio/editor') as typeof selectors).getBlock(),
    [],
  );
  const blockResets = useSelect(
    (select) =>
      (select('blockstudio/editor') as typeof selectors).getBlockResets(),
    [],
  );
  const blocksNative = useSelect(
    (select) =>
      (select('blockstudio/editor') as typeof selectors).getBlocksNative(),
    [],
  );
  const editor = useSelect(
    (select) => (select('blockstudio/editor') as typeof selectors).getEditor(),
    [],
  );
  const files = useSelect(
    (select) => (select('blockstudio/editor') as typeof selectors).getFiles(),
    [],
  );
  const filesChanged = useSelect(
    (select) =>
      (select('blockstudio/editor') as typeof selectors).getFilesChanged(),
    [],
  );
  const formatCode = useSelect(
    (select) =>
      (select('blockstudio/editor') as typeof selectors).getFormatCode(),
    [],
  );
  const isGutenberg = useSelect(
    (select) =>
      (select('blockstudio/editor') as typeof selectors).isGutenberg(),
    [],
  );
  const path = useSelect(
    (select) => (select('blockstudio/editor') as typeof selectors).getPath(),
    [],
  );
  const options = useSelect(
    (select) => (select('blockstudio/editor') as typeof selectors).getOptions(),
    [],
  );
  const settings = useSelect(
    (select) =>
      (select('blockstudio/editor') as typeof selectors).getSettings(),
    [],
  );

  const globalAssets = options?.editor?.assets || [];
  const blockAssets =
    (block as unknown as BlockstudioBlock['blockstudio'])?.editor?.assets || [];
  const cssClasses = useGetCssClasses(
    window?.blockstudioAdmin.data.styles,
    [...globalAssets, ...blockAssets],
    [block, options],
  );

  const debouncedType = useCallback(
    debounce((x: string) => handleEditorChange(x), 500),
    [path, files, filesChanged, block],
  );

  const ext = LANGUAGE_MAP[getFilename(path).split('.')[1]];

  useEffect(() => {
    if (!monaco) return;

    const disposables = [];

    monaco.languages.json.jsonDefaults.setDiagnosticsOptions({
      validate: true,
      allowComments: false,
      schemas: [],
      enableSchemaRequest: true,
    });

    monaco.editor.defineTheme('blockstudio', {
      base: 'vs-dark',
      inherit: true,
      rules: [],
      colors: {
        'editor.background': '#121618',
      },
    });

    monaco.editor.setTheme(settings?.theme || 'blockstudio');

    if (settings?.theme && settings.theme !== 'blockstudio') {
      import(`../../../themes/${MONACO_THEMES[settings.theme]}.json`).then(
        (data) => {
          monaco.editor.defineTheme(settings.theme, data);
          monaco.editor.setTheme(settings.theme);
        },
      );
    }

    const completions = [
      ...new Set(
        [
          Object.values(window.blockstudioAdmin.data.functions)[0],
          Object.values(window.blockstudioAdmin.data.functions)[1],
        ]
          .flat()
          .filter((func) => !(func as string).startsWith('_')),
      ),
    ];

    const phpCompletionProvider =
      monaco.languages.registerCompletionItemProvider('php', {
        triggerCharacters: ['<', '?'],
        // @ts-ignore
        provideCompletionItems: (model, position) => {
          let inPhpBlock = false;
          for (let i = 1; i <= position.lineNumber; i++) {
            const line = model.getLineContent(i);

            if (line.includes('<?php')) {
              inPhpBlock = true;
            }
            if (line.includes('?>')) {
              inPhpBlock = false;
            }

            if (i === position.lineNumber) {
              const lineUpToCursor = line.substring(0, position.column);
              if (lineUpToCursor.includes('<?php')) {
                inPhpBlock = true;
              }
              const closingTagIndex = line.indexOf('?>');
              if (closingTagIndex !== -1 && position.column > closingTagIndex) {
                inPhpBlock = false;
              }
            }
          }

          if (inPhpBlock) {
            return {
              suggestions: completions.map((completion) => {
                return {
                  label: completion,
                  kind: monaco.languages.CompletionItemKind.Function,
                  insertText: completion,
                  insertTextRules:
                    monaco.languages.CompletionItemInsertTextRule
                      .InsertAsSnippet,
                };
              }),
            };
          }
        },
      });
    disposables.push(phpCompletionProvider);

    const twigCompletionProvider =
      monaco.languages.registerCompletionItemProvider('twig', {
        triggerCharacters: ["'", '"'],
        provideCompletionItems: (model, position) => {
          const line = model.getLineContent(position.lineNumber);
          const textBefore = line.substring(0, position.column - 1);
          const matches = textBefore.match(/{{?\s*fn\((['"])([\w\d_]+)?$/);
          if (!matches) {
            return Promise.resolve({ suggestions: [] });
          }
          const prefix = matches[2] || '';
          const matchingCompletions = completions.filter((completion: string) =>
            completion.startsWith(prefix),
          );
          const suggestions = matchingCompletions.map((completion) => ({
            label: completion,
            kind: monaco.languages.CompletionItemKind.Function,
            insertText: completion,
          }));
          return Promise.resolve({ suggestions });
        },
      });
    disposables.push(twigCompletionProvider);

    [
      {
        language: 'json',
        parser: 'json',
        plugins: [parserBabel],
      },
      {
        language: 'javascript',
        parser: 'typescript',
        plugins: [parserTypescript],
      },
      {
        language: 'css',
        parser: 'css',
        plugins: [parserPostCss],
      },
      {
        language: 'scss',
        parser: 'css',
        plugins: [parserPostCss],
      },
    ].forEach((item) => {
      const provider = monaco.languages.registerDocumentFormattingEditProvider(
        item.language,
        {
          provideDocumentFormattingEdits(model) {
            const formatted = prettier.format(model.getValue(), {
              parser: item?.parser,
              plugins: item?.plugins,
            });
            return [
              {
                range: model.getFullModelRange(),
                text: formatted,
              },
            ];
          },
        },
      );
      disposables.push(provider);
    });

    ['twig', 'php'].forEach((item) => {
      const attributeCompletionProvider =
        monaco.languages.registerCompletionItemProvider(item, {
          triggerCharacters: [':'],
          // @ts-ignore
          provideCompletionItems: (model, position) => {
            const line = model.getLineContent(position.lineNumber);
            const match = line.match(/@attribute(\w*)/);

            if (match) {
              const deleteLength = match[0].length + 1;
              const suggestions = Object.entries(
                blocksNative[block.name].attributes,
              )
                .filter(([key, _]) => key !== 'blockstudio')
                .map(
                  ([_, attribute]: [
                    string,
                    BlockstudioBlock['blockstudio']['attributes'][0],
                  ]) => {
                    let folder = 'single';
                    let id = 'default';
                    let template = '';

                    if (attribute.field === 'attributes') {
                      folder = 'attributes';
                    }

                    if (attribute.field === 'files') {
                      folder = 'files';

                      if (['id', 'url'].includes(attribute.returnFormat)) {
                        id = attribute.returnFormat;
                      }

                      if (attribute?.multiple) {
                        id = `multiple-${id}`;
                      }
                    }

                    if (attribute.field === 'icon') {
                      folder = 'icon';

                      if (attribute.returnFormat === 'element') {
                        id = 'element';
                      }
                    }

                    if (attribute.field === 'link') {
                      folder = 'link';

                      if (attribute.opensInNewTab) {
                        id = 'default-new-tab';
                      }
                    }

                    if (
                      attribute.field === 'radio' ||
                      (attribute.field === 'select' && !attribute?.multiple)
                    ) {
                      folder = 'option';

                      if (attribute?.returnFormat === 'both') {
                        id = 'both';
                      }
                    }

                    if (
                      attribute.field === 'checkbox' ||
                      (attribute.field === 'select' && attribute?.multiple)
                    ) {
                      folder = 'option-multiple';
                      id = 'multiple-default';

                      if (attribute?.returnFormat === 'both') {
                        id = 'multiple-both';
                      }
                    }

                    if (attribute.field === 'toggle') {
                      folder = 'toggle';
                    }

                    template = templates[folder][`${id}.${item}`].replaceAll(
                      'fieldName',
                      attribute.id,
                    );

                    return {
                      label: attribute.id,
                      kind: monaco.languages.CompletionItemKind.Snippet,
                      insertText: `${template}$1`,
                      insertTextRules:
                        monaco.languages.CompletionItemInsertTextRule
                          .InsertAsSnippet,
                      additionalTextEdits: [
                        {
                          range: new monaco.Range(
                            position.lineNumber,
                            position.column - deleteLength,
                            position.lineNumber,
                            position.column,
                          ),
                          text: '',
                        },
                      ],
                    };
                  },
                );

              return { suggestions };
            }
            return { suggestions: [] };
          },
        });

      disposables.push(attributeCompletionProvider);
    });

    const jsHoverProvider = monaco.languages.registerHoverProvider(
      'javascript',
      {
        provideHover: async (model, position) => {
          const word = model.getWordAtPosition(position);

          if (word) {
            try {
              const lineContent = model.getLineContent(position.lineNumber);
              const blockstudioMatcher = moduleMatcher('', false, true);
              const modulesString =
                lineContent.match(blockstudioMatcher as string)?.[0] || '';
              const match = moduleMatcher(modulesString, true) as {
                name: string;
                version: string;
              };

              if (match) {
                const packageName = match.name;
                const packageVersion = match.version;
                if (!packageName || !packageVersion) return null;

                try {
                  const res = await fetch(
                    `https://registry.npmjs.org/${packageName}/${packageVersion}`,
                  );
                  const data = await res.json();
                  const npmLink = `https://www.npmjs.com/package/${packageName}/v/${packageVersion}`;
                  return {
                    range: new monaco.Range(
                      position.lineNumber,
                      word.startColumn,
                      position.lineNumber,
                      word.endColumn,
                    ),
                    contents: [
                      { value: `**${data.name}**` },
                      { value: data.description },
                      { value: `[View on npm](${npmLink})` },
                    ],
                  };
                } catch {
                  return null;
                }
              }
            } catch (e) {}
          }

          return null;
        },
      },
    );
    disposables.push(jsHoverProvider);

    return () => {
      disposables.forEach((disposable) => disposable.dispose());
    };
  }, [block, monaco]);

  useEffect(() => {
    if (!monaco) return;

    const disposables = [];

    ['php', 'twig'].forEach((item) => {
      const provider = monaco.languages.registerCompletionItemProvider(item, {
        // @ts-ignore
        provideCompletionItems: (model, position) => {
          const textUntilPosition = model.getValueInRange({
            startLineNumber: position.lineNumber,
            startColumn: 1,
            endLineNumber: position.lineNumber,
            endColumn: position.column,
          });

          const classAttributePattern = /class\s*=\s*"([^"]*)$/;

          if (classAttributePattern.test(textUntilPosition)) {
            const classesAll = cssClasses;
            const classesBlock = new Set() as Set<string>;

            Object.values(block.assets || {})
              .map((asset: BlockstudioEditorBlock['assets'][0]) => asset.path)
              .forEach((path: string) => {
                if (isCss(path)) {
                  const css = filesChanged?.[path] || files[path]?.value;
                  if (css) {
                    getCssClasses(classesBlock, css);
                  }
                }
              });

            const allClasses = new Set([...classesAll, ...classesBlock]);

            return {
              suggestions: [...allClasses].map((cssClass) => {
                return {
                  label: cssClass,
                  kind: monaco.languages.CompletionItemKind.Property,
                  insertText: cssClass,
                  detail: 'CSS Class',
                };
              }),
            };
          }

          return { suggestions: [] };
        },
      });

      disposables.push(provider);
    });

    return () => {
      disposables.forEach((disposable) => disposable.dispose());
    };
  }, [cssClasses, monaco, block]);

  useEffect(() => {
    if (isGutenberg) {
      window.parent.postMessage(
        {
          files: getCurrentBlockFileValues(block, files),
        },
        '*',
      );
    }
  }, []);

  useEffect(() => {
    const handle = setTimeout(handleEditorChangeUpdate, 500);
    return () => clearTimeout(handle);
  }, [filesChanged, isGutenberg, block]);

  useEffect(() => {
    const resize = new Event('resize');
    window.dispatchEvent(resize);
  }, [settings, isPreview, block]);

  useEffect(() => {
    const name = settings?.theme || 'blockstudio';
    if (monaco && name !== 'blockstudio') {
      import(`../../../themes/${MONACO_THEMES[name]}.json`).then((data) => {
        monaco.editor.defineTheme(name, data);
        monaco.editor.setTheme(name);
      });
    } else if (monaco) {
      monaco.editor.setTheme(name);
    }
  }, [settings.theme]);

  useEffect(() => {
    const modelValue = editorRef?.current?.getModel()?.getValue();
    const fileValue = files[path].value;
    const fileChangedValue = path in filesChanged;
    if (
      modelValue !== fileValue &&
      !fileChangedValue &&
      !blockResets.includes(path)
    ) {
      editorRef?.current?.getModel()?.setValue(fileValue);
      setBlockResets([...blockResets, path]);
    }

    editorRef?.current?.focus();
  }, [path]);

  useEffect(() => {
    if (editorRef.current) {
      editorRef.current.getAction('editor.action.formatDocument').run();
    }
  }, [formatCode]);

  const handleEditorDidMount = (editor) => {
    editorRef.current = editor;

    window.addEventListener('resize', () => {
      editor.layout({
        width: 'auto',
        height: 'auto',
      });

      const { width, height } = editor._domElement.getBoundingClientRect();
      editor.layout({
        width,
        height,
      });
    });

    window.requestAnimationFrame(() => {
      editor.focus();
    });
    setEditorMounted(true);
    parent.postMessage('loaded', window.blockstudioAdmin.site);
  };

  const handleEditorChange = (value: string) => {
    if (files[path].value !== value) {
      console.log(files[path].value);
      console.log(value);
      setFilesChanged({
        ...filesChanged,
        [files[path].path]: value,
      });
    } else {
      const { [files[path].path]: value, ...newObj } = filesChanged;
      setFilesChanged(newObj);
    }
  };

  const handleEditorChangeUpdate = () => {
    if (!isGutenberg) return;

    const newFiles = {};
    block.filesPaths.forEach((path: string) => {
      newFiles[path] = files[path]?.value || '';
    });

    checkForPhpErrors(filesChanged).then(() => {
      if (isGutenberg) {
        window.parent.postMessage(
          {
            filesChanged: {
              ...newFiles,
              ...filesChanged,
            },
          },
          '*',
        );
      }
    });
  };

  return files?.[path]?.path ? (
    <Editor
      options={editor}
      height="calc(100vh - var(--blockstudio-editor-admin-bar) - var(--blockstudio-editor-toolbar) - var(--blockstudio-editor-console))"
      path={files[path].path}
      defaultValue={files[path].value}
      language={ext === 'css' ? 'scss' : ext}
      theme="blockstudio"
      onChange={isPreview ? debouncedType : handleEditorChange}
      onMount={handleEditorDidMount}
    />
  ) : null;
};
