const defaultConfig = require('@wordpress/scripts/config/webpack.config');
const DependencyExtractionWebpackPlugin = require('@wordpress/dependency-extraction-webpack-plugin');
const path = require('path');

module.exports = {
  ...defaultConfig,
  entry: {
    'blocks/index.tsx': path.resolve(__dirname, 'src/blocks/index.tsx'),
    'pages/index': path.resolve(__dirname, 'src/pages/index.ts'),
  },
  output: {
    ...defaultConfig.output,
    path: path.resolve(__dirname, 'includes/admin/assets'),
  },
  resolve: {
    ...defaultConfig.resolve,
    alias: {
      ...defaultConfig.resolve.alias,
      '@/blocks': path.resolve(__dirname, 'src/blocks'),
      '@/components': path.resolve(__dirname, 'src/components'),
      '@/const': path.resolve(__dirname, 'src/const'),
      '@/pages': path.resolve(__dirname, 'src/pages'),
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
