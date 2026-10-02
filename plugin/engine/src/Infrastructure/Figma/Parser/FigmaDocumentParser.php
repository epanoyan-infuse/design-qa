<?php

declare(strict_types=1);

namespace DesignQa\Infrastructure\Figma\Parser;

use DesignQa\Application\Port\SourceException;
use DesignQa\Domain\Model\Design;

/**
 * Turns a GET /v1/files/:key/nodes response into a Design.
 */
final readonly class FigmaDocumentParser
{
    public function __construct(
        private ScreenDetector $screens = new ScreenDetector(),
        private FigmaTextCollector $texts = new FigmaTextCollector(),
    ) {}

    /**
     * @param array<mixed> $response
     */
    public function parse(array $response, string $nodeId): Design
    {
        $document = is_array($response['nodes'] ?? null) && is_array($response['nodes'][$nodeId]['document'] ?? null)
            ? $response['nodes'][$nodeId]['document']
            : throw new SourceException(sprintf('Figma did not return node %s. Check the node-id in the Figma link.', $nodeId));

        $root = new FigmaNode($document);
        $screenNodes = $this->screens->screens($root);
        if ($screenNodes === []) {
            throw new SourceException(sprintf(
                'The Figma link points at "%s" (%s), which contains no screens. Link to a frame, or to a section that holds the screen frames.',
                $root->name(),
                $root->type(),
            ));
        }

        $screens = array_map(fn(FigmaNode $node) => $this->texts->collect($node, $this->screens->spec($node)), $screenNodes);
        $fileName = is_string($response['name'] ?? null) ? $response['name'] . ' / ' : '';

        return new Design($fileName . $root->name(), $screens);
    }
}
