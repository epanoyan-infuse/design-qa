<?php

declare(strict_types=1);

namespace DesignQa\Tests\Unit\Infrastructure\Figma;

use DesignQa\Infrastructure\Figma\FigmaApiException;
use DesignQa\Infrastructure\Figma\HttpFigmaClient;
use DesignQa\Tests\Support\InMemoryTokenStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

#[CoversClass(HttpFigmaClient::class)]
#[CoversClass(FigmaApiException::class)]
final class HttpFigmaClientTest extends TestCase
{
    private const TOKEN = 'figd_secret-test-token';

    public function testSendsTokenHeaderAndDecodesNodes(): void
    {
        $response = new MockResponse('{"nodes":{"2421:637":{"document":{"id":"2421:637"}}}}');
        $client = new HttpFigmaClient(new MockHttpClient($response), new InMemoryTokenStore(self::TOKEN));

        $data = $client->nodes('BAJsKFuGKXBxu2aV44JIT8', ['2421:637', '1:2']);

        self::assertSame(['nodes' => ['2421:637' => ['document' => ['id' => '2421:637']]]], $data);
        self::assertSame('GET', $response->getRequestMethod());
        self::assertSame('https://api.figma.com/v1/files/BAJsKFuGKXBxu2aV44JIT8/nodes?ids=2421:637%2C1:2', $response->getRequestUrl());
        self::assertContains('X-Figma-Token: ' . self::TOKEN, $response->getRequestOptions()['headers']);
    }

    public function testFailsClearlyWithoutToken(): void
    {
        $client = new HttpFigmaClient(new MockHttpClient(), new InMemoryTokenStore(null, 'macOS keychain'));

        $this->expectException(FigmaApiException::class);
        $this->expectExceptionMessage('No Figma key found (looked in: macOS keychain).');
        $client->me();
    }

    /**
     * @return iterable<string, array{int, string}>
     */
    public static function errorStatuses(): iterable
    {
        yield 'rejected key' => [403, 'Figma rejected the key'];
        yield 'not found' => [404, 'Figma file or node not found'];
        yield 'rate limit' => [429, 'Figma read limit reached, try again in 60 seconds.'];
        yield 'server error' => [502, 'Figma is having problems'];
    }

    #[DataProvider('errorStatuses')]
    public function testTranslatesErrorsWithoutLeakingToken(int $status, string $expected): void
    {
        $response = new MockResponse('{"err":"x"}', ['http_code' => $status, 'response_headers' => ['retry-after' => '60']]);
        $client = new HttpFigmaClient(new MockHttpClient($response), new InMemoryTokenStore(self::TOKEN));

        try {
            $client->me();
            self::fail('Expected a FigmaApiException.');
        } catch (FigmaApiException $e) {
            self::assertStringContainsString($expected, $e->getMessage());
            self::assertStringNotContainsString(self::TOKEN, $e->getMessage());
            self::assertSame($status, $e->getCode());
        }
    }

    public function testRateLimitOnViewSeatExplainsTheSeatAndWaitInHours(): void
    {
        $response = new MockResponse('{}', ['http_code' => 429, 'response_headers' => ['retry-after' => '210565', 'x-figma-rate-limit-type' => 'low']]);
        $client = new HttpFigmaClient(new MockHttpClient($response), new InMemoryTokenStore(self::TOKEN));

        $this->expectExceptionMessage('try again in about 58 hours. The key belongs to an account with a View or Collab seat');
        $client->nodes('AbC', ['1:2']);
    }

    public function testFileVersionReadsMetaEndpoint(): void
    {
        $response = new MockResponse('{"file":{"name":"Kesler","version":"2404367974164068797"}}');
        $client = new HttpFigmaClient(new MockHttpClient($response), new InMemoryTokenStore(self::TOKEN));

        self::assertSame('2404367974164068797', $client->fileVersion('BAJsKFuGKXBxu2aV44JIT8'));
        self::assertSame('https://api.figma.com/v1/files/BAJsKFuGKXBxu2aV44JIT8/meta', $response->getRequestUrl());
    }

    public function testRejectsInvalidJson(): void
    {
        $client = new HttpFigmaClient(new MockHttpClient(new MockResponse('<html>')), new InMemoryTokenStore(self::TOKEN));

        $this->expectException(FigmaApiException::class);
        $this->expectExceptionMessage('not valid JSON');
        $client->me();
    }
}
