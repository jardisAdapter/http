<?php

declare(strict_types=1);

namespace JardisAdapter\Http\Tests\Unit\Exception;

use JardisAdapter\Http\Exception\HttpClientException;
use JardisAdapter\Http\Exception\NetworkException;
use JardisAdapter\Http\Exception\RequestException;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Client\RequestExceptionInterface;

final class ExceptionTest extends TestCase
{
    public function testHttpClientExceptionImplementsPsrInterface(): void
    {
        $exception = new HttpClientException('test error');

        $this->assertInstanceOf(ClientExceptionInterface::class, $exception);
        $this->assertSame('test error', $exception->getMessage());
    }

    public function testNetworkExceptionHoldsRequest(): void
    {
        $request = (new Psr17Factory())->createRequest('GET', 'https://example.com');
        $exception = new NetworkException($request, 'connection refused', 7);

        $this->assertInstanceOf(NetworkExceptionInterface::class, $exception);
        $this->assertInstanceOf(ClientExceptionInterface::class, $exception);
        $this->assertSame($request, $exception->getRequest());
        $this->assertSame('connection refused', $exception->getMessage());
        $this->assertSame(7, $exception->getCode());
    }

    public function testRequestExceptionHoldsRequest(): void
    {
        $request = (new Psr17Factory())->createRequest('POST', 'https://example.com');
        $exception = new RequestException($request, 'malformed URI');

        $this->assertInstanceOf(RequestExceptionInterface::class, $exception);
        $this->assertInstanceOf(ClientExceptionInterface::class, $exception);
        $this->assertSame($request, $exception->getRequest());
        $this->assertSame('malformed URI', $exception->getMessage());
    }
}
