<?php

declare(strict_types=1);

namespace Blockstudio\PHPStan\Agents;

/**
 * Reads what a preset actually enforces from the preset files themselves.
 *
 * The rule list is taken from the NEON include chain rather than restated, so a
 * layer that gains or loses a rule changes the generated contract with it.
 */
final class PresetContract
{
    private const LAYER_LABELS = [
        'extension' => 'base',
    ];

    private const RULE_CONTRACTS = [
        'Blockstudio\PHPStan\Rules\BlockAttributeAccessRule' => [
            'Every `$a[...]` key a PHP template reads must exist in the `block.json` beside it.',
        ],
        'Blockstudio\PHPStan\Rules\TemplateAttributeAccessRule' => [
            'Twig and Blade templates must read declared field keys too.',
        ],
        'Blockstudio\PHPStan\Rules\BlockTagRule' => [
            'A block tag must name a registered block and pass only attributes that block declares.',
        ],
        'Blockstudio\PHPStan\Rules\BlockJsonShapeRule' => [
            '`block.json` attributes must use a known field type, carry an `id` when the type stores a value, and give `select`, `radio`, and `checkbox` fields their options.',
        ],
        'Blockstudio\PHPStan\Rules\FieldJsonShapeRule' => [
            '`field.json` definitions must declare a name, valid attributes, and resolvable nested `custom/*` references.',
        ],
        'Blockstudio\PHPStan\Rules\ExtensionJsonShapeRule' => [
            'Extension JSON must name the block it extends and declare `blockstudio.extend`.',
        ],
        'Blockstudio\PHPStan\Rules\PageJsonShapeRule' => [
            'A page directory with an `index.php` needs a `page.json`, and that file must declare a title, a slug, and a valid `postStatus`.',
        ],
        'Blockstudio\PHPStan\Rules\DbSchemaShapeRule' => [
            '`db.php` schemas must declare a `fields` array of known field types.',
        ],
        'Blockstudio\PHPStan\Rules\RpcSchemaShapeRule' => [
            '`rpc.php` definitions must declare a valid HTTP method and a valid `public` value.',
        ],
        'Blockstudio\PHPStan\Rules\CronSchemaShapeRule' => [
            '`cron.php` tasks must declare a schedule and a callback.',
        ],
        'Blockstudio\PHPStan\Rules\BlockstudioJsonShapeRule' => [
            '`blockstudio.json` groups must use the nested object form, so `"tailwind": {"enabled": true}` rather than `"tailwind": true`.',
        ],
        'Blockstudio\PHPStan\Rules\SettingsPathRule' => [
            '`Settings::get()` paths must exist in the settings schema.',
        ],
        'Blockstudio\PHPStan\Rules\HookCallbackRule' => [
            '`blockstudio/*` filter and action names must exist.',
        ],
        'Blockstudio\PHPStan\Rules\ThemeProjectRule' => [
            'Every analysed root must exist and carry a `style.css` with a `Theme Name` header.',
            'Block assets are declared in `block.json`; block code must not call `wp_enqueue_script()` or `wp_enqueue_style()`.',
            'A block `style.scss` must scope its rules through `%selector%`, and assets referenced by `block.json` must exist.',
            'Every field must define a `default`, and repeaters must declare non-negative `min` and `max` bounds.',
        ],
        'Blockstudio\PHPStan\Rules\ExtremeThemeRule' => [
            'JavaScript and Interactivity API sources must parse, keep debug output, banned browser APIs, and leaked globals out, clean up their listeners, and respect reduced motion.',
            'Tailwind classes must compile, and unknown utilities or hard-coded values where a semantic token exists are reported.',
        ],
        'Blockstudio\PHPStan\Rules\ExtremeForbiddenFunctionRule' => [
            'Project PHP must not shell out or evaluate code: `exec()`, `passthru()`, `popen()`, `proc_open()`, `shell_exec()`, `system()`, and `eval()` are rejected.',
        ],
        'Blockstudio\PHPStan\Rules\ExtremeRawDatabaseWriteRule' => [
            'Raw `$wpdb->insert()`, `->update()`, `->delete()`, and `->query()` writes are rejected in favour of a bounded data API.',
        ],
        'Blockstudio\PHPStan\Rules\ExtremeOutputEscapingRule' => [
            'Echoed values must pass through an escaping function such as `esc_html()`, `esc_attr()`, `esc_url()`, or `wp_kses_post()`.',
        ],
        'Blockstudio\PHPStan\Rules\WordPressRenderRule' => [
            'A caller-supplied WordPress render probe must answer `{"ok":true}` before analysis passes.',
        ],
    ];

    /**
     * @param list<array{label: string, contracts: list<string>}> $layers
     * @param array<string, string> $parameters
     */
    private function __construct(
        private readonly string $preset,
        private readonly array $layers,
        private readonly array $parameters
    ) {}

    public static function forPreset(
        string $preset,
        ?string $packageRoot = null
    ): self {
        $packageRoot ??= dirname(__DIR__, 2);
        $file = rtrim(str_replace('\\', '/', $packageRoot), '/')
            . '/' . $preset . '.neon';

        if (!is_file($file)) {
            throw new \InvalidArgumentException(
                sprintf('Unknown preset "%s".', $preset)
            );
        }

        $layers = [];
        $parameters = [];
        self::read($file, $layers, $parameters);

        return new self($preset, $layers, $parameters);
    }

    public function preset(): string
    {
        return $this->preset;
    }

    /**
     * @return list<array{label: string, contracts: list<string>}>
     */
    public function layers(): array
    {
        return $this->layers;
    }

    /**
     * @return list<string>
     */
    public function strictness(): array
    {
        $lines = [];
        $level = $this->parameters['level'] ?? null;
        $checks = [];
        $analysers = [];

        foreach ($this->parameters as $name => $value) {
            if ($value !== 'true') {
                continue;
            }
            if (str_starts_with($name, 'check') || str_starts_with($name, 'report')) {
                $checks[] = $name;
                continue;
            }
            if (str_starts_with($name, 'blockstudioExtreme')) {
                $analysers[] = $name;
            }
        }

        sort($checks);
        sort($analysers);

        if ($level !== null) {
            $lines[] = sprintf('PHPStan runs at level `%s`.', $level);
        }
        if ($checks !== []) {
            $lines[] = sprintf(
                'Stricter PHPStan behaviour is on: %s.',
                self::inlineCode($checks)
            );
        }
        if ($analysers !== []) {
            $lines[] = sprintf(
                'Analysis reaches beyond PHP: %s.',
                self::inlineCode($analysers)
            );
        }

        return $lines;
    }

    /**
     * @return list<string>
     */
    public static function describedRules(): array
    {
        return array_keys(self::RULE_CONTRACTS);
    }

    /**
     * @param list<array{label: string, contracts: list<string>}> $layers
     * @param array<string, string> $parameters
     */
    private static function read(
        string $file,
        array &$layers,
        array &$parameters
    ): void {
        $contents = file_get_contents($file);
        if ($contents === false) {
            throw new \InvalidArgumentException(
                sprintf('Unable to read preset %s.', $file)
            );
        }

        foreach (self::listValues($contents, 'includes') as $include) {
            self::read(dirname($file) . '/' . $include, $layers, $parameters);
        }

        $parameters = array_merge($parameters, self::parameters($contents));

        $contracts = [];
        foreach (self::ruleClasses($contents) as $class) {
            if (!isset(self::RULE_CONTRACTS[$class])) {
                throw new \RuntimeException(
                    sprintf('Rule %s has no documented contract.', $class)
                );
            }
            foreach (self::RULE_CONTRACTS[$class] as $contract) {
                $contracts[] = $contract;
            }
        }

        if ($contracts === []) {
            return;
        }

        $stem = basename($file, '.neon');
        $layers[] = [
            'label' => self::LAYER_LABELS[$stem] ?? $stem,
            'contracts' => $contracts,
        ];
    }

    /**
     * @return list<string>
     */
    private static function ruleClasses(string $contents): array
    {
        preg_match_all(
            '/class:\s*(Blockstudio\\\\PHPStan\\\\Rules\\\\[A-Za-z0-9_]+)/',
            $contents,
            $matches
        );

        return array_values(array_unique($matches[1]));
    }

    /**
     * @return list<string>
     */
    private static function listValues(string $contents, string $key): array
    {
        $values = [];
        foreach (self::section($contents, $key) as $line) {
            if (preg_match('/^\s+-\s*(\S+)\s*$/', $line, $match) === 1) {
                $values[] = $match[1];
            }
        }

        return $values;
    }

    /**
     * @return array<string, string>
     */
    private static function parameters(string $contents): array
    {
        $parameters = [];
        foreach (self::section($contents, 'parameters') as $line) {
            if (preg_match('/^ {2}(\w+):\s*(\S.*)$/', $line, $match) === 1) {
                $parameters[$match[1]] = trim($match[2]);
            }
        }

        return $parameters;
    }

    /**
     * @return list<string>
     */
    private static function section(string $contents, string $key): array
    {
        $lines = preg_split('/\R/', $contents);
        if ($lines === false) {
            return [];
        }

        $collected = [];
        $inside = false;

        foreach ($lines as $line) {
            if (preg_match('/^(\S[^:]*):\s*$/', $line, $match) === 1) {
                $inside = $match[1] === $key;
                continue;
            }
            if ($inside && trim($line) !== '') {
                $collected[] = $line;
            }
        }

        return $collected;
    }

    /**
     * @param list<string> $values
     */
    private static function inlineCode(array $values): string
    {
        return implode(
            ', ',
            array_map(static fn(string $value): string => '`' . $value . '`', $values)
        );
    }
}
