<?php

declare(strict_types=1);

namespace Blockstudio\PHPStan\Tests\Unit\Theme;

use Blockstudio\PHPStan\Theme\CommandRunner;
use PHPUnit\Framework\TestCase;

final class CommandRunnerTest extends TestCase
{
    public function test_captures_success_and_failure_without_a_shell(): void
    {
        $runner = new CommandRunner();
        $success = $runner->run(
            [PHP_BINARY, '-r', 'fwrite(STDOUT, "ok");'],
            sys_get_temp_dir(),
            5
        );

        $this->assertSame(0, $success->exitCode);
        $this->assertSame('ok', $success->stdout);
        $this->assertFalse($success->timedOut);

        $failure = $runner->run(
            [PHP_BINARY, '-r', 'fwrite(STDERR, "bad"); exit(7);'],
            sys_get_temp_dir(),
            5
        );

        $this->assertSame(7, $failure->exitCode);
        $this->assertSame('bad', $failure->stderr);
        $this->assertFalse($failure->timedOut);
    }

    public function test_enforces_a_bounded_timeout(): void
    {
        $runner = new CommandRunner();
        $result = $runner->run(
            [PHP_BINARY, '-r', 'sleep(5);'],
            sys_get_temp_dir(),
            1
        );

        $this->assertTrue($result->timedOut);
    }
}
