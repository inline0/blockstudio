<?php

declare(strict_types=1);

namespace Blockstudio\PHPStan\Theme;

/**
 * Runs an argv command without invoking a shell.
 */
final class CommandRunner
{
    /**
     * @param non-empty-list<string> $command
     */
    public function run(
        array $command,
        string $workingDirectory,
        int $timeoutSeconds
    ): CommandResult {
        $pipes = [];
        $process = @proc_open(
            $command,
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
            $workingDirectory,
            null,
            ['bypass_shell' => true]
        );

        if (!is_resource($process)) {
            return new CommandResult(
                127,
                '',
                'Unable to start the configured command.'
            );
        }

        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $stdout = '';
        $stderr = '';
        $startedAt = microtime(true);
        $timedOut = false;
        $exitCode = -1;

        while (true) {
            $stdout .= stream_get_contents($pipes[1]) ?: '';
            $stderr .= stream_get_contents($pipes[2]) ?: '';

            $status = proc_get_status($process);
            if (!$status['running']) {
                $exitCode = (int) $status['exitcode'];
                break;
            }

            if (microtime(true) - $startedAt >= max(1, $timeoutSeconds)) {
                $timedOut = true;
                proc_terminate($process);
                usleep(100000);
                $status = proc_get_status($process);
                if ($status['running']) {
                    proc_terminate($process, 9);
                }
                break;
            }

            usleep(10000);
        }

        $stdout .= stream_get_contents($pipes[1]) ?: '';
        $stderr .= stream_get_contents($pipes[2]) ?: '';
        fclose($pipes[1]);
        fclose($pipes[2]);

        $closedExitCode = proc_close($process);
        if ($exitCode < 0) {
            $exitCode = $closedExitCode >= 0 ? $closedExitCode : 1;
        }

        return new CommandResult(
            $exitCode,
            $stdout,
            $stderr,
            $timedOut
        );
    }
}
