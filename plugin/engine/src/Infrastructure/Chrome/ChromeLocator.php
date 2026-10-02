<?php

declare(strict_types=1);

namespace DesignQa\Infrastructure\Chrome;

use Closure;

/**
 * Finds the Google Chrome (or Chromium) executable on this computer.
 */
final readonly class ChromeLocator
{
    public const ENV_VARIABLE = 'CHROME_PATH';

    /** @var Closure(string): bool */
    private Closure $isExecutable;

    /**
     * @param array<string, mixed>        $environment  usually getenv()
     * @param string                      $osFamily     PHP_OS_FAMILY
     * @param (callable(string): bool)|null $isExecutable defaults to is_executable()
     */
    public function __construct(
        private array $environment,
        private string $osFamily = PHP_OS_FAMILY,
        ?callable $isExecutable = null,
    ) {
        $this->isExecutable = $isExecutable !== null
            ? Closure::fromCallable($isExecutable)
            : static fn(string $path): bool => is_file($path) && is_executable($path);
    }

    public function locate(): ?string
    {
        foreach ($this->candidates() as $path) {
            if (($this->isExecutable)($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * @return list<string> paths in the order they are tried
     */
    public function candidates(): array
    {
        $candidates = [];
        $override = $this->environment[self::ENV_VARIABLE] ?? null;
        if (is_string($override) && $override !== '') {
            $candidates[] = $override;
        }

        $home = $this->environment['HOME'] ?? null;

        return match ($this->osFamily) {
            'Darwin' => [
                ...$candidates,
                '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
                ...(is_string($home) ? [$home . '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome'] : []),
                '/Applications/Chromium.app/Contents/MacOS/Chromium',
            ],
            'Windows' => [
                ...$candidates,
                'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
                'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe',
            ],
            default => [
                ...$candidates,
                '/usr/bin/google-chrome',
                '/usr/bin/google-chrome-stable',
                '/usr/bin/chromium',
                '/usr/bin/chromium-browser',
            ],
        };
    }
}
