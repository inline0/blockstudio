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

use BlockstudioVendor\ScssPhp\ScssPhp\Ast\Sass\Interpolation;
use BlockstudioVendor\ScssPhp\ScssPhp\Ast\Sass\Statement;
use BlockstudioVendor\ScssPhp\ScssPhp\Visitor\StatementVisitor;
use BlockstudioVendor\SourceSpan\FileSpan;
/**
 * A loud CSS-style comment.
 *
 * @internal
 */
final class LoudComment implements Statement
{
    private readonly Interpolation $text;
    public function __construct(Interpolation $text)
    {
        $this->text = $text;
    }
    public function getText(): Interpolation
    {
        return $this->text;
    }
    public function getSpan(): FileSpan
    {
        return $this->text->getSpan();
    }
    public function accept(StatementVisitor $visitor)
    {
        return $visitor->visitLoudComment($this);
    }
    public function __toString(): string
    {
        return (string) $this->text;
    }
}
