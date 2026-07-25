<?php

declare(strict_types=1);

namespace Blockstudio\PHPStan\Theme;

use TailwindPHP\TailwindCompiler;

final class TailwindAnalyzer
{
    /**
     * @return list<Diagnostic>
     */
    public function analyse(ProjectScanner $scanner): array
    {
        $diagnostics = [];

        foreach ($scanner->roots() as $root) {
            if (!is_dir($root)) {
                continue;
            }

            try {
                $diagnostics = array_merge(
                    $diagnostics,
                    $this->analyseRoot($scanner, $root)
                );
            } catch (\Throwable $exception) {
                $diagnostics[] = new Diagnostic(
                    'Tailwind analysis failed: ' . $exception->getMessage(),
                    'blockstudio.tailwind.compile',
                    $root . '/style.css'
                );
            }
        }

        return $diagnostics;
    }

    /**
     * @return list<Diagnostic>
     */
    private function analyseRoot(ProjectScanner $scanner, string $root): array
    {
        if (!class_exists(TailwindCompiler::class)) {
            return [
                new Diagnostic(
                    'TailwindPHP is required by the extreme-theme preset.',
                    'blockstudio.tailwind.compilerMissing',
                    $root . '/style.css'
                ),
            ];
        }

        $css = '@import "tailwindcss";' . "\n";
        $customUtilities = [];
        $colorTokens = [];

        foreach ($scanner->files(['css', 'scss']) as $file) {
            if ($scanner->rootForFile($file) !== $root) {
                continue;
            }

            $contents = $scanner->read($file);
            $css .= $contents . "\n";

            if (
                preg_match_all(
                    '/@utility\s+([^\s{]+)\s*\{/i',
                    $contents,
                    $matches
                ) !== false
            ) {
                foreach ($matches[1] as $utility) {
                    $utility = strtolower(trim($utility));
                    if ($utility !== '') {
                        $customUtilities[$utility] = true;
                    }
                }
            }

            if (
                preg_match_all(
                    '/--color-([a-z0-9-]+)\s*:\s*([^;]+);/i',
                    $contents,
                    $matches,
                    PREG_SET_ORDER
                ) !== false
            ) {
                foreach ($matches as $match) {
                    $value = $this->normalizeColorValue(trim($match[2]));
                    if ($value !== '' && !str_starts_with($value, 'var(')) {
                        $colorTokens[$value] = strtolower($match[1]);
                    }
                }
            }
        }

        $compiler = new TailwindCompiler($css, ['base' => $root]);
        $candidateFiles = $this->candidates($scanner, $root, $compiler);
        $candidates = array_keys($candidateFiles);
        sort($candidates);
        $compiler->css($candidates);

        $diagnostics = [];
        $designSystem = $compiler->getDesignSystem();

        foreach ($candidates as $candidate) {
            if (
                $this->isDynamicCandidate($candidate)
                || $this->isCustomUtilityCandidate($candidate, $customUtilities)
            ) {
                continue;
            }

            $base = $this->candidateBase($candidate);
            if (!$this->looksLikeUtility($base) || $base === 'container') {
                continue;
            }

            if (
                !$designSystem->hasInvalidCandidate($candidate)
                && $compiler->properties($candidate) !== []
            ) {
                continue;
            }

            $file = $candidateFiles[$candidate];
            $diagnostics[] = new Diagnostic(
                sprintf('Unknown Tailwind utility "%s".', $candidate),
                'blockstudio.tailwind.unknownUtility',
                $file,
                $this->lineForCandidate($scanner->read($file), $candidate)
            );
        }

        if ($colorTokens !== []) {
            foreach ($scanner->files(['php', 'twig', 'blade.php', 'js', 'css', 'scss']) as $file) {
                if ($scanner->rootForFile($file) !== $root) {
                    continue;
                }

                $contents = $scanner->read($file);
                if (
                    preg_match_all(
                        '/\b(bg|text|border|ring|fill)-\[((?:#[0-9a-fA-F]{3,8})|(?:rgba?\([^\]]+\)))\]/',
                        $contents,
                        $matches,
                        PREG_OFFSET_CAPTURE
                    ) === false
                ) {
                    continue;
                }

                foreach ($matches[2] as $index => $match) {
                    $value = $this->normalizeColorValue($match[0]);
                    $token = $colorTokens[$value] ?? null;
                    if ($token === null) {
                        continue;
                    }

                    $prefix = $matches[1][$index][0];
                    $diagnostics[] = new Diagnostic(
                        sprintf(
                            'Use the Tailwind theme token "%s-%s" instead of hardcoded "%s-[%s]".',
                            $prefix,
                            $token,
                            $prefix,
                            $match[0]
                        ),
                        'blockstudio.tailwind.semanticToken',
                        $file,
                        ProjectScanner::lineForOffset($contents, $match[1])
                    );
                }
            }
        }

        return $diagnostics;
    }

    /**
     * @return array<string, string>
     */
    private function candidates(
        ProjectScanner $scanner,
        string $root,
        TailwindCompiler $compiler
    ): array {
        $candidates = [];

        foreach ($scanner->files(['php', 'twig', 'blade.php', 'html', 'js', 'css', 'scss']) as $file) {
            if ($scanner->rootForFile($file) !== $root) {
                continue;
            }

            $contents = $scanner->read($file);
            $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if ($extension === 'css' || $extension === 'scss') {
                $found = $this->applyCandidates($contents);
            } elseif ($extension === 'js') {
                $found = $compiler->extractCandidatesFromStrings($contents);
            } else {
                $found = $compiler->extractCandidates($contents);
            }

            foreach ($found as $candidate) {
                $candidate = trim((string) $candidate);
                if ($candidate !== '' && !is_numeric($candidate)) {
                    $candidates[$candidate] ??= $file;
                }
            }
        }

        return $candidates;
    }

    /**
     * @return list<string>
     */
    private function applyCandidates(string $contents): array
    {
        if (
            preg_match_all('/@apply\s+([^;{}]+)[;}]/i', $contents, $matches)
            === false
        ) {
            return [];
        }

        $candidates = [];
        foreach ($matches[1] as $match) {
            foreach (preg_split('/\s+/', trim($match)) ?: [] as $candidate) {
                if ($candidate !== '') {
                    $candidates[] = $candidate;
                }
            }
        }

        return array_values(array_unique($candidates));
    }

    private function candidateBase(string $candidate): string
    {
        $parts = explode(':', $candidate);
        $base = (string) end($parts);
        $base = ltrim($base, '!');
        if (str_starts_with($base, '-')) {
            $base = substr($base, 1);
        }

        return strtolower($base);
    }

    private function looksLikeUtility(string $base): bool
    {
        if (in_array($base, [
            'absolute',
            'antialiased',
            'block',
            'container',
            'fixed',
            'flex',
            'grid',
            'hidden',
            'inline',
            'inline-flex',
            'inline-grid',
            'not-sr-only',
            'relative',
            'sr-only',
            'static',
            'sticky',
        ], true)) {
            return true;
        }

        foreach ([
            'animate-',
            'basis-',
            'bg-',
            'border-',
            'bottom-',
            'col-',
            'content-',
            'cursor-',
            'decoration-',
            'delay-',
            'duration-',
            'ease-',
            'fill-',
            'font-',
            'gap-',
            'grid-cols-',
            'grid-rows-',
            'grow',
            'h-',
            'items-',
            'justify-',
            'leading-',
            'left-',
            'list-',
            'm-',
            'max-h-',
            'max-w-',
            'mb-',
            'min-h-',
            'min-w-',
            'ml-',
            'mr-',
            'mt-',
            'mx-',
            'my-',
            'object-',
            'opacity-',
            'order-',
            'outline-',
            'overflow-',
            'overscroll-',
            'p-',
            'pb-',
            'pl-',
            'pointer-events-',
            'pr-',
            'pt-',
            'px-',
            'py-',
            'right-',
            'ring-',
            'rotate-',
            'rounded-',
            'row-',
            'scale-',
            'select-',
            'self-',
            'shadow-',
            'shrink',
            'skew-',
            'text-',
            'top-',
            'tracking-',
            'translate-',
            'underline-offset-',
            'w-',
            'z-',
        ] as $prefix) {
            if (str_starts_with($base, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private function isDynamicCandidate(string $candidate): bool
    {
        return str_contains($candidate, '<?')
            || str_contains($candidate, '?>')
            || str_contains($candidate, '${');
    }

    /**
     * @param array<string, true> $customUtilities
     */
    private function isCustomUtilityCandidate(
        string $candidate,
        array $customUtilities
    ): bool {
        $base = $this->candidateBase($candidate);
        if (isset($customUtilities[$base])) {
            return true;
        }

        foreach (array_keys($customUtilities) as $utility) {
            if (
                str_ends_with($utility, '-*')
                && str_starts_with($base, substr($utility, 0, -1))
            ) {
                return true;
            }
        }

        return false;
    }

    private function normalizeColorValue(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;
        return str_replace([', ', ','], ' ', $value);
    }

    private function lineForCandidate(string $contents, string $candidate): int
    {
        $offset = strpos($contents, $candidate);
        return ProjectScanner::lineForOffset(
            $contents,
            $offset !== false ? $offset : 0
        );
    }
}
