<?php

declare(strict_types=1);

namespace Blockstudio\PHPStan\Tests\Unit\Type;

use PHPStan\Testing\TypeInferenceTestCase;

final class BlockTemplateVariableTypeTest extends TypeInferenceTestCase
{
    /** @return string[] */
    public static function getAdditionalConfigFiles(): array
    {
        return [dirname(__DIR__, 3) . '/extension.neon'];
    }

    public function test_attributes_are_typed_from_block_json(): void
    {
        $this->assertTemplateTypes(
            __DIR__ . '/data/attributes/blockstudio/hero/index.php'
        );
    }

    public function test_attributes_fall_back_when_block_json_declares_none(): void
    {
        $this->assertTemplateTypes(
            __DIR__ . '/data/fallback/blockstudio/plain/index.php'
        );
    }

    public function test_template_assignments_keep_their_own_inference(): void
    {
        $this->assertTemplateTypes(
            __DIR__ . '/data/local/blockstudio/card/index.php'
        );
    }

    public function test_non_template_files_are_untouched(): void
    {
        $this->assertTemplateTypes(__DIR__ . '/data/non-template/helper.php');
    }

    private function assertTemplateTypes(string $file): void
    {
        foreach (self::gatherAssertTypes($file) as $assert) {
            $this->assertFileAsserts(...$assert);
        }
    }
}
