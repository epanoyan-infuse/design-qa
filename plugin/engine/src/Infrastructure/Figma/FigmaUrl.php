<?php

declare(strict_types=1);

namespace DesignQa\Infrastructure\Figma;

use InvalidArgumentException;

/**
 * A parsed Figma link: the file key and, optionally, the node the link points at.
 *
 * Accepts figma.com/design, /file and /proto links. For branch links
 * (/design/<main key>/branch/<branch key>/...) the branch key is the file to read. The "node-id" query value uses "-" in
 * browser links ("2421-637") and ":" in the API ("2421:637"); both are normalised to ":".
 */
final readonly class FigmaUrl
{
    private function __construct(
        public string $fileKey,
        public ?string $nodeId,
    ) {}

    public static function parse(string $url): self
    {
        $parts = parse_url(trim($url));
        $host = strtolower(is_array($parts) ? $parts['host'] ?? '' : '');
        if (!is_array($parts) || ($host !== 'figma.com' && !str_ends_with($host, '.figma.com'))) {
            throw new InvalidArgumentException(sprintf('Not a Figma link: "%s".', $url));
        }

        if (preg_match('~^/(?:design|file|proto)/([A-Za-z0-9]+)(?:/branch/([A-Za-z0-9]+))?(?:/|$)~', $parts['path'] ?? '', $match) !== 1) {
            throw new InvalidArgumentException(sprintf('The Figma link has no file key: "%s".', $url));
        }
        $fileKey = ($match[2] ?? '') !== '' ? $match[2] : $match[1];

        parse_str($parts['query'] ?? '', $query);
        $nodeId = $query['node-id'] ?? null;
        $nodeId = is_string($nodeId) && $nodeId !== '' ? self::normaliseNodeId($nodeId) : null;

        return new self($fileKey, $nodeId);
    }

    private static function normaliseNodeId(string $nodeId): string
    {
        $nodeId = str_replace('-', ':', rawurldecode($nodeId));
        if (preg_match('~^[A-Za-z]?\d+:\d+(?:;\d+:\d+)*$~', $nodeId) !== 1) {
            throw new InvalidArgumentException(sprintf('Invalid Figma node id: "%s".', $nodeId));
        }

        return $nodeId;
    }

    /**
     * A browser link that opens this exact node in Figma, selected (the reverse of normaliseNodeId:
     * "-" in browser links, ":" internally).
     */
    public static function nodeLink(string $fileKey, string $nodeId): string
    {
        return sprintf('https://www.figma.com/design/%s/?node-id=%s', rawurlencode($fileKey), rawurlencode(str_replace(':', '-', $nodeId)));
    }
}
