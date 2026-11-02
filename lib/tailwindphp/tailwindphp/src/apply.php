<?php

declare (strict_types=1);
namespace BlockstudioVendor\TailwindPHP;

use function BlockstudioVendor\TailwindPHP\Ast\rule;
use function BlockstudioVendor\TailwindPHP\Compile\compileCandidates;
use BlockstudioVendor\TailwindPHP\DesignSystem\DesignSystem;
use function BlockstudioVendor\TailwindPHP\Walk\walk;
use BlockstudioVendor\TailwindPHP\Walk\WalkAction;
/**
 * Substitute @apply at-rules with actual utility declarations.
 *
 * Port of: packages/tailwindcss/src/apply.ts
 *
 * @port-deviation:tracking TypeScript uses Set<AstNode> for direct object references.
 * PHP uses string path keys (e.g., "0.1.2") since PHP arrays are value types.
 *
 * @port-deviation:sourcemaps TypeScript tracks source locations for @apply candidates
 * to enable accurate source map generation. PHP omits this.
 *
 * @port-deviation:errors TypeScript has detailed error messages for circular dependencies,
 * unknown utilities, missing variants, and missing @reference. PHP uses simpler error handling.
 *
 * @port-deviation:registration PHP registers @utility definitions inline during @apply processing
 * to ensure dependencies are resolved before dependents use them.
 *
 * This function handles @apply in two phases:
 * 1. Process @apply within @utility definitions (in topological order)
 * 2. Register the processed @utility definitions with the design system
 * 3. Process remaining @apply rules in the CSS
 *
 * @param array &$ast
 * @param DesignSystem $designSystem
 * @return int Features flags
 */
function substituteAtApply(array &$ast, DesignSystem $designSystem): int
{
    $features = FEATURE_NONE;
    // Wrap the whole AST in a root rule to make sure there is always a parent
    // available for `@apply` at-rules.
    $root = rule('&', $ast);
    // Track all nodes containing @apply (by their index path in the AST)
    $parentsWithApply = [];
    // Track all the dependencies of nodes (by path)
    $dependencies = [];
    // Track all @utility definitions by its root (name)
    $definitions = [];
    // Track @utility nodes by path for registration
    $utilityNodes = [];
    // First pass: collect all @utility definitions and @apply rules
    collectApplyInfo($root['nodes'], [], $parentsWithApply, $dependencies, $definitions, $utilityNodes, $designSystem, $features);
    // Topological sort before substituting @apply
    $seen = [];
    $sorted = [];
    $wip = [];
    $visit = null;
    $visit = function (string $pathKey, array $path = []) use (&$visit, &$seen, &$sorted, &$wip, &$dependencies, &$definitions) {
        if (isset($seen[$pathKey])) {
            return;
        }
        if (isset($wip[$pathKey])) {
            // Build a readable dependency chain for the error message
            $chain = array_merge($path, [$pathKey]);
            $chainStr = implode(' → ', $chain);
            throw new \Exception("Circular dependency detected in @apply: {$chainStr}");
        }
        $wip[$pathKey] = \true;
        foreach (array_keys($dependencies[$pathKey] ?? []) as $dependencyName) {
            foreach ($definitions[$dependencyName] ?? [] as $depPathKey) {
                $path[] = $pathKey;
                $visit($depPathKey, $path);
                array_pop($path);
            }
        }
        $seen[$pathKey] = \true;
        unset($wip[$pathKey]);
        $sorted[] = $pathKey;
    };
    foreach ($parentsWithApply as $pathKey => $value) {
        $visit((string) $pathKey);
    }
    // Track which @utility nodes have been processed
    $processedUtilities = [];
    // Process @apply in sorted order, interleaving substitution and registration
    // For @utility nodes: substitute @apply first (recursively), then register immediately
    // This ensures dependencies are registered before dependents try to use them
    foreach ($sorted as $pathKey) {
        // Recursively substitute every @apply in the node's subtree. Matching
        // apply.ts, each parent gets a full `walk()` over its children so nested
        // rules are handled in place; we cannot defer nested rules to their own
        // path entry because substituting a multi-declaration @apply shifts the
        // indices of the following siblings, invalidating their tracked paths.
        substituteApplyInNode($root['nodes'], $pathKey, $designSystem);
        if (isset($utilityNodes[$pathKey])) {
            // Register the utility immediately after substitution
            $node = getNodeAtPath($root['nodes'], (string) $pathKey);
            if ($node !== null) {
                registerCssUtility($node, $designSystem);
            }
            $processedUtilities[$pathKey] = \true;
        }
    }
    // Register any @utility nodes that weren't processed (those without @apply)
    foreach ($utilityNodes as $pathKey => $name) {
        if (!isset($processedUtilities[$pathKey])) {
            $node = getNodeAtPath($root['nodes'], (string) $pathKey);
            if ($node !== null) {
                registerCssUtility($node, $designSystem);
            }
        }
    }
    // Extract the processed nodes from the root wrapper
    $ast = $root['nodes'];
    return $features;
}
/**
 * Get a node at the given path in the AST.
 *
 * @param array &$ast
 * @param string $pathKey
 * @return array|null Returns the node by reference
 */
function &getNodeAtPath(array &$ast, string $pathKey): ?array
{
    $path = explode('.', $pathKey);
    $path = array_map('intval', $path);
    $null = null;
    $current =& $ast;
    foreach ($path as $i => $index) {
        if (!isset($current[$index])) {
            return $null;
        }
        if ($i === count($path) - 1) {
            return $current[$index];
        } else {
            if (!isset($current[$index]['nodes'])) {
                return $null;
            }
            $current =& $current[$index]['nodes'];
        }
    }
    return $null;
}
/**
 * Register a @utility node with the design system.
 *
 * @param array $node The @utility at-rule node (with @apply already resolved)
 * @param DesignSystem $designSystem
 */
function registerCssUtility(array $node, DesignSystem $designSystem): void
{
    if ($node['kind'] !== 'at-rule' || $node['name'] !== '@utility') {
        return;
    }
    // Delegate to createCssUtility so functional utilities get the full
    // `--value(…)` / `--modifier(…)` / `--default(…)` resolution engine.
    $register = createCssUtility($node);
    if ($register !== null) {
        $register($designSystem);
    }
}
/**
 * Deep clone a utility AST node.
 *
 * @param array $node The node to clone
 * @return array Cloned node
 */
function cloneUtilityNode(array $node): array
{
    $cloned = $node;
    if (isset($cloned['nodes'])) {
        $cloned['nodes'] = array_map(fn($child) => cloneUtilityNode($child), $cloned['nodes']);
    }
    return $cloned;
}
/**
 * Collect information about @apply rules in the AST.
 *
 * @param array &$ast The AST to walk (the nodes array)
 * @param array $currentPath Current path in the AST
 * @param array &$parentsWithApply Paths of nodes containing @apply
 * @param array &$dependencies Dependencies for each path
 * @param array &$definitions @utility definitions by name
 * @param array &$utilityNodes Map of path to utility name for @utility nodes
 * @param DesignSystem $designSystem
 * @param int &$features Feature flags
 */
function collectApplyInfo(array &$ast, array $currentPath, array &$parentsWithApply, array &$dependencies, array &$definitions, array &$utilityNodes, DesignSystem $designSystem, int &$features): void
{
    foreach ($ast as $index => &$node) {
        $nodePath = array_merge($currentPath, [$index]);
        $pathKey = implode('.', $nodePath);
        if (!isset($node['kind'])) {
            continue;
        }
        if ($node['kind'] === 'rule' || $node['kind'] === 'context') {
            // Check if this node contains @apply directly in its children
            $hasApply = \false;
            if (isset($node['nodes'])) {
                foreach ($node['nodes'] as $child) {
                    if (isset($child['kind']) && $child['kind'] === 'at-rule' && $child['name'] === '@apply') {
                        $hasApply = \true;
                        $features |= FEATURE_AT_APPLY;
                        foreach (resolveApplyDependencies($child, $designSystem) as $dependency) {
                            if (!isset($dependencies[$pathKey])) {
                                $dependencies[$pathKey] = [];
                            }
                            $dependencies[$pathKey][$dependency] = \true;
                            // Mark every ancestor rule that also uses @apply as
                            // depending on this utility, mirroring apply.ts's
                            // walk over ctx.path(). Because substitution is
                            // recursive, an ancestor's pass resolves this nested
                            // @apply, so the ancestor must be topologically
                            // sorted after the utility it depends on.
                            for ($ancestorDepth = count($nodePath) - 1; $ancestorDepth >= 1; $ancestorDepth--) {
                                $ancestorKey = implode('.', array_slice($nodePath, 0, $ancestorDepth));
                                if (isset($parentsWithApply[$ancestorKey])) {
                                    $dependencies[$ancestorKey][$dependency] = \true;
                                }
                            }
                        }
                    }
                }
            }
            if ($hasApply) {
                $parentsWithApply[$pathKey] = \true;
            }
            // Recurse into children
            if (isset($node['nodes'])) {
                collectApplyInfo($node['nodes'], $nodePath, $parentsWithApply, $dependencies, $definitions, $utilityNodes, $designSystem, $features);
            }
        } elseif ($node['kind'] === 'at-rule') {
            // Do not allow @apply rules inside @keyframes rules
            if ($node['name'] === '@keyframes') {
                if (isset($node['nodes'])) {
                    foreach ($node['nodes'] as $child) {
                        if (isset($child['kind']) && $child['kind'] === 'at-rule' && $child['name'] === '@apply') {
                            throw new \Exception('You cannot use `@apply` inside `@keyframes`.');
                        }
                    }
                }
                continue;
            }
            // @utility defines a utility
            if ($node['name'] === '@utility') {
                $name = preg_replace('/-\*$/', '', $node['params']);
                if (!isset($definitions[$name])) {
                    $definitions[$name] = [];
                }
                $definitions[$name][] = $pathKey;
                // Track this as a @utility node
                $utilityNodes[$pathKey] = $name;
                // Check for @apply inside @utility
                if (isset($node['nodes'])) {
                    $hasApply = \false;
                    $nodesToWalk = $node['nodes'];
                    walk($nodesToWalk, function (&$child) use (&$hasApply, &$dependencies, &$features, $pathKey, $designSystem) {
                        if ($child['kind'] === 'at-rule' && $child['name'] === '@apply') {
                            $hasApply = \true;
                            $features |= FEATURE_AT_APPLY;
                            foreach (resolveApplyDependencies($child, $designSystem) as $dependency) {
                                if (!isset($dependencies[$pathKey])) {
                                    $dependencies[$pathKey] = [];
                                }
                                $dependencies[$pathKey][$dependency] = \true;
                            }
                        }
                        return WalkAction::Continue;
                    });
                    if ($hasApply) {
                        $parentsWithApply[$pathKey] = \true;
                    }
                }
            }
            // Recurse into at-rule children
            if (isset($node['nodes'])) {
                collectApplyInfo($node['nodes'], $nodePath, $parentsWithApply, $dependencies, $definitions, $utilityNodes, $designSystem, $features);
            }
        }
    }
}
/**
 * Substitute @apply rules in a node at the given path.
 *
 * Recursively substitutes every @apply at-rule in the node's subtree via
 * walk(), so nested child rules are resolved in place. This mirrors apply.ts,
 * which runs a single walk() per sorted parent.
 *
 * @param array &$ast The AST (the nodes array, not wrapped)
 * @param string $pathKey The path to the node (e.g., "0" or "0.1.2")
 * @param DesignSystem $designSystem
 */
function substituteApplyInNode(array &$ast, string $pathKey, DesignSystem $designSystem): void
{
    $path = explode('.', $pathKey);
    $path = array_map('intval', $path);
    // Navigate to the node using the path
    $current =& $ast;
    foreach ($path as $i => $index) {
        if (!isset($current[$index])) {
            return;
        }
        if ($i === count($path) - 1) {
            $current =& $current[$index];
        } else {
            if (!isset($current[$index]['nodes'])) {
                return;
            }
            $current =& $current[$index]['nodes'];
        }
    }
    if (!isset($current['nodes'])) {
        return;
    }
    // Use walk to recursively find and replace all @apply rules in the subtree
    walk($current['nodes'], function (&$child) use ($designSystem) {
        if (!isset($child['kind']) || $child['kind'] !== 'at-rule' || $child['name'] !== '@apply') {
            return WalkAction::Continue;
        }
        // Categorize the applied names: dashed idents indicate CSS mixins
        // (https://drafts.csswg.org/css-mixins-1/#apply-rule)
        $parts = array_values(array_filter(preg_split('/\s+/', trim($child['params'])), fn($p) => $p !== ''));
        $normalIdents = [];
        $dashedIdents = [];
        foreach ($parts as $part) {
            if (str_starts_with($part, '--')) {
                $dashedIdents[] = $part;
            } else {
                $normalIdents[] = $part;
            }
        }
        if (!empty($dashedIdents)) {
            // An `@apply` consisting only of dashed idents is a CSS mixin;
            // these are not utilities and must be emitted literally.
            if (empty($normalIdents)) {
                return WalkAction::Skip;
            }
            // Mixing mixins and our `@apply` behavior is invalid; the rules
            // must be written separately.
            $list = implode(' ', $dashedIdents);
            throw new \Exception("You cannot use `@apply` with both mixins and utilities. Please move `@apply {$list}` into a separate rule.");
        }
        if (!empty($child['nodes']) && !empty($normalIdents)) {
            $list = implode(' ', $normalIdents);
            throw new \Exception("The rule `@apply {$list}` must not have a body.");
        }
        $newNodes = compileApplyAtRule($child, $designSystem);
        return WalkAction::Replace($newNodes);
    });
}
/**
 * Compile an @apply at-rule and return replacement nodes.
 *
 * @param array $node The @apply at-rule node
 * @param DesignSystem $designSystem
 * @return array The replacement nodes
 */
function compileApplyAtRule(array $node, DesignSystem $designSystem): array
{
    // Parse the candidates from @apply params
    $candidates = preg_split('/\s+/', trim($node['params']));
    $candidates = array_filter($candidates);
    if (empty($candidates)) {
        return [];
    }
    // Compile the candidates to CSS
    $compiled = compileCandidates($candidates, $designSystem, ['respectImportant' => \false]);
    // Collect the nodes to insert in place of the @apply rule
    $newNodes = [];
    foreach ($compiled['astNodes'] as $candidateNode) {
        if ($candidateNode['kind'] === 'rule') {
            // Insert the rule's children instead of the rule itself
            foreach ($candidateNode['nodes'] ?? [] as $nodeChild) {
                $newNodes[] = $nodeChild;
            }
        } else {
            $newNodes[] = $candidateNode;
        }
    }
    return $newNodes;
}
/**
 * Resolve dependencies from an @apply at-rule.
 *
 * @port-deviation:parsing TypeScript uses designSystem.parseCandidate() which handles
 * all candidate formats. PHP manually parses candidates to avoid caching issues when
 * utilities aren't registered yet.
 *
 * This extracts the base utility name from each candidate in the @apply params.
 * We don't use designSystem->parseCandidate here because that would cache results
 * before custom utilities are registered.
 *
 * @param array $node The @apply at-rule node
 * @param DesignSystem $designSystem
 * @return iterable<string> Dependency names
 */
function resolveApplyDependencies(array $node, DesignSystem $designSystem): iterable
{
    $candidates = preg_split('/\s+/', trim($node['params']));
    foreach ($candidates as $candidate) {
        if (empty($candidate)) {
            continue;
        }
        // Extract the base utility name without parsing through the design system
        // This avoids caching issues when utilities aren't registered yet
        // Remove leading ! (legacy important syntax)
        if (str_starts_with($candidate, '!')) {
            $candidate = substr($candidate, 1);
        }
        // Remove trailing ! (important syntax)
        if (str_ends_with($candidate, '!')) {
            $candidate = substr($candidate, 0, -1);
        }
        // Remove variants (everything before the last :)
        if (str_contains($candidate, ':')) {
            $parts = explode(':', $candidate);
            $candidate = array_pop($parts);
        }
        // Remove modifier (everything after /)
        if (str_contains($candidate, '/')) {
            $candidate = explode('/', $candidate)[0];
        }
        // Skip arbitrary properties like [color:red]
        if (str_starts_with($candidate, '[')) {
            continue;
        }
        // The root is the candidate name for static utilities,
        // or the part before the last hyphen for functional utilities
        // For dependency resolution, we just need the full base name
        yield $candidate;
    }
}
