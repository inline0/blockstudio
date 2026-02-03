<?php

declare (strict_types=1);
namespace BlockstudioVendor\TailwindPHP\Walk;

/**
 * AST Walk - Traversal utilities for the CSS AST.
 *
 * Port of: packages/tailwindcss/src/walk.ts
 *
 * @port-deviation:actions TypeScript uses enum for WalkAction.
 * PHP uses constants and WalkAction class with static helper methods.
 */
// Walk action kinds
const WALK_CONTINUE = 0;
const WALK_SKIP = 1;
const WALK_STOP = 2;
const WALK_REPLACE = 3;
const WALK_REPLACE_SKIP = 4;
const WALK_REPLACE_STOP = 5;
/**
 * WalkAction helper class.
 */
class WalkAction
{
    public const Continue = ['kind' => WALK_CONTINUE];
    public const Skip = ['kind' => WALK_SKIP];
    public const Stop = ['kind' => WALK_STOP];
    /**
     * Replace the current node with the given nodes and continue visiting replacements.
     *
     * @param array|array[] $nodes
     * @return array
     */
    public static function Replace(array $nodes): array
    {
        // If nodes is not a list of nodes, wrap it
        if (!empty($nodes) && !isset($nodes[0])) {
            $nodes = [$nodes];
        }
        return ['kind' => WALK_REPLACE, 'nodes' => $nodes];
    }
    /**
     * Replace the current node with the given nodes and skip visiting replacements.
     *
     * @param array|array[] $nodes
     * @return array
     */
    public static function ReplaceSkip(array $nodes): array
    {
        if (!empty($nodes) && !isset($nodes[0])) {
            $nodes = [$nodes];
        }
        return ['kind' => WALK_REPLACE_SKIP, 'nodes' => $nodes];
    }
    /**
     * Replace the current node with the given nodes and stop walking.
     *
     * @param array|array[] $nodes
     * @return array
     */
    public static function ReplaceStop(array $nodes): array
    {
        if (!empty($nodes) && !isset($nodes[0])) {
            $nodes = [$nodes];
        }
        return ['kind' => WALK_REPLACE_STOP, 'nodes' => $nodes];
    }
}
/**
 * Walk context passed to visitor callbacks.
 */
class VisitContext
{
    public ?array $parent = null;
    public int $depth = 0;
    private array $stack;
    public function __construct(array &$stack)
    {
        $this->stack =& $stack;
    }
    /**
     * Get the path from root to current node.
     *
     * @return array
     */
    public function path(): array
    {
        $path = [];
        for ($i = 1; $i < count($this->stack); $i++) {
            $parent = $this->stack[$i][2];
            if ($parent !== null) {
                $path[] = $parent;
            }
        }
        return $path;
    }
}
/**
 * Walk through an AST, visiting each node.
 *
 * @param array &$ast The AST to walk (modified in-place)
 * @param callable|array $hooks Either a function (enter callback) or array with 'enter' and/or 'exit' callbacks
 * @return void
 */
function walk(array &$ast, callable|array $hooks): void
{
    if (is_callable($hooks)) {
        walkImplementation($ast, $hooks);
    } else {
        walkImplementation($ast, $hooks['enter'] ?? null, $hooks['exit'] ?? null);
    }
}
/**
 * Internal walk implementation.
 *
 * @param array &$ast
 * @param callable|null $enter
 * @param callable|null $exit
 * @return void
 */
function walkImplementation(array &$ast, ?callable $enter = null, ?callable $exit = null): void
{
    // Stack format: [&nodes, offset, parent]
    // We need to use references carefully to allow in-place modifications
    $stack = [[&$ast, 0, null]];
    $ctx = new VisitContext($stack);
    while (count($stack) > 0) {
        $depth = count($stack) - 1;
        $frame =& $stack[$depth];
        $nodes =& $frame[0];
        $offset = $frame[1];
        $parent = $frame[2];
        // Done with this level
        if ($offset >= 0 && $offset >= count($nodes)) {
            array_pop($stack);
            continue;
        }
        // Also done if negative offset's absolute value exceeds array size
        if ($offset < 0 && ~$offset >= count($nodes)) {
            array_pop($stack);
            continue;
        }
        $ctx->parent = $parent;
        $ctx->depth = $depth;
        // Enter phase (offsets are non-negative)
        if ($offset >= 0) {
            // Pass node by reference to allow in-place modifications
            $node =& $nodes[$offset];
            $result = $enter !== null ? $enter($node, $ctx) : WalkAction::Continue;
            if ($result === null) {
                $result = WalkAction::Continue;
            }
            switch ($result['kind']) {
                case WALK_CONTINUE:
                    if (isset($nodes[$offset]['nodes']) && count($nodes[$offset]['nodes']) > 0) {
                        $stack[] = [&$nodes[$offset]['nodes'], 0, $nodes[$offset]];
                    }
                    $frame[1] = ~$offset;
                    // Prepare for exit phase, same offset
                    unset($node);
                    continue 2;
                case WALK_STOP:
                    return;
                // Stop immediately
                case WALK_SKIP:
                    $frame[1] = ~$offset;
                    // Prepare for exit phase, same offset
                    unset($node);
                    continue 2;
                case WALK_REPLACE:
                    unset($node);
                    array_splice($nodes, $offset, 1, $result['nodes']);
                    continue 2;
                // Re-process at same offset
                case WALK_REPLACE_STOP:
                    unset($node);
                    array_splice($nodes, $offset, 1, $result['nodes']);
                    return;
                // Stop immediately
                case WALK_REPLACE_SKIP:
                    unset($node);
                    array_splice($nodes, $offset, 1, $result['nodes']);
                    $frame[1] = $offset + count($result['nodes']);
                    // Advance to next sibling past replacements
                    continue 2;
                default:
                    throw new \Exception("Invalid WalkAction kind in enter: {$result['kind']}");
            }
        }
        // Exit phase for nodes[~offset]
        $index = ~$offset;
        // Two's complement to get original offset
        $node =& $nodes[$index];
        $result = $exit !== null ? $exit($node, $ctx) : WalkAction::Continue;
        if ($result === null) {
            $result = WalkAction::Continue;
        }
        switch ($result['kind']) {
            case WALK_CONTINUE:
                $frame[1] = $index + 1;
                // Advance to next sibling
                unset($node);
                continue 2;
            case WALK_STOP:
                return;
            // Stop immediately
            case WALK_REPLACE:
                unset($node);
                array_splice($nodes, $index, 1, $result['nodes']);
                $frame[1] = $index + count($result['nodes']);
                // Advance to next sibling past replacements
                continue 2;
            case WALK_REPLACE_STOP:
                unset($node);
                array_splice($nodes, $index, 1, $result['nodes']);
                return;
            // Stop immediately
            case WALK_REPLACE_SKIP:
                unset($node);
                array_splice($nodes, $index, 1, $result['nodes']);
                $frame[1] = $index + count($result['nodes']);
                // Advance to next sibling past replacements
                continue 2;
            default:
                throw new \Exception("Invalid WalkAction kind in exit: {$result['kind']}");
        }
    }
}
