<?php

declare(strict_types=1);

namespace Blockstudio\PHPStan\Rules;

use Blockstudio\PHPStan\Theme\CommandRunner;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\FileNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Executes an explicit live-render probe and consumes its JSON result.
 *
 * @implements Rule<FileNode>
 */
final class WordPressRenderRule implements Rule
{
    private bool $processed = false;

    /**
     * @param list<string> $command
     */
    public function __construct(
        private readonly array $command,
        private readonly string $workingDirectory,
        private readonly int $timeoutSeconds,
        private readonly CommandRunner $runner
    ) {}

    public function getNodeType(): string
    {
        return FileNode::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if ($this->processed || $this->command === []) {
            return [];
        }
        $this->processed = true;

        if (!is_dir($this->workingDirectory)) {
            return [
                $this->error(
                    'Live render working directory does not exist: '
                    . $this->workingDirectory,
                    $scope->getFile()
                ),
            ];
        }

        $result = $this->runner->run(
            $this->command,
            $this->workingDirectory,
            $this->timeoutSeconds
        );

        if ($result->timedOut) {
            return [
                $this->error(
                    sprintf(
                        'Live render command timed out after %d seconds.',
                        max(1, $this->timeoutSeconds)
                    ),
                    $scope->getFile()
                ),
            ];
        }

        if ($result->exitCode !== 0) {
            $detail = trim($result->stderr);
            return [
                $this->error(
                    'Live render command failed with exit code '
                    . $result->exitCode
                    . ($detail !== '' ? ': ' . $this->oneLine($detail) : '.'),
                    $scope->getFile()
                ),
            ];
        }

        $payload = json_decode(trim($result->stdout), true);
        if (!is_array($payload) || !is_bool($payload['ok'] ?? null)) {
            return [
                $this->error(
                    'Live render command must print a JSON object with a boolean "ok" field.',
                    $scope->getFile()
                ),
            ];
        }

        if ($payload['ok']) {
            return [];
        }

        $file = is_string($payload['file'] ?? null)
            ? $payload['file']
            : $scope->getFile();
        $line = is_int($payload['line'] ?? null)
            ? max(1, $payload['line'])
            : 1;
        $message = is_string($payload['message'] ?? null)
            && trim($payload['message']) !== ''
                ? trim($payload['message'])
                : 'Live WordPress render reported a failure.';

        return [$this->error($message, $file, $line)];
    }

    private function error(
        string $message,
        string $file,
        int $line = 1
    ): \PHPStan\Rules\IdentifierRuleError {
        return RuleErrorBuilder::message($message)
            ->identifier('blockstudio.wordpress.render')
            ->file($file)
            ->line($line)
            ->build();
    }

    private function oneLine(string $value): string
    {
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;
        return substr(trim($value), 0, 500);
    }
}
