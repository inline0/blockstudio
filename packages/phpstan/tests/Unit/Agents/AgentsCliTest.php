<?php

declare(strict_types=1);

namespace Blockstudio\PHPStan\Tests\Unit\Agents;

use Blockstudio\PHPStan\Agents\ContractDocument;
use Blockstudio\PHPStan\Theme\CommandResult;
use Blockstudio\PHPStan\Theme\CommandRunner;
use PHPUnit\Framework\TestCase;

final class AgentsCliTest extends TestCase
{
    use ProjectFixture;

    public function testHelpDocumentsTheOwnershipContract(): void
    {
        $result = $this->runCli(['--help']);

        self::assertSame(0, $result->exitCode);
        self::assertStringContainsString(
            'Blockstudio project contract',
            $result->stdout
        );
        self::assertStringContainsString('--force', $result->stdout);
        self::assertStringContainsString(
            'belongs to the author',
            $result->stdout
        );
    }

    public function testUnknownOptionHasStableUsageExitCode(): void
    {
        $result = $this->runCli(['--nope']);

        self::assertSame(2, $result->exitCode);
        self::assertStringContainsString('Unknown option: --nope.', $result->stderr);
    }

    public function testStdoutPrintsTheContractWithoutWriting(): void
    {
        $root = $this->theme();

        $result = $this->runCli(['--root', $root, '--stdout'], $root);

        self::assertSame(0, $result->exitCode);
        self::assertStringContainsString(ContractDocument::MARKER, $result->stdout);
        self::assertStringContainsString('Analysis runs the `extreme-theme`', $result->stdout);
        self::assertFileDoesNotExist($root . '/AGENTS.md');
    }

    public function testWriteThenCheckReportsACurrentContract(): void
    {
        $root = $this->theme();

        $written = $this->runCli(['--root', $root], $root);
        $checked = $this->runCli(['--root', $root, '--check'], $root);

        self::assertSame(0, $written->exitCode);
        self::assertStringContainsString('Blockstudio contract created:', $written->stdout);
        self::assertFileExists($root . '/AGENTS.md');
        self::assertSame(0, $checked->exitCode);
        self::assertStringContainsString('Blockstudio contract current:', $checked->stdout);
    }

    public function testCheckFailsWhenTheContractIsMissingOrStale(): void
    {
        $root = $this->theme();

        $missing = $this->runCli(['--root', $root, '--check'], $root);
        $this->runCli(['--root', $root], $root);
        $this->json($root . '/blocks/quote/block.json', ['name' => 'acme/quote']);
        $stale = $this->runCli(['--root', $root, '--check'], $root);

        self::assertSame(1, $missing->exitCode);
        self::assertSame(1, $stale->exitCode);
        self::assertStringContainsString(
            'Blockstudio contract is out of date',
            $stale->stderr
        );
    }

    public function testAUserOwnedContractIsRefused(): void
    {
        $root = $this->theme();
        file_put_contents($root . '/AGENTS.md', "# House rules\n");

        $refused = $this->runCli(['--root', $root], $root);
        $forced = $this->runCli(['--root', $root, '--force'], $root);

        self::assertSame(2, $refused->exitCode);
        self::assertStringContainsString(
            'Refusing to overwrite the user-owned file',
            $refused->stderr
        );
        self::assertSame(0, $forced->exitCode);
        self::assertStringContainsString(
            ContractDocument::MARKER,
            (string) file_get_contents($root . '/AGENTS.md')
        );
    }

    public function testAnExplicitConfigurationDecidesWhatIsDescribed(): void
    {
        $root = $this->theme();
        $configuration = $root . '/analysis/blockstudio.json';
        $this->json($configuration, ['phpstan' => ['preset' => 'base']]);

        $result = $this->runCli(
            ['--root=' . $root, '--config=' . $configuration, '--stdout'],
            $root
        );

        self::assertSame(0, $result->exitCode);
        self::assertStringContainsString(
            'Configuration: `analysis/blockstudio.json`',
            $result->stdout
        );
        self::assertStringContainsString(
            'Analysis runs the `base` preset.',
            $result->stdout
        );
        self::assertStringNotContainsString('## Enabled features', $result->stdout);
    }

    public function testAnAlternateOutputPathIsHonoured(): void
    {
        $root = $this->theme();

        $result = $this->runCli(
            ['--root', $root, '--output', $root . '/docs/CONTRACT.md'],
            $root
        );

        self::assertSame(0, $result->exitCode);
        self::assertFileExists($root . '/docs/CONTRACT.md');
        self::assertFileDoesNotExist($root . '/AGENTS.md');
    }

    /**
     * @param list<string> $arguments
     */
    private function runCli(
        array $arguments,
        ?string $workingDirectory = null
    ): CommandResult {
        return (new CommandRunner())->run(
            array_merge(
                [
                    PHP_BINARY,
                    dirname(__DIR__, 3) . '/bin/blockstudio-agents',
                ],
                $arguments
            ),
            $workingDirectory ?? dirname(__DIR__, 3),
            30
        );
    }
}
