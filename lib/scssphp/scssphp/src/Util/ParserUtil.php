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

use BlockstudioVendor\ScssPhp\ScssPhp\Parser\StringScanner;
/**
 * @internal
 */
final class ParserUtil
{
    /**
     * Consumes an escape sequence from $scanner and returns the character it
     * represents.
     */
    public static function consumeEscapedCharacter(StringScanner $scanner): string
    {
        // See https://drafts.csswg.org/css-syntax-3/#consume-escaped-code-point.
        $scanner->expectChar('\\');
        $first = $scanner->peekChar();
        if ($first === null) {
            return "�";
        }
        if (Character::isNewline($first)) {
            $scanner->error('Expected escape sequence.');
        }
        if (Character::isHex($first)) {
            $value = 0;
            for ($i = 0; $i < 6; $i++) {
                $next = $scanner->peekChar();
                if ($next === null || !Character::isHex($next)) {
                    break;
                }
                $value *= 16;
                $value += hexdec($scanner->readChar());
                assert(\is_int($value));
            }
            if (Character::isWhitespace($scanner->peekChar())) {
                $scanner->readChar();
            }
            if ($value === 0 || $value >= 0xd800 && $value <= 0xdfff || $value >= 0x10ffff) {
                return "�";
            }
            return mb_chr($value, 'UTF-8');
        }
        return $scanner->readUtf8Char();
    }
}
