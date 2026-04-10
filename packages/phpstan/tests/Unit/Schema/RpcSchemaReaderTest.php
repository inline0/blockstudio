<?php

declare(strict_types=1);

namespace Blockstudio\PHPStan\Tests\Unit\Schema;

use Blockstudio\PHPStan\Schema\RpcSchemaReader;
use PHPUnit\Framework\TestCase;

final class RpcSchemaReaderTest extends TestCase
{
    private RpcSchemaReader $reader;
    private string $rpcDir;

    protected function setUp(): void
    {
        $this->reader = new RpcSchemaReader();
        $this->rpcDir = __DIR__ . '/../../data/rpc';
    }

    public function test_extracts_all_function_names(): void
    {
        $functions = $this->reader->getFunctions($this->rpcDir . '/valid.php');

        $this->assertNotNull($functions);
        $this->assertArrayHasKey('subscribe', $functions);
        $this->assertArrayHasKey('configured', $functions);
        $this->assertArrayHasKey('open_endpoint', $functions);
    }

    public function test_closure_function_has_callback_marker(): void
    {
        $functions = $this->reader->getFunctions($this->rpcDir . '/valid.php');

        $this->assertNotNull($functions);
        $this->assertSame(['callback' => true], $functions['subscribe']);
    }

    public function test_configured_function_extracts_methods(): void
    {
        $functions = $this->reader->getFunctions($this->rpcDir . '/valid.php');

        $this->assertNotNull($functions);
        $this->assertArrayHasKey('methods', $functions['configured']);
        $this->assertSame(['POST', 'GET'], $functions['configured']['methods']);
    }

    public function test_configured_function_extracts_public_bool(): void
    {
        $functions = $this->reader->getFunctions($this->rpcDir . '/valid.php');

        $this->assertNotNull($functions);
        $this->assertTrue($functions['configured']['public']);
    }

    public function test_open_function_extracts_string_public(): void
    {
        $functions = $this->reader->getFunctions($this->rpcDir . '/valid.php');

        $this->assertNotNull($functions);
        $this->assertSame('open', $functions['open_endpoint']['public']);
    }

    public function test_missing_file_returns_null(): void
    {
        $result = $this->reader->getFunctions($this->rpcDir . '/nonexistent.php');
        $this->assertNull($result);
    }

    public function test_caching_returns_same_data_on_repeat_calls(): void
    {
        $first = $this->reader->load($this->rpcDir . '/valid.php');
        $second = $this->reader->load($this->rpcDir . '/valid.php');

        $this->assertSame($first, $second);
    }
}
