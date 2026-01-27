<?php
/**
 * Tests for the Utils class.
 */

declare(strict_types=1);

namespace Blockstudio\Tests\Unit;

use Blockstudio\Tests\TestCase;
use Blockstudio\Utils;

class UtilsTest extends TestCase
{
    /**
     * Test attributes() returns empty string for empty data.
     */
    public function test_attributes_returns_empty_string_for_empty_data(): void
    {
        $result = Utils::attributes([]);

        $this->assertSame('', $result);
    }

    /**
     * Test attributes() generates data attributes.
     */
    public function test_attributes_generates_data_attributes(): void
    {
        $data = [
            'blockId' => 'abc123',
            'isActive' => 'true',
        ];

        $result = Utils::attributes($data);

        $this->assertStringContainsString('data-block-id="abc123"', $result);
        $this->assertStringContainsString('data-is-active="true"', $result);
    }

    /**
     * Test attributes() converts camelCase to kebab-case.
     */
    public function test_attributes_converts_camelcase_to_kebab(): void
    {
        $data = [
            'myCustomAttribute' => 'value',
        ];

        $result = Utils::attributes($data);

        $this->assertStringContainsString('data-my-custom-attribute="value"', $result);
    }

    /**
     * Test attributes() filters by allowed keys.
     */
    public function test_attributes_filters_by_allowed_keys(): void
    {
        $data = [
            'allowed' => 'yes',
            'notAllowed' => 'no',
        ];

        $result = Utils::attributes($data, ['allowed']);

        $this->assertStringContainsString('data-allowed="yes"', $result);
        $this->assertStringNotContainsString('not-allowed', $result);
    }

    /**
     * Test attributes() skips empty values.
     */
    public function test_attributes_skips_empty_values(): void
    {
        $data = [
            'filled' => 'value',
            'empty' => '',
            'nullValue' => null,
        ];

        $result = Utils::attributes($data);

        $this->assertStringContainsString('data-filled="value"', $result);
        $this->assertStringNotContainsString('data-empty', $result);
        $this->assertStringNotContainsString('data-null-value', $result);
    }

    /**
     * Test attributes() generates CSS variables when variables=true.
     */
    public function test_attributes_generates_css_variables(): void
    {
        $data = [
            'primaryColor' => '#ff0000',
            'fontSize' => '16px',
        ];

        $result = Utils::attributes($data, [], true);

        $this->assertStringContainsString('--primary-color: #ff0000;', $result);
        $this->assertStringContainsString('--font-size: 16px;', $result);
        $this->assertStringNotContainsString('data-', $result);
    }

    /**
     * Test attributes() extracts value from array with 'value' key.
     */
    public function test_attributes_extracts_value_from_array(): void
    {
        $data = [
            'setting' => ['value' => 'extracted'],
        ];

        $result = Utils::attributes($data);

        $this->assertStringContainsString('data-setting="extracted"', $result);
    }

    /**
     * Test attributes() JSON encodes arrays without 'value' key.
     */
    public function test_attributes_json_encodes_arrays(): void
    {
        $data = [
            'items' => ['one', 'two', 'three'],
        ];

        $result = Utils::attributes($data);

        // Should contain JSON-encoded array
        $this->assertStringContainsString('data-items=', $result);
        $this->assertStringContainsString('[', $result);
    }

    /**
     * Test consoleLog() outputs script tag with JSON data.
     */
    public function test_console_log_outputs_script(): void
    {
        $data = ['test' => 'value'];

        ob_start();
        Utils::consoleLog($data);
        $output = ob_get_clean();

        $this->assertStringContainsString('<script>', $output);
        $this->assertStringContainsString('console.log(', $output);
        $this->assertStringContainsString('{"test":"value"}', $output);
        $this->assertStringContainsString('</script>', $output);
    }
}
