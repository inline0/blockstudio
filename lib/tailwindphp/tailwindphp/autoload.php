<?php

namespace BlockstudioVendor;

/**
 * TailwindPHP standalone autoloader.
 *
 * Loads all required files in the correct order for environments
 * that don't use Composer's autoloader (e.g. WordPress plugins
 * with vendor prefixing).
 */
$dir = __DIR__ . '/src/';
$files = ['_tailwindphp/LightningCss.php', '_tailwindphp/CssMinifier.php', 'attribute-selector-parser.php', 'utils/segment.php', 'utils/escape.php', 'utils/default-map.php', 'utils/to-key-path.php', 'utils/brace-expansion.php', 'utils/compare.php', 'utils/compare-breakpoints.php', 'utils/dimensions.php', 'utils/topological-sort.php', 'utils/replace-shadow-colors.php', 'utils/is-color.php', 'utils/math-operators.php', 'utils/is-valid-arbitrary.php', 'utils/infer-data-type.php', 'ast.php', 'css-parser.php', 'walk.php', 'value-parser.php', 'selector-parser.php', 'constant-fold-declaration.php', 'property-order.php', 'expand-declaration.php', 'theme.php', 'utils/decode-arbitrary-value.php', 'candidate.php', 'compile.php', 'utilities.php', 'variants.php', 'design-system.php', 'apply.php', 'at-import.php', 'css-functions.php', 'plugin.php', 'plugin/plugins/typography-plugin.php', 'plugin/plugins/forms-plugin.php', 'index.php'];
foreach ($files as $file) {
    require_once $dir . $file;
}
unset($dir, $files, $file);
