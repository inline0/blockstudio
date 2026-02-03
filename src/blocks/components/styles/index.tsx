import { Global } from '@emotion/react';
import { style } from '@/const/index';

export const Styles = () => {
  return (
    <Global
      styles={{
        ':root': {
          '--blockstudio-border': style.border,
          '--blockstudio-border-radius': style.borderRadius,
          '--blockstudio-fields-space': '16px !important',
        },
        '.blockstudio-configurator': {
          '.interface-interface-skeleton__sidebar': {
            bottom: 'unset !important',
          },
          '.blockstudio-fields__field--link .components-button': {
            pointerEvents: 'none',
          },
        },
        '.blockstudio-space': {
          display: 'grid',
          gridTemplateColumns: 'minmax(0, 1fr)',
          gridGap: 'var(--blockstudio-fields-space)',

          '&--half': {
            gridGap: 'calc(var(--blockstudio-fields-space) / 3)',
          },
        },
        '.blockstudio-fields': {
          borderBottom: style.border,

          '&:first-child': {
            marginTop: '1px !important',
          },

          '.components-base-control, .components-base-control__field': {
            marginBottom: '0 !important',
          },
          '& > .blockstudio-fields__field--tabs > .components-panel__body': {
            padding: '0 !important',
          },
        },
        '.blockstudio-fields__link-modal': {
          width: '100% !important',
          maxWidth: '500px !important',
        },
        '.blockstudio-fields__link-modal .components-modal__content': {
          padding: '0 !important',
        },
        '.blockstudio-fields__link-modal .block-editor-link-control__field': {
          marginLeft: '32px !important',
          marginRight: '32px !important',
        },
        '.blockstudio-fields__link-modal .block-editor-link-control__search-actions':
          {
            right: '35px !important',
          },
        '.blockstudio-fields__link-modal .block-editor-link-control__tools, .blockstudio-fields__link-modal .block-editor-link-control__search-item':
          {
            paddingLeft: '32px !important',
            paddingRight: '32px !important',
          },
        '.blockstudio-fields__link-modal .components-modal__content:before': {
          display: 'none !important',
        },
        '.blockstudio-fields__link-modal .block-editor-link-control__search-results':
          {
            padding: '8px 0 !important',
          },
        '.blockstudio-fields__field--tabs .blockstudio-fields__field--tabs > div > div':
          {
            border: 'var(--blockstudio-border) !important',
            borderRadius: 'var(--blockstudio-border-radius) !important',
          },
        '.components-panel__body + .blockstudio-fields__field--tabs': {
          borderTop: 'var(--blockstudio-border) !important',
        },

        '.wp-block.blockstudio-block': {
          '.block-editor-default-block-appender': {
            height: 'unset',
            minHeight: '24px',
          },
        },
      }}
    />
  );
};
