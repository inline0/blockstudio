import { __ } from '@/utils/__';
import { css } from '@/utils/css';

export const Error = () => {
  return (
    <div
      css={css({
        display: 'flex',
        justifyContent: 'center',
        alignItems: 'center',
        textAlign: 'center',
        minHeight: '100px',
      })}
      dangerouslySetInnerHTML={{
        __html: `<div>${__(
          '⚠️ There is an error - please send a screenshot of your console errors to&nbsp<a style="display: inline-block;" href="mailto:hi@blockstudio.dev" target="_blank">hi@blockstudio.dev</a>',
        )}</div>`,
      }}
    />
  );
};
