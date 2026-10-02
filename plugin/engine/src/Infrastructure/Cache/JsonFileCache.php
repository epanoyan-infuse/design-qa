<?php

declare(strict_types=1);

namespace DesignQa\Infrastructure\Cache;

use Closure;
use JsonException;

/**
 * Stores JSON documents as private files (0600 in a 0700 directory) with a time-to-live.
 */
final readonly class JsonFileCache
{
    /** @var Closure(): int */
    private Closure $clock;

    /**
     * @param (callable(): int)|null $clock current Unix time; defaults to time()
     */
    public function __construct(
        private string $directory,
        private int $ttlSeconds,
        ?callable $clock = null,
    ) {
        $this->clock = Closure::fromCallable($clock ?? time(...));
    }

    /**
     * @return array<mixed>|null null when missing, expired or unreadable
     */
    public function get(string $key): ?array
    {
        $file = $this->file($key);
        $modified = is_file($file) ? filemtime($file) : false;
        if ($modified === false || ($this->clock)() - $modified > $this->ttlSeconds) {
            return null;
        }

        $contents = file_get_contents($file);
        try {
            $data = $contents === false ? null : json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return is_array($data) ? $data : null;
    }

    /**
     * @param array<mixed> $data
     */
    public function put(string $key, array $data): void
    {
        if (!is_dir($this->directory) && !@mkdir($this->directory, 0o700, true) && !is_dir($this->directory)) {
            return; // caching is an optimisation; never fail a check because of it
        }

        try {
            $json = json_encode($data, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return;
        }

        $file = $this->file($key);
        $temporary = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';

        // Restrict the umask for the write itself, so the file is never briefly world/group
        // readable between creation and a later chmod.
        $previousUmask = umask(0o077);
        try {
            $written = file_put_contents($temporary, $json);
        } finally {
            umask($previousUmask);
        }
        if ($written === false) {
            return;
        }
        rename($temporary, $file);
        touch($file, ($this->clock)());
    }

    private function file(string $key): string
    {
        return $this->directory . '/' . hash('sha256', $key) . '.json';
    }
}
