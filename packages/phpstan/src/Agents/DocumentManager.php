<?php

declare(strict_types=1);

namespace Blockstudio\PHPStan\Agents;

/**
 * Owns the generated contract file the same way the hook manager owns its hook.
 *
 * A file without the generated marker belongs to the author and is never
 * replaced unless the author asks for it.
 */
final class DocumentManager
{
    public function __construct(
        private readonly ContractDocument $document = new ContractDocument()
    ) {}

    public function render(
        string $path,
        ProjectProfile $profile,
        PresetContract $contract
    ): string {
        return $this->document->render($profile, $contract, $this->notes($path));
    }

    public function write(
        string $path,
        ProjectProfile $profile,
        PresetContract $contract,
        bool $force = false
    ): DocumentResult {
        $exists = is_file($path);

        if ($exists && !$this->isOwned($path) && !$force) {
            throw new \RuntimeException(sprintf(
                'Refusing to overwrite the user-owned file at %s. Pass --force to replace it.',
                $path
            ));
        }

        $contents = $this->render($path, $profile, $contract);

        if ($exists && $this->read($path) === $contents) {
            return new DocumentResult('unchanged', $path, $contents);
        }

        $this->writeFile($path, $contents);

        return new DocumentResult(
            $exists ? 'updated' : 'created',
            $path,
            $contents
        );
    }

    private function notes(string $path): string
    {
        if (!is_file($path) || !$this->isOwned($path)) {
            return '';
        }

        $contents = $this->read($path);
        $start = strpos($contents, ContractDocument::NOTES_START);
        $end = strpos($contents, ContractDocument::NOTES_END);

        if ($start === false || $end === false || $end < $start) {
            return '';
        }

        $start += strlen(ContractDocument::NOTES_START);

        return trim(substr($contents, $start, $end - $start), "\r\n");
    }

    private function isOwned(string $path): bool
    {
        return str_contains($this->read($path), ContractDocument::MARKER);
    }

    private function read(string $path): string
    {
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new \RuntimeException(sprintf('Unable to read %s.', $path));
        }

        return $contents;
    }

    private function writeFile(string $path, string $contents): void
    {
        $directory = dirname($path);
        if (
            !is_dir($directory)
            && !@mkdir($directory, 0775, true)
            && !is_dir($directory)
        ) {
            throw new \RuntimeException(
                sprintf('Unable to create %s.', $directory)
            );
        }

        $temporary = tempnam($directory, '.blockstudio-');
        if ($temporary === false) {
            throw new \RuntimeException(
                sprintf('Unable to create a temporary file beside %s.', $path)
            );
        }

        try {
            if (file_put_contents($temporary, $contents) === false) {
                throw new \RuntimeException(
                    sprintf('Unable to write %s.', $path)
                );
            }
            if (!@chmod($temporary, 0644)) {
                throw new \RuntimeException(
                    sprintf('Unable to set permissions on %s.', $path)
                );
            }
            if (!@rename($temporary, $path)) {
                throw new \RuntimeException(
                    sprintf('Unable to install %s.', $path)
                );
            }
        } finally {
            if (is_file($temporary)) {
                @unlink($temporary);
            }
        }
    }
}
