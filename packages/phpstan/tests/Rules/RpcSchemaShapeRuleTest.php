<?php

declare(strict_types=1);

namespace Blockstudio\PHPStan\Tests\Rules;

use Blockstudio\PHPStan\Rules\RpcSchemaShapeRule;
use Blockstudio\PHPStan\Schema\ProjectScanner;
use Blockstudio\PHPStan\Schema\RpcSchemaReader;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use ReflectionClass;

/**
 * @extends RuleTestCase<RpcSchemaShapeRule>
 */
final class RpcSchemaShapeRuleTest extends RuleTestCase
{
    private string $fixtureDir = '';

    protected function setUp(): void
    {
        parent::setUp();
        $reflection = new ReflectionClass(RpcSchemaShapeRule::class);
        $property = $reflection->getProperty('validatedPaths');
        $property->setAccessible(true);
        $property->setValue(null, []);
    }

    protected function getRule(): Rule
    {
        return new RpcSchemaShapeRule(
            new ProjectScanner($this->fixtureDir),
            new RpcSchemaReader()
        );
    }

    public function test_valid_rpc_passes(): void
    {
        $this->fixtureDir = __DIR__ . '/data/rpc-shape/valid';
        $this->analyse(
            [$this->fixtureDir . '/blockstudio/api/index.php'],
            []
        );
    }

    public function test_attribute_valid_rpc_passes(): void
    {
        $this->fixtureDir = __DIR__ . '/data/rpc-shape/attribute-valid';
        $this->analyse(
            [$this->fixtureDir . '/blockstudio/api/index.php'],
            []
        );
    }

    public function test_invalid_method_reported(): void
    {
        $this->fixtureDir = __DIR__ . '/data/rpc-shape/invalid-method';
        $errors = $this->gatherErrorMessages([$this->fixtureDir . '/blockstudio/api/index.php']);
        $this->assertContainsErrorMessage('invalid HTTP method', $errors);
    }

    public function test_invalid_public_value_reported(): void
    {
        $this->fixtureDir = __DIR__ . '/data/rpc-shape/invalid-public';
        $errors = $this->gatherErrorMessages([$this->fixtureDir . '/blockstudio/api/index.php']);
        $this->assertContainsErrorMessage('"public" must be bool or "open"', $errors);
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
