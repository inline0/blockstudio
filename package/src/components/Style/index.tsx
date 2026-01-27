import { Global } from '@emotion/react';
import { lightenColor } from '@/editor/utils/lightenColor';
import { css } from '@/utils/css';

export const Style = () => {
  return (
    <Global
      styles={css({
        ':root': {
          '--blockstudio-editor-900': '#07090a',
          '--blockstudio-editor-800': '#121618',
          '--blockstudio-editor-700': '#1d2327',
          '--blockstudio-editor-600': '#283036',
          '--blockstudio-editor-500': '#333d44',
          '--blockstudio-editor-400': '#8698a4',
          '--blockstudio-editor-300': '#a3b1ba',
          '--blockstudio-editor-200': '#c1c9d0',
          '--blockstudio-editor-100': '#dee2e6',
          '--blockstudio-editor-50': '#fbfcfc',
          '--blockstudio-editor-highlight': lightenColor(
            '--wp-admin-theme-color',
            0.1,
          ),
          '--blockstudio-editor-changed': lightenColor(
            '--wp-admin-theme-color',
            0.5,
          ),
          '--blockstudio-editor-error': '#f87171',
          '--blockstudio-editor-success': '#4ade80',
        },
      })}
    />
  );
};
