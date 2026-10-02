<?php

declare(strict_types=1);

namespace DesignQa\Infrastructure\Figma;

use DesignQa\Infrastructure\Secrets\TokenStore;
use JsonException;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class HttpFigmaClient implements FigmaClient
{
    public const BASE_URL = 'https://api.figma.com';

    public function __construct(
        private HttpClientInterface $http,
        private TokenStore $tokens,
        private string $baseUrl = self::BASE_URL,
    ) {}

    public function me(): array
    {
        return $this->get('/v1/me');
    }

    public function fileVersion(string $fileKey): string
    {
        $path = sprintf('/v1/files/%s/meta', rawurlencode($fileKey));
        $data = $this->get($path);
        $file = is_array($data['file'] ?? null) ? $data['file'] : [];
        $version = $file['version'] ?? null;

        return is_string($version) && $version !== '' ? $version : throw FigmaApiException::invalidResponse($path);
    }

    public function nodes(string $fileKey, array $nodeIds): array
    {
        return $this->get(sprintf('/v1/files/%s/nodes', rawurlencode($fileKey)), ['ids' => implode(',', $nodeIds)]);
    }

    /**
     * @param array<string, string> $query
     *
     * @return array<string, mixed>
     */
    private function get(string $path, array $query = []): array
    {
        $token = $this->tokens->fetch() ?? throw FigmaApiException::missingToken($this->tokens->describe());

        try {
            $response = $this->http->request('GET', $this->baseUrl . $path, [
                'headers' => ['X-Figma-Token' => $token->reveal()],
                'query' => $query,
            ]);
            $status = $response->getStatusCode();
            if ($status !== 200) {
                $headers = $response->getHeaders(false);

                throw FigmaApiException::forStatus(
                    $status,
                    $path,
                    $headers['retry-after'][0] ?? null,
                    $headers['x-figma-rate-limit-type'][0] ?? null,
                );
            }
            $body = $response->getContent();
        } catch (ExceptionInterface $e) {
            throw FigmaApiException::network($path, $e->getMessage());
        }

        try {
            $data = json_decode($body, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw FigmaApiException::invalidResponse($path);
        }

        if (!is_array($data)) {
            throw FigmaApiException::invalidResponse($path);
        }

        /** @var array<string, mixed> $data */
        return $data;
    }
}
