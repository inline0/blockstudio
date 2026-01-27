const defaultConfig = require('@wordpress/scripts/config/webpack.config');
const DependencyExtractionWebpackPlugin = require('@wordpress/dependency-extraction-webpack-plugin');
const BrowserSyncPlugin = require('browser-sync-webpack-plugin');
const path = require('path');

module.exports = {
  ...defaultConfig,
  resolve: {
    ...defaultConfig.resolve,
    alias: {
      ...defaultConfig.resolve.alias,
      '@/admin': path.resolve(__dirname, 'src/admin'),
      '@/blocks': path.resolve(__dirname, 'src/blocks'),
      '@/builder': path.resolve(__dirname, 'src/builder'),
      '@/components': path.resolve(__dirname, 'src/components'),
      '@/configurator': path.resolve(__dirname, 'src/configurator'),
      '@/const': path.resolve(__dirname, 'src/const'),
      '@/editor': path.resolve(__dirname, 'src/editor'),
      '@/tailwind': path.resolve(__dirname, 'src/tailwind'),
      '@/type': path.resolve(__dirname, 'src/type'),
      '@/utils': path.resolve(__dirname, 'src/utils'),
    },
  },
  plugins: [
    ...defaultConfig.plugins.filter(
      (plugin) =>
        plugin.constructor.name !== 'DependencyExtractionWebpackPlugin'
    ),
    new DependencyExtractionWebpackPlugin({
      injectPolyfill: true,
    }),
    new BrowserSyncPlugin({
      notify: false,
      proxy:
        'https://fabrikat.local/blockstudio/wp-admin/admin.php?page=blockstudio',
    }),
  ],
};
