<?php

/**
 * SCSSPHP
 *
 * @copyright 2012-2020 Leaf Corcoran
 *
 * @license http://opensource.org/licenses/MIT MIT
 *
 * @link http://scssphp.github.io/scssphp
 */
namespace BlockstudioVendor\ScssPhp\ScssPhp\Ast\Sass\Statement;

use BlockstudioVendor\ScssPhp\ScssPhp\Ast\Sass\Statement;
use BlockstudioVendor\ScssPhp\ScssPhp\Visitor\StatementVisitor;
use BlockstudioVendor\SourceSpan\FileSpan;
/**
 * A silent Sass-style comment.
 *
 * @internal
 */
final class SilentComment implements Statement
{
    private readonly string $text;
    private readonly FileSpan $span;
    public function __construct(string $text, FileSpan $span)
    {
        $this->text = $text;
        $this->span = $span;
    }
    public function getText(): string
    {
        return $this->text;
    }
    public function getSpan(): FileSpan
    {
        return $this->span;
    }
    public function accept(StatementVisitor $visitor)
    {
        return $visitor->visitSilentComment($this);
    }
    public function __toString(): string
    {
        return $this->text;
    }
}
