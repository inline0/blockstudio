<?php

/**
 * TailwindPHP CLI Application.
 *
 * Port of: @tailwindcss/cli
 *
 * This is a PHP port of the official Tailwind CSS CLI tool (@tailwindcss/cli).
 * The goal is 1:1 behavior parity - same options, same output, same experience.
 *
 * Original: https://github.com/tailwindlabs/tailwindcss/tree/next/packages/%40tailwindcss-cli
 * License: MIT (https://github.com/tailwindlabs/tailwindcss/blob/next/LICENSE)
 *
 * @port-deviation:async The original uses async/await for file operations and watch mode.
 * PHP uses synchronous operations with pcntl for signal handling.
 *
 * @port-deviation:replacement The original uses Node.js fs/path modules.
 * PHP uses native file functions and RecursiveDirectoryIterator.
 *
 * @credits Tailwind Labs (https://tailwindcss.com)
 */
declare (strict_types=1);
namespace BlockstudioVendor\TailwindPHP\Cli;

use BlockstudioVendor\TailwindPHP\Cli\Console\Input;
use BlockstudioVendor\TailwindPHP\Cli\Console\Output;
use BlockstudioVendor\TailwindPHP\Tailwind;
/**
 * TailwindPHP CLI Application.
 *
 * A 1:1 port of @tailwindcss/cli - same options, same behavior.
 *
 * Usage:
 *   tailwindphp [--input input.css] [--output output.css] [--watch] [options]
 */
class Application
{
    public const VERSION = '1.0.0';
    public const NAME = 'tailwindphp';
    private Input $input;
    private Output $output;
    public function __construct(?Input $input = null, ?Output $output = null)
    {
        $this->input = $input ?? new Input();
        $this->output = $output ?? new Output();
    }
    /**
     * Run the CLI application.
     *
     * @return int Exit code
     */
    public function run(): int
    {
        try {
            // Handle --version
            if ($this->input->wantsVersion()) {
                $this->showVersion();
                return 0;
            }
            // Handle --help or no arguments
            if ($this->input->wantsHelp() || $this->shouldShowHelp()) {
                $this->showHelp();
                return 0;
            }
            // Run build (implicit command, just like tailwindcss)
            return $this->build();
        } catch (\Throwable $e) {
            $this->output->writeErrorln($this->output->color('red', 'Error: ') . $e->getMessage());
            if ($this->input->isVerbose()) {
                $this->output->writeErrorln();
                $this->output->writeErrorln($this->output->color('gray', $e->getTraceAsString()));
            }
            return 1;
        }
    }
    /**
     * Check if we should show help (no meaningful arguments provided).
     */
    private function shouldShowHelp(): bool
    {
        // If stdout is a TTY and no arguments provided, show help
        if (stream_isatty(\STDOUT) && !$this->hasAnyOption()) {
            return \true;
        }
        return \false;
    }
    /**
     * Check if any meaningful option was provided.
     */
    private function hasAnyOption(): bool
    {
        return $this->input->hasOption('input') || $this->input->hasOption('i') || $this->input->hasOption('output') || $this->input->hasOption('o') || $this->input->hasOption('watch') || $this->input->hasOption('w') || $this->input->hasOption('minify') || $this->input->hasOption('m');
    }
    /**
     * Build CSS (the implicit default command).
     */
    private function build(): int
    {
        $startTime = microtime(\true);
        // Show header
        $this->output->writeErrorln($this->output->color('cyan', '≈ ') . self::NAME . ' v' . self::VERSION);
        $this->output->writeErrorln();
        // Get options
        $inputFile = $this->getStringOption('input', 'i');
        $outputFile = $this->getStringOption('output', 'o', '-');
        $watch = $this->input->hasOption('watch') || $this->input->hasOption('w');
        $minify = $this->input->hasOption('minify') || $this->input->hasOption('m');
        $optimize = $this->input->hasOption('optimize');
        $cwd = $this->getStringOption('cwd', '', '.');
        // Change to specified working directory
        if ($cwd !== '.') {
            if (!is_dir($cwd)) {
                $this->output->writeErrorln("Specified cwd {$cwd} does not exist.");
                return 1;
            }
            chdir($cwd);
        }
        // Read input CSS
        $css = '@import "tailwindcss";';
        if ($inputFile !== '' && $inputFile !== '-') {
            if (!file_exists($inputFile)) {
                $this->output->writeErrorln("Specified input file {$inputFile} does not exist.");
                return 1;
            }
            $css = file_get_contents($inputFile);
        } elseif ($inputFile === '-') {
            // Read from stdin
            $css = stream_get_contents(\STDIN) ?: '@import "tailwindcss";';
        }
        // Check input/output are not the same
        if ($inputFile !== '' && $inputFile !== '-' && $inputFile === $outputFile) {
            $this->output->writeErrorln('Specified input file and output file are identical.');
            return 1;
        }
        // Compile
        $compiled = \BlockstudioVendor\TailwindPHP\compile($css, ['base' => $inputFile !== '' && $inputFile !== '-' ? dirname(realpath($inputFile)) : getcwd()]);
        // Get sources for content scanning
        $sources = $compiled['sources'] ?? [];
        // If no sources from @source directive, scan current directory
        if (empty($sources)) {
            $sources = [['base' => getcwd(), 'pattern' => '**/*', 'negated' => \false]];
        }
        // Scan for candidates
        $candidates = $this->scanSources($sources);
        // Build CSS
        $result = $compiled['build']($candidates);
        // Minify/optimize if requested
        if ($minify || $optimize) {
            $result = Tailwind::minify($result);
        }
        // Write output
        if ($outputFile === '-') {
            // Write to stdout
            echo $result;
        } else {
            // Ensure output directory exists
            $outputDir = dirname($outputFile);
            if ($outputDir !== '' && !is_dir($outputDir)) {
                mkdir($outputDir, 0755, \true);
            }
            file_put_contents($outputFile, $result);
        }
        $duration = round((microtime(\true) - $startTime) * 1000);
        $this->output->writeErrorln("Done in {$duration}ms");
        // Watch mode
        if ($watch) {
            return $this->watchLoop($inputFile, $outputFile, $sources, $minify || $optimize);
        }
        return 0;
    }
    /**
     * Watch for changes and rebuild.
     *
     * @param array<array{base: string, pattern: string, negated: bool}> $sources
     */
    private function watchLoop(string $inputFile, string $outputFile, array $sources, bool $minify): int
    {
        $this->output->writeErrorln();
        $this->output->writeErrorln('Watching for changes...');
        // Track file modification times
        $fileTimes = [];
        $watchFiles = $this->getWatchFiles($sources);
        foreach ($watchFiles as $file) {
            if (file_exists($file)) {
                $fileTimes[$file] = filemtime($file) ?: 0;
            }
        }
        // Also watch input file
        if ($inputFile !== '' && $inputFile !== '-' && file_exists($inputFile)) {
            $fileTimes[$inputFile] = filemtime($inputFile) ?: 0;
        }
        // Set up signal handling
        $running = \true;
        if (function_exists('pcntl_signal')) {
            pcntl_signal(\SIGINT, function () use (&$running) {
                $running = \false;
            });
            pcntl_signal(\SIGTERM, function () use (&$running) {
                $running = \false;
            });
        }
        $pollInterval = 500000;
        // 500ms in microseconds
        while ($running) {
            if (function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }
            $changed = \false;
            // Check for changes
            foreach ($fileTimes as $file => $oldTime) {
                if (!file_exists($file)) {
                    continue;
                }
                $newTime = filemtime($file) ?: 0;
                if ($newTime > $oldTime) {
                    $changed = \true;
                    $fileTimes[$file] = $newTime;
                }
            }
            // Check for new files
            $currentFiles = $this->getWatchFiles($sources);
            foreach ($currentFiles as $file) {
                if (!isset($fileTimes[$file]) && file_exists($file)) {
                    $changed = \true;
                    $fileTimes[$file] = filemtime($file) ?: 0;
                }
            }
            if ($changed) {
                $startTime = microtime(\true);
                try {
                    // Re-read input
                    $css = '@import "tailwindcss";';
                    if ($inputFile !== '' && $inputFile !== '-' && file_exists($inputFile)) {
                        $css = file_get_contents($inputFile);
                    }
                    // Recompile
                    $compiled = \BlockstudioVendor\TailwindPHP\compile($css, ['base' => $inputFile !== '' && $inputFile !== '-' ? dirname(realpath($inputFile)) : getcwd()]);
                    // Update sources
                    $newSources = $compiled['sources'] ?? [];
                    if (!empty($newSources)) {
                        $sources = $newSources;
                    }
                    // Scan and build
                    $candidates = $this->scanSources($sources);
                    $result = $compiled['build']($candidates);
                    if ($minify) {
                        $result = Tailwind::minify($result);
                    }
                    // Write output
                    if ($outputFile !== '-') {
                        file_put_contents($outputFile, $result);
                    }
                    $duration = round((microtime(\true) - $startTime) * 1000);
                    $this->output->writeErrorln("Done in {$duration}ms");
                } catch (\Throwable $e) {
                    $this->output->writeErrorln($this->output->color('red', 'Error: ') . $e->getMessage());
                }
            }
            usleep($pollInterval);
        }
        $this->output->writeErrorln();
        return 0;
    }
    /**
     * Scan source patterns for candidates.
     *
     * @param array<array{base: string, pattern: string, negated: bool}> $sources
     * @return array<string>
     */
    private function scanSources(array $sources): array
    {
        $candidates = [];
        $extensions = ['php', 'html', 'htm', 'twig', 'blade.php', 'vue', 'jsx', 'tsx', 'svelte', 'astro', 'mdx'];
        foreach ($sources as $source) {
            if ($source['negated']) {
                continue;
            }
            $base = $source['base'] ?: getcwd();
            $pattern = $source['pattern'];
            $files = $this->globRecursive($base, $pattern, $extensions);
            foreach ($files as $file) {
                $content = file_get_contents($file);
                if ($content !== \false) {
                    $extracted = Tailwind::extractCandidates($content);
                    $candidates = array_merge($candidates, $extracted);
                }
            }
        }
        return array_unique($candidates);
    }
    /**
     * Get files to watch.
     *
     * @param array<array{base: string, pattern: string, negated: bool}> $sources
     * @return array<string>
     */
    private function getWatchFiles(array $sources): array
    {
        $files = [];
        $extensions = ['php', 'html', 'htm', 'twig', 'blade.php', 'vue', 'jsx', 'tsx', 'svelte', 'astro', 'mdx', 'css'];
        foreach ($sources as $source) {
            if ($source['negated']) {
                continue;
            }
            $base = $source['base'] ?: getcwd();
            $pattern = $source['pattern'];
            $files = array_merge($files, $this->globRecursive($base, $pattern, $extensions));
        }
        return array_unique($files);
    }
    /**
     * Recursively glob for files.
     *
     * @param array<string> $extensions
     * @return array<string>
     */
    private function globRecursive(string $base, string $pattern, array $extensions): array
    {
        $files = [];
        if (!is_dir($base)) {
            return [];
        }
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base, \RecursiveDirectoryIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }
            $ext = $file->getExtension();
            $filename = $file->getFilename();
            // Check for compound extensions like .blade.php
            foreach ($extensions as $allowedExt) {
                if (str_contains($allowedExt, '.')) {
                    if (str_ends_with($filename, '.' . $allowedExt)) {
                        $files[] = $file->getPathname();
                        continue 2;
                    }
                } elseif ($ext === $allowedExt) {
                    $files[] = $file->getPathname();
                    continue 2;
                }
            }
        }
        return $files;
    }
    /**
     * Get a string option with short alias support.
     */
    private function getStringOption(string $long, string $short = '', string $default = ''): string
    {
        if ($short !== '' && $this->input->hasOption($short)) {
            $value = $this->input->getOption($short);
            return is_string($value) ? $value : $default;
        }
        if ($this->input->hasOption($long)) {
            $value = $this->input->getOption($long);
            return is_string($value) ? $value : $default;
        }
        return $default;
    }
    /**
     * Show version.
     */
    private function showVersion(): void
    {
        $this->output->writeln('≈ ' . self::NAME . ' v' . self::VERSION);
    }
    /**
     * Show help.
     */
    private function showHelp(): void
    {
        $this->output->writeln('≈ ' . self::NAME . ' v' . self::VERSION);
        $this->output->writeln();
        $this->output->writeln($this->output->color('yellow', 'Usage:'));
        $this->output->writeln('  tailwindphp [--input input.css] [--output output.css] [--watch] [options…]');
        $this->output->writeln();
        $this->output->writeln($this->output->color('yellow', 'Options:'));
        $this->output->writeln('  ' . $this->output->color('green', '-i, --input') . ' ················· Input file');
        $this->output->writeln('  ' . $this->output->color('green', '-o, --output') . ' ················ Output file [default: `-`]');
        $this->output->writeln('  ' . $this->output->color('green', '-w, --watch') . ' ················· Watch for changes and rebuild as needed');
        $this->output->writeln('  ' . $this->output->color('green', '-m, --minify') . ' ················ Optimize and minify the output');
        $this->output->writeln('  ' . $this->output->color('green', '    --optimize') . ' ·············· Optimize the output without minifying');
        $this->output->writeln('  ' . $this->output->color('green', '    --cwd') . ' ··················· The current working directory [default: `.`]');
        $this->output->writeln('  ' . $this->output->color('green', '-h, --help') . ' ·················· Display usage information');
    }
    /**
     * Get input handler.
     */
    public function getInput(): Input
    {
        return $this->input;
    }
    /**
     * Get output handler.
     */
    public function getOutput(): Output
    {
        return $this->output;
    }
}
