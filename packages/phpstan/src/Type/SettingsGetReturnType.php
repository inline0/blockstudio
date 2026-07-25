<?php

declare(strict_types=1);

namespace Blockstudio\PHPStan\Type;

use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\ParametersAcceptorSelector;
use PHPStan\Type\ArrayType;
use PHPStan\Type\BooleanType;
use PHPStan\Type\DynamicStaticMethodReturnTypeExtension;
use PHPStan\Type\IntegerType;
use PHPStan\Type\StringType;
use PHPStan\Type\Type;

/**
 * Provides a typed return value for Settings::get() based on a static
 * map of known settings paths.
 */
final class SettingsGetReturnType implements DynamicStaticMethodReturnTypeExtension
{
    /** @return array<string, Type> */
    private static function pathTypes(): array
    {
        return [
            'users/ids' => new ArrayType(new IntegerType(), new IntegerType()),
            'users/roles' => new ArrayType(new IntegerType(), new StringType()),
            'assets/enqueue' => new BooleanType(),
            'assets/reset/enabled' => new BooleanType(),
            'assets/reset/fullWidth' => new ArrayType(new IntegerType(), new StringType()),
            'assets/minify/css' => new BooleanType(),
            'assets/minify/js' => new BooleanType(),
            'assets/process/scss' => new BooleanType(),
            'assets/process/scssFiles' => new BooleanType(),
            'cache/enabled' => new BooleanType(),
            'cache/path' => new StringType(),
            'githooks/commit' => new BooleanType(),
            'phpstan/preset' => new StringType(),
            'phpstan/roots' => new ArrayType(new IntegerType(), new StringType()),
            'phpstan/excludePaths' => new ArrayType(new IntegerType(), new StringType()),
            'phpstan/maxFiles' => new IntegerType(),
            'themeDefaults/titleTag' => new BooleanType(),
            'themeDefaults/suppressDirectoryUpdates' => new BooleanType(),
            'themeDefaults/syncPagesInDevelopment' => new BooleanType(),
            'performance/profile' => new StringType(),
            'performance/wordpress/headNoise' => new BooleanType(),
            'performance/wordpress/embeds' => new BooleanType(),
            'performance/wordpress/xmlrpc' => new BooleanType(),
            'performance/wordpress/editor' => new BooleanType(),
            'performance/wordpress/frontendAssets' => new BooleanType(),
            'performance/wordpress/media' => new BooleanType(),
            'performance/wordpress/heartbeat' => new BooleanType(),
            'performance/preload/links' => new StringType(),
            'performance/media/lazy' => new BooleanType(),
            'performance/media/skeleton' => new BooleanType(),
            'performance/media/metadata' => new BooleanType(),
            'performance/media/rootMargin' => new StringType(),
            'performance/measurement/enabled' => new BooleanType(),
            'performance/measurement/queryMonitor' => new BooleanType(),
            'performance/measurement/headers' => new BooleanType(),
            'performance/measurement/timings' => new BooleanType(),
            'performance/staticPrerender/enabled' => new BooleanType(),
            'performance/staticPrerender/ttl' => new IntegerType(),
            'performance/staticPrerender/invalidate' => new StringType(),
            'performance/staticPrerender/earlyServe' => new BooleanType(),
            'performance/staticPrerender/serveLoggedIn' => new BooleanType(),
            'performance/staticPrerender/dynamicPaths' => new ArrayType(new IntegerType(), new StringType()),
            'performance/staticPrerender/warm/enabled' => new BooleanType(),
            'performance/staticPrerender/warm/interval' => new IntegerType(),
            'performance/staticPrerender/warm/concurrency' => new IntegerType(),
            'performance/staticPrerender/warm/transport' => new StringType(),
            'editor/formatOnSave' => new BooleanType(),
            'editor/assets' => new ArrayType(new IntegerType(), new StringType()),
            'editor/markup' => new StringType(),
            'tailwind/enabled' => new BooleanType(),
            'tailwind/config' => new StringType(),
            'blockEditor/disableLoading' => new BooleanType(),
            'blockEditor/enhance' => new BooleanType(),
            'blockEditor/cssClasses' => new ArrayType(new IntegerType(), new StringType()),
            'blockEditor/cssVariables' => new ArrayType(new IntegerType(), new StringType()),
            'ai/enableContextGeneration' => new BooleanType(),
            'blockTags/enabled' => new BooleanType(),
            'blockTags/allow' => new ArrayType(new IntegerType(), new StringType()),
            'blockTags/deny' => new ArrayType(new IntegerType(), new StringType()),
            'dev/grab/enabled' => new BooleanType(),
            'dev/canvas/enabled' => new BooleanType(),
            'dev/canvas/adminBar' => new BooleanType(),
            'dev/perf' => new BooleanType(),
        ];
    }

    public static function isKnownPath(string $path): bool
    {
        return array_key_exists($path, self::pathTypes());
    }

    /** @return list<string> */
    public static function getAllPaths(): array
    {
        return array_keys(self::pathTypes());
    }

    public function getClass(): string
    {
        return \Blockstudio\Settings::class;
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

        $path = $args[0]->value->value;
        $types = self::pathTypes();

        return $types[$path] ?? $defaultReturn;
    }
}
