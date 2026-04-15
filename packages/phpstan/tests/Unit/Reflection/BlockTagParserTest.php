<?php

declare(strict_types=1);

namespace Blockstudio\PHPStan\Tests\Unit\Reflection;

use Blockstudio\PHPStan\Reflection\BlockTagParser;
use PHPUnit\Framework\TestCase;

final class BlockTagParserTest extends TestCase
{
    private BlockTagParser $parser;

    protected function setUp(): void
    {
        $this->parser = new BlockTagParser();
    }

    public function test_parses_self_closing_bs_tag(): void
    {
        $tags = $this->parser->extractTags('<bs:mytheme-hero title="Hello" />');
        $this->assertCount(1, $tags);
        $this->assertSame('mytheme/hero', $tags[0]['name']);
        $this->assertSame('Hello', $tags[0]['attributes']['title']);
    }

    public function test_parses_self_closing_block_tag(): void
    {
        $tags = $this->parser->extractTags('<block name="core/separator" />');
        $this->assertCount(1, $tags);
        $this->assertSame('core/separator', $tags[0]['name']);
    }

    public function test_parses_block_tag_with_attributes(): void
    {
        $tags = $this->parser->extractTags('<block name="mytheme/card" title="Featured" class="big" />');
        $this->assertCount(1, $tags);
        $this->assertSame('mytheme/card', $tags[0]['name']);
        $this->assertSame('Featured', $tags[0]['attributes']['title']);
        $this->assertSame('big', $tags[0]['attributes']['class']);
    }

    public function test_parses_multiple_tags(): void
    {
        $content = '<bs:mytheme-hero title="A" /><block name="core/separator" /><bs:mytheme-card />';
        $tags = $this->parser->extractTags($content);
        $this->assertCount(3, $tags);
    }

    public function test_parses_container_bs_tag(): void
    {
        $tags = $this->parser->extractTags('<bs:mytheme-section class="wide">content</bs:mytheme-section>');
        $this->assertCount(1, $tags);
        $this->assertSame('mytheme/section', $tags[0]['name']);
    }

    public function test_handles_single_quoted_attributes(): void
    {
        $tags = $this->parser->extractTags("<bs:mytheme-hero title='Hello' />");
        $this->assertCount(1, $tags);
        $this->assertSame('Hello', $tags[0]['attributes']['title']);
    }

    public function test_handles_attributes_with_angle_brackets_in_quotes(): void
    {
        $tags = $this->parser->extractTags('<bs:mytheme-hero title="a > b" />');
        $this->assertCount(1, $tags);
        $this->assertSame('a > b', $tags[0]['attributes']['title']);
    }

    public function test_returns_correct_line_numbers(): void
    {
        $content = "line1\n<bs:mytheme-hero />\nline3\n<block name=\"core/group\" />";
        $tags = $this->parser->extractTags($content);
        $this->assertCount(2, $tags);
        $this->assertSame(2, $tags[0]['line']);
        $this->assertSame(4, $tags[1]['line']);
    }

    public function test_ignores_malformed_bs_tag_without_hyphen(): void
    {
        $tags = $this->parser->extractTags('<bs:nohyphen />');
        $this->assertCount(0, $tags);
    }

    public function test_ignores_block_tag_without_name(): void
    {
        $tags = $this->parser->extractTags('<block class="foo" />');
        $this->assertCount(0, $tags);
    }

    public function test_empty_content_returns_empty(): void
    {
        $tags = $this->parser->extractTags('');
        $this->assertCount(0, $tags);
    }

    public function test_no_tags_returns_empty(): void
    {
        $tags = $this->parser->extractTags('<div class="hero"><h1>Hello</h1></div>');
        $this->assertCount(0, $tags);
    }
}
