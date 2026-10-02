<?php

declare(strict_types=1);

namespace DesignQa\Cli;

use DateTimeZone;

/**
 * Picks the computer's time zone for report times. PHP falls back to UTC when no zone is
 * configured (some builds report a compiled-in "UTC" through ini_get). A zone configured on
 * purpose, in php.ini or with `php -d date.timezone=...`, is always respected.
 */
final class LocalTimeZone
{
    /**
     * @param string|false $tz              the TZ environment variable
     * @param string|false $localtimeLink   target of /etc/localtime, e.g. ".../zoneinfo/Asia/Yerevan"
     * @param string|false $configuredZone  get_cfg_var('date.timezone'): set in php.ini or with -d, false otherwise
     *
     * @return string|null the zone to use, or null to keep PHP's setting
     */
    public static function detect(string|false $tz, string|false $localtimeLink, string|false $configuredZone): ?string
    {
        if (is_string($configuredZone) && $configuredZone !== '') {
            return null; // configured on purpose
        }
        $known = timezone_identifiers_list(DateTimeZone::ALL_WITH_BC);
        $candidates = [
            ltrim((string) $tz, ':'),
            $localtimeLink === false ? '' : (string) preg_replace('~^.*/zoneinfo/~', '', $localtimeLink),
        ];
        foreach ($candidates as $zone) {
            if ($zone !== '' && in_array($zone, $known, true)) {
                return $zone;
            }
        }

        return null;
    }

    public static function apply(): void
    {
        $configured = get_cfg_var('date.timezone');
        $zone = self::detect(
            getenv('TZ'),
            is_link('/etc/localtime') ? readlink('/etc/localtime') : false,
            is_string($configured) ? $configured : false,
        );
        if ($zone !== null) {
            date_default_timezone_set($zone);
        }
    }
}
