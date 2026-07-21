<?php

declare(strict_types=1);

namespace JardisAdapter\Http\Tests\Unit\Handler;

use JardisAdapter\Http\Config\ClientConfig;
use JardisAdapter\Http\Exception\NetworkException;
use JardisAdapter\Http\Handler\Retry;
use JardisAdapter\Http\Message\Psr17Factory;
use JardisAdapter\Http\Message\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

final class RetryTest extends TestCase
{
    private Psr17Factory $factory;
    private ClientConfig $config;

    protected function setUp(): void
    {
        $this->factory = new Psr17Factory();
        $this->config = new ClientConfig();
    }

    public function testReturnsResponseOnFirstSuccess(): void
    {
        $transport = fn() => new Response(200);
        $handler = new Retry($transport(...), maxRetries: 3, delayMs: 0);

        $response = $handler($this->createRequest(), $this->config);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testRetriesOnServerError(): void
    {
        $callCount = 0;
        $transport = function () use (&$callCount) {
            $callCount++;
            return $callCount < 3 ? new Response(503) : new Response(200);
        };

        $handler = new Retry($transport(...), maxRetries: 3, delayMs: 0);
        $response = $handler($this->createRequest(), $this->config);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(3, $callCount);
    }

    public function testReturnsLastResponseWhenExhausted(): void
    {
        $callCount = 0;
        $transport = function () use (&$callCount) {
            $callCount++;
            return new Response(500);
        };

        $handler = new Retry($transport(...), maxRetries: 2, delayMs: 0);
        $response = $handler($this->createRequest(), $this->config);

        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame(3, $callCount);
    }

    public function testDoesNotRetryOn4xx(): void
    {
        $callCount = 0;
        $transport = function () use (&$callCount) {
            $callCount++;
            return new Response(404);
        };

        $handler = new Retry($transport(...), maxRetries: 3, delayMs: 0);
        $response = $handler($this->createRequest(), $this->config);

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame(1, $callCount);
    }

    public function testRetriesOnNetworkException(): void
    {
        $callCount = 0;
        $request = $this->createRequest();
        $transport = function () use (&$callCount, $request) {
            $callCount++;
            if ($callCount === 1) {
                throw new NetworkException($request, 'timeout');
            }
            return new Response(200);
        };

        $handler = new Retry($transport(...), maxRetries: 3, delayMs: 0);
        $response = $handler($request, $this->config);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(2, $callCount);
    }

    public function testThrowsAfterAllRetriesFailWithException(): void
    {
        $request = $this->createRequest();
        $transport = function () use ($request) {
            throw new NetworkException($request, 'connection refused');
        };

        $handler = new Retry($transport(...), maxRetries: 2, delayMs: 0);

        $this->expectException(NetworkException::class);
        $this->expectExceptionMessage('connection refused');

        $handler($request, $this->config);
    }

    private function createRequest(): RequestInterface
    {
        return $this->factory->createRequest('GET', 'https://example.com');
    }
}
