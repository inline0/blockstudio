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

use BlockstudioVendor\League\Uri\Contracts\UriInterface;
use BlockstudioVendor\ScssPhp\ScssPhp\Ast\Sass\Statement;
use BlockstudioVendor\ScssPhp\ScssPhp\Exception\SassFormatException;
use BlockstudioVendor\ScssPhp\ScssPhp\Logger\LoggerInterface;
use BlockstudioVendor\ScssPhp\ScssPhp\Parser\CssParser;
use BlockstudioVendor\ScssPhp\ScssPhp\Parser\SassParser;
use BlockstudioVendor\ScssPhp\ScssPhp\Parser\ScssParser;
use BlockstudioVendor\ScssPhp\ScssPhp\Syntax;
use BlockstudioVendor\ScssPhp\ScssPhp\Visitor\StatementVisitor;
use BlockstudioVendor\SourceSpan\FileSpan;
/**
 * A Sass stylesheet.
 *
 * This is the root Sass node. It contains top-level statements.
 *
 * @extends ParentStatement<Statement[]>
 *
 * @internal
 */
final class Stylesheet extends ParentStatement
{
    private readonly bool $plainCss;
    private readonly FileSpan $span;
    /**
     * @param Statement[] $children
     */
    public function __construct(array $children, FileSpan $span, bool $plainCss = \false)
    {
        $this->span = $span;
        $this->plainCss = $plainCss;
        parent::__construct($children);
    }
    public function isPlainCss(): bool
    {
        return $this->plainCss;
    }
    public function getSpan(): FileSpan
    {
        return $this->span;
    }
    public function accept(StatementVisitor $visitor)
    {
        return $visitor->visitStylesheet($this);
    }
    /**
     * @throws SassFormatException when parsing fails
     */
    public static function parse(string $contents, Syntax $syntax, ?LoggerInterface $logger = null, ?UriInterface $sourceUrl = null): self
    {
        return match ($syntax) {
            Syntax::SASS => self::parseSass($contents, $logger, $sourceUrl),
            Syntax::SCSS => self::parseScss($contents, $logger, $sourceUrl),
            Syntax::CSS => self::parseCss($contents, $logger, $sourceUrl),
        };
    }
    /**
     * @throws SassFormatException when parsing fails
     */
    public static function parseSass(string $contents, ?LoggerInterface $logger = null, ?UriInterface $sourceUrl = null): self
    {
        return (new SassParser($contents, $logger, $sourceUrl))->parse();
    }
    /**
     * @throws SassFormatException when parsing fails
     */
    public static function parseScss(string $contents, ?LoggerInterface $logger = null, ?UriInterface $sourceUrl = null): self
    {
        return (new ScssParser($contents, $logger, $sourceUrl))->parse();
    }
    /**
     * @throws SassFormatException when parsing fails
     */
    public static function parseCss(string $contents, ?LoggerInterface $logger = null, ?UriInterface $sourceUrl = null): self
    {
        return (new CssParser($contents, $logger, $sourceUrl))->parse();
    }
    public function __toString(): string
    {
        return implode(' ', $this->getChildren());
    }
}
