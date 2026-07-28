<?php

declare(strict_types=1);

namespace Blockstudio\PHPStan\Type;

use Blockstudio\PHPStan\Reflection\BlockTemplateDetector;
use Blockstudio\PHPStan\Schema\BlockJsonReader;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Variable;
use PHPStan\Analyser\Scope;
use PHPStan\Type\ArrayType;
use PHPStan\Type\BooleanType;
use PHPStan\Type\ExpressionTypeResolverExtension;
use PHPStan\Type\MixedType;
use PHPStan\Type\StringType;
use PHPStan\Type\Type;

final class BlockTemplateVariableType implements ExpressionTypeResolverExtension
{
    private const ATTRIBUTE_VARIABLES = [
        'a' => true,
        'attributes' => true,
    ];

    private const ARRAY_VARIABLES = [
        'b' => true,
        'block' => true,
        'c' => true,
        'context' => true,
    ];

    private const STRING_VARIABLES = [
        'content' => true,
        'inner_blocks' => true,
        'islandPhase' => true,
    ];

    private const BOOLEAN_VARIABLES = [
        'isEditor' => true,
        'isPreview' => true,
        'isIsland' => true,
        'isIslandPlaceholder' => true,
        'isIslandFragment' => true,
    ];

    public function __construct(
        private readonly BlockTemplateDetector $detector,
        private readonly BlockJsonReader $reader
    ) {}

    public function getType(Expr $expr, Scope $scope): ?Type
    {
        if (!$expr instanceof Variable || !is_string($expr->name)) {
            return null;
        }

        $name = $expr->name;

        if (
            !isset(self::ATTRIBUTE_VARIABLES[$name])
            && !isset(self::ARRAY_VARIABLES[$name])
            && !isset(self::STRING_VARIABLES[$name])
            && !isset(self::BOOLEAN_VARIABLES[$name])
        ) {
            return null;
        }

        if ($scope->getFunction() !== null || $scope->isInAnonymousFunction()) {
            return null;
        }

        if ($scope->hasVariableType($name)->yes()) {
            return null;
        }

        $blockJson = $this->detector->getBlockJsonForTemplate($scope->getFile());
        if ($blockJson === null) {
            return null;
        }

        if (isset(self::ATTRIBUTE_VARIABLES[$name])) {
            return $this->attributeType($blockJson);
        }

        if (isset(self::ARRAY_VARIABLES[$name])) {
            return $this->openArrayType();
        }

        if (isset(self::STRING_VARIABLES[$name])) {
            return new StringType();
        }

        return new BooleanType();
    }

    private function attributeType(string $blockJsonPath): Type
    {
        if ($this->reader->getCustomFieldIssues($blockJsonPath) !== []) {
            return $this->openArrayType();
        }

        $keys = $this->reader->getAttributeKeys($blockJsonPath);
        if ($keys === null || $keys === []) {
            return $this->openArrayType();
        }

        return $this->reader->getAttributeType($blockJsonPath) ?? $this->openArrayType();
    }

    private function openArrayType(): Type
    {
        return new ArrayType(new StringType(), new MixedType());
    }
}
