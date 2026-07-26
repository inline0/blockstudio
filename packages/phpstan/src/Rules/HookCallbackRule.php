<?php

declare(strict_types=1);

namespace Blockstudio\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Warns when add_filter/add_action misspells a known blockstudio/* hook.
 *
 * The inventory below is derived from every statically named hook the runtime
 * fires, and hooks whose name is assembled at runtime are matched by pattern.
 * A hook that resembles no known name is left alone, because this rule cannot
 * see the installed runtime and a newer or private hook is not an error.
 *
 * @implements Rule<FuncCall>
 */
final class HookCallbackRule implements Rule
{
    /** @var list<string> */
    private const KNOWN_HOOKS = [
        'blockstudio/admin/enabled',
        'blockstudio/assets',
        'blockstudio/assets/disable',
        'blockstudio/assets/enable',
        'blockstudio/assets/process/css/content',
        'blockstudio/assets/process/js/content',
        'blockstudio/assets/process/scss/importPaths',
        'blockstudio/assets/process/scss/import_paths',
        'blockstudio/assets/process/scss/prelude',
        'blockstudio/block_tags/allow',
        'blockstudio/block_tags/builders',
        'blockstudio/block_tags/deny',
        'blockstudio/block_tags/prefixes',
        'blockstudio/block_tags/renderers',
        'blockstudio/block_tags/tag_aliases',
        'blockstudio/blocks/attributes',
        'blockstudio/blocks/attributes/populate',
        'blockstudio/blocks/attributes/render',
        'blockstudio/blocks/components/inner_blocks/frontend/wrap',
        'blockstudio/blocks/components/inner_blocks/render',
        'blockstudio/blocks/components/innerblocks/frontend/wrap',
        'blockstudio/blocks/components/innerblocks/render',
        'blockstudio/blocks/components/rich_text/render',
        'blockstudio/blocks/components/richtext/render',
        'blockstudio/blocks/components/use_block_props/render',
        'blockstudio/blocks/components/useblockprops/render',
        'blockstudio/blocks/conditions',
        'blockstudio/blocks/meta',
        'blockstudio/blocks/populate',
        'blockstudio/blocks/render',
        'blockstudio/blocks/topology_refreshed',
        'blockstudio/buffer/output',
        'blockstudio/cache/context',
        'blockstudio/cache/dir',
        'blockstudio/cache/max_files_per_scope',
        'blockstudio/cache/outcome',
        'blockstudio/cache/site_key',
        'blockstudio/cache/watch_debounce',
        'blockstudio/canvas/documents',
        'blockstudio/canvas/inventory',
        'blockstudio/canvas/item_loaded',
        'blockstudio/canvas/source_compiled',
        'blockstudio/content/orphan_action',
        'blockstudio/cron',
        'blockstudio/database',
        'blockstudio/db/after_create',
        'blockstudio/db/after_delete',
        'blockstudio/db/after_update',
        'blockstudio/db/before_create',
        'blockstudio/db/before_delete',
        'blockstudio/db/before_update',
        'blockstudio/discovery/sources',
        'blockstudio/editor/canvas/body_class',
        'blockstudio/error/exception',
        'blockstudio/error/logged',
        'blockstudio/field_types',
        'blockstudio/fields',
        'blockstudio/fields/paths',
        'blockstudio/files/url',
        'blockstudio/generated_output/path',
        'blockstudio/init',
        'blockstudio/init/before',
        'blockstudio/islands/allowed',
        'blockstudio/islands/attributes',
        'blockstudio/islands/endpoint_url',
        'blockstudio/islands/fragment',
        'blockstudio/islands/is_island',
        'blockstudio/islands/loading',
        'blockstudio/islands/marker_attributes',
        'blockstudio/islands/marker_tag',
        'blockstudio/islands/max_per_request',
        'blockstudio/islands/mode',
        'blockstudio/islands/placeholder',
        'blockstudio/islands/registered',
        'blockstudio/islands/rendered',
        'blockstudio/islands/request_attributes',
        'blockstudio/islands/signature',
        'blockstudio/islands/signature_payload',
        'blockstudio/islands/verify_signature',
        'blockstudio/pages/allow_external_loader_path',
        'blockstudio/pages/collection_post_type_args',
        'blockstudio/pages/create_post_data',
        'blockstudio/pages/docs_allowed_html',
        'blockstudio/pages/layout_error',
        'blockstudio/pages/manifest_scan_interval',
        'blockstudio/pages/orphan_action',
        'blockstudio/pages/paths',
        'blockstudio/pages/post_created',
        'blockstudio/pages/post_updated',
        'blockstudio/pages/reconciled',
        'blockstudio/pages/serve_markdown',
        'blockstudio/pages/sync_engine_inputs',
        'blockstudio/pages/synced',
        'blockstudio/pages/template_candidates',
        'blockstudio/pages/topology_refreshed',
        'blockstudio/pages/update_post_data',
        'blockstudio/parser/element_mapping',
        'blockstudio/parser/renderers',
        'blockstudio/path',
        'blockstudio/patterns/paths',
        'blockstudio/patterns/registered',
        'blockstudio/performance/config',
        'blockstudio/performance/default_config',
        'blockstudio/performance/measurement_enabled',
        'blockstudio/render',
        'blockstudio/render/dependencies',
        'blockstudio/render/footer',
        'blockstudio/render/head',
        'blockstudio/rpc',
        'blockstudio/rpc/after_call',
        'blockstudio/rpc/before_call',
        'blockstudio/runtime/identity',
        'blockstudio/runtime/initialized',
        'blockstudio/settings/path',
        'blockstudio/site_templates/discovered',
        'blockstudio/site_templates/parser',
        'blockstudio/site_templates/part_paths',
        'blockstudio/site_templates/parts',
        'blockstudio/site_templates/paths',
        'blockstudio/site_templates/registered',
        'blockstudio/site_templates/source_compiled',
        'blockstudio/site_templates/template_candidates',
        'blockstudio/site_templates/template_paths',
        'blockstudio/site_templates/templates',
        'blockstudio/static_prerender/cacheable_html',
        'blockstudio/static_prerender/outcome',
        'blockstudio/static_prerender/public_urls',
        'blockstudio/static_prerender/render_internal',
        'blockstudio/static_prerender/request_bypass',
        'blockstudio/tailwind/cache_max_age',
        'blockstudio/tailwind/cache_max_files',
        'blockstudio/tailwind/css',
        'blockstudio/theme_defaults/sync_pages_in_development',
        'blockstudio/ui/directories',
        'blockstudio/ui/examples',
        'blockstudio/ui/inventory',
        'blockstudio/url',
    ];

    /** @var list<string> */
    private const KNOWN_HOOK_PATTERNS = [
        'blockstudio/settings/*',
        'blockstudio/performance/*',
        'blockstudio/ui/*/variants-style',
    ];

    public function getNodeType(): string
    {
        return FuncCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node->name instanceof Name) {
            return [];
        }

        $funcName = (string) $node->name;
        if (!in_array($funcName, ['add_filter', 'add_action'], true)) {
            return [];
        }

        $args = $node->getArgs();
        if (count($args) < 1 || !$args[0]->value instanceof String_) {
            return [];
        }

        $hook = $args[0]->value->value;
        if (!str_starts_with($hook, 'blockstudio/')) {
            return [];
        }

        if (self::isKnownHook($hook)) {
            return [];
        }

        $suggestion = $this->findSimilar($hook, self::KNOWN_HOOKS);
        if ($suggestion === null) {
            return [];
        }

        return [
            RuleErrorBuilder::message(
                sprintf('Unknown Blockstudio hook "%s". Did you mean "%s"?', $hook, $suggestion)
            )
                ->identifier('blockstudio.hook')
                ->build(),
        ];
    }

    public static function isKnownHook(string $hook): bool
    {
        if (in_array($hook, self::KNOWN_HOOKS, true)) {
            return true;
        }

        foreach (self::KNOWN_HOOK_PATTERNS as $pattern) {
            $expression = '#^' . str_replace('\*', '.*', preg_quote($pattern, '#')) . '$#';
            if (preg_match($expression, $hook) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $candidates
     */
    private function findSimilar(string $needle, array $candidates): ?string
    {
        $best = null;
        $bestDistance = PHP_INT_MAX;
        foreach ($candidates as $candidate) {
            $distance = levenshtein($needle, $candidate);
            if ($distance < $bestDistance && $distance <= 5) {
                $bestDistance = $distance;
                $best = $candidate;
            }
        }
        return $best;
    }
}
