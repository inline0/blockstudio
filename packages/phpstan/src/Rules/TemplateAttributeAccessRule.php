<?php

declare(strict_types=1);

namespace Blockstudio\PHPStan\Rules;

use Blockstudio\PHPStan\Schema\BlockJsonReader;
use Blockstudio\PHPStan\Schema\ProjectScanner;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\FileNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Validates attribute accesses in Twig and Blade templates.
 *
 * Scans sibling .twig and .blade.php files for a.fieldName (Twig dot notation)
 * and $a['fieldName'] (Blade/PHP bracket notation) and validates them against
 * the block.json schema.
 *
 * @implements Rule<FileNode>
 */
final class TemplateAttributeAccessRule implements Rule
{
    /** @var array<string, true> */
    private static array $scannedFiles = [];

    public function __construct(
        private readonly ProjectScanner $scanner,
        private readonly BlockJsonReader $reader
    ) {}

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

                $data = $this->reader->load($blockJsonPath);
                if ($data === null) {
                    continue;
                }

                $validKeys = $this->collectKeys($data['blockstudio']['attributes'] ?? []);
                if (empty($validKeys)) {
                    continue;
                }

                $accesses = $this->extractAccesses($content, $template);
                foreach ($accesses as $access) {
                    if (in_array($access['key'], $validKeys, true)) {
                        continue;
                    }

                    $suggestion = $this->findSimilar($access['key'], $validKeys);
                    $message = sprintf(
                        'Field "%s" does not exist in block.json (block: %s) in %s.',
                        $access['key'],
                        (string) ($data['name'] ?? basename($blockDir)),
                        basename($template)
                    );
                    if ($suggestion !== null) {
                        $message .= sprintf(' Did you mean "%s"?', $suggestion);
                    }

                    $errors[] = RuleErrorBuilder::message($message)
                        ->identifier('blockstudio.templateField')
                        ->file($template)
                        ->line($access['line'])
                        ->build();
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
        foreach (['twig', 'blade.php'] as $ext) {
            $candidate = $dir . '/index.' . $ext;
            if (file_exists($candidate)) {
                $files[] = $candidate;
            }
        }
        return $files;
    }

    /**
     * @return list<array{key: string, line: int}>
     */
    private function extractAccesses(string $content, string $file): array
    {
        $accesses = [];
        $lines = explode("\n", $content);
        $isTwig = str_ends_with($file, '.twig');

        foreach ($lines as $lineNum => $line) {
            if ($isTwig) {
                // Twig: {{ a.fieldName }}, {% if a.fieldName %}, a.fieldName|filter
                if (preg_match_all('/\ba\.([a-zA-Z_]\w*)/', $line, $matches, PREG_SET_ORDER)) {
                    foreach ($matches as $match) {
                        $accesses[] = ['key' => $match[1], 'line' => $lineNum + 1];
                    }
                }
            } else {
                // Blade: $a['fieldName'] or $a["fieldName"]
                if (preg_match_all('/\$a\[[\'"]([\w]+)[\'"]\]/', $line, $matches, PREG_SET_ORDER)) {
                    foreach ($matches as $match) {
                        $accesses[] = ['key' => $match[1], 'line' => $lineNum + 1];
                    }
                }
            }
        }

        return $accesses;
    }

    /**
     * @param array<int, mixed> $attributes
     * @return list<string>
     */
    private function collectKeys(array $attributes, string $prefix = ''): array
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
                $keys = array_merge($keys, $this->collectKeys($field['attributes'], $key));
                continue;
            }
            if ($type === 'tabs' && isset($field['tabs']) && is_array($field['tabs'])) {
                foreach ($field['tabs'] as $tab) {
                    if (is_array($tab) && isset($tab['attributes']) && is_array($tab['attributes'])) {
                        $keys = array_merge($keys, $this->collectKeys($tab['attributes'], $prefix));
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

    /**
     * @param list<string> $candidates
     */
    private function findSimilar(string $needle, array $candidates): ?string
    {
        $best = null;
        $bestDistance = PHP_INT_MAX;
        foreach ($candidates as $candidate) {
            $distance = levenshtein($needle, $candidate);
            if ($distance < $bestDistance && $distance <= 3) {
                $bestDistance = $distance;
                $best = $candidate;
            }
        }
        return $best;
    }
}
