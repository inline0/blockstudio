<?php
/**
 * Base Test Case for Blockstudio Unit Tests
 *
 * Provides Brain Monkey setup/teardown for WordPress function mocking.
 */

declare(strict_types=1);

namespace Blockstudio\Tests;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;
use Brain\Monkey;

abstract class TestCase extends PHPUnitTestCase
{
    /**
     * Set up Brain Monkey before each test.
     */
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        // Set up common WordPress function stubs
        $this->setUpWordPressFunctions();
    }

    /**
     * Tear down Brain Monkey after each test.
     */
    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    /**
     * Set up common WordPress function stubs.
     *
     * These are functions commonly used throughout the plugin.
     * Individual tests can override these as needed.
     */
    protected function setUpWordPressFunctions(): void
    {
        // Translation functions - return the string as-is
        Monkey\Functions\stubs([
            '__' => static fn($text, $domain = 'default') => $text,
            '_e' => static fn($text, $domain = 'default') => print($text),
            '_n' => static fn($single, $plural, $number, $domain = 'default') => $number === 1 ? $single : $plural,
            '_x' => static fn($text, $context, $domain = 'default') => $text,
            'esc_html__' => static fn($text, $domain = 'default') => $text,
            'esc_attr__' => static fn($text, $domain = 'default') => $text,
            'esc_html_e' => static fn($text, $domain = 'default') => print($text),
            'esc_attr_e' => static fn($text, $domain = 'default') => print($text),
        ]);

        // Escaping functions
        Monkey\Functions\stubs([
            'esc_html' => static fn($text) => htmlspecialchars($text, ENT_QUOTES, 'UTF-8'),
            'esc_attr' => static fn($text) => htmlspecialchars($text, ENT_QUOTES, 'UTF-8'),
            'esc_url' => static fn($url) => filter_var($url, FILTER_SANITIZE_URL),
            'esc_js' => static fn($text) => $text,
            'wp_kses_post' => static fn($text) => $text,
            'sanitize_text_field' => static fn($text) => trim(strip_tags($text)),
            'sanitize_title' => static fn($text) => strtolower(preg_replace('/[^a-z0-9-]/', '-', strtolower($text))),
        ]);

        // Path functions
        Monkey\Functions\stubs([
            'plugin_dir_path' => static fn($file) => dirname($file) . '/',
            'plugin_dir_url' => static fn($file) => 'https://example.com/wp-content/plugins/' . basename(dirname($file)) . '/',
            'plugins_url' => static fn($path = '', $file = '') => 'https://example.com/wp-content/plugins/' . $path,
            'trailingslashit' => static fn($path) => rtrim($path, '/\\') . '/',
            'untrailingslashit' => static fn($path) => rtrim($path, '/\\'),
            'wp_normalize_path' => static fn($path) => str_replace('\\', '/', $path),
        ]);

        // Common utility functions
        Monkey\Functions\stubs([
            'wp_json_encode' => static fn($data, $flags = 0) => json_encode($data, $flags),
            'wp_parse_args' => static fn($args, $defaults = []) => array_merge($defaults, (array) $args),
            'wp_list_pluck' => static fn($list, $field) => array_column($list, $field),
            'absint' => static fn($value) => abs((int) $value),
        ]);
    }

    /**
     * Get the path to a test fixture.
     */
    protected function getFixturePath(string $path = ''): string
    {
        return __DIR__ . '/Fixtures/' . ltrim($path, '/');
    }

    /**
     * Load a JSON fixture file.
     */
    protected function loadJsonFixture(string $path): array
    {
        $fullPath = $this->getFixturePath($path);
        if (!file_exists($fullPath)) {
            throw new \RuntimeException("Fixture not found: {$fullPath}");
        }

        $content = file_get_contents($fullPath);
        return json_decode($content, true) ?? [];
    }
}
