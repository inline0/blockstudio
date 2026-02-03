<?php

/**
 * CLI Input Handler.
 *
 * Port of: @tailwindcss/cli argument parsing
 *
 * This provides command-line argument parsing similar to how the
 * original @tailwindcss/cli handles options via Commander.js.
 *
 * Original: https://github.com/tailwindlabs/tailwindcss/tree/next/packages/%40tailwindcss-cli
 * License: MIT (https://github.com/tailwindlabs/tailwindcss/blob/next/LICENSE)
 *
 * @port-deviation:replacement The original uses Commander.js for argument parsing.
 * PHP implementation parses argv directly with equivalent behavior.
 *
 * @credits Tailwind Labs (https://tailwindcss.com)
 */
declare (strict_types=1);
namespace BlockstudioVendor\TailwindPHP\Cli\Console;

/**
 * Parse and access command line arguments.
 *
 * Parses argv into commands, arguments, and options.
 * Supports short (-v) and long (--verbose) options.
 */
class Input
{
    private string $command = '';
    /** @var array<string> */
    private array $arguments = [];
    /** @var array<string, string|bool> */
    private array $options = [];
    /** @var array<string> */
    private array $rawArgs;
    /**
     * @param array<string>|null $argv Command line arguments (defaults to $_SERVER['argv'])
     */
    public function __construct(?array $argv = null)
    {
        $this->rawArgs = $argv ?? $_SERVER['argv'] ?? [];
        $this->parse();
    }
    /**
     * Parse the command line arguments.
     */
    private function parse(): void
    {
        $args = $this->rawArgs;
        // Remove script name
        array_shift($args);
        // Re-index the array
        $args = array_values($args);
        // First non-option argument is the command
        $foundCommand = \false;
        $count = count($args);
        for ($i = 0; $i < $count; $i++) {
            $arg = $args[$i];
            if (str_starts_with($arg, '--')) {
                // Long option: --name or --name=value
                $this->parseLongOption($arg);
            } elseif (str_starts_with($arg, '-') && $arg !== '-' && strlen($arg) === 2) {
                // Short option with potential value: -c value or -c=value
                $char = $arg[1];
                // Check if next arg is a value (not starting with -)
                if ($i + 1 < $count && !str_starts_with($args[$i + 1], '-')) {
                    $this->options[$char] = $args[$i + 1];
                    $i++;
                    // Skip the value
                } else {
                    $this->options[$char] = \true;
                }
            } elseif (str_starts_with($arg, '-') && $arg !== '-') {
                // Multiple short options like -vvv (only flags, no values)
                $this->parseShortOption($arg);
            } elseif (!$foundCommand) {
                // First positional argument is the command
                $this->command = $arg;
                $foundCommand = \true;
            } else {
                // Additional positional arguments
                $this->arguments[] = $arg;
            }
        }
    }
    /**
     * Parse a long option (--name or --name=value).
     */
    private function parseLongOption(string $arg): void
    {
        $option = substr($arg, 2);
        if (str_contains($option, '=')) {
            [$name, $value] = explode('=', $option, 2);
            $this->options[$name] = $value;
        } else if (str_starts_with($option, 'no-')) {
            $this->options[substr($option, 3)] = \false;
        } else {
            $this->options[$option] = \true;
        }
    }
    /**
     * Parse short options (-v, -vvv).
     */
    private function parseShortOption(string $arg): void
    {
        $chars = substr($arg, 1);
        // Handle multiple short options like -vvv
        for ($i = 0; $i < strlen($chars); $i++) {
            $char = $chars[$i];
            $this->options[$char] = \true;
        }
    }
    /**
     * Get the command name.
     */
    public function getCommand(): string
    {
        return $this->command;
    }
    /**
     * Get positional arguments.
     *
     * @return array<string>
     */
    public function getArguments(): array
    {
        return $this->arguments;
    }
    /**
     * Get a specific positional argument.
     */
    public function getArgument(int $index, ?string $default = null): ?string
    {
        return $this->arguments[$index] ?? $default;
    }
    /**
     * Check if an option exists.
     */
    public function hasOption(string $name): bool
    {
        return array_key_exists($name, $this->options);
    }
    /**
     * Get an option value.
     *
     * @param string|bool|null $default
     * @return string|bool|null
     */
    public function getOption(string $name, string|bool|null $default = null): string|bool|null
    {
        return $this->options[$name] ?? $default;
    }
    /**
     * Get a string option value.
     */
    public function getStringOption(string $name, string $default = ''): string
    {
        $value = $this->options[$name] ?? $default;
        return is_string($value) ? $value : $default;
    }
    /**
     * Get a boolean option value.
     */
    public function getBoolOption(string $name, bool $default = \false): bool
    {
        if (!array_key_exists($name, $this->options)) {
            return $default;
        }
        $value = $this->options[$name];
        if (is_bool($value)) {
            return $value;
        }
        return in_array(strtolower((string) $value), ['true', '1', 'yes', 'on'], \true);
    }
    /**
     * Get all options.
     *
     * @return array<string, string|bool>
     */
    public function getOptions(): array
    {
        return $this->options;
    }
    /**
     * Check for help option.
     */
    public function wantsHelp(): bool
    {
        return $this->hasOption('help') || $this->hasOption('h');
    }
    /**
     * Check for version option.
     */
    public function wantsVersion(): bool
    {
        return $this->hasOption('version') || $this->hasOption('V');
    }
    /**
     * Check for verbose option.
     */
    public function isVerbose(): bool
    {
        return $this->hasOption('verbose') || $this->hasOption('v');
    }
    /**
     * Check for quiet option.
     */
    public function isQuiet(): bool
    {
        return $this->hasOption('quiet') || $this->hasOption('q');
    }
    /**
     * Get the raw arguments.
     *
     * @return array<string>
     */
    public function getRawArgs(): array
    {
        return $this->rawArgs;
    }
}
