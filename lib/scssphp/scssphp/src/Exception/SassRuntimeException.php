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
namespace BlockstudioVendor\ScssPhp\ScssPhp\Exception;

use BlockstudioVendor\SourceSpan\FileSpan;
/**
 * @internal
 */
interface SassRuntimeException extends SassException
{
    public function withAdditionalSpan(FileSpan $span, string $label, ?\Throwable $previous = null): MultiSpanSassRuntimeException;
}
