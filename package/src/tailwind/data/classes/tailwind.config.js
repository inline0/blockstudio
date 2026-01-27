const defaultTheme = require('tailwindcss/defaultTheme');

module.exports = {
  theme: {
    screens: {
      ...defaultTheme.screen,
    },
  },
  safelist: [
    {
      pattern: /.+/,
    },
  ],
};
