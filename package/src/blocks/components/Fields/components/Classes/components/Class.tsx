import { css as cssLang } from '@codemirror/lang-css';
import CodeMirror from '@uiw/react-codemirror';
import { Button } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { useEffect, useState } from '@wordpress/element';
import { pencil } from '@wordpress/icons';
import { cloneDeep, set } from 'lodash-es';
import parserCss from 'prettier/esm/parser-postcss.mjs';
import prettier from 'prettier/esm/standalone.mjs';
import { style } from '@/const/css';
import { CustomClasses } from '@/tailwind/components/CustomClasses';
import { selectors } from '@/tailwind/store/selectors';
import { findCssRules } from '@/tailwind/utils/findCssRules';
import { BlockstudioBlockAttributes } from '@/type/types';
import { css } from '@/utils/css';

export const Class = ({
  attributes,
  keyName,
  setAttributes,
  setValue,
  text,
  value,
}: {
  attributes?: BlockstudioBlockAttributes;
  keyName: string;
  setAttributes?: (props: BlockstudioBlockAttributes) => void;
  setValue?: (value: string, temporaryValue?: string) => void;
  text: string;
  value: string;
}) => {
  const customClasses = useSelect(
    (select) =>
      (select('blockstudio/tailwind') as typeof selectors).getCustomClasses(),
    [],
  );
  const [cssRule, setCssRule] = useState('');
  const isCustom = customClasses.map((item) => item.className).includes(text);
  const index = customClasses.map((item) => item.className).indexOf(text);

  useEffect(() => {
    setCssRule(
      prettier
        .format(findCssRules(`.${text}`)?.[0] || '', {
          parser: 'css',
          plugins: [parserCss],
        })
        .trim()
        .replaceAll('\\:', ':'),
    );
  }, [text]);

  return (
    <div
      css={css({
        display: 'flex',
        position: 'relative',
        border: '1px solid #949494',
        borderStyle: isCustom && 'dashed',
        borderRadius: '9999px',
      })}
    >
      <Button
        size="small"
        onClick={() => {
          if (setValue) {
            setValue(
              value
                .split(' ')
                .filter((c) => c !== text)
                .join(' '),
            );
          }

          const clone = cloneDeep(attributes);
          set(
            clone,
            keyName,
            value
              .split(' ')
              .filter((c) => c !== text)
              .join(' '),
          );
          set(clone, `${keyName}__temporary`, '');
          setAttributes({
            ...attributes,
            blockstudio: clone.blockstudio,
          });
        }}
        css={css({
          width: 'max-content',
          paddingLeft: '8px',
          paddingRight: isCustom ? '0' : '8px',
          '&:hover': {
            '& + div': {
              opacity: '1',
              pointerEvents: 'all',
            },
          },
        })}
        title={`Remove ${text}`}
      >
        {text}
      </Button>
      {isCustom && (
        <CustomClasses
          {...{ index }}
          buttonProps={{
            css: css({
              svg: {
                width: '16px',
              },
            }),
            icon: pencil,
            size: 'small',
          }}
        />
      )}
      {cssRule && (
        <div
          tabIndex={-1}
          css={css({
            position: 'fixed',
            right: '32px',
            borderRadius: style.borderRadius,
            width: 'calc(280px - 32px)',
            marginTop: '32px',
            backgroundColor: '#fff',
            border: style.borderInput,
            boxShadow:
              '0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1)',
            padding: '4px',
            transition: 'opacity 0.2s',
            zIndex: 999,
            pointerEvents: 'none',
            opacity: '0',
            overflow: 'hidden',
          })}
        >
          <CodeMirror
            css={css({
              '*': {
                fontSize: '10px',
                overflow: 'hidden',
              },
            })}
            basicSetup={{
              highlightActiveLine: false,
              autocompletion: true,
              lineNumbers: false,
              foldGutter: false,
            }}
            extensions={[cssLang()]}
            value={cssRule}
          />
        </div>
      )}
    </div>
  );
};
