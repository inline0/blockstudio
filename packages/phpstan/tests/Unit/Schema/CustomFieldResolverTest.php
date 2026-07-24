<?php

declare(strict_types=1);

namespace Blockstudio\PHPStan\Tests\Unit\Schema;

use Blockstudio\PHPStan\Schema\CustomFieldResolver;
use Blockstudio\PHPStan\Schema\ProjectScanner;
use PHPUnit\Framework\TestCase;

final class CustomFieldResolverTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/bs-custom-field-test-' . uniqid();
        mkdir($this->tempDir . '/blockstudio/fields', 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tempDir);
    }

    public function test_expands_id_structure_overrides_and_conditions_like_runtime(): void
    {
        $this->writeField('hero', [
            'name' => 'theme/hero',
            'attributes' => [
                [
                    'id' => 'title',
                    'type' => 'text',
                    'conditions' => [[['id' => 'enabled']]],
                ],
                ['id' => 'enabled', 'type' => 'toggle'],
                [
                    'id' => 'content',
                    'type' => 'group',
                    'attributes' => [
                        ['id' => 'description', 'type' => 'textarea'],
                    ],
                ],
            ],
        ]);

        $result = $this->resolver()->resolve([[
            'type' => 'custom/theme/hero',
            'idStructure' => 'hero_{id}',
            'overrides' => [
                'enabled' => ['id' => 'active'],
            ],
            'conditions' => [[['id' => 'external']]],
        ]]);

        $this->assertSame([], $result['issues']);
        $this->assertSame('hero_title', $result['attributes'][0]['id']);
        $this->assertSame('hero_enabled', $result['attributes'][0]['conditions'][0][0]['id']);
        $this->assertSame('external', $result['attributes'][0]['conditions'][1][0]['id']);
        $this->assertSame('active', $result['attributes'][1]['id']);
        $this->assertSame('external', $result['attributes'][1]['conditions'][0][0]['id']);
        $this->assertSame('hero_content', $result['attributes'][2]['id']);
        $this->assertSame(
            'description',
            $result['attributes'][2]['attributes'][0]['id']
        );
    }

    public function test_expands_nested_references_and_all_container_positions(): void
    {
        $this->writeField('base', [
            'name' => 'theme/base',
            'attributes' => [
                ['id' => 'value', 'type' => 'text'],
            ],
        ]);
        $this->writeField('nested', [
            'name' => 'theme/nested',
            'attributes' => [
                [
                    'type' => 'custom/theme/base',
                    'idStructure' => 'nested_{id}',
                ],
            ],
        ]);

        $result = $this->resolver()->resolve([
            [
                'type' => 'custom/theme/nested',
                'idStructure' => 'outer_{id}',
            ],
            [
                'type' => 'tabs',
                'tabs' => [[
                    'attributes' => [[
                        'type' => 'custom/theme/base',
                        'idStructure' => 'tab_{id}',
                    ]],
                ]],
            ],
            [
                'type' => 'group',
                'attributes' => [[
                    'type' => 'custom/theme/base',
                    'idStructure' => 'anonymous_{id}',
                ]],
            ],
            [
                'id' => 'content',
                'type' => 'group',
                'attributes' => [[
                    'type' => 'custom/theme/base',
                    'idStructure' => 'inner_{id}',
                ]],
            ],
            [
                'id' => 'items',
                'type' => 'repeater',
                'attributes' => [[
                    'type' => 'custom/theme/base',
                    'idStructure' => 'row_{id}',
                ]],
            ],
        ]);

        $this->assertSame([], $result['issues']);
        $this->assertSame('outer_nested_value', $result['attributes'][0]['id']);
        $this->assertSame('tab_value', $result['attributes'][1]['tabs'][0]['attributes'][0]['id']);
        $this->assertSame('anonymous_value', $result['attributes'][2]['attributes'][0]['id']);
        $this->assertSame('inner_value', $result['attributes'][3]['attributes'][0]['id']);
        $this->assertSame('row_value', $result['attributes'][4]['attributes'][0]['id']);
    }

    public function test_missing_reference_is_preserved_and_reported(): void
    {
        $reference = ['type' => 'custom/theme/missing'];

        $result = $this->resolver()->resolve([$reference]);

        $this->assertSame([$reference], $result['attributes']);
        $this->assertSame('missing', $result['issues'][0]['type']);
        $this->assertSame('theme/missing', $result['issues'][0]['name']);
    }

    public function test_ambiguous_reference_is_preserved_and_reported_deterministically(): void
    {
        $first = $this->writeField('first', [
            'name' => 'theme/shared',
            'attributes' => [['id' => 'first', 'type' => 'text']],
        ]);
        $second = $this->writeField('second', [
            'name' => 'theme/shared',
            'attributes' => [['id' => 'second', 'type' => 'text']],
        ]);
        $reference = ['type' => 'custom/theme/shared'];

        $result = $this->resolver()->resolve([$reference]);

        $this->assertSame([$reference], $result['attributes']);
        $this->assertSame('ambiguous', $result['issues'][0]['type']);
        $expectedPaths = [$first, $second];
        sort($expectedPaths);
        $this->assertSame($expectedPaths, $result['issues'][0]['paths']);
    }

    public function test_nested_cycle_stops_without_recursing_forever(): void
    {
        $this->writeField('first', [
            'name' => 'theme/first',
            'attributes' => [['type' => 'custom/theme/second']],
        ]);
        $this->writeField('second', [
            'name' => 'theme/second',
            'attributes' => [['type' => 'custom/theme/first']],
        ]);

        $result = $this->resolver()->resolve([
            ['type' => 'custom/theme/first'],
        ]);

        $this->assertSame('cycle', $result['issues'][0]['type']);
        $this->assertSame('theme/first', $result['issues'][0]['name']);
        $this->assertCount(3, $result['issues'][0]['paths']);
    }

    public function test_invalid_definition_is_preserved_and_reported(): void
    {
        $this->writeField('invalid', [
            'name' => 'theme/invalid',
            'attributes' => [],
        ]);
        $reference = ['type' => 'custom/theme/invalid'];

        $result = $this->resolver()->resolve([$reference]);

        $this->assertSame([$reference], $result['attributes']);
        $this->assertSame('invalid', $result['issues'][0]['type']);
    }

    /**
     * @param array<string, mixed> $definition
     */
    private function writeField(string $directory, array $definition): string
    {
        $path = $this->tempDir . '/blockstudio/fields/' . $directory . '/field.json';
        mkdir(dirname($path), 0777, true);
        file_put_contents($path, json_encode($definition, JSON_PRETTY_PRINT));
        return $path;
    }

    private function resolver(): CustomFieldResolver
    {
        return new CustomFieldResolver(new ProjectScanner($this->tempDir));
    }

    private function removeDir(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = scandir($path);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $fullPath = $path . '/' . $item;
            if (is_dir($fullPath)) {
                $this->removeDir($fullPath);
            } else {
                unlink($fullPath);
            }
        }

        rmdir($path);
    }
}
