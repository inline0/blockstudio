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
namespace BlockstudioVendor\ScssPhp\ScssPhp\Visitor;

use BlockstudioVendor\ScssPhp\ScssPhp\Ast\Css\CssAtRule;
use BlockstudioVendor\ScssPhp\ScssPhp\Ast\Css\CssComment;
use BlockstudioVendor\ScssPhp\ScssPhp\Ast\Css\CssDeclaration;
use BlockstudioVendor\ScssPhp\ScssPhp\Ast\Css\CssImport;
use BlockstudioVendor\ScssPhp\ScssPhp\Ast\Css\CssKeyframeBlock;
use BlockstudioVendor\ScssPhp\ScssPhp\Ast\Css\CssMediaRule;
use BlockstudioVendor\ScssPhp\ScssPhp\Ast\Css\CssStyleRule;
use BlockstudioVendor\ScssPhp\ScssPhp\Ast\Css\CssStylesheet;
use BlockstudioVendor\ScssPhp\ScssPhp\Ast\Css\CssSupportsRule;
/**
 * An interface for visitors that traverse CSS statements.
 *
 * @internal
 *
 * @template T
 * @template-extends ModifiableCssVisitor<T>
 */
interface CssVisitor extends ModifiableCssVisitor
{
    /**
     * @return T
     */
    public function visitCssAtRule(CssAtRule $node);
    /**
     * @return T
     */
    public function visitCssComment(CssComment $node);
    /**
     * @return T
     */
    public function visitCssDeclaration(CssDeclaration $node);
    /**
     * @return T
     */
    public function visitCssImport(CssImport $node);
    /**
     * @return T
     */
    public function visitCssKeyframeBlock(CssKeyframeBlock $node);
    /**
     * @return T
     */
    public function visitCssMediaRule(CssMediaRule $node);
    /**
     * @return T
     */
    public function visitCssStyleRule(CssStyleRule $node);
    /**
     * @return T
     */
    public function visitCssStylesheet(CssStylesheet $node);
    /**
     * @return T
     */
    public function visitCssSupportsRule(CssSupportsRule $node);
}
