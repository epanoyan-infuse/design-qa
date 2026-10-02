<?php

declare(strict_types=1);

namespace DesignQa\Infrastructure\Secrets;

use InvalidArgumentException;
use LogicException;
use SensitiveParameter;

/**
 * Wraps a credential so it cannot leak through dumps, logs, stack traces or serialization.
 * The raw value is only available through an explicit reveal() call.
 */
final class Secret
{
    private readonly string $value;

    public function __construct(#[SensitiveParameter] string $value)
    {
        $value = trim($value);
        if ($value === '') {
            throw new InvalidArgumentException('A secret cannot be empty.');
        }
        $this->value = $value;
    }

    public function reveal(): string
    {
        return $this->value;
    }

    /**
     * @return array<string, string>
     */
    public function __debugInfo(): array
    {
        return ['value' => '***'];
    }

    public function __toString(): string
    {
        return '***';
    }

    /**
     * @return never
     */
    public function __serialize(): array
    {
        throw new LogicException('Secrets cannot be serialized.');
    }
}
