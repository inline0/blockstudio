<?php

declare (strict_types=1);
namespace BlockstudioVendor\TailwindPHP\CssParser;

use function BlockstudioVendor\TailwindPHP\Ast\comment;
use function BlockstudioVendor\TailwindPHP\Ast\decl;
use function BlockstudioVendor\TailwindPHP\Ast\parseAtRule;
use function BlockstudioVendor\TailwindPHP\Ast\rule;
/**
 * CSS Parser - Character-by-character CSS tokenizer.
 *
 * Port of: packages/tailwindcss/src/css-parser.ts
 *
 * @port-deviation:sourcemaps TypeScript tracks source locations (src/dst) on every node
 * for source map generation. PHP omits source tracking since source maps aren't implemented.
 *
 * @port-deviation:stack TypeScript uses simple array push/pop for stack.
 * PHP uses associative array with 'parent' and 'index' to track position because
 * PHP arrays are value types, requiring explicit index tracking for modifications.
 *
 * @port-deviation:bom TypeScript checks for UTF-16 BOM (\uFEFF).
 * PHP checks for UTF-8 BOM (EF BB BF) which is more common in PHP environments.
 *
 * @port-deviation:performance PHP version uses direct character comparison ($c === '/')
 * instead of ord() calls, and tracks buffer/stack lengths separately to avoid repeated
 * strlen() calls. These optimizations provide ~20-30% speedup while maintaining identical output.
 */
// Character constants as strings for direct comparison (faster than ord())
const C_BACKSLASH = '\\';
const C_SLASH = '/';
const C_ASTERISK = '*';
const C_DOUBLE_QUOTE = '"';
const C_SINGLE_QUOTE = "'";
const C_COLON = ':';
const C_SEMICOLON = ';';
const C_LINE_BREAK = "\n";
const C_CARRIAGE_RETURN = "\r";
const C_SPACE = ' ';
const C_TAB = "\t";
const C_OPEN_CURLY = '{';
const C_CLOSE_CURLY = '}';
const C_OPEN_PAREN = '(';
const C_CLOSE_PAREN = ')';
const C_OPEN_BRACKET = '[';
const C_CLOSE_BRACKET = ']';
const C_DASH = '-';
const C_AT_SIGN = '@';
const C_EXCLAMATION = '!';
/**
 * CSS syntax error with source location information.
 */
class CssSyntaxError extends \Exception
{
    public ?array $loc;
    public function __construct(string $message, ?array $loc = null)
    {
        parent::__construct($message);
        $this->loc = $loc;
    }
}
/**
 * Parse CSS string into AST.
 *
 * @param string $input
 * @return array<array>
 * @throws CssSyntaxError
 */
function parse(string $input): array
{
    // Handle BOM (UTF-8 BOM is 3 bytes: EF BB BF)
    if (isset($input[2]) && $input[0] === "\xef" && $input[1] === "\xbb" && $input[2] === "\xbf") {
        $input = ' ' . substr($input, 3);
    }
    $ast = [];
    $licenseComments = [];
    $stack = [];
    $parent = null;
    $node = null;
    $buffer = '';
    $bufferLen = 0;
    $closingBracketStack = '';
    $closingLen = 0;
    $len = strlen($input);
    for ($i = 0; $i < $len; $i++) {
        $c = $input[$i];
        // Skip over the CR in CRLF
        if ($c === C_CARRIAGE_RETURN && isset($input[$i + 1]) && $input[$i + 1] === C_LINE_BREAK) {
            continue;
        }
        // Backslash - escape next character
        if ($c === C_BACKSLASH) {
            if ($bufferLen === 0) {
                $buffer = $c;
                $bufferLen = 1;
            } else {
                $buffer .= $c;
                $bufferLen++;
            }
            if (isset($input[$i + 1])) {
                $buffer .= $input[$i + 1];
                $bufferLen++;
                $i++;
            }
            continue;
        }
        // Start of a comment
        if ($c === C_SLASH && isset($input[$i + 1]) && $input[$i + 1] === C_ASTERISK) {
            $start = $i;
            for ($j = $i + 2; $j < $len; $j++) {
                $pc = $input[$j];
                if ($pc === C_BACKSLASH) {
                    $j++;
                    continue;
                }
                if ($pc === C_ASTERISK && isset($input[$j + 1]) && $input[$j + 1] === C_SLASH) {
                    $i = $j + 1;
                    break;
                }
            }
            $commentString = substr($input, $start, $i - $start + 1);
            // License comments (/*! ... */)
            if (isset($commentString[2]) && $commentString[2] === C_EXCLAMATION) {
                $licenseComments[] = comment(substr($commentString, 2, -2));
            }
            continue;
        }
        // Start of a string
        if ($c === C_SINGLE_QUOTE || $c === C_DOUBLE_QUOTE) {
            $end = parseStringChar($input, $i, $c, $len);
            $chunk = substr($input, $i, $end - $i + 1);
            $buffer .= $chunk;
            $bufferLen += $end - $i + 1;
            $i = $end;
            continue;
        }
        // Skip consecutive whitespace
        if (($c === C_SPACE || $c === C_LINE_BREAK || $c === C_TAB) && isset($input[$i + 1])) {
            $pc = $input[$i + 1];
            if ($pc === C_SPACE || $pc === C_LINE_BREAK || $pc === C_TAB || $pc === C_CARRIAGE_RETURN && isset($input[$i + 2]) && $input[$i + 2] === C_LINE_BREAK) {
                continue;
            }
        }
        // Replace newlines with spaces
        if ($c === C_LINE_BREAK) {
            if ($bufferLen === 0) {
                continue;
            }
            $lastChar = $buffer[$bufferLen - 1];
            if ($lastChar !== C_SPACE && $lastChar !== C_LINE_BREAK && $lastChar !== C_TAB) {
                $buffer .= ' ';
                $bufferLen++;
            }
            continue;
        }
        // Custom property (starts with --)
        if ($c === C_DASH && isset($input[$i + 1]) && $input[$i + 1] === C_DASH && $bufferLen === 0) {
            $customPropStack = '';
            $customStackLen = 0;
            $start = $i;
            $colonIdx = -1;
            for ($j = $i + 2; $j < $len; $j++) {
                $pc = $input[$j];
                if ($pc === C_BACKSLASH) {
                    $j++;
                    continue;
                }
                if ($pc === C_SINGLE_QUOTE || $pc === C_DOUBLE_QUOTE) {
                    $j = parseStringChar($input, $j, $pc, $len);
                    continue;
                }
                if ($pc === C_SLASH && isset($input[$j + 1]) && $input[$j + 1] === C_ASTERISK) {
                    for ($k = $j + 2; $k < $len; $k++) {
                        $pk = $input[$k];
                        if ($pk === C_BACKSLASH) {
                            $k++;
                            continue;
                        }
                        if ($pk === C_ASTERISK && isset($input[$k + 1]) && $input[$k + 1] === C_SLASH) {
                            $j = $k + 1;
                            break;
                        }
                    }
                    continue;
                }
                if ($colonIdx === -1 && $pc === C_COLON) {
                    $colonIdx = $bufferLen + $j - $start;
                    continue;
                }
                if ($pc === C_SEMICOLON && $customStackLen === 0) {
                    $buffer .= substr($input, $start, $j - $start);
                    $bufferLen += $j - $start;
                    $i = $j;
                    break;
                }
                if ($pc === C_OPEN_PAREN) {
                    $customPropStack .= ')';
                    $customStackLen++;
                } elseif ($pc === C_OPEN_BRACKET) {
                    $customPropStack .= ']';
                    $customStackLen++;
                } elseif ($pc === C_OPEN_CURLY) {
                    $customPropStack .= '}';
                    $customStackLen++;
                }
                if (($pc === C_CLOSE_CURLY || $j === $len - 1) && $customStackLen === 0) {
                    if ($pc === C_CLOSE_CURLY) {
                        $i = $j - 1;
                        $buffer .= substr($input, $start, $j - $start);
                        $bufferLen += $j - $start;
                    } else {
                        $i = $j;
                        $buffer .= substr($input, $start, $j - $start + 1);
                        $bufferLen += $j - $start + 1;
                    }
                    break;
                }
                if ($pc === C_CLOSE_PAREN || $pc === C_CLOSE_BRACKET || $pc === C_CLOSE_CURLY) {
                    if ($customStackLen > 0 && $pc === $customPropStack[$customStackLen - 1]) {
                        $customPropStack = substr($customPropStack, 0, -1);
                        $customStackLen--;
                    }
                }
            }
            $declaration = parseDeclaration($buffer, $colonIdx);
            if (!$declaration) {
                throw new CssSyntaxError('Invalid custom property, expected a value');
            }
            if ($parent !== null) {
                $parent['nodes'][] = $declaration;
            } else {
                $ast[] = $declaration;
            }
            $buffer = '';
            $bufferLen = 0;
            continue;
        }
        // End of body-less at-rule
        if ($c === C_SEMICOLON && $bufferLen > 0 && $buffer[0] === C_AT_SIGN) {
            $node = parseAtRule($buffer);
            if ($parent !== null) {
                $parent['nodes'][] = $node;
            } else {
                $ast[] = $node;
            }
            $buffer = '';
            $bufferLen = 0;
            $node = null;
            continue;
        }
        // End of declaration
        if ($c === C_SEMICOLON && ($closingLen === 0 || $closingBracketStack[$closingLen - 1] !== ')')) {
            $declaration = parseDeclaration($buffer);
            if (!$declaration) {
                if ($bufferLen === 0) {
                    continue;
                }
                throw new CssSyntaxError('Invalid declaration: `' . trim($buffer) . '`');
            }
            if ($parent !== null) {
                $parent['nodes'][] = $declaration;
            } else {
                $ast[] = $declaration;
            }
            $buffer = '';
            $bufferLen = 0;
            continue;
        }
        // Start of a block
        if ($c === C_OPEN_CURLY && ($closingLen === 0 || $closingBracketStack[$closingLen - 1] !== ')')) {
            $closingBracketStack .= '}';
            $closingLen++;
            $node = rule(trim($buffer));
            if ($parent !== null) {
                $nodeIndex = count($parent['nodes']);
                $parent['nodes'][$nodeIndex] = $node;
                $stack[] = ['parent' => $parent, 'index' => $nodeIndex];
                $parent =& $parent['nodes'][$nodeIndex];
            } else {
                $stack[] = ['parent' => null, 'index' => -1];
                $parent = $node;
            }
            $buffer = '';
            $bufferLen = 0;
            continue;
        }
        // End of a block
        if ($c === C_CLOSE_CURLY && ($closingLen === 0 || $closingBracketStack[$closingLen - 1] !== ')')) {
            if ($closingLen === 0) {
                $context = $bufferLen > 0 ? ' near `' . trim(substr($buffer, 0, 50)) . '`' : '';
                throw new CssSyntaxError("Unexpected closing } - missing opening {{$context}");
            }
            $closingBracketStack = substr($closingBracketStack, 0, -1);
            $closingLen--;
            if ($bufferLen > 0) {
                if ($buffer[0] === C_AT_SIGN) {
                    $node = parseAtRule($buffer);
                    $parent['nodes'][] = $node;
                    $buffer = '';
                    $bufferLen = 0;
                    $node = null;
                } else {
                    $colonIdx = strpos($buffer, ':');
                    $decl = parseDeclaration($buffer, $colonIdx !== \false ? $colonIdx : -1);
                    if (!$decl) {
                        throw new CssSyntaxError('Invalid declaration: `' . trim($buffer) . '`');
                    }
                    $parent['nodes'][] = $decl;
                }
            }
            $stackItem = array_pop($stack);
            $grandParent = $stackItem['parent'];
            if ($grandParent === null) {
                $ast[] = $parent;
                $parent = null;
            } else {
                $grandParent['nodes'][$stackItem['index']] = $parent;
                $parent = $grandParent;
            }
            $buffer = '';
            $bufferLen = 0;
            continue;
        }
        // Open paren
        if ($c === C_OPEN_PAREN) {
            $closingBracketStack .= ')';
            $closingLen++;
            $buffer .= '(';
            $bufferLen++;
            continue;
        }
        // Close paren
        if ($c === C_CLOSE_PAREN) {
            if ($closingLen > 0 && $closingBracketStack[$closingLen - 1] !== ')') {
                $context = $bufferLen > 0 ? ' in `' . trim(substr($buffer, 0, 50)) . '`' : '';
                throw new CssSyntaxError("Unexpected closing ) - missing opening ({$context}");
            }
            $closingBracketStack = substr($closingBracketStack, 0, -1);
            $closingLen--;
            $buffer .= ')';
            $bufferLen++;
            continue;
        }
        // Skip leading whitespace
        if ($bufferLen === 0 && ($c === C_SPACE || $c === C_LINE_BREAK || $c === C_TAB)) {
            continue;
        }
        $buffer .= $c;
        $bufferLen++;
    }
    // Handle leftover at-rule at end of input
    if ($bufferLen > 0 && $buffer[0] === C_AT_SIGN) {
        $ast[] = parseAtRule($buffer);
    }
    // Check for unterminated blocks
    if ($closingLen > 0 && $parent !== null) {
        if ($parent['kind'] === 'rule') {
            throw new CssSyntaxError("Missing closing } at {$parent['selector']}");
        }
        if ($parent['kind'] === 'at-rule') {
            throw new CssSyntaxError("Missing closing } at {$parent['name']} {$parent['params']}");
        }
    }
    if (count($licenseComments) > 0) {
        return array_merge($licenseComments, $ast);
    }
    return $ast;
}
/**
 * Parse a declaration from buffer.
 *
 * @param string $buffer
 * @param int $colonIdx
 * @return array|null
 */
function parseDeclaration(string $buffer, int $colonIdx = -1): ?array
{
    if ($colonIdx === -1) {
        $colonIdx = strpos($buffer, ':');
    }
    if ($colonIdx === \false) {
        return null;
    }
    $importantIdx = strpos($buffer, '!important', $colonIdx + 1);
    return decl(trim(substr($buffer, 0, $colonIdx)), trim(substr($buffer, $colonIdx + 1, $importantIdx === \false ? null : $importantIdx - $colonIdx - 1)), $importantIdx !== \false);
}
/**
 * Parse a string (single or double quoted) - character version.
 *
 * @param string $input
 * @param int $startIdx
 * @param string $quoteChar Quote character as string
 * @param int $len Input length
 * @return int End index of the string
 * @throws CssSyntaxError
 */
function parseStringChar(string $input, int $startIdx, string $quoteChar, int $len): int
{
    for ($i = $startIdx + 1; $i < $len; $i++) {
        $pc = $input[$i];
        if ($pc === C_BACKSLASH) {
            $i++;
            continue;
        }
        if ($pc === $quoteChar) {
            return $i;
        }
        if ($pc === C_SEMICOLON) {
            if (isset($input[$i + 1])) {
                $nc = $input[$i + 1];
                if ($nc === C_LINE_BREAK || $nc === C_CARRIAGE_RETURN && isset($input[$i + 2]) && $input[$i + 2] === C_LINE_BREAK) {
                    throw new CssSyntaxError('Unterminated string: ' . substr($input, $startIdx, $i - $startIdx + 1) . $quoteChar);
                }
            }
        }
        if ($pc === C_LINE_BREAK || $pc === C_CARRIAGE_RETURN && isset($input[$i + 1]) && $input[$i + 1] === C_LINE_BREAK) {
            throw new CssSyntaxError('Unterminated string: ' . substr($input, $startIdx, $i - $startIdx) . $quoteChar);
        }
    }
    return $startIdx;
}
