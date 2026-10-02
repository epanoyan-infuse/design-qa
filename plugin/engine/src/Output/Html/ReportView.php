<?php

declare(strict_types=1);

namespace DesignQa\Output\Html;

/**
 * Everything the HTML report shows, already decided and formatted. The template only lays it out.
 */
final readonly class ReportView
{
    /**
     * @param list<DeviceView> $devices main tabs, desktop first
     * @param list<string>     $notChecked screens not checked, with the reason
     */
    public function __construct(
        public string $title,
        public string $pageUrl,
        public string $checkedAt,
        public int $critical,
        public int $nonCritical,
        public int $manual,
        public int $compared,
        public int $total,
        public array $devices,
        public array $notChecked,
        public string $dataJson,
    ) {}

    public function verdict(): string
    {
        return match (true) {
            $this->critical === 1 => 'Not matching yet: 1 critical issue',
            $this->critical > 1 => sprintf('Not matching yet: %d critical issues', $this->critical),
            default => 'No critical issues',
        };
    }
}
