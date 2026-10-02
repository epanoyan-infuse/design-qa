<?php

declare(strict_types=1);

namespace DesignQa\Infrastructure\Chrome;

use DesignQa\Domain\Model\ScreenSpec;

/**
 * How Chrome pretends to be a device for one screen width. Phone widths also get a phone user
 * agent and touch, because WordPress/Elementor can serve different markup to phones.
 */
final readonly class DeviceProfile
{
    public const DEFAULT_VIEWPORT_HEIGHT = 900;
    public const PHONE_MAX_WIDTH = 767;
    private const PHONE_USER_AGENT = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1';

    private function __construct(
        public int $width,
        public int $height,
        public bool $mobile,
        public ?string $userAgent,
    ) {}

    public static function for(ScreenSpec $spec): self
    {
        $phone = $spec->width <= self::PHONE_MAX_WIDTH;

        return new self($spec->width, $spec->viewportHeight ?? self::DEFAULT_VIEWPORT_HEIGHT, $phone, $phone ? self::PHONE_USER_AGENT : null);
    }
}
