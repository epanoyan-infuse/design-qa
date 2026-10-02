<?php

declare(strict_types=1);

namespace DesignQa\Infrastructure\Figma;

use DesignQa\Application\Port\DesignSource;
use DesignQa\Application\Port\SourceException;
use DesignQa\Domain\Model\Design;
use DesignQa\Infrastructure\Figma\Parser\FigmaDocumentParser;
use InvalidArgumentException;

final readonly class FigmaDesignSource implements DesignSource
{
    public function __construct(
        private FigmaClient $client,
        private FigmaDocumentParser $parser = new FigmaDocumentParser(),
    ) {}

    public function load(string $designUrl): Design
    {
        try {
            $url = FigmaUrl::parse($designUrl);
        } catch (InvalidArgumentException $e) {
            throw new SourceException($e->getMessage(), 0, $e);
        }
        if ($url->nodeId === null) {
            throw new SourceException('The Figma link has no node-id. In Figma, select the section or frame with the screens and use "Copy link to selection".');
        }

        try {
            $response = $this->client->nodes($url->fileKey, [$url->nodeId]);
        } catch (FigmaApiException $e) {
            throw new SourceException($e->getMessage(), 0, $e);
        }

        return $this->parser->parse($response, $url->nodeId);
    }
}
