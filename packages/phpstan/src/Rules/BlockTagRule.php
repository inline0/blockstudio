<?php

declare(strict_types=1);

namespace Blockstudio\PHPStan\Rules;

use Blockstudio\PHPStan\Reflection\BlockTagParser;
use Blockstudio\PHPStan\Schema\BlockJsonReader;
use Blockstudio\PHPStan\Schema\ProjectScanner;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\FileNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Validates <block name="..."> and <bs:name> tag syntax in templates.
 *
 * Scans PHP, Twig, and Blade template files for block tag references
 * and validates that the referenced blocks exist and attributes match.
 *
 * @implements Rule<FileNode>
 */
final class BlockTagRule implements Rule
{
    /** @var array<string, true> */
    private static array $scannedFiles = [];

    private readonly BlockTagParser $parser;

    public function __construct(
        private readonly ProjectScanner $scanner,
        private readonly BlockJsonReader $reader
    ) {
        $this->parser = new BlockTagParser();
    }

    public function getNodeType(): string
    {
        return FileNode::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $errors = [];

        foreach ($this->scanner->getBlockJsonPaths() as $blockJsonPath) {
            $blockDir = dirname($blockJsonPath);
            $templates = $this->findTemplateFiles($blockDir);

            foreach ($templates as $template) {
                if (isset(self::$scannedFiles[$template])) {
                    continue;
                }
                self::$scannedFiles[$template] = true;

                $content = @file_get_contents($template);
                if ($content === false) {
                    continue;
                }

                $tags = $this->parser->extractTags($content);
                foreach ($tags as $tag) {
                    $errors = array_merge(
                        $errors,
                        $this->validateTag($tag, $template)
                    );
                }
            }
        }

        return $errors;
    }

    /**
     * @return list<string>
     */
    private function findTemplateFiles(string $dir): array
    {
        $files = [];
        $extensions = ['php', 'twig', 'blade.php'];

        foreach ($extensions as $ext) {
            $candidate = $dir . '/index.' . $ext;
            if (file_exists($candidate)) {
                $files[] = $candidate;
            }
        }

        return $files;
    }

    /**
     * @param array{name: string, attributes: array<string, string>, line: int} $tag
     * @return list<\PHPStan\Rules\IdentifierRuleError>
     */
    private function validateTag(array $tag, string $file): array
    {
        $errors = [];
        $blockName = $tag['name'];

        // Check if the block name starts with "core/" (WordPress core blocks are always valid)
        if (str_starts_with($blockName, 'core/')) {
            return [];
        }

        $blockJsonPath = $this->scanner->findBlockJsonByName($blockName);
        if ($blockJsonPath === null) {
            $errors[] = RuleErrorBuilder::message(sprintf(
                'Block tag references unknown block "%s" in %s',
                $blockName,
                basename($file)
            ))
                ->identifier('blockstudio.blockTag.unknown')
                ->file($file)
                ->line($tag['line'])
                ->build();
            return $errors;
        }

        $blockData = $this->reader->load($blockJsonPath);
        if ($blockData === null) {
            return [];
        }

        $validKeys = $this->collectAttributeKeys($blockData['blockstudio']['attributes'] ?? []);

        // Skip "data-*" and "html-*" attributes (pass-through to HTML)
        $tagAttrs = array_filter(
            array_keys($tag['attributes']),
            static fn(string $key) => !str_starts_with($key, 'data-') && !str_starts_with($key, 'html-')
        );

        foreach ($tagAttrs as $attrName) {
            if (!in_array($attrName, $validKeys, true)) {
                $errors[] = RuleErrorBuilder::message(sprintf(
                    'Block tag "%s" has unknown attribute "%s" in %s',
                    $blockName,
                    $attrName,
                    basename($file)
                ))
                    ->identifier('blockstudio.blockTag.attribute')
                    ->file($file)
                    ->line($tag['line'])
                    ->build();
            }
        }

        return $errors;
    }

    /**
     * @param array<int, mixed> $attributes
     * @return list<string>
     */
    private function collectAttributeKeys(array $attributes, string $prefix = ''): array
    {
        $keys = [];

        foreach ($attributes as $field) {
            if (!is_array($field)) {
                continue;
            }

            $id = (string) ($field['id'] ?? $field['key'] ?? '');
            if ($id === '') {
                continue;
            }

            $type = (string) ($field['type'] ?? 'text');
            $key = $prefix === '' ? $id : $prefix . '_' . $id;

            if ($type === 'group' && isset($field['attributes']) && is_array($field['attributes'])) {
                $keys = array_merge($keys, $this->collectAttributeKeys($field['attributes'], $key));
                continue;
            }

            if ($type === 'tabs' && isset($field['tabs']) && is_array($field['tabs'])) {
                foreach ($field['tabs'] as $tab) {
                    if (is_array($tab) && isset($tab['attributes']) && is_array($tab['attributes'])) {
                        $keys = array_merge($keys, $this->collectAttributeKeys($tab['attributes'], $prefix));
                    }
                }
                continue;
            }

            if ($type === 'message') {
                continue;
            }

            $keys[] = $key;
        }

        return $keys;
    }
}
