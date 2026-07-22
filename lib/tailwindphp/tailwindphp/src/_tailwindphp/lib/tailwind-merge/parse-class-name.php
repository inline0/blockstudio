<?php

declare (strict_types=1);
/**
 * Port of: https://github.com/dcastil/tailwind-merge/blob/main/src/lib/parse-class-name.ts
 *
 * Parses class names into their component parts.
 *
 * @port-deviation:types Uses PHP arrays instead of TypeScript interfaces
 */
namespace BlockstudioVendor\TailwindPHP\Lib\TailwindMerge;

class ParseClassName
{
    public const IMPORTANT_MODIFIER = '!';
    private const MODIFIER_SEPARATOR = ':';
    private ?string $prefix;
    /** @var callable|null */
    private $experimentalParseClassName;
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config)
    {
        $this->prefix = $config['prefix'] ?? null;
        $this->experimentalParseClassName = $config['experimentalParseClassName'] ?? null;
    }
    /**
     * Parse a class name into its component parts.
     *
     * @return array{
     *     modifiers: array<string>,
     *     hasImportantModifier: bool,
     *     baseClassName: string,
     *     maybePostfixModifierPosition: ?int,
     *     isExternal?: bool
     * }
     */
    public function parse(string $className): array
    {
        // Handle prefix
        if ($this->prefix !== null) {
            $fullPrefix = $this->prefix . self::MODIFIER_SEPARATOR;
            if (!str_starts_with($className, $fullPrefix)) {
                return ['modifiers' => [], 'hasImportantModifier' => \false, 'baseClassName' => $className, 'maybePostfixModifierPosition' => null, 'isExternal' => \true];
            }
            $className = substr($className, strlen($fullPrefix));
        }
        $result = $this->parseInternal($className);
        // Handle experimental parse
        if ($this->experimentalParseClassName !== null) {
            $result = ($this->experimentalParseClassName)(['className' => $className, 'parseClassName' => fn($cn) => $this->parseInternal($cn)]);
        }
        return $result;
    }
    /**
     * @return array{
     *     modifiers: array<string>,
     *     hasImportantModifier: bool,
     *     baseClassName: string,
     *     maybePostfixModifierPosition: ?int,
     *     isExternal?: bool
     * }
     */
    private function parseInternal(string $className): array
    {
        $modifiers = [];
        $bracketDepth = 0;
        $parenDepth = 0;
        $modifierStart = 0;
        $postfixModifierPosition = null;
        $len = strlen($className);
        for ($index = 0; $index < $len; $index++) {
            $char = $className[$index];
            if ($bracketDepth === 0 && $parenDepth === 0) {
                if ($char === self::MODIFIER_SEPARATOR) {
                    $modifiers[] = substr($className, $modifierStart, $index - $modifierStart);
                    $modifierStart = $index + 1;
                    continue;
                }
                if ($char === '/') {
                    $postfixModifierPosition = $index;
                    continue;
                }
            }
            if ($char === '[') {
                $bracketDepth++;
            } elseif ($char === ']') {
                $bracketDepth--;
            } elseif ($char === '(') {
                $parenDepth++;
            } elseif ($char === ')') {
                $parenDepth--;
            }
        }
        $baseClassNameWithImportantModifier = count($modifiers) === 0 ? $className : substr($className, $modifierStart);
        // Check for important modifier
        $baseClassName = $baseClassNameWithImportantModifier;
        $hasImportantModifier = \false;
        if (str_ends_with($baseClassNameWithImportantModifier, self::IMPORTANT_MODIFIER)) {
            $baseClassName = substr($baseClassNameWithImportantModifier, 0, -1);
            $hasImportantModifier = \true;
        } elseif (str_starts_with($baseClassNameWithImportantModifier, self::IMPORTANT_MODIFIER)) {
            // Legacy v3 support
            $baseClassName = substr($baseClassNameWithImportantModifier, 1);
            $hasImportantModifier = \true;
        }
        $maybePostfixModifierPosition = $postfixModifierPosition !== null && $postfixModifierPosition > $modifierStart ? $postfixModifierPosition - $modifierStart : null;
        return ['modifiers' => $modifiers, 'hasImportantModifier' => $hasImportantModifier, 'baseClassName' => $baseClassName, 'maybePostfixModifierPosition' => $maybePostfixModifierPosition];
    }
}
