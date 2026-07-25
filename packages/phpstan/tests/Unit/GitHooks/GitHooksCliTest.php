<?php

declare(strict_types=1);

namespace Blockstudio\PHPStan\Tests\Unit\GitHooks;

use Blockstudio\PHPStan\Theme\CommandResult;
use Blockstudio\PHPStan\Theme\CommandRunner;
use PHPUnit\Framework\TestCase;

final class GitHooksCliTest extends TestCase
{
    public function testHelpDocumentsTheAnalysisOnlyContract(): void
    {
        $result = $this->runCli(['--help']);

        self::assertSame(0, $result->exitCode);
        self::assertStringContainsString(
            'Blockstudio Git hooks',
            $result->stdout
        );
        self::assertStringContainsString(
            'analysis-only pre-commit hook',
            $result->stdout
        );
        self::assertStringNotContainsString('format', strtolower($result->stdout));
    }

    public function testUnknownCommandHasStableUsageExitCode(): void
    {
        $result = $this->runCli(['unknown']);

        self::assertSame(2, $result->exitCode);
        self::assertStringContainsString(
            'Unknown command: unknown.',
            $result->stderr
        );
    }

    /**
     * @param list<string> $arguments
     */
    private function runCli(array $arguments): CommandResult
    {
        return (new CommandRunner())->run(
            array_merge(
                [
                    PHP_BINARY,
                    dirname(__DIR__, 3) . '/bin/blockstudio-githooks',
                ],
                $arguments
            ),
            dirname(__DIR__, 3),
            20
        );
    }
}
