import { Link } from '@tiptap/extension-link';
import { TextAlign } from '@tiptap/extension-text-align';
import { Underline } from '@tiptap/extension-underline';
import { useEditor, EditorContent } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import {
  Button,
  SelectControl,
  Toolbar,
  ToolbarButton,
} from '@wordpress/components';
import { useEffect, useRef, useState } from '@wordpress/element';
import {
  formatBold,
  formatItalic,
  formatListBullets,
  formatListNumbered,
  formatStrikethrough,
  formatUnderline,
  alignLeft,
  alignCenter,
  alignRight,
  alignJustify,
  link,
  code,
} from '@wordpress/icons';
import parserHtml from 'prettier/plugins/html';
import prettier from 'prettier/standalone';
import { Base } from '@/blocks/components/Base';
import { Code } from '@/blocks/components/Fields/components/Code';
import { Alignment, BlockstudioAttribute } from '@/types/block';
import { Any } from '@/types/types';
import { __ } from '@/utils/__';
import { css } from '@/utils/css';
import { LinkModal } from './Link';

const textAlignMap = {
  left: alignLeft,
  center: alignCenter,
  right: alignRight,
  justify: alignJustify,
};

export const WYSIWYG = ({
  item,
  onChange,
  value,
}: {
  item: BlockstudioAttribute;
  onChange: (value: string) => void;
  value: string;
}) => {
  const originalVal = useRef(false);
  const [codeMode, setCodeMode] = useState(item?.mode === 'code');
  const [openLink, setOpenLink] = useState(false);
  const [opensInNewTab, setOpensInNewTab] = useState(false);
  const [selection, setSelection] = useState<number[] | null>(null);
  const [val, setVal] = useState(value);

  const editor = useEditor({
    extensions: [
      StarterKit.configure({
        heading: {
          levels: item?.toolbar?.tags?.headings?.length
            ? item?.toolbar?.tags?.headings
            : [],
        },
      }),
      Link.configure({
        autolink: false,
        openOnClick: false,
        HTMLAttributes: item?.toolbar?.formats?.link?.attributes || {},
      }),
      Underline,
      TextAlign.configure({
        types: item?.toolbar?.formats?.textAlign?.types || [
          'heading',
          'paragraph',
          'list',
        ],
        alignments: item?.toolbar?.formats?.textAlign?.alignments || [],
      }),
    ],
    content: val,
    onUpdate: ({ editor }) => setVal(editor.getHTML()),
    onSelectionUpdate: ({ editor }) =>
      setSelection([
        editor.state.selection.$anchor.pos,
        editor.state.selection.$head.pos,
      ]),
  });

  useEffect(() => {
    originalVal.current && onChange(val);
  }, [val]);

  useEffect(() => {
    if (editor && !originalVal.current) {
      editor.commands.setContent(val);
      originalVal.current = true;
    }
  }, [editor]);

  useEffect(() => {
    if (!editor) return;

    if (codeMode) {
      const formatHtml = async () => {
        const formatted = await prettier.format(editor.getHTML(), {
          parser: 'html',
          plugins: [parserHtml],
        });
        setVal(formatted);
      };
      formatHtml();
    } else {
      editor.commands.setContent(val);
    }
  }, [codeMode, editor]);

  const headings = item?.toolbar?.tags?.headings || [];
  const tags = [
    {
      value: 'p',
      label: __('p'),
    },
    headings.includes(1) && {
      value: 1,
      label: __('h1'),
    },
    headings.includes(2) && {
      value: 2,
      label: __('h2'),
    },
    headings.includes(3) && {
      value: 3,
      label: __('h3'),
    },
    headings.includes(4) && {
      value: 4,
      label: __('h4'),
    },
    headings.includes(5) && {
      value: 5,
      label: __('h5'),
    },
    headings.includes(6) && {
      value: 6,
      label: __('h6'),
    },
  ].filter((e) => e);

  return (
    <Base>
      {!codeMode && (
        <div
          css={css({
            border: '1px solid #1e1e1e',
            borderRadius: 'var(--blockstudio-border-radius)',
            overflow: 'hidden',
          })}
        >
          {(item?.toolbar?.tags?.headings?.length ||
            (item?.toolbar?.formats &&
              Object.values(item?.toolbar?.formats).filter((e) => e))) && (
            <Toolbar
              css={css({
                position: 'relative',
                display: 'flex',
                alignItems: 'center',
                top: '-1px',
                left: '-1px',
                width: 'calc(100% + 2px)',
                overflowY: 'auto',
              })}
              label={__('WYSIWYG Toolbar')}
            >
              {tags.length >= 2 && (
                <div
                  css={css({
                    padding: '0 8px',
                    minWidth: '64px',
                  })}
                >
                  <SelectControl
                    options={
                      tags as {
                        value: string;
                        label: string;
                      }[]
                    }
                    onChange={(val: string) => {
                      if (val === 'p') {
                        editor.commands.setParagraph();
                      } else {
                        editor.commands.setHeading({
                          level: Number(val) as Any,
                        });
                      }
                    }}
                    value={
                      (editor?.isActive('heading', { level: 1 })
                        ? 1
                        : editor?.isActive('heading', { level: 2 })
                          ? 2
                          : editor?.isActive('heading', { level: 3 })
                            ? 3
                            : editor?.isActive('heading', { level: 4 })
                              ? 4
                              : editor?.isActive('heading', { level: 5 })
                                ? 5
                                : editor?.isActive('heading', { level: 6 })
                                  ? 6
                                  : 'p') as string
                    }
                  />
                </div>
              )}
              {item?.toolbar?.formats?.bold && (
                <ToolbarButton
                  icon={formatBold}
                  label={__('Bold')}
                  onClick={() => editor.commands.toggleBold()}
                  isPressed={editor?.isActive('bold')}
                />
              )}
              {item?.toolbar?.formats?.italic && (
                <ToolbarButton
                  icon={formatItalic}
                  label={__('Italic')}
                  onClick={() => editor.commands.toggleItalic()}
                  isPressed={editor?.isActive('italic')}
                />
              )}
              {item?.toolbar?.formats?.underline && (
                <ToolbarButton
                  icon={formatUnderline}
                  label={__('Underline')}
                  onClick={() => editor.commands.toggleUnderline()}
                  isPressed={editor?.isActive('underline')}
                />
              )}
              {item?.toolbar?.formats?.strikethrough && (
                <ToolbarButton
                  icon={formatStrikethrough}
                  label={__('Strikethrough')}
                  onClick={() => editor.commands.toggleStrike()}
                  isPressed={editor?.isActive('strike')}
                />
              )}
              {item?.toolbar?.formats?.code && (
                <ToolbarButton
                  icon={code}
                  label={__('Code')}
                  onClick={() => editor.commands.toggleCode()}
                  isPressed={editor?.isActive('code')}
                />
              )}
              {item?.toolbar?.formats?.link && (
                <ToolbarButton
                  icon={link}
                  label={__('Link')}
                  onClick={() => {
                    setOpensInNewTab(
                      editor?.getAttributes('link')?.target === '_blank',
                    );
                    setOpenLink(true);
                  }}
                  isPressed={editor?.getAttributes('link')?.href}
                />
              )}
              {item?.toolbar?.formats?.unorderedList && (
                <ToolbarButton
                  icon={formatListBullets}
                  label={__('Unordered list')}
                  onClick={() => editor.commands.toggleBulletList()}
                  isPressed={editor?.isActive('bulletList')}
                />
              )}
              {item?.toolbar?.formats?.orderedList && (
                <ToolbarButton
                  icon={formatListNumbered}
                  label={__('Ordered list')}
                  onClick={() => editor.commands.toggleOrderedList()}
                  isPressed={editor?.isActive('orderedList')}
                />
              )}
              {['left', 'center', 'right', 'justify'].map((align) => {
                if (
                  (
                    item?.toolbar?.formats?.textAlign?.alignments || []
                  ).includes(align as Alignment)
                ) {
                  return (
                    <ToolbarButton
                      key={`align-${align}`}
                      icon={(textAlignMap as Record<string, typeof alignLeft>)[align]}
                      label={__(`Align ${align}`)}
                      onClick={() => editor.commands.setTextAlign(align)}
                      isPressed={editor?.isActive({ textAlign: align })}
                    />
                  );
                }
                return null;
              })}
            </Toolbar>
          )}
          <div
            css={css({
              padding: '16px;',
              strong: {
                fontWeight: 'bold',
              },
              'h1, h2, h3, h4, h5, h6': {
                textTransform: 'none',
                margin: '16px 0',
              },
              '.ProseMirror *:first-child': {
                marginTop: '0',
              },
              h1: {
                fontSize: '24px',
              },
              h2: {
                fontSize: '22px',
              },
              h3: {
                fontSize: '20px',
              },
              h4: {
                fontSize: '18px',
              },
              h5: {
                fontSize: '16px',
              },
              h6: {
                fontSize: '15px',
              },
              p: {
                fontSize: '13px',
                margin: '12px 0',
              },
              'ul, ol': {
                margin: '0',
                padding: '0',
                paddingLeft: '16px',
              },
              ul: {
                listStyleType: 'disc',
              },
              ol: {
                listStyleType: 'decimal',
              },
              code: {
                fontFamily: 'monospace',
                fontSize: '13px',
              },
            })}
          >
            <EditorContent editor={editor} />
          </div>
        </div>
      )}
      {codeMode && (
        <div
          css={css({
            height: '200px',
          })}
        >
          <Code
            value={val}
            item={
              {
                height: '200px',
                language: 'html',
              } as {
                height: string;
                language: string;
              } & BlockstudioAttribute
            }
            onChange={setVal}
          />
        </div>
      )}
      {item.mode === 'both' && (
        <div
          css={css({
            display: 'flex',
            marginTop: '8px',
          })}
        >
          <Button
            variant="link"
            css={css({
              marginLeft: 'auto',
            })}
            onClick={() => setCodeMode(!codeMode)}
          >
            {__(`Switch to ${codeMode ? 'Visual' : 'Code'}`)}
          </Button>
        </div>
      )}
      {openLink && (
        <LinkModal
          onChange={(e) => {
            setOpensInNewTab(
              !!(e as unknown as BlockstudioAttribute).opensInNewTab,
            );
            editor.commands.setTextSelection({
              from: selection?.[0] ?? 0,
              to: selection?.[1] ?? 0,
            });
            editor.commands.setLink({
              href: (e as unknown as BlockstudioAttribute).url,
              target: (e as unknown as BlockstudioAttribute).opensInNewTab
                ? '_blank'
                : '_self',
            });
          }}
          onRemove={() => {
            editor.commands.setTextSelection({
              from: selection?.[0] ?? 0,
              to: selection?.[1] ?? 0,
            });
            editor.commands.unsetLink();
            setOpensInNewTab(false);
          }}
          setOpen={setOpenLink}
          value={{
            url: editor.getAttributes('link').href,
            opensInNewTab,
          }}
          opensInNewTab={item?.toolbar?.formats?.link?.opensInNewTab}
        />
      )}
    </Base>
  );
};
