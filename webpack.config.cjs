const defaultConfig = require('@wordpress/scripts/config/webpack.config');
const DependencyExtractionWebpackPlugin = require('@wordpress/dependency-extraction-webpack-plugin');
const path = require('path');

module.exports = {
  ...defaultConfig,
  resolve: {
    ...defaultConfig.resolve,
    alias: {
      ...defaultConfig.resolve.alias,
      '@/blocks': path.resolve(__dirname, 'src/blocks'),
      '@/components': path.resolve(__dirname, 'src/components'),
      '@/const': path.resolve(__dirname, 'src/const'),
      '@/tailwind': path.resolve(__dirname, 'src/tailwind'),
      '@/types': path.resolve(__dirname, 'src/types'),
      '@/utils': path.resolve(__dirname, 'src/utils'),
    },
  },
  plugins: [
    ...defaultConfig.plugins.filter(
      (plugin) => plugin.constructor.name !== 'DependencyExtractionWebpackPlugin'
    ),
    new DependencyExtractionWebpackPlugin({
      injectPolyfill: true,
    }),
  ],
};
