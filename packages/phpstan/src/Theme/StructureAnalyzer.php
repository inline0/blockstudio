<?php

declare(strict_types=1);

namespace Blockstudio\PHPStan\Theme;

/**
 * Structural checks enabled by theme.neon.
 */
final class StructureAnalyzer
{
    private const BLOCK_ASSET_KEYS = [
        'editorScript',
        'editorStyle',
        'render',
        'script',
        'style',
        'viewScript',
        'viewScriptModule',
    ];

    public function __construct(private readonly ProjectScanner $scanner) {}

    /**
     * @return list<Diagnostic>
     */
    public function analyse(): array
    {
        $diagnostics = [];

        foreach ($this->scanner->roots() as $root) {
            if (!is_dir($root)) {
                $diagnostics[] = new Diagnostic(
                    sprintf('Configured theme root does not exist: %s', $root),
                    'blockstudio.theme.root.missing',
                    $root
                );
                continue;
            }

            $diagnostics = array_merge(
                $diagnostics,
                $this->styleHeaderDiagnostics($root),
                $this->assetDiagnostics($root),
                $this->fieldContractDiagnostics($root)
            );
        }

        if ($this->scanner->limitReached()) {
            $root = $this->scanner->roots()[0] ?? '';
            $diagnostics[] = new Diagnostic(
                sprintf(
                    'Theme scan stopped at the configured %d-file limit. Narrow the roots or increase blockstudioThemeMaxFiles.',
                    $this->scanner->scanLimit()
                ),
                'blockstudio.theme.scanLimit',
                $root
            );
        }

        return $this->stable($diagnostics);
    }

    /**
     * @return list<Diagnostic>
     */
    private function styleHeaderDiagnostics(string $root): array
    {
        $style = $root . '/style.css';
        if (!is_file($style)) {
            return [
                new Diagnostic(
                    'A WordPress theme root must contain style.css.',
                    'blockstudio.theme.style.missing',
                    $style
                ),
            ];
        }

        if (preg_match('/^[ \t*]*Theme Name\s*:\s*\S+/mi', $this->scanner->read($style)) !== 1) {
            return [
                new Diagnostic(
                    'style.css must declare a non-empty Theme Name header.',
                    'blockstudio.theme.style.header',
                    $style
                ),
            ];
        }

        return [];
    }

    /**
     * @return list<Diagnostic>
     */
    private function assetDiagnostics(string $root): array
    {
        $diagnostics = [];

        foreach ($this->scanner->files(['php']) as $file) {
            if ($this->scanner->rootForFile($file) !== $root) {
                continue;
            }

            $relative = $this->scanner->relativePath($file, $root);
            if (!$this->isBlockFile($relative)) {
                continue;
            }

            $contents = $this->scanner->read($file);
            if (
                preg_match_all(
                    '/\b(wp_enqueue_(?:script|style|block_assets|block_style|block_script))\s*\(/',
                    $contents,
                    $matches,
                    PREG_OFFSET_CAPTURE
                ) !== false
            ) {
                foreach ($matches[1] as $match) {
                    $diagnostics[] = new Diagnostic(
                        sprintf(
                            'Block assets are managed by Blockstudio; declare the asset instead of calling %s() in block code.',
                            $match[0]
                        ),
                        'blockstudio.theme.asset.manualEnqueue',
                        $file,
                        ProjectScanner::lineForOffset($contents, $match[1])
                    );
                }
            }
        }

        foreach ($this->scanner->files(['scss']) as $file) {
            if ($this->scanner->rootForFile($file) !== $root || basename($file) !== 'style.scss') {
                continue;
            }

            if (
                is_file(dirname($file) . '/block.json')
                && !str_contains($this->scanner->read($file), '%selector%')
            ) {
                $diagnostics[] = new Diagnostic(
                    'Block style.scss must use %selector% so styles remain scoped to the owning block.',
                    'blockstudio.theme.asset.selectorScope',
                    $file
                );
            }
        }

        foreach ($this->scanner->filesNamed('block.json') as $file) {
            if ($this->scanner->rootForFile($file) !== $root) {
                continue;
            }

            $data = $this->scanner->jsonObject($file);
            if ($data === null) {
                continue;
            }

            foreach (self::BLOCK_ASSET_KEYS as $key) {
                foreach ($this->assetReferences($data[$key] ?? null) as $reference) {
                    $path = $this->resolveAssetPath($file, $reference);
                    if ($path !== null && !is_file($path)) {
                        $diagnostics[] = new Diagnostic(
                            sprintf('block.json %s references missing file "%s".', $key, $reference),
                            'blockstudio.theme.asset.missing',
                            $file
                        );
                    }
                }
            }
        }

        return $diagnostics;
    }

    /**
     * @return list<Diagnostic>
     */
    private function fieldContractDiagnostics(string $root): array
    {
        $diagnostics = [];

        foreach (array_merge(
            $this->scanner->filesNamed('block.json'),
            $this->scanner->filesNamed('field.json')
        ) as $file) {
            if ($this->scanner->rootForFile($file) !== $root) {
                continue;
            }

            $data = $this->scanner->jsonObject($file);
            if ($data === null) {
                continue;
            }

            $attributes = basename($file) === 'field.json'
                ? ($data['attributes'] ?? [])
                : ($data['blockstudio']['attributes'] ?? []);

            $diagnostics = array_merge(
                $diagnostics,
                $this->validateAttributes($attributes, $file)
            );
        }

        return $diagnostics;
    }

    /**
     * @param array<string, true> $inheritedDefaults
     * @return list<Diagnostic>
     */
    private function validateAttributes(
        mixed $attributes,
        string $file,
        array $inheritedDefaults = []
    ): array {
        if (!is_array($attributes)) {
            return [];
        }

        $diagnostics = [];
        foreach ($attributes as $index => $field) {
            if (!is_array($field)) {
                continue;
            }

            $type = isset($field['type']) && is_string($field['type'])
                ? $field['type']
                : '';
            $id = $field['id'] ?? $field['key'] ?? null;

            if ($type === 'tabs' && is_array($field['tabs'] ?? null)) {
                foreach ($field['tabs'] as $tab) {
                    if (is_array($tab)) {
                        $diagnostics = array_merge(
                            $diagnostics,
                            $this->validateAttributes(
                                $tab['attributes'] ?? [],
                                $file,
                                $inheritedDefaults
                            )
                        );
                    }
                }
                continue;
            }

            if ($type === 'group') {
                $diagnostics = array_merge(
                    $diagnostics,
                    $this->validateAttributes(
                        $field['attributes'] ?? [],
                        $file,
                        $this->nestedDefaultKeys($field, $id, $inheritedDefaults)
                    )
                );
                continue;
            }

            if ($type === 'repeater') {
                $diagnostics = array_merge(
                    $diagnostics,
                    $this->validateAttributes(
                        $field['attributes'] ?? [],
                        $file,
                        $this->repeaterDefaultKeys($field)
                    )
                );
            }

            if (
                is_string($id)
                && $id !== ''
                && $this->requiresDefault($type)
                && !array_key_exists('default', $field)
                && !isset($inheritedDefaults[$id])
            ) {
                $diagnostics[] = new Diagnostic(
                    sprintf('Blockstudio field "%s" must define a default value.', $id),
                    'blockstudio.field.default',
                    $file
                );
            }

            if ($type === 'repeater') {
                $diagnostics = array_merge(
                    $diagnostics,
                    $this->repeaterBoundsDiagnostics(
                        $field,
                        $file,
                        is_string($id) && $id !== ''
                            ? $id
                            : 'attributes[' . (string) $index . ']'
                    )
                );
            }
        }

        return $diagnostics;
    }

    private function requiresDefault(string $type): bool
    {
        return $type !== ''
            && !in_array($type, ['group', 'message', 'tabs'], true)
            && !str_starts_with($type, 'custom/');
    }

    /**
     * @param array<mixed> $field
     * @param array<string, true> $inheritedDefaults
     * @return array<string, true>
     */
    private function nestedDefaultKeys(
        array $field,
        mixed $id,
        array $inheritedDefaults
    ): array {
        if (is_array($field['default'] ?? null)) {
            return $this->defaultKeys($field['default']);
        }

        if (is_string($id) && $id !== '' && isset($inheritedDefaults[$id])) {
            return [];
        }

        return $inheritedDefaults;
    }

    /**
     * @param array<mixed> $field
     * @return array<string, true>
     */
    private function repeaterDefaultKeys(array $field): array
    {
        $default = $field['default'] ?? null;
        if (!is_array($default) || $default === []) {
            return [];
        }

        $first = reset($default);
        return is_array($first) ? $this->defaultKeys($first) : [];
    }

    /**
     * @param array<mixed> $default
     * @return array<string, true>
     */
    private function defaultKeys(array $default): array
    {
        $keys = [];
        foreach (array_keys($default) as $key) {
            if (is_string($key)) {
                $keys[$key] = true;
            }
        }

        return $keys;
    }

    /**
     * @param array<mixed> $field
     * @return list<Diagnostic>
     */
    private function repeaterBoundsDiagnostics(
        array $field,
        string $file,
        string $label
    ): array {
        $min = $field['min'] ?? null;
        $max = $field['max'] ?? null;
        if (is_int($min) && is_int($max) && $min >= 0 && $max >= $min) {
            return [];
        }

        if (is_array($field['default'] ?? null) && $field['default'] !== []) {
            return [];
        }

        return [
            new Diagnostic(
                sprintf(
                    'Repeater field "%s" must define non-negative integer min/max bounds with max greater than or equal to min.',
                    $label
                ),
                'blockstudio.field.repeaterBounds',
                $file
            ),
        ];
    }

    /**
     * @return list<string>
     */
    private function assetReferences(mixed $value): array
    {
        if (is_string($value)) {
            return [$value];
        }

        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, 'is_string'));
    }

    private function resolveAssetPath(string $blockJson, string $reference): ?string
    {
        if (str_starts_with($reference, 'file:')) {
            $reference = substr($reference, 5);
        } elseif (!str_starts_with($reference, './') && !str_starts_with($reference, '../')) {
            return null;
        }

        $path = dirname($blockJson) . '/' . $reference;
        $directory = realpath(dirname($path));
        return ($directory !== false ? $directory : dirname($path)) . '/' . basename($path);
    }

    private function isBlockFile(string $relative): bool
    {
        $relative = ltrim(str_replace('\\', '/', $relative), '/');
        return str_starts_with($relative, 'blocks/')
            || str_starts_with($relative, 'blockstudio/');
    }

    /**
     * @param list<Diagnostic> $diagnostics
     * @return list<Diagnostic>
     */
    private function stable(array $diagnostics): array
    {
        usort(
            $diagnostics,
            static fn(Diagnostic $a, Diagnostic $b): int => [
                $a->file,
                $a->line,
                $a->identifier,
                $a->message,
            ] <=> [
                $b->file,
                $b->line,
                $b->identifier,
                $b->message,
            ]
        );

        return $diagnostics;
    }
}
