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

use BlockstudioVendor\ScssPhp\ScssPhp\Value\SassBoolean;
use BlockstudioVendor\ScssPhp\ScssPhp\Value\SassCalculation;
use BlockstudioVendor\ScssPhp\ScssPhp\Value\SassColor;
use BlockstudioVendor\ScssPhp\ScssPhp\Value\SassFunction;
use BlockstudioVendor\ScssPhp\ScssPhp\Value\SassList;
use BlockstudioVendor\ScssPhp\ScssPhp\Value\SassMap;
use BlockstudioVendor\ScssPhp\ScssPhp\Value\SassMixin;
use BlockstudioVendor\ScssPhp\ScssPhp\Value\SassNumber;
use BlockstudioVendor\ScssPhp\ScssPhp\Value\SassString;
/**
 * An interface for visitors that traverse SassScript $values.
 *
 * @internal
 *
 * @template T
 */
interface ValueVisitor
{
    /**
     * @return T
     */
    public function visitBoolean(SassBoolean $value);
    /**
     * @return T
     */
    public function visitCalculation(SassCalculation $value);
    /**
     * @return T
     */
    public function visitColor(SassColor $value);
    /**
     * @return T
     */
    public function visitFunction(SassFunction $value);
    /**
     * @return T
     */
    public function visitMixin(SassMixin $value);
    /**
     * @return T
     */
    public function visitList(SassList $value);
    /**
     * @return T
     */
    public function visitMap(SassMap $value);
    /**
     * @return T
     */
    public function visitNull();
    /**
     * @return T
     */
    public function visitNumber(SassNumber $value);
    /**
     * @return T
     */
    public function visitString(SassString $value);
}
