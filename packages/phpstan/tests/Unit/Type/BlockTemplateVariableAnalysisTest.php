<?php

declare(strict_types=1);

namespace Blockstudio\PHPStan\Tests\Unit\Type;

use Blockstudio\PHPStan\Theme\CommandRunner;
use PHPUnit\Framework\TestCase;

final class BlockTemplateVariableAnalysisTest extends TestCase
{
    /** @var array<string, array{stdout: string, messages: array<string, list<array{line: int, identifier: string, message: string}>>}> */
    private static array $analysis = [];

    public function test_mixed_findings_are_gone_from_a_block_template(): void
    {
        $analysis = $this->analyseFixture();

        $this->assertSame(
            [],
            $analysis['messages']['blockstudio/button/index.php'] ?? [],
            'Block template should be free of findings.'
        );
        $this->assertStringNotContainsString('on mixed', $analysis['stdout']);
        $this->assertStringNotContainsString(
            'Cannot cast mixed to string',
            $analysis['stdout']
        );
    }

    public function test_attribute_shape_reaches_the_analysis(): void
    {
        $analysis = $this->analyseFixture();
        $messages = $analysis['messages']['blockstudio/typo/index.php'] ?? [];
        $identifiers = array_column($messages, 'identifier');

        $this->assertContains('nullCoalesce.offset', $identifiers);
        $this->assertStringContainsString(
            "Offset 'tytle' on array{title: string}",
            $analysis['stdout']
        );
    }

    /**
     * @return array{stdout: string, messages: array<string, list<array{line: int, identifier: string, message: string}>>}
     */
    private function analyseFixture(): array
    {
        if (self::$analysis !== []) {
            return self::$analysis['result'];
        }

        $package = dirname(__DIR__, 3);
        $root = $package . '/tests/fixtures/template-variables';
        $result = (new CommandRunner())->run(
            [
                PHP_BINARY,
                $package . '/bin/blockstudio-phpstan',
                '--preset',
                'base',
                '--root',
                $root,
                '--',
                '--no-progress',
                '--error-format=json',
            ],
            $package,
            180
        );

        $decoded = json_decode($result->stdout, true);
        $this->assertIsArray(
            $decoded,
            "Analysis did not return JSON:\n" . $result->stdout . $result->stderr
        );

        $messages = [];
        foreach ($decoded['files'] ?? [] as $file => $data) {
            $relative = substr((string) $file, strlen($root) + 1);
            $messages[$relative] = array_map(
                static fn(array $message): array => [
                    'line' => (int) $message['line'],
                    'identifier' => (string) ($message['identifier'] ?? ''),
                    'message' => (string) $message['message'],
                ],
                $data['messages']
            );
        }

        self::$analysis['result'] = [
            'stdout' => $result->stdout,
            'messages' => $messages,
        ];

        return self::$analysis['result'];
    }
}
