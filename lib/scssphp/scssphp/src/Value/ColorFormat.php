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
namespace BlockstudioVendor\ScssPhp\ScssPhp\Value;

use BlockstudioVendor\JiriPudil\SealedClasses\Sealed;
/**
 * @internal
 */
#[Sealed(permits: [ColorFormatEnum::class, SpanColorFormat::class])]
interface ColorFormat
{
}
