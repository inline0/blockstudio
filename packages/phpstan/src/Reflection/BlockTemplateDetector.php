<?php

declare(strict_types=1);

namespace Blockstudio\PHPStan\Reflection;

use Blockstudio\PHPStan\Schema\ProjectScanner;

/**
 * Determines whether a given file is a Blockstudio block template.
 * A template is a PHP file that lives next to a block.json.
 */
final class BlockTemplateDetector
{
    /** @var array<string, string|false> */
    private array $cache = [];

    public function __construct(private readonly ProjectScanner $scanner) {}

    /**
     * Returns the path to the sibling block.json if this file is a template,
     * or null otherwise.
     */
    public function getBlockJsonForTemplate(string $filePath): ?string
    {
        if (isset($this->cache[$filePath])) {
            $value = $this->cache[$filePath];
            return $value === false ? null : $value;
        }

        if (!str_ends_with($filePath, '.php')) {
            $this->cache[$filePath] = false;
            return null;
        }

        $blockJson = $this->scanner->findSiblingBlockJson($filePath);
        $this->cache[$filePath] = $blockJson ?? false;

        return $blockJson;
    }
}
