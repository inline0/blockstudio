<?php

/**
 * SCSSPHP
 *
 * @copyright 2012-2020 Leaf Corcoran
 *
 * @license http://opensource.org/licenses/MIT MIT
 *
 * @link http://scssphp.github.io/scssphp
 */
namespace BlockstudioVendor\ScssPhp\ScssPhp\Ast\Css;

/**
 * @internal
 */
enum MediaQuerySingletonMergeResult implements \BlockstudioVendor\ScssPhp\ScssPhp\Ast\Css\MediaQueryMergeResult
{
    case empty;
    case unrepresentable;
}
