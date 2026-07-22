<?php

declare (strict_types=1);
/**
 * Port of: https://github.com/dcastil/tailwind-merge/blob/main/src/lib/class-group-utils.ts
 *
 * Utilities for building and querying the class group map.
 *
 * @port-deviation:storage Uses PHP arrays instead of JS Map/Set
 */
namespace BlockstudioVendor\TailwindPHP\Lib\TailwindMerge;

class ClassGroupUtils
{
    private const CLASS_PART_SEPARATOR = '-';
    private const ARBITRARY_PROPERTY_PREFIX = 'arbitrary..';
    /** @var array<string, mixed> */
    private array $classMap;
    /** @var array<string, array<string>> */
    private array $conflictingClassGroups;
    /** @var array<string, array<string>> */
    private array $conflictingClassGroupModifiers;
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config)
    {
        $this->classMap = $this->createClassMap($config);
        $this->conflictingClassGroups = $config['conflictingClassGroups'] ?? [];
        $this->conflictingClassGroupModifiers = $config['conflictingClassGroupModifiers'] ?? [];
    }
    public function getClassGroupId(string $className): ?string
    {
        if (str_starts_with($className, '[') && str_ends_with($className, ']')) {
            return $this->getGroupIdForArbitraryProperty($className);
        }
        $classParts = explode(self::CLASS_PART_SEPARATOR, $className);
        // Classes like `-inset-1` produce an empty string as first classPart
        $startIndex = $classParts[0] === '' && count($classParts) > 1 ? 1 : 0;
        return $this->getGroupRecursive($classParts, $startIndex, $this->classMap);
    }
    /**
     * @return array<string>
     */
    public function getConflictingClassGroupIds(string $classGroupId, bool $hasPostfixModifier): array
    {
        if ($hasPostfixModifier) {
            $modifierConflicts = $this->conflictingClassGroupModifiers[$classGroupId] ?? null;
            $baseConflicts = $this->conflictingClassGroups[$classGroupId] ?? null;
            if ($modifierConflicts !== null) {
                if ($baseConflicts !== null) {
                    return array_merge($baseConflicts, $modifierConflicts);
                }
                return $modifierConflicts;
            }
            return $baseConflicts ?? [];
        }
        return $this->conflictingClassGroups[$classGroupId] ?? [];
    }
    /**
     * @param array<string> $classParts
     * @param array<string, mixed> $classPartObject
     */
    private function getGroupRecursive(array $classParts, int $startIndex, array $classPartObject): ?string
    {
        $classPathsLength = count($classParts) - $startIndex;
        if ($classPathsLength === 0) {
            return $classPartObject['classGroupId'] ?? null;
        }
        $currentClassPart = $classParts[$startIndex];
        $nextClassPartObject = $classPartObject['nextPart'][$currentClassPart] ?? null;
        if ($nextClassPartObject !== null) {
            $result = $this->getGroupRecursive($classParts, $startIndex + 1, $nextClassPartObject);
            if ($result !== null) {
                return $result;
            }
        }
        $validators = $classPartObject['validators'] ?? null;
        if ($validators === null) {
            return null;
        }
        // Build classRest string
        $classRest = $startIndex === 0 ? implode(self::CLASS_PART_SEPARATOR, $classParts) : implode(self::CLASS_PART_SEPARATOR, array_slice($classParts, $startIndex));
        foreach ($validators as $validatorObj) {
            $validator = $validatorObj['validator'];
            if ($validator($classRest)) {
                return $validatorObj['classGroupId'];
            }
        }
        return null;
    }
    private function getGroupIdForArbitraryProperty(string $className): ?string
    {
        $content = substr($className, 1, -1);
        $colonIndex = strpos($content, ':');
        if ($colonIndex === \false) {
            return null;
        }
        $property = substr($content, 0, $colonIndex);
        return $property ? self::ARBITRARY_PROPERTY_PREFIX . $property : null;
    }
    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    private function createClassMap(array $config): array
    {
        $classGroups = $config['classGroups'] ?? [];
        $theme = $config['theme'] ?? [];
        $classMap = ['nextPart' => [], 'validators' => null, 'classGroupId' => null];
        foreach ($classGroups as $classGroupId => $group) {
            $this->processClassesRecursively($group, $classMap, $classGroupId, $theme);
        }
        return $classMap;
    }
    /**
     * @param array<mixed> $classGroup
     * @param array<string, mixed> $classPartObject
     * @param array<string, mixed> $theme
     */
    private function processClassesRecursively(array $classGroup, array &$classPartObject, string $classGroupId, array $theme): void
    {
        foreach ($classGroup as $classDefinition) {
            $this->processClassDefinition($classDefinition, $classPartObject, $classGroupId, $theme);
        }
    }
    /**
     * @param mixed $classDefinition
     * @param array<string, mixed> $classPartObject
     * @param array<string, mixed> $theme
     */
    private function processClassDefinition(mixed $classDefinition, array &$classPartObject, string $classGroupId, array $theme): void
    {
        if (is_string($classDefinition)) {
            $target = $classDefinition === '' ? $classPartObject : $this->getPart($classPartObject, $classDefinition);
            $target['classGroupId'] = $classGroupId;
            if ($classDefinition !== '') {
                $this->setPart($classPartObject, $classDefinition, $target);
            } else {
                $classPartObject['classGroupId'] = $classGroupId;
            }
            return;
        }
        // Check if it's a theme getter (special array structure)
        if (is_array($classDefinition) && isset($classDefinition['isThemeGetter']) && $classDefinition['isThemeGetter'] === \true) {
            $themeGetter = $classDefinition['__invoke'];
            $themeValues = $themeGetter($theme);
            $this->processClassesRecursively($themeValues, $classPartObject, $classGroupId, $theme);
            return;
        }
        if (is_callable($classDefinition)) {
            // It's a validator function
            if (!isset($classPartObject['validators'])) {
                $classPartObject['validators'] = [];
            }
            $classPartObject['validators'][] = ['classGroupId' => $classGroupId, 'validator' => $classDefinition];
            return;
        }
        if (is_array($classDefinition)) {
            // Check if it's an associative array (object-like definition)
            // by checking if it has non-integer keys
            $isAssociative = \false;
            foreach (array_keys($classDefinition) as $key) {
                if (!is_int($key)) {
                    $isAssociative = \true;
                    break;
                }
            }
            if ($isAssociative) {
                // Associative array - process as object definition
                foreach ($classDefinition as $key => $value) {
                    $part = $this->getPart($classPartObject, (string) $key);
                    // Ensure $value is always an array for recursive processing
                    $valueToProcess = is_array($value) ? $value : [$value];
                    $this->processClassesRecursively($valueToProcess, $part, $classGroupId, $theme);
                    $this->setPart($classPartObject, (string) $key, $part);
                }
            } else {
                // Sequential array - process each element
                foreach ($classDefinition as $item) {
                    $this->processClassDefinition($item, $classPartObject, $classGroupId, $theme);
                }
            }
        }
    }
    /**
     * @param array<string, mixed> $classPartObject
     * @return array<string, mixed>
     */
    private function getPart(array $classPartObject, string $path): array
    {
        $current = $classPartObject;
        $parts = explode(self::CLASS_PART_SEPARATOR, $path);
        foreach ($parts as $part) {
            if (!isset($current['nextPart'][$part])) {
                $current['nextPart'][$part] = ['nextPart' => [], 'validators' => null, 'classGroupId' => null];
            }
            $current = $current['nextPart'][$part];
        }
        return $current;
    }
    /**
     * @param array<string, mixed> $classPartObject
     * @param array<string, mixed> $value
     */
    private function setPart(array &$classPartObject, string $path, array $value): void
    {
        $parts = explode(self::CLASS_PART_SEPARATOR, $path);
        $current =& $classPartObject;
        foreach ($parts as $i => $part) {
            if (!isset($current['nextPart'][$part])) {
                $current['nextPart'][$part] = ['nextPart' => [], 'validators' => null, 'classGroupId' => null];
            }
            if ($i === count($parts) - 1) {
                $current['nextPart'][$part] = $value;
            } else {
                $current =& $current['nextPart'][$part];
            }
        }
    }
}
