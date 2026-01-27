import { __experimentalScrollable as Scrollable } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { useEffect, useRef } from '@wordpress/element';
import { colors } from '@/admin/const/colors';
import { selectors } from '@/editor/store/selectors';
import { BlockstudioEditorStore } from '@/type/types';
import { __ } from '@/utils/__';
import { css } from '@/utils/css';

export const Console = () => {
  const ref = useRef(null);
  const con = useSelect(
    (select) => (select('blockstudio/editor') as typeof selectors).getConsole(),
    [],
  );
  const settings = useSelect(
    (select) =>
      (select('blockstudio/editor') as typeof selectors).getSettings(),
    [],
  );

  useEffect(() => {
    if (ref.current) {
      ref.current.scrollTo({ top: 999999 });
    }
  }, [con, settings.console]);

  return settings?.console ? (
    <Scrollable
      ref={ref}
      css={css({
        borderTop: `1px solid ${colors.gray[600]}`,
        height: 'var(--blockstudio-editor-console)',
        width: '100%',
      })}
      scrollDirection={'auto'}
    >
      <div
        id="blockstudio-editor-console"
        css={css({
          background: colors.gray[800],
          height: '100%',
          width: '100%',
          fontSize: '13px',
          fontFamily: 'consolas, monospace',
          padding: '16px',
          boxSizing: 'border-box',
          '& > *': {
            '&:last-of-type': {
              paddingBottom: '16px',
            },
          },
        })}
      >
        {Object.values(con).map(
          (item: BlockstudioEditorStore['errors'], index: number) => {
            const def = !item?.type && !item?.emoji;
            const time = item?.time ? `[${item?.time}] ` : '';
            const text = `${
              item?.type === 'error' && !item?.text
                ? __('There is an error in your block template file')
                : item?.text
            }`;

            return (
              <div
                key={`console-${index}`}
                css={css({
                  display: 'grid',
                  gridTemplateColumns: 'auto minmax(0, 1fr)',
                })}
              >
                <span
                  css={css({
                    position: 'relative',
                    top: '3px',
                    display: 'flex',
                    width: '24px',
                  })}
                >
                  {item?.type === 'success' || def
                    ? '✅'
                    : item?.type === 'error'
                      ? '🚨'
                      : item?.emoji}
                </span>
                <span
                  title={text}
                  css={css({
                    cursor: 'default',
                    display: 'grid',
                    gridTemplateColumns: 'auto minmax(0, 1fr)',
                    gridGap: '8px',
                  })}
                >
                  <span
                    css={css({
                      color:
                        item?.type === 'success' || def
                          ? colors.success
                          : item?.type === 'error'
                            ? colors.error
                            : colors.white,
                    })}
                  >
                    {time}
                  </span>
                  <span
                    css={css({
                      color: colors.gray[300],
                      wordBreak: 'break-all',
                    })}
                  >
                    {text}
                  </span>
                </span>
              </div>
            );
          },
        )}
      </div>
    </Scrollable>
  ) : null;
};
