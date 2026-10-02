<?php

declare(strict_types=1);

namespace DesignQa\Infrastructure\Figma;

use RuntimeException;

/**
 * A Figma API failure with a message that is safe and useful to show to the developer.
 * Messages never contain the token.
 */
final class FigmaApiException extends RuntimeException
{
    public static function missingToken(string $where): self
    {
        return new self(sprintf('No Figma key found (looked in: %s).', $where));
    }

    /**
     * @param string|null $retryAfter    seconds, from the Retry-After header
     * @param string|null $rateLimitType "low" or "high", from the X-Figma-Rate-Limit-Type header
     */
    public static function forStatus(int $status, string $path, ?string $retryAfter = null, ?string $rateLimitType = null): self
    {
        $message = match (true) {
            $status === 401, $status === 403 => 'Figma rejected the key, or the key has no access to this file. Check that the key is valid and that its account can open the file.',
            $status === 404 => 'Figma file or node not found. Check the Figma link.',
            $status === 429 => self::rateLimitMessage($retryAfter, $rateLimitType),
            $status >= 500 => 'Figma is having problems right now. Try again in a few minutes.',
            default => 'Unexpected answer from Figma.',
        };

        return new self(sprintf('%s (HTTP %d on %s)', $message, $status, $path), $status);
    }

    private static function rateLimitMessage(?string $retryAfter, ?string $rateLimitType): string
    {
        $message = 'Figma read limit reached';
        if ($retryAfter !== null && is_numeric($retryAfter)) {
            $message .= sprintf(', try again %s', self::humanDelay((int) $retryAfter));
        }
        $message .= '.';

        return $message . match ($rateLimitType) {
            'low' => ' The key belongs to an account with a View or Collab seat, which Figma allows only a few reads a month. Use a key from an account with a Dev or Full seat in the team that owns the file.',
            default => ' Files in a free Figma team (Starter plan) also allow very few reads.',
        };
    }

    private static function humanDelay(int $seconds): string
    {
        return match (true) {
            $seconds < 90 => sprintf('in %d seconds', $seconds),
            $seconds < 5400 => sprintf('in about %d minutes', (int) round($seconds / 60)),
            $seconds < 259200 => sprintf('in about %d hours', (int) round($seconds / 3600)),
            default => sprintf('in about %d days', (int) round($seconds / 86400)),
        };
    }

    public static function network(string $path, string $reason): self
    {
        return new self(sprintf('Could not reach Figma (%s): %s', $path, $reason));
    }

    public static function invalidResponse(string $path): self
    {
        return new self(sprintf('Figma returned a response that is not valid JSON (%s).', $path));
    }
}
