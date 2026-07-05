<?php

declare(strict_types=1);

namespace BlockstudioVendor\Storh;

final class AtomicFilesystem
{
    private const TEMP_FILE_GRACE_SECONDS = 60;

    public static function ensure_directory(string $directory): void
    {
        if (is_dir($directory)) {
            return;
        }

        if (! @mkdir($directory, 0777, true) && ! is_dir($directory)) {
            throw new StorageException('Could not create storage directory: ' . $directory);
        }
    }

    public static function write_atomic(string $path, string $contents): void
    {
        self::ensure_directory(dirname($path));
        $temp = dirname($path) . '/.' . basename($path) . '.' . bin2hex(random_bytes(6)) . '.tmp';

        $handle = @fopen($temp, 'wb');
        if (false === $handle) {
            throw new StorageException('Could not open temporary storage file for writing: ' . $temp);
        }

        try {
            self::write_all($handle, $contents, $temp);
            self::sync_handle($handle, $temp);
        } finally {
            fclose($handle);
        }

        if (! @rename($temp, $path)) {
            @unlink($temp);
            throw new StorageException('Could not atomically replace storage file: ' . $path);
        }
        self::sync_directory(dirname($path));
    }

    /**
     * @param resource $handle
     */
    public static function write_all(mixed $handle, string $contents, string $path): void
    {
        $length = strlen($contents);
        if (0 === $length) {
            return;
        }

        $offset = @fwrite($handle, $contents);
        if (false === $offset || 0 === $offset) {
            throw new StorageException('Could not write storage file: ' . $path);
        }

        if ($offset === $length) {
            return;
        }

        // @codeCoverageIgnoreStart
        while ($offset < $length) {
            $written = @fwrite($handle, substr($contents, $offset));
            if (false === $written || 0 === $written) {
                throw new StorageException('Could not write storage file: ' . $path);
            }
            $offset += $written;
        }
        // @codeCoverageIgnoreEnd
    }

    public static function append(string $path, string $contents): void
    {
        self::ensure_directory(dirname($path));
        $handle = @fopen($path, 'ab');
        if (false === $handle) {
            throw new StorageException('Could not open storage file for appending: ' . $path);
        }

        try {
            self::write_all($handle, $contents, $path);
            self::sync_handle($handle, $path);
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param resource $handle
     */
    public static function sync_handle(mixed $handle, string $path): void
    {
        if (! @fflush($handle)) {
            throw new StorageException('Could not flush storage file: ' . $path);
        }

        if (function_exists('fsync') && ! @fsync($handle)) {
            throw new StorageException('Could not sync storage file: ' . $path);
        }
    }

    public static function sync_directory(string $directory): void
    {
        $handle = @fopen($directory, 'rb');
        if (false === $handle) {
            return;
        }

        try {
            if (function_exists('fsync')) {
                @fsync($handle);
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public static function read_jsonc_object(string $path): array
    {
        $contents = @file_get_contents($path);
        if (false === $contents) {
            throw new StorageException('Could not read storage file: ' . $path);
        }

        try {
            return Jsonc::decode_object($contents);
        } catch (\JsonException $exception) {
            throw new StorageException('Invalid JSONC storage file: ' . $path, 0, $exception);
        }
    }

    public static function cleanup_temp_files(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (! $file instanceof \SplFileInfo || ! $file->isFile()) {
                continue;
            }

            $name = $file->getBasename();
            if (! str_starts_with($name, '.') || ! str_ends_with($name, '.tmp')) {
                continue;
            }

            if (self::temp_file_owner_is_alive($name)) {
                continue;
            }

            if (! self::has_process_owner($name) && time() - $file->getMTime() < self::TEMP_FILE_GRACE_SECONDS) {
                continue;
            }

            @unlink($file->getPathname());
        }
    }

    private static function has_process_owner(string $name): bool
    {
        return 1 === preg_match('/^\.(\d+)\.[0-9a-f]+\.\d+\.tmp$/', $name);
    }

    private static function temp_file_owner_is_alive(string $name): bool
    {
        if (1 !== preg_match('/^\.(\d+)\.[0-9a-f]+\.\d+\.tmp$/', $name, $matches)) {
            return false;
        }

        $pid = (int) $matches[1];
        if ($pid < 1) {
            return false;
        }

        if (function_exists('posix_kill')) {
            return posix_kill($pid, 0);
        }

        // @codeCoverageIgnoreStart
        return is_dir('/proc/' . $pid);
        // @codeCoverageIgnoreEnd
    }
}
