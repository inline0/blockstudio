import { FILE_TYPES } from '@/editor/const/fileTypes';
import { css } from '@/utils/css';

export const Type = ({ lang }: { lang: string }) => {
  const getLang = (item) => {
    return item
      ?.split(/(\\|\/)/g)
      .pop()
      .split('.')
      .pop();
  };

  return (
    <div
      css={css({
        height: '16px',
        width: '16px',
        background: FILE_TYPES?.[getLang(lang)] || FILE_TYPES?.base,
        fontSize: '9px',
        fontWeight: 600,
        textTransform: 'uppercase',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        color:
          getLang(lang) === 'php' ||
          getLang(lang) === 'json' ||
          getLang(lang) === 'css' ||
          getLang(lang) === 'scss' ||
          !FILE_TYPES?.[getLang(lang)]
            ? '#fff'
            : '#000',
        borderRadius: '1px',
        marginRight: '6px',
        lineHeight: '0',
      })}
    >
      {getLang(lang)?.substring(0, 1)}
    </div>
  );
};
