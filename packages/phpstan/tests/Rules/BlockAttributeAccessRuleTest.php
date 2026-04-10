<?php

declare(strict_types=1);

namespace Blockstudio\PHPStan\Tests\Rules;

use Blockstudio\PHPStan\Reflection\BlockTemplateDetector;
use Blockstudio\PHPStan\Reflection\FieldTypeRegistry;
use Blockstudio\PHPStan\Rules\BlockAttributeAccessRule;
use Blockstudio\PHPStan\Schema\BlockJsonReader;
use Blockstudio\PHPStan\Schema\ProjectScanner;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

/**
 * @extends RuleTestCase<BlockAttributeAccessRule>
 */
final class BlockAttributeAccessRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        $scanner = new ProjectScanner(__DIR__ . '/data/blocks');
        $detector = new BlockTemplateDetector($scanner);
        $reader = new BlockJsonReader(new FieldTypeRegistry());

        return new BlockAttributeAccessRule($detector, $reader);
    }

    public function test_valid_field_access_passes(): void
    {
        $this->analyse(
            [__DIR__ . '/data/blocks/hero/index.php'],
            []
        );
    }

    public function test_unknown_field_reports_error(): void
    {
        $this->analyse(
            [__DIR__ . '/data/blocks/typo/index.php'],
            [
                [
                    'Field "tytle" does not exist in block.json (block: test/typo). Did you mean "title"?',
                    4,
                ],
            ]
        );
    }

    public function test_group_field_uses_flat_underscore_keys(): void
    {
        $this->analyse(
            [__DIR__ . '/data/blocks/group/index.php'],
            []
        );
    }

    public function test_repeater_field_uses_array_key(): void
    {
        $this->analyse(
            [__DIR__ . '/data/blocks/repeater/index.php'],
            []
        );
    }

    public function test_attributes_variable_also_supported(): void
    {
        $this->analyse(
            [__DIR__ . '/data/blocks/hero/attributes.php'],
            []
        );
    }

    public function test_non_template_file_skipped(): void
    {
        $this->analyse(
            [__DIR__ . '/data/non-template/random.php'],
            []
        );
    }

    public function test_dynamic_dim_skipped(): void
    {
        $this->analyse(
            [__DIR__ . '/data/blocks/hero/dynamic.php'],
            []
        );
    }
}
