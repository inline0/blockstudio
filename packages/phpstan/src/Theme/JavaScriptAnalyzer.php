<?php

declare(strict_types=1);

namespace Blockstudio\PHPStan\Theme;

use Phasis\Ast\Expression\Literal;
use Phasis\Ast\Expression\Property;
use Phasis\Ast\Expression\TemplateLiteral;
use Phasis\Ast\Jsx\JsxAttribute;
use Phasis\Ast\Jsx\JsxElement;
use Phasis\Ast\Jsx\JsxExpressionContainer;
use Phasis\Ast\Jsx\JsxIdentifier;
use Phasis\Ast\Node;
use Phasis\Ast\Walker;
use Phasis\Exceptions\SyntaxError;
use Phasis\Parser\Jsx\JsxMode;
use Phasis\Parser\Jsx\JsxOptions;
use Phasis\Parser\Parser;

final class JavaScriptAnalyzer
{
    private const KNOWN_INTERACTIVITY_IMPORTS = [
        'getContext',
        'getElement',
        'getServerContext',
        'setServerContext',
        'splitTask',
        'store',
        'withScope',
    ];

    /**
     * @return list<Diagnostic>
     */
    public function analyse(ProjectScanner $scanner): array
    {
        $diagnostics = [];
        $handlers = $this->handlerIndex($scanner);
        $stores = $this->storeIndex($scanner);
        $componentDirectives = $this->componentDirectives($scanner);
        $templateNamespaces = $this->templateNamespaces(
            $scanner,
            $componentDirectives
        );
        $templateDataTokens = $this->templateDataTokens($scanner);

        foreach ($scanner->files(['js']) as $file) {
            $contents = $scanner->read($file);
            if ($this->isMinified($file, $contents)) {
                continue;
            }

            $diagnostics = array_merge(
                $diagnostics,
                $this->syntaxDiagnostics($file, $contents),
                $this->debugDiagnostics($file, $contents),
                $this->bannedApiDiagnostics($file, $contents),
                $this->importSpecifierDiagnostics($file, $contents),
                $this->leakedGlobalDiagnostics($file, $contents),
                $this->rootGuardDiagnostics($file, $contents),
                $this->listenerDiagnostics($file, $contents),
                $this->motionDiagnostics($file, $contents),
                $this->initShapeDiagnostics($file, $contents),
                $this->domContractDiagnostics(
                    $file,
                    $contents,
                    $templateDataTokens
                ),
                $this->interactivityDiagnostics($scanner, $file, $contents),
                $this->derivedBindingDiagnostics(
                    $scanner,
                    $file,
                    $contents,
                    $componentDirectives
                )
            );
        }

        foreach ($this->templateDirectives($scanner) as $directive) {
            $directory = dirname($directive['file']);
            $available = $handlers[$directory][$directive['group']] ?? [];
            if (isset($available[$directive['handler']])) {
                continue;
            }

            $diagnostics[] = new Diagnostic(
                sprintf(
                    'Interactivity directive references missing %s handler "%s".',
                    $directive['group'],
                    $directive['handler']
                ),
                'blockstudio.interactivity.handler',
                $directive['file'],
                $directive['line']
            );
        }

        return array_merge(
            $diagnostics,
            $this->interactivityTemplateDiagnostics($scanner, $stores),
            $this->interactivityComponentDiagnostics(
                $stores,
                $componentDirectives
            ),
            $this->interactivityOrphanDiagnostics(
                $stores,
                $templateNamespaces
            )
        );
    }

    /**
     * @return list<Diagnostic>
     */
    private function syntaxDiagnostics(string $file, string $contents): array
    {
        $goal = preg_match('/^[ \t]*(?:import|export)\b/m', $contents) === 1
            ? 'module'
            : 'script';

        try {
            Parser::parseSource($contents, $goal);
            return [];
        } catch (SyntaxError $primary) {
            $fallback = $goal === 'module' ? 'script' : 'module';
            try {
                Parser::parseSource($contents, $fallback);
                return [];
            } catch (SyntaxError) {
                return [
                    new Diagnostic(
                        'JavaScript syntax error: ' . $primary->getMessage(),
                        'blockstudio.javascript.syntax',
                        $file,
                        $primary->location !== null
                            ? max(1, $primary->location->line)
                            : 1
                    ),
                ];
            }
        }
    }

    /**
     * @return list<Diagnostic>
     */
    private function debugDiagnostics(string $file, string $contents): array
    {
        $diagnostics = [];
        if (
            preg_match_all(
                '/\b(?:console\.[A-Za-z_$][A-Za-z0-9_$]*|debugger\b|(?:(?:window|globalThis)\.)?(?:alert|confirm|prompt)\s*\()/',
                $contents,
                $matches,
                PREG_OFFSET_CAPTURE
            ) === false
        ) {
            return [];
        }

        foreach ($matches[0] as $match) {
            $diagnostics[] = new Diagnostic(
                sprintf('Remove debug output "%s" from shipped JavaScript.', $match[0]),
                'blockstudio.javascript.debugOutput',
                $file,
                ProjectScanner::lineForOffset($contents, $match[1])
            );
        }

        return $diagnostics;
    }

    /**
     * @return list<Diagnostic>
     */
    private function bannedApiDiagnostics(string $file, string $contents): array
    {
        $patterns = [
            '/\bdocument\.(?:write|writeln)\s*\(/' => 'document.write()',
            '/\beval\s*\(/' => 'eval()',
            '/\b(?:new\s+)?Function\s*\(/' => 'Function()',
            '/\b(?:setTimeout|setInterval)\s*\(\s*([\'"])/' => 'string timer',
            '/\.open\s*\(\s*([\'"])[^\'"]+\1\s*,[^,]+,\s*false\s*\)/s' => 'synchronous XMLHttpRequest',
            '/(?<![A-Za-z0-9_$])(?:jQuery|\$)\s*\(/' => 'jQuery',
            '/\.innerHTML\s*=/' => 'innerHTML assignment',
        ];
        $diagnostics = [];

        foreach ($patterns as $pattern => $label) {
            if (
                preg_match_all($pattern, $contents, $matches, PREG_OFFSET_CAPTURE)
                === false
            ) {
                continue;
            }

            foreach ($matches[0] as $match) {
                $diagnostics[] = new Diagnostic(
                    sprintf('Avoid unsafe browser API %s.', $label),
                    'blockstudio.javascript.bannedApi',
                    $file,
                    ProjectScanner::lineForOffset($contents, $match[1])
                );
            }
        }

        return $diagnostics;
    }

    /**
     * @return list<Diagnostic>
     */
    private function importSpecifierDiagnostics(
        string $file,
        string $contents
    ): array {
        $diagnostics = [];
        $imports = [];
        $patterns = [
            '/\b(?:import|export)\s+(?:[^\'";]+?\s+from\s+)?([\'"])([^\'"]+)\1/',
            '/\bimport\s*\(\s*([\'"])([^\'"]+)\1\s*\)/',
        ];

        foreach ($patterns as $pattern) {
            if (
                preg_match_all(
                    $pattern,
                    $contents,
                    $matches,
                    PREG_OFFSET_CAPTURE
                ) === false
            ) {
                continue;
            }

            foreach ($matches[2] as $match) {
                $imports[$match[1]] = $match[0];
                if ($this->isAllowedImportSpecifier($match[0])) {
                    continue;
                }

                $diagnostics[] = new Diagnostic(
                    sprintf(
                        'Import "%s" must be @wordpress/interactivity or an exact npm:<package>@<version> specifier.',
                        $match[0]
                    ),
                    'blockstudio.javascript.importSpecifier',
                    $file,
                    ProjectScanner::lineForOffset($contents, $match[1])
                );
            }
        }

        if (
            preg_match_all(
                '/\bimport\s*\(\s*(?![\'"])/',
                $contents,
                $dynamic,
                PREG_OFFSET_CAPTURE
            ) !== false
        ) {
            foreach ($dynamic[0] as $match) {
                $diagnostics[] = new Diagnostic(
                    'Dynamic import() requires a pinned literal specifier.',
                    'blockstudio.javascript.importSpecifier',
                    $file,
                    ProjectScanner::lineForOffset($contents, $match[1])
                );
            }
        }

        if (
            in_array('@wordpress/interactivity', $imports, true)
            && count(array_unique($imports)) > 1
        ) {
            foreach ($imports as $offset => $specifier) {
                if ($specifier === '@wordpress/interactivity') {
                    continue;
                }

                $diagnostics[] = new Diagnostic(
                    sprintf(
                        'Interactivity modules must not mix runtime import "%s" with @wordpress/interactivity.',
                        $specifier
                    ),
                    'blockstudio.interactivity.moduleImport',
                    $file,
                    ProjectScanner::lineForOffset($contents, $offset)
                );
            }
        }

        return $diagnostics;
    }

    private function isAllowedImportSpecifier(string $specifier): bool
    {
        return $specifier === '@wordpress/interactivity'
            || preg_match(
                '/^npm:(?:@[a-z0-9][a-z0-9._-]*\/)?[a-z0-9][a-z0-9._-]*@\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/',
                $specifier
            ) === 1;
    }

    /**
     * @return list<Diagnostic>
     */
    private function leakedGlobalDiagnostics(string $file, string $contents): array
    {
        $diagnostics = [];
        $isModule = preg_match('/^[ \t]*(?:import|export)\b/m', $contents) === 1;
        if (
            !$isModule
            && preg_match_all(
                '/^(?:var|let|const|function|class)\b/m',
                $contents,
                $topLevel,
                PREG_OFFSET_CAPTURE
            ) !== false
        ) {
            foreach ($topLevel[0] as $match) {
                $diagnostics[] = new Diagnostic(
                    'Top-level script declarations leak into global scope; wrap the script or use a module.',
                    'blockstudio.javascript.leakedGlobal',
                    $file,
                    ProjectScanner::lineForOffset($contents, $match[1])
                );
            }
        }

        if (
            preg_match_all(
                '/^[ \t]*(?!const\b|let\b|var\b|function\b|class\b|import\b|export\b)([A-Za-z_$][A-Za-z0-9_$]*)\s*=(?!=)/m',
                $contents,
                $matches,
                PREG_OFFSET_CAPTURE
            ) === false
        ) {
            return $diagnostics;
        }

        foreach ($matches[1] as $match) {
            if (in_array($match[0], ['exports', 'module'], true)) {
                continue;
            }

            $diagnostics[] = new Diagnostic(
                sprintf('Assignment to undeclared global "%s".', $match[0]),
                'blockstudio.javascript.leakedGlobal',
                $file,
                ProjectScanner::lineForOffset($contents, $match[1])
            );
        }

        return $diagnostics;
    }

    /**
     * @return list<Diagnostic>
     */
    private function rootGuardDiagnostics(string $file, string $contents): array
    {
        if (
            preg_match_all(
                '/\b(?:const|let|var)\s+([A-Za-z_$][A-Za-z0-9_$]*)\s*=\s*(?:document|[^;\n]+)\.querySelector(?:All)?\s*\([^;]+;/',
                $contents,
                $matches,
                PREG_OFFSET_CAPTURE
            ) === false
        ) {
            return [];
        }

        $diagnostics = [];
        foreach ($matches[1] as $match) {
            $name = preg_quote($match[0], '/');
            $hasGuard = preg_match(
                '/\bif\s*\(\s*!\s*' . $name . '\s*\)|\b' . $name . '\?\./',
                $contents
            ) === 1;
            $isUsed = preg_match('/\b' . $name . '\s*\./', $contents) === 1;
            if (!$hasGuard && $isUsed) {
                $diagnostics[] = new Diagnostic(
                    sprintf('Guard query result "%s" before dereferencing it.', $match[0]),
                    'blockstudio.javascript.rootGuard',
                    $file,
                    ProjectScanner::lineForOffset($contents, $match[1])
                );
            }
        }

        return $diagnostics;
    }

    /**
     * @return list<Diagnostic>
     */
    private function listenerDiagnostics(string $file, string $contents): array
    {
        if (
            !str_contains($contents, 'addEventListener')
            || str_contains($contents, 'removeEventListener')
            || preg_match('/\bonce\s*:\s*true\b/', $contents) === 1
        ) {
            return [];
        }

        $relative = str_replace('\\', '/', $file);
        if (
            !str_contains($relative, '/blocks/')
            && !str_contains($relative, '/blockstudio/')
        ) {
            return [];
        }

        $offset = strpos($contents, 'addEventListener');
        return [
            new Diagnostic(
                'Block JavaScript event listeners need cleanup or an explicit once option.',
                'blockstudio.javascript.listenerCleanup',
                $file,
                ProjectScanner::lineForOffset(
                    $contents,
                    $offset !== false ? $offset : 0
                )
            ),
        ];
    }

    /**
     * @return list<Diagnostic>
     */
    private function motionDiagnostics(string $file, string $contents): array
    {
        if (
            (
                !str_contains($contents, 'requestAnimationFrame')
                && preg_match('/\.animate\s*\(/', $contents) !== 1
            )
            || str_contains($contents, 'prefers-reduced-motion')
            || str_contains($contents, 'matchMedia')
        ) {
            return [];
        }

        $offset = strpos($contents, 'requestAnimationFrame');
        if ($offset === false) {
            $offset = strpos($contents, '.animate');
        }

        return [
            new Diagnostic(
                'Animation code must respect the prefers-reduced-motion media query.',
                'blockstudio.javascript.reducedMotion',
                $file,
                ProjectScanner::lineForOffset(
                    $contents,
                    $offset !== false ? $offset : 0
                )
            ),
        ];
    }

    /**
     * @return list<Diagnostic>
     */
    private function initShapeDiagnostics(
        string $file,
        string $contents
    ): array {
        if (
            preg_match_all(
                '/\baddEventListener\s*\(\s*([\'"])DOMContentLoaded\1/',
                $contents,
                $matches,
                PREG_OFFSET_CAPTURE
            ) === false
            || $matches[0] === []
        ) {
            return [];
        }

        $diagnostics = [];
        foreach (array_slice($matches[0], 1) as $match) {
            $diagnostics[] = new Diagnostic(
                'Register one DOMContentLoaded entry point per script.',
                'blockstudio.javascript.initShape',
                $file,
                ProjectScanner::lineForOffset($contents, $match[1])
            );
        }

        if (!str_contains($contents, 'document.readyState')) {
            $diagnostics[] = new Diagnostic(
                'Guard DOMContentLoaded startup with document.readyState for late-loaded scripts.',
                'blockstudio.javascript.initShape',
                $file,
                ProjectScanner::lineForOffset(
                    $contents,
                    $matches[0][0][1]
                )
            );
        }

        return $diagnostics;
    }

    /**
     * @param array<string, true> $templateDataTokens
     * @return list<Diagnostic>
     */
    private function domContractDiagnostics(
        string $file,
        string $contents,
        array $templateDataTokens
    ): array {
        if (
            preg_match_all(
                '/\b(?:querySelector(?:All)?|closest|matches)\s*\(\s*([\'"])([^\'"]*)\1/',
                $contents,
                $selectors,
                PREG_OFFSET_CAPTURE
            ) === false
        ) {
            return [];
        }

        $diagnostics = [];
        foreach ($selectors[2] as $selector) {
            if (
                preg_match_all(
                    '/\[\s*(data-[a-z0-9][a-z0-9-]*)/i',
                    $selector[0],
                    $tokens
                ) === false
            ) {
                continue;
            }

            foreach ($tokens[1] as $token) {
                $token = strtolower($token);
                if (isset($templateDataTokens[$token])) {
                    continue;
                }

                $diagnostics[] = new Diagnostic(
                    sprintf(
                        'Selector token "[%s]" does not exist in an analysed template.',
                        $token
                    ),
                    'blockstudio.javascript.domContract',
                    $file,
                    ProjectScanner::lineForOffset(
                        $contents,
                        $selector[1]
                    )
                );
            }
        }

        return $diagnostics;
    }

    /**
     * @return list<Diagnostic>
     */
    private function interactivityDiagnostics(
        ProjectScanner $scanner,
        string $file,
        string $contents
    ): array {
        $usesStore = preg_match('/\bstore\s*\(/', $contents) === 1;
        $imports = $this->interactivityImports($contents);
        if (!$usesStore && $imports === []) {
            return [];
        }

        $diagnostics = [];
        foreach ($imports as $name => $offset) {
            if (!in_array($name, self::KNOWN_INTERACTIVITY_IMPORTS, true)) {
                $diagnostics[] = new Diagnostic(
                    sprintf('Unknown @wordpress/interactivity import "%s".', $name),
                    'blockstudio.interactivity.import',
                    $file,
                    ProjectScanner::lineForOffset($contents, $offset)
                );
            }
        }

        if ($usesStore && !isset($imports['store'])) {
            $offset = strpos($contents, 'store');
            $diagnostics[] = new Diagnostic(
                'store() must be imported from @wordpress/interactivity.',
                'blockstudio.interactivity.import',
                $file,
                ProjectScanner::lineForOffset(
                    $contents,
                    $offset !== false ? $offset : 0
                )
            );
        }

        if (
            preg_match(
                '/\bstore\s*\(\s*([\'"])([^\'"]*)\1/',
                $contents,
                $namespaceMatch,
                PREG_OFFSET_CAPTURE
            ) === 1
        ) {
            $namespace = trim($namespaceMatch[2][0]);
            $expected = $this->siblingBlockName($scanner, $file);
            $validNamespace = preg_match(
                '/^[a-z0-9-]+\/[a-z0-9-]+$/',
                $namespace
            ) === 1;
            if (
                !$validNamespace
                || ($expected !== null && $namespace !== $expected)
            ) {
                $message = !$validNamespace
                    ? 'Interactivity store namespace must be a literal "vendor/block" value using lowercase segments.'
                    : sprintf('Interactivity store namespace must match block name "%s".', $expected);
                $diagnostics[] = new Diagnostic(
                    $message,
                    'blockstudio.interactivity.namespace',
                    $file,
                    ProjectScanner::lineForOffset(
                        $contents,
                        $namespaceMatch[2][1]
                    )
                );
            }
        } elseif ($usesStore) {
            $offset = strpos($contents, 'store');
            $diagnostics[] = new Diagnostic(
                'Interactivity store namespace must be a string literal.',
                'blockstudio.interactivity.namespace',
                $file,
                ProjectScanner::lineForOffset(
                    $contents,
                    $offset !== false ? $offset : 0
                )
            );
        }

        if (
            $usesStore
            && str_contains($contents, 'document.querySelector')
            && !str_contains($contents, 'getElement')
        ) {
            $offset = strpos($contents, 'document.querySelector');
            $diagnostics[] = new Diagnostic(
                'Interactivity handlers should scope DOM access through getElement().ref.',
                'blockstudio.interactivity.scopedDom',
                $file,
                ProjectScanner::lineForOffset(
                    $contents,
                    $offset !== false ? $offset : 0
                )
            );
        }

        $stateBody = $this->objectBody($contents, 'state');
        if (
            $stateBody !== null
            && preg_match(
                '/\b([A-Za-z_$][A-Za-z0-9_$]*)\s*:\s*(?:async\s*)?(?:function\b|\([^)]*\)\s*=>|[A-Za-z_$][A-Za-z0-9_$]*\s*=>)/',
                $stateBody['body'],
                $match,
                PREG_OFFSET_CAPTURE
            ) === 1
        ) {
            $diagnostics[] = new Diagnostic(
                sprintf(
                    'Derived state "%s" must use a getter so dependency tracking remains reactive.',
                    $match[1][0]
                ),
                'blockstudio.interactivity.derivedState',
                $file,
                ProjectScanner::lineForOffset(
                    $contents,
                    $stateBody['offset'] + $match[1][1]
                )
            );
        }

        return $diagnostics;
    }

    /**
     * @param list<array{
     *   file:string,
     *   line:int,
     *   name:string,
     *   value:?string,
     *   namespace:?string
     * }> $componentDirectives
     * @return list<Diagnostic>
     */
    private function derivedBindingDiagnostics(
        ProjectScanner $scanner,
        string $file,
        string $contents,
        array $componentDirectives
    ): array {
        if (preg_match('/\bstore\s*\(/', $contents) !== 1) {
            return [];
        }

        $attributes = [];
        $classes = [];
        $namespaces = [];
        if (
            preg_match_all(
                '/\bstore\s*\(\s*([\'"])([a-z0-9-]+\/[a-z0-9-]+)\1/',
                $contents,
                $registered
            ) !== false
        ) {
            $namespaces = array_fill_keys($registered[2], true);
        }

        foreach ($componentDirectives as $directive) {
            if (
                $directive['namespace'] === null
                || !isset($namespaces[$directive['namespace']])
                || preg_match(
                    '/^data-wp-(bind|class)--([a-z0-9-]+)$/',
                    $directive['name'],
                    $binding
                ) !== 1
            ) {
                continue;
            }

            if ($binding[1] === 'class') {
                $classes[$binding[2]] = true;
            } else {
                $attributes[$binding[2]] = true;
            }
        }

        $directory = dirname($file) . '/';
        foreach (
            $scanner->files(['php', 'twig', 'blade.php', 'html'])
            as $template
        ) {
            if (!str_starts_with($template, $directory)) {
                continue;
            }

            $markup = $scanner->read($template);
            if (
                preg_match_all(
                    '/\bdata-wp-(bind|class)--([a-z0-9-]+)/i',
                    $markup,
                    $bindings
                ) === false
            ) {
                continue;
            }

            foreach ($bindings[2] as $index => $name) {
                if (strtolower($bindings[1][$index]) === 'class') {
                    $classes[$name] = true;
                } else {
                    $attributes[$name] = true;
                }
            }
        }

        $diagnostics = [];
        foreach ($attributes as $name => $_) {
            if (
                preg_match(
                    '/\b(?:setAttribute|toggleAttribute)\s*\(\s*([\'"])'
                    . preg_quote($name, '/')
                    . '\1/',
                    $contents,
                    $match,
                    PREG_OFFSET_CAPTURE
                ) !== 1
            ) {
                continue;
            }

            $diagnostics[] = new Diagnostic(
                sprintf(
                    'Store code mutates bound attribute "%s"; expose it through derived state instead.',
                    $name
                ),
                'blockstudio.interactivity.derivedState',
                $file,
                ProjectScanner::lineForOffset(
                    $contents,
                    $match[0][1]
                )
            );
        }

        foreach ($classes as $name => $_) {
            if (
                preg_match(
                    '/\bclassList\.(?:add|remove|toggle)\s*\(\s*([\'"])'
                    . preg_quote($name, '/')
                    . '\1/',
                    $contents,
                    $match,
                    PREG_OFFSET_CAPTURE
                ) !== 1
            ) {
                continue;
            }

            $diagnostics[] = new Diagnostic(
                sprintf(
                    'Store code mutates bound class "%s"; expose it through derived state instead.',
                    $name
                ),
                'blockstudio.interactivity.derivedState',
                $file,
                ProjectScanner::lineForOffset(
                    $contents,
                    $match[0][1]
                )
            );
        }

        return $diagnostics;
    }

    /**
     * @return array<string, int>
     */
    private function interactivityImports(string $contents): array
    {
        if (
            preg_match_all(
                '/import\s*\{([^}]+)\}\s*from\s*[\'"]@wordpress\/interactivity[\'"]/',
                $contents,
                $matches,
                PREG_OFFSET_CAPTURE
            ) === false
        ) {
            return [];
        }

        $imports = [];
        foreach ($matches[1] as $match) {
            foreach (explode(',', $match[0]) as $part) {
                $part = trim($part);
                if ($part === '') {
                    continue;
                }

                $name = preg_split('/\s+as\s+/', $part)[0] ?? '';
                if ($name !== '') {
                    $offset = strpos($contents, $name, $match[1]);
                    $imports[$name] = $offset !== false ? $offset : $match[1];
                }
            }
        }

        return $imports;
    }

    private function siblingBlockName(
        ProjectScanner $scanner,
        string $file
    ): ?string {
        $blockJson = dirname($file) . '/block.json';
        if (!is_file($blockJson)) {
            return null;
        }

        $data = $scanner->jsonObject($blockJson);
        return is_string($data['name'] ?? null) && $data['name'] !== ''
            ? $data['name']
            : null;
    }

    /**
     * @return array<string, true>
     */
    private function templateDataTokens(ProjectScanner $scanner): array
    {
        $tokens = [];
        foreach (
            $scanner->files(['php', 'twig', 'blade.php', 'html'])
            as $file
        ) {
            $contents = $scanner->read($file);
            if (
                preg_match_all(
                    '/\b(data-[a-z0-9][a-z0-9-]*)\b/i',
                    $contents,
                    $matches
                ) === false
            ) {
                continue;
            }

            foreach ($matches[1] as $token) {
                $tokens[strtolower($token)] = true;
            }
        }

        foreach ($scanner->files(['tsx', 'jsx']) as $file) {
            foreach ($this->componentDataTokens($file, $scanner->read($file)) as $token) {
                $tokens[$token] = true;
            }
        }

        return $tokens;
    }

    /**
     * @return list<string>
     */
    private function componentDataTokens(string $file, string $contents): array
    {
        if (stripos($contents, 'data-') === false) {
            return [];
        }

        try {
            $program = Parser::parseSource(
                $contents,
                'module',
                new JsxOptions(JsxMode::Preserve),
                $file
            );
        } catch (SyntaxError) {
            return $this->dataTokensInText($contents);
        }

        $tokens = [];
        Walker::walk(
            $program,
            function (Node $node) use (&$tokens): null {
                $name = match (true) {
                    $node instanceof JsxAttribute
                        && $node->name instanceof JsxIdentifier
                        => $node->name->name,
                    $node instanceof Property
                        && $node->key instanceof Literal
                        && is_string($node->key->value)
                        => $node->key->value,
                    default => null,
                };
                if ($name !== null) {
                    array_push($tokens, ...$this->dataTokensInText($name));
                }

                return null;
            }
        );

        return array_values(array_unique($tokens));
    }

    /**
     * @return list<string>
     */
    private function dataTokensInText(string $text): array
    {
        if (
            preg_match_all(
                '/(?<![a-z0-9-])(data-[a-z0-9][a-z0-9-]*)/i',
                $text,
                $matches
            ) === false
        ) {
            return [];
        }

        return array_values(array_unique(array_map(
            'strtolower',
            $matches[1]
        )));
    }

    /**
     * @return array<string, array{
     *   file:string,
     *   line:int,
     *   directory:string,
     *   groups:array<string, array<string, true>>
     * }>
     */
    private function storeIndex(ProjectScanner $scanner): array
    {
        $stores = [];
        foreach ($scanner->files(['js']) as $file) {
            $contents = $scanner->read($file);
            if (
                $this->isMinified($file, $contents)
                || preg_match_all(
                    '/\bstore\s*\(\s*([\'"])([a-z0-9-]+\/[a-z0-9-]+)\1/',
                    $contents,
                    $matches,
                    PREG_OFFSET_CAPTURE
                ) === false
            ) {
                continue;
            }

            $groups = [];
            foreach (['state', 'actions', 'callbacks'] as $group) {
                $groups[$group] = $this->propertyNames(
                    $this->objectBody($contents, $group)
                );
            }

            foreach ($matches[2] as $match) {
                $stores[$match[0]] = [
                    'file' => $file,
                    'line' => ProjectScanner::lineForOffset(
                        $contents,
                        $match[1]
                    ),
                    'directory' => dirname($file),
                    'groups' => $groups,
                ];
            }
        }

        return $stores;
    }

    /**
     * @param list<array{
     *   file:string,
     *   line:int,
     *   name:string,
     *   value:?string,
     *   namespace:?string
     * }> $componentDirectives
     * @return array<string, list<array{
     *   file:string,
     *   line:int,
     *   directory:string
     * }>>
     */
    private function templateNamespaces(
        ProjectScanner $scanner,
        array $componentDirectives
    ): array {
        $namespaces = [];
        foreach ($componentDirectives as $directive) {
            if (
                $directive['name'] !== 'data-wp-interactive'
                || $directive['namespace'] === null
            ) {
                continue;
            }

            $namespaces[$directive['namespace']][] = [
                'file' => $directive['file'],
                'line' => $directive['line'],
                'directory' => dirname($directive['file']),
            ];
        }

        foreach (
            $scanner->files(['php', 'twig', 'blade.php', 'html'])
            as $file
        ) {
            $contents = $scanner->read($file);
            if (
                preg_match_all(
                    '/\bdata-wp-interactive\s*=\s*([\'"])([^\'"]+)\1/i',
                    $contents,
                    $matches,
                    PREG_OFFSET_CAPTURE
                ) === false
            ) {
                continue;
            }

            foreach ($matches[2] as $match) {
                $namespace = $this->normaliseTemplateNamespace($match[0]);
                if ($namespace === null) {
                    continue;
                }

                $namespaces[$namespace][] = [
                    'file' => $file,
                    'line' => ProjectScanner::lineForOffset(
                        $contents,
                        $match[1]
                    ),
                    'directory' => dirname($file),
                ];
            }
        }

        return $namespaces;
    }

    private function normaliseTemplateNamespace(string $value): ?string
    {
        $value = trim($value);
        if (str_starts_with($value, '{')) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded)
                && is_string($decoded['namespace'] ?? null)
                    ? $decoded['namespace']
                    : '';
        }

        return preg_match('/^[a-z0-9-]+\/[a-z0-9-]+$/', $value) === 1
            ? $value
            : null;
    }

    /**
     * @param array<string, array{
     *   file:string,
     *   line:int,
     *   directory:string,
     *   groups:array<string, array<string, true>>
     * }> $stores
     * @return list<Diagnostic>
     */
    private function interactivityTemplateDiagnostics(
        ProjectScanner $scanner,
        array $stores
    ): array {
        $diagnostics = [];
        foreach (
            $scanner->files(['php', 'twig', 'blade.php', 'html'])
            as $file
        ) {
            $contents = $scanner->read($file);
            if (!str_contains($contents, 'data-wp-')) {
                continue;
            }

            $namespace = null;
            if (
                preg_match(
                    '/\bdata-wp-interactive\s*=\s*([\'"])([^\'"]+)\1/i',
                    $contents,
                    $namespaceMatch
                ) === 1
            ) {
                $namespace = $this->normaliseTemplateNamespace(
                    $namespaceMatch[2]
                );
            }

            $store = $namespace !== null
                ? ($stores[$namespace] ?? null)
                : null;
            if ($store === null) {
                foreach ($stores as $candidate) {
                    if ($candidate['directory'] === dirname($file)) {
                        $store = $candidate;
                        break;
                    }
                }
            }

            if (
                preg_match_all(
                    '/\b(data-wp-[a-z0-9-]+(?:--[a-z0-9-]+)?)\s*=\s*([\'"])([^\'"]*)\2/i',
                    $contents,
                    $directives,
                    PREG_OFFSET_CAPTURE
                ) !== false
            ) {
                foreach ($directives[1] as $index => $nameMatch) {
                    $diagnostic = $this->directiveDiagnostic(
                        $file,
                        ProjectScanner::lineForOffset(
                            $contents,
                            $nameMatch[1]
                        ),
                        strtolower($nameMatch[0]),
                        $directives[3][$index][0],
                        $store
                    );
                    if ($diagnostic !== null) {
                        $diagnostics[] = $diagnostic;
                    }
                }
            }

            foreach (preg_split('/\R/', $contents) ?: [] as $index => $line) {
                if (!str_contains($line, 'data-wp-context')) {
                    continue;
                }

                if (str_contains($line, '<?')) {
                    if (
                        str_contains($line, 'esc_attr')
                        && str_contains($line, 'wp_json_encode')
                    ) {
                        continue;
                    }

                    $diagnostics[] = new Diagnostic(
                        'Server-generated data-wp-context must use esc_attr(wp_json_encode(...)).',
                        'blockstudio.interactivity.context',
                        $file,
                        $index + 1
                    );
                    continue;
                }

                $diagnostics[] = new Diagnostic(
                    'Generate data-wp-context with escaped server-side JSON instead of a hand-written attribute.',
                    'blockstudio.interactivity.context',
                    $file,
                    $index + 1
                );
            }
        }

        return $diagnostics;
    }

    /**
     * @param array{
     *   file:string,
     *   line:int,
     *   directory:string,
     *   groups:array<string, array<string, true>>
     * }|null $store
     */
    private function directiveDiagnostic(
        string $file,
        int $line,
        string $name,
        string $value,
        ?array $store,
        bool $checkHandlers = false
    ): ?Diagnostic {
        $value = preg_replace(
            '/^[a-z0-9-]+\/[a-z0-9-]+::!?/i',
            '',
            $value
        ) ?? $value;
        $value = ltrim($value, '!');
        $segments = explode('.', $value);
        $group = $segments[0];
        $path = implode('.', array_slice($segments, 1));

        $behavior = str_starts_with($name, 'data-wp-on')
            || in_array($name, ['data-wp-watch', 'data-wp-init'], true);
        if ($behavior) {
            if (!in_array($group, ['actions', 'callbacks'], true)) {
                return new Diagnostic(
                    sprintf(
                        '%s must reference actions.* or callbacks.*.',
                        $name
                    ),
                    'blockstudio.interactivity.handler',
                    $file,
                    $line
                );
            }

            if (
                $checkHandlers
                && $store !== null
                && !isset($store['groups'][$group][$path])
            ) {
                return new Diagnostic(
                    sprintf(
                        'Interactivity directive references missing %s handler "%s".',
                        $group,
                        $path
                    ),
                    'blockstudio.interactivity.handler',
                    $file,
                    $line
                );
            }

            return null;
        }

        $binding = str_starts_with($name, 'data-wp-bind')
            || str_starts_with($name, 'data-wp-class')
            || str_starts_with($name, 'data-wp-style')
            || $name === 'data-wp-text';
        if (
            !$binding
            || !in_array($group, ['state', 'actions', 'callbacks'], true)
            || $store === null
            || ($path !== '' && isset($store['groups'][$group][$path]))
        ) {
            return null;
        }

        return new Diagnostic(
            sprintf(
                '%s references missing store path "%s".',
                $name,
                $value
            ),
            'blockstudio.interactivity.binding',
            $file,
            $line
        );
    }

    /**
     * @param array<string, array{
     *   file:string,
     *   line:int,
     *   directory:string,
     *   groups:array<string, array<string, true>>
     * }> $stores
     * @param list<array{
     *   file:string,
     *   line:int,
     *   name:string,
     *   value:?string,
     *   namespace:?string
     * }> $directives
     * @return list<Diagnostic>
     */
    private function interactivityComponentDiagnostics(
        array $stores,
        array $directives
    ): array {
        $fileNamespaces = [];
        foreach ($directives as $directive) {
            if (
                $directive['name'] === 'data-wp-interactive'
                && $directive['namespace'] !== null
            ) {
                $fileNamespaces[$directive['file']]
                    ??= $directive['namespace'];
            }
        }

        $diagnostics = [];
        foreach ($directives as $directive) {
            if (
                $directive['value'] === null
                || in_array(
                    $directive['name'],
                    ['data-wp-interactive', 'data-wp-context'],
                    true
                )
            ) {
                continue;
            }

            $namespace = preg_match(
                '/^([a-z0-9-]+\/[a-z0-9-]+)::/',
                $directive['value'],
                $prefix
            ) === 1
                ? $prefix[1]
                : ($directive['namespace']
                    ?? $fileNamespaces[$directive['file']]
                    ?? null);
            $store = $namespace !== null
                ? ($stores[$namespace] ?? null)
                : null;
            $directory = dirname($directive['file']);
            if ($store === null && $namespace === null) {
                foreach ($stores as $candidate) {
                    if ($candidate['directory'] === $directory) {
                        $store = $candidate;
                        break;
                    }
                }
            }

            $diagnostic = $this->directiveDiagnostic(
                $directive['file'],
                $directive['line'],
                $directive['name'],
                $directive['value'],
                $store,
                true
            );
            if ($diagnostic !== null) {
                $diagnostics[] = $diagnostic;
            }
        }

        return $diagnostics;
    }

    /**
     * @return list<array{
     *   file:string,
     *   line:int,
     *   name:string,
     *   value:?string,
     *   namespace:?string
     * }>
     */
    private function componentDirectives(ProjectScanner $scanner): array
    {
        $directives = [];
        foreach ($scanner->files(['tsx', 'jsx']) as $file) {
            $contents = $scanner->read($file);
            if (stripos($contents, 'data-wp-') === false) {
                continue;
            }

            array_push(
                $directives,
                ...$this->fileComponentDirectives($file, $contents)
            );
        }

        return $directives;
    }

    /**
     * @return list<array{
     *   file:string,
     *   line:int,
     *   name:string,
     *   value:?string,
     *   namespace:?string
     * }>
     */
    private function fileComponentDirectives(
        string $file,
        string $contents
    ): array {
        try {
            $program = Parser::parseSource(
                $contents,
                'module',
                new JsxOptions(JsxMode::Preserve),
                $file
            );
        } catch (SyntaxError) {
            return $this->textComponentDirectives($file, $contents);
        }

        $directives = [];
        $scopes = [];
        Walker::walk(
            $program,
            function (Node $node) use ($file, &$directives, &$scopes): null {
                if ($node instanceof JsxElement) {
                    $namespace = null;
                    foreach ($node->openingElement->attributes as $attribute) {
                        if (
                            $attribute instanceof JsxAttribute
                            && $attribute->name instanceof JsxIdentifier
                            && strtolower($attribute->name->name)
                                === 'data-wp-interactive'
                        ) {
                            $value = $this->staticString($attribute->value);
                            $namespace = $value !== null
                                ? $this->normaliseTemplateNamespace($value)
                                : null;
                        }
                    }
                    $scopes[] = $namespace ?? $this->innermostScope($scopes);

                    return null;
                }

                [$name, $value] = match (true) {
                    $node instanceof JsxAttribute
                        && $node->name instanceof JsxIdentifier
                        => [$node->name->name, $node->value],
                    $node instanceof Property
                        && $node->key instanceof Literal
                        && is_string($node->key->value)
                        => [$node->key->value, $node->value],
                    default => [null, null],
                };
                $name = $name !== null ? strtolower($name) : '';
                if (!str_starts_with($name, 'data-wp-')) {
                    return null;
                }

                $string = $this->staticString($value);
                $namespace = match (true) {
                    $name === 'data-wp-interactive' => $string !== null
                        ? $this->normaliseTemplateNamespace($string)
                        : null,
                    $node instanceof JsxAttribute
                        => $this->innermostScope($scopes),
                    default => null,
                };
                $directives[] = [
                    'file' => $file,
                    'line' => $node->location->line,
                    'name' => $name,
                    'value' => $string,
                    'namespace' => $namespace,
                ];

                return null;
            },
            function (Node $node) use (&$scopes): void {
                if ($node instanceof JsxElement) {
                    array_pop($scopes);
                }
            }
        );

        return $directives;
    }

    /**
     * @return list<array{
     *   file:string,
     *   line:int,
     *   name:string,
     *   value:?string,
     *   namespace:?string
     * }>
     */
    private function textComponentDirectives(
        string $file,
        string $contents
    ): array {
        if (
            preg_match_all(
                '/\b(data-wp-[a-z0-9-]+(?:--[a-z0-9-]+)?)\s*=\s*([\'"])([^\'"]*)\2/i',
                $contents,
                $matches,
                PREG_OFFSET_CAPTURE
            ) === false
        ) {
            return [];
        }

        $directives = [];
        foreach ($matches[1] as $index => $match) {
            $name = strtolower($match[0]);
            $value = $matches[3][$index][0];
            $directives[] = [
                'file' => $file,
                'line' => ProjectScanner::lineForOffset($contents, $match[1]),
                'name' => $name,
                'value' => $value,
                'namespace' => $name === 'data-wp-interactive'
                    ? $this->normaliseTemplateNamespace($value)
                    : null,
            ];
        }

        return $directives;
    }

    /**
     * @param list<?string> $scopes
     */
    private function innermostScope(array $scopes): ?string
    {
        return $scopes === [] ? null : $scopes[array_key_last($scopes)];
    }

    private function staticString(?Node $node): ?string
    {
        if ($node instanceof JsxExpressionContainer) {
            $node = $node->expression;
        }

        if ($node instanceof Literal) {
            return is_string($node->value) ? $node->value : null;
        }

        if ($node instanceof TemplateLiteral && $node->expressions === []) {
            $value = '';
            foreach ($node->quasis as $quasi) {
                if ($quasi->cookedValue === null) {
                    return null;
                }
                $value .= $quasi->cookedValue;
            }

            return $value;
        }

        return null;
    }

    /**
     * @param array<string, array{
     *   file:string,
     *   line:int,
     *   directory:string,
     *   groups:array<string, array<string, true>>
     * }> $stores
     * @param array<string, list<array{
     *   file:string,
     *   line:int,
     *   directory:string
     * }>> $templates
     * @return list<Diagnostic>
     */
    private function interactivityOrphanDiagnostics(
        array $stores,
        array $templates
    ): array {
        $diagnostics = [];
        foreach ($templates as $namespace => $references) {
            if (
                isset($stores[$namespace])
                || str_starts_with($namespace, 'bsui/')
            ) {
                continue;
            }

            foreach ($references as $reference) {
                $diagnostics[] = new Diagnostic(
                    sprintf(
                        'Template namespace "%s" has no matching store registration.',
                        $namespace
                    ),
                    'blockstudio.interactivity.orphan',
                    $reference['file'],
                    $reference['line']
                );
            }
        }

        foreach ($stores as $namespace => $store) {
            if (isset($templates[$namespace])) {
                continue;
            }

            $diagnostics[] = new Diagnostic(
                sprintf(
                    'Store namespace "%s" is not referenced by an analysed template.',
                    $namespace
                ),
                'blockstudio.interactivity.orphan',
                $store['file'],
                $store['line']
            );
        }

        return $diagnostics;
    }

    /**
     * @param array{body:string, offset:int}|null $body
     * @return array<string, true>
     */
    private function propertyNames(?array $body): array
    {
        if (
            $body === null
            || preg_match_all(
                '/(?:^|[,;])\s*(?:async\s+|get\s+)?([A-Za-z_$][A-Za-z0-9_$]*)\s*(?::|\()/m',
                $body['body'],
                $matches
            ) === false
        ) {
            return [];
        }

        $names = [];
        foreach ($matches[1] as $name) {
            $names[$name] = true;
        }

        return $names;
    }

    /**
     * @return array<string, array<string, array<string, true>>>
     */
    private function handlerIndex(ProjectScanner $scanner): array
    {
        $index = [];

        foreach ($scanner->files(['js']) as $file) {
            $contents = $scanner->read($file);
            foreach (['actions', 'callbacks'] as $group) {
                $body = $this->objectBody($contents, $group);
                if ($body === null) {
                    continue;
                }

                if (
                    preg_match_all(
                        '/(?:^|[,;])\s*(?:async\s+)?([A-Za-z_$][A-Za-z0-9_$]*)\s*(?::|\()/m',
                        $body['body'],
                        $matches
                    ) === false
                ) {
                    continue;
                }

                foreach ($matches[1] as $name) {
                    $index[dirname($file)][$group][$name] = true;
                }
            }
        }

        return $index;
    }

    /**
     * @return list<array{file:string, group:string, handler:string, line:int}>
     */
    private function templateDirectives(ProjectScanner $scanner): array
    {
        $directives = [];
        foreach ($scanner->files(['php', 'twig', 'blade.php', 'html']) as $file) {
            $contents = $scanner->read($file);
            if (
                preg_match_all(
                    '/data-wp-(?:on(?:--(?:window|document))?--[a-z0-9-]+|watch)\s*=\s*([\'"])(actions|callbacks)\.([A-Za-z_$][A-Za-z0-9_$]*)\1/i',
                    $contents,
                    $matches,
                    PREG_OFFSET_CAPTURE
                ) === false
            ) {
                continue;
            }

            foreach ($matches[3] as $index => $match) {
                $directives[] = [
                    'file' => $file,
                    'group' => strtolower($matches[2][$index][0]),
                    'handler' => $match[0],
                    'line' => ProjectScanner::lineForOffset($contents, $match[1]),
                ];
            }
        }

        return $directives;
    }

    /**
     * @return array{body:string, offset:int}|null
     */
    private function objectBody(string $contents, string $property): ?array
    {
        if (
            preg_match(
                '/\b' . preg_quote($property, '/') . '\s*:\s*\{/',
                $contents,
                $match,
                PREG_OFFSET_CAPTURE
            ) !== 1
        ) {
            return null;
        }

        $start = $match[0][1] + strlen($match[0][0]);
        $depth = 1;
        $length = strlen($contents);
        $quote = null;
        $escaped = false;

        for ($position = $start; $position < $length; $position++) {
            $char = $contents[$position];

            if ($quote !== null) {
                if ($escaped) {
                    $escaped = false;
                } elseif ($char === '\\') {
                    $escaped = true;
                } elseif ($char === $quote) {
                    $quote = null;
                }
                continue;
            }

            if ($char === '"' || $char === "'" || $char === '`') {
                $quote = $char;
                continue;
            }

            if ($char === '{') {
                $depth++;
            } elseif ($char === '}') {
                $depth--;
                if ($depth === 0) {
                    return [
                        'body' => substr($contents, $start, $position - $start),
                        'offset' => $start,
                    ];
                }
            }
        }

        return null;
    }

    private function isMinified(string $file, string $contents): bool
    {
        if (str_ends_with(strtolower($file), '.min.js')) {
            return true;
        }

        $newline = strpos($contents, "\n");
        $firstLine = $newline === false
            ? $contents
            : substr($contents, 0, $newline);

        return strlen($firstLine) > 1000;
    }
}
