<?php

declare(strict_types=1);

namespace Blockstudio\PHPStan\Tests\Rules;

use Blockstudio\PHPStan\Rules\DbSchemaShapeRule;
use Blockstudio\PHPStan\Schema\DbSchemaReader;
use Blockstudio\PHPStan\Schema\ProjectScanner;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use ReflectionClass;

/**
 * @extends RuleTestCase<DbSchemaShapeRule>
 */
final class DbSchemaShapeRuleTest extends RuleTestCase
{
    private string $fixtureDir = '';

    protected function setUp(): void
    {
        parent::setUp();
        $reflection = new ReflectionClass(DbSchemaShapeRule::class);
        $property = $reflection->getProperty('validatedPaths');
        $property->setAccessible(true);
        $property->setValue(null, []);
    }

    protected function getRule(): Rule
    {
        return new DbSchemaShapeRule(
            new ProjectScanner($this->fixtureDir),
            new DbSchemaReader()
        );
    }

    public function test_valid_db_passes(): void
    {
        $this->fixtureDir = __DIR__ . '/data/db-shape/valid';
        $this->analyse(
            [$this->fixtureDir . '/blockstudio/users/index.php'],
            []
        );
    }

    public function test_missing_fields_reported(): void
    {
        $this->fixtureDir = __DIR__ . '/data/db-shape/missing-fields';
        $errors = $this->gatherErrorMessages([$this->fixtureDir . '/blockstudio/users/index.php']);
        $this->assertContainsErrorMessage('missing or empty "fields"', $errors);
    }

    public function test_missing_field_type_reported(): void
    {
        $this->fixtureDir = __DIR__ . '/data/db-shape/missing-type';
        $errors = $this->gatherErrorMessages([$this->fixtureDir . '/blockstudio/users/index.php']);
        $this->assertContainsErrorMessage('missing "type"', $errors);
    }

    public function test_invalid_field_type_reported(): void
    {
        $this->fixtureDir = __DIR__ . '/data/db-shape/invalid-type';
        $errors = $this->gatherErrorMessages([$this->fixtureDir . '/blockstudio/users/index.php']);
        $this->assertContainsErrorMessage('invalid type', $errors);
    }

    /**
     * @param list<string> $files
     * @return list<string>
     */
    private function gatherErrorMessages(array $files): array
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
