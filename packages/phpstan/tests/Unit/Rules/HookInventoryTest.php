<?php

declare(strict_types=1);

namespace Blockstudio\PHPStan\Tests\Unit\Rules;

use Blockstudio\PHPStan\Rules\HookCallbackRule;
use PhpParser\Node;
use PhpParser\Node\Expr\BinaryOp\Concat;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;

/**
 * Keeps the hook inventory of HookCallbackRule derived from the runtime.
 *
 * Hook names assembled at runtime cannot be extracted as literals, so dynamic
 * families are matched through the rule's patterns, and the one name whose
 * family has a single member is declared below.
 */
final class HookInventoryTest extends TestCase
{
    /** @var list<string> */
    private const DYNAMIC_HOOKS = [
        'blockstudio/path',
    ];

    /** @var list<string> */
    private const FIRING_FUNCTIONS = [
        'apply_filters',
        'apply_filters_ref_array',
        'apply_filters_deprecated',
        'do_action',
        'do_action_ref_array',
        'do_action_deprecated',
    ];

    public function test_every_fired_hook_is_accepted_by_the_rule(): void
    {
        $fired = $this->firedHooks();
        $missing = array_values(
            array_filter(
                $fired['names'],
                static fn(string $hook): bool => !HookCallbackRule::isKnownHook($hook)
            )
        );

        $this->assertSame(
            [],
            $missing,
            sprintf(
                'HookCallbackRule does not accept %d hook(s) the runtime fires: %s',
                count($missing),
                implode(', ', $missing)
            )
        );
    }

    public function test_every_dynamic_hook_family_is_accepted_by_a_pattern(): void
    {
        $fired = $this->firedHooks();
        $unmatched = array_values(
            array_filter(
                $fired['patterns'],
                static fn(string $pattern): bool => $pattern !== 'blockstudio/*'
                    && !HookCallbackRule::isKnownHook(str_replace('*', 'example', $pattern))
            )
        );

        $this->assertSame(
            [],
            $unmatched,
            sprintf(
                'HookCallbackRule has no pattern for %d dynamic hook family/families: %s',
                count($unmatched),
                implode(', ', $unmatched)
            )
        );
    }

    public function test_the_inventory_lists_no_hook_the_runtime_never_fires(): void
    {
        $fired = $this->firedHooks();
        $known = (new ReflectionClass(HookCallbackRule::class))->getConstant('KNOWN_HOOKS');
        $this->assertIsArray($known);

        $stale = array_values(array_diff($known, $fired['names'], self::DYNAMIC_HOOKS));

        $this->assertSame(
            [],
            $stale,
            sprintf(
                'HookCallbackRule lists %d hook(s) the runtime never fires: %s',
                count($stale),
                implode(', ', $stale)
            )
        );
    }

    /**
     * @return array{names: list<string>, patterns: list<string>}
     */
    private function firedHooks(): array
    {
        $package = dirname(__DIR__, 3);
        $plugin = dirname($package, 2);
        $sources = [$plugin . '/blockstudio.php', $plugin . '/bootstrap.php'];

        foreach ($sources as $source) {
            if (!is_file($source)) {
                $this->markTestSkipped('Blockstudio runtime sources are not available.');
            }
        }
        if (!is_dir($plugin . '/includes')) {
            $this->markTestSkipped('Blockstudio runtime sources are not available.');
        }

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($plugin . '/includes', RecursiveDirectoryIterator::SKIP_DOTS)
        );
        foreach ($files as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $sources[] = $file->getPathname();
            }
        }
        sort($sources);

        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $visitor = new class (self::FIRING_FUNCTIONS) extends NodeVisitorAbstract {
            /** @var array<string, true> */
            public array $names = [];

            /** @var array<string, true> */
            public array $patterns = [];

            /**
             * @param list<string> $functions
             */
            public function __construct(private array $functions)
            {
            }

            public function enterNode(Node $node): null
            {
                if (!$node instanceof FuncCall || !$node->name instanceof Name) {
                    return null;
                }
                if (!in_array((string) $node->name, $this->functions, true)) {
                    return null;
                }

                $args = $node->getArgs();
                if ($args === []) {
                    return null;
                }

                $hook = $args[0]->value;
                if ($hook instanceof String_) {
                    if (str_starts_with($hook->value, 'blockstudio/')) {
                        $this->names[$hook->value] = true;
                    }

                    return null;
                }
                if (!$hook instanceof Concat) {
                    return null;
                }

                $pattern = (string) preg_replace('#\*+#', '*', $this->pattern($hook));
                if (str_starts_with($pattern, 'blockstudio/')) {
                    $this->patterns[$pattern] = true;
                }

                return null;
            }

            private function pattern(Node $node): string
            {
                if ($node instanceof Concat) {
                    return $this->pattern($node->left) . $this->pattern($node->right);
                }

                return $node instanceof String_ ? $node->value : '*';
            }
        };

        $traverser = new NodeTraverser();
        $traverser->addVisitor($visitor);

        foreach ($sources as $source) {
            $traverser->traverse($parser->parse((string) file_get_contents($source)) ?? []);
        }

        $names = array_keys($visitor->names);
        $patterns = array_keys($visitor->patterns);
        sort($names);
        sort($patterns);

        return [
            'names' => $names,
            'patterns' => $patterns,
        ];
    }
}
