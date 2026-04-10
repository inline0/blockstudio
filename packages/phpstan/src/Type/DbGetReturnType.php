<?php

declare(strict_types=1);

namespace Blockstudio\PHPStan\Type;

use Blockstudio\PHPStan\Schema\DbSchemaReader;
use Blockstudio\PHPStan\Schema\ProjectScanner;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\ParametersAcceptorSelector;
use PHPStan\Type\DynamicStaticMethodReturnTypeExtension;
use PHPStan\Type\Generic\GenericObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\NullType;

/**
 * Provides a typed Db<TRecord> instance from Db::get() calls.
 *
 * Reads the sibling db.php for the requested block, parses its fields,
 * and returns a generic Db type with the record shape filled in.
 */
final class DbGetReturnType implements DynamicStaticMethodReturnTypeExtension
{
    public function __construct(
        private readonly ProjectScanner $scanner,
        private readonly DbSchemaReader $dbReader
    ) {}

    public function getClass(): string
    {
        return \Blockstudio\Db::class;
    }

    public function isStaticMethodSupported(MethodReflection $methodReflection): bool
    {
        return $methodReflection->getName() === 'get';
    }

    public function getTypeFromStaticMethodCall(
        MethodReflection $methodReflection,
        StaticCall $methodCall,
        Scope $scope
    ): Type {
        $defaultReturn = ParametersAcceptorSelector::selectFromArgs(
            $scope,
            $methodCall->getArgs(),
            $methodReflection->getVariants()
        )->getReturnType();

        $args = $methodCall->getArgs();
        if (count($args) < 1 || !$args[0]->value instanceof String_) {
            return $defaultReturn;
        }

        $blockName = $args[0]->value->value;
        $dbPath = $this->scanner->findDbPhpByBlockName($blockName);
        if ($dbPath === null) {
            return $defaultReturn;
        }

        $recordType = $this->dbReader->getRecordType($dbPath);
        if ($recordType === null) {
            return $defaultReturn;
        }

        return TypeCombinator::union(
            new GenericObjectType('Blockstudio\\Db', [$recordType]),
            new NullType()
        );
    }
}
