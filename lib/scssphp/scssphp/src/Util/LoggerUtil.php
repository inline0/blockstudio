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
namespace BlockstudioVendor\ScssPhp\ScssPhp\Util;

use BlockstudioVendor\ScssPhp\ScssPhp\Deprecation;
use BlockstudioVendor\ScssPhp\ScssPhp\Logger\DeprecationProcessingLogger;
use BlockstudioVendor\ScssPhp\ScssPhp\Logger\LoggerInterface;
use BlockstudioVendor\ScssPhp\ScssPhp\StackTrace\Trace;
use BlockstudioVendor\SourceSpan\FileSpan;
/**
 * @internal
 */
final class LoggerUtil
{
    public static function warnForDeprecation(LoggerInterface $logger, Deprecation $deprecation, string $message, ?FileSpan $span = null, ?Trace $trace = null): void
    {
        if ($deprecation->isFuture() && !$logger instanceof DeprecationProcessingLogger) {
            return;
        }
        $logger->warn($message, $deprecation, $span, $trace);
    }
}
