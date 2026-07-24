<?php

declare(strict_types=1);

namespace Blockstudio\PHPStan\Tests\Rules;

use Blockstudio\PHPStan\Reflection\FieldTypeRegistry;
use Blockstudio\PHPStan\Rules\BlockJsonShapeRule;
use Blockstudio\PHPStan\Schema\BlockJsonReader;
use Blockstudio\PHPStan\Schema\CustomFieldResolver;
use Blockstudio\PHPStan\Schema\ProjectScanner;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use ReflectionClass;

/**
 * @extends RuleTestCase<BlockJsonShapeRule>
 */
final class BlockJsonShapeRuleTest extends RuleTestCase
{
    private string $fixtureDir = '';

    protected function setUp(): void
    {
        parent::setUp();
        $reflection = new ReflectionClass(BlockJsonShapeRule::class);
        $property = $reflection->getProperty('validatedPaths');
        $property->setValue(null, []);
    }

    protected function getRule(): Rule
    {
        $scanner = new ProjectScanner($this->fixtureDir);
        $reader = new BlockJsonReader(
            new FieldTypeRegistry(),
            new CustomFieldResolver($scanner)
        );
        return new BlockJsonShapeRule($scanner, $reader);
    }

    public function test_valid_block_passes(): void
    {
        $this->fixtureDir = __DIR__ . '/data/shape/valid-block';
        $this->analyse(
            [$this->fixtureDir . '/blockstudio/hero/index.php'],
            []
        );
    }

    public function test_missing_name_reported(): void
    {
        $this->fixtureDir = __DIR__ . '/data/shape/missing-name';
        $errors = $this->gatherErrors([$this->fixtureDir . '/blockstudio/bad/index.php']);
        $this->assertContainsErrorMessage('missing required "name"', $errors);
    }

    public function test_missing_field_id_reported(): void
    {
        $this->fixtureDir = __DIR__ . '/data/shape/missing-field-id';
        $errors = $this->gatherErrors([$this->fixtureDir . '/blockstudio/bad/index.php']);
        $this->assertContainsErrorMessage('missing "id"', $errors);
    }

    public function test_missing_field_type_reported(): void
    {
        $this->fixtureDir = __DIR__ . '/data/shape/missing-field-type';
        $errors = $this->gatherErrors([$this->fixtureDir . '/blockstudio/bad/index.php']);
        $this->assertContainsErrorMessage('missing "type"', $errors);
    }

    public function test_unknown_field_type_reported(): void
    {
        $this->fixtureDir = __DIR__ . '/data/shape/unknown-type';
        $errors = $this->gatherErrors([$this->fixtureDir . '/blockstudio/bad/index.php']);
        $this->assertContainsErrorMessage('unknown type', $errors);
    }

    public function test_select_without_options_reported(): void
    {
        $this->fixtureDir = __DIR__ . '/data/shape/select-no-options';
        $errors = $this->gatherErrors([$this->fixtureDir . '/blockstudio/bad/index.php']);
        $this->assertContainsErrorMessage('"options" or "populate"', $errors);
    }

    public function test_duplicate_field_id_reported(): void
    {
        $this->fixtureDir = __DIR__ . '/data/shape/duplicate-id';
        $errors = $this->gatherErrors([$this->fixtureDir . '/blockstudio/bad/index.php']);
        $this->assertContainsErrorMessage('duplicate field id', $errors);
    }

    public function test_custom_field_type_passes(): void
    {
        $this->fixtureDir = __DIR__ . '/data/shape/custom-field';
        $this->analyse(
            [$this->fixtureDir . '/blockstudio/good/index.php'],
            []
        );
    }

    public function test_missing_custom_field_definition_is_reported(): void
    {
        $this->fixtureDir = __DIR__ . '/data/shape/missing-custom-field';
        $errors = $this->gatherErrors(
            [$this->fixtureDir . '/blockstudio/bad/index.php']
        );
        $this->assertContainsErrorMessage(
            'Custom field "test/not-found"',
            $errors
        );
        $this->assertContainsErrorMessage('no discoverable field.json', $errors);
    }

    public function test_ambiguous_custom_field_definition_is_reported(): void
    {
        $this->fixtureDir = __DIR__ . '/data/shape/ambiguous-custom-field';
        $errors = $this->gatherErrors(
            [$this->fixtureDir . '/blockstudio/bad/index.php']
        );
        $this->assertContainsErrorMessage(
            'Custom field "test/shared"',
            $errors
        );
        $this->assertContainsErrorMessage('is ambiguous', $errors);
    }

    public function test_plugin_dependencies_must_use_slug_keys_and_object_values(): void
    {
        $this->fixtureDir = __DIR__ . '/data/shape/plugin-dependencies-invalid';
        $errors = $this->gatherErrors([$this->fixtureDir . '/blockstudio/bad/index.php']);
        $this->assertContainsErrorMessage('keys must be WordPress plugin slugs', $errors);
        $this->assertContainsErrorMessage('blockstudio.pluginDependencies.woocommerce', $errors);
        $this->assertContainsErrorMessage('blockstudio.pluginDependencies.contact-form-7.version', $errors);
    }

    /**
     * @param list<string> $files
     * @return list<string>
     */
    private function gatherErrors(array $files): array
    {
        $errors = $this->gatherAnalyserErrors($files);
        return array_map(static fn($e) => $e->getMessage(), $errors);
    }

    /**
     * @param list<string> $errors
     */
    private function assertContainsErrorMessage(string $needle, array $errors): void
    {
        foreach ($errors as $error) {
            if (str_contains($error, $needle)) {
                $this->assertTrue(true);
                return;
            }
        }
        $this->fail("No error contained '$needle'. Got: " . implode("\n", $errors));
    }
}
