<?php

declare(strict_types=1);

namespace JardisAdapter\Http\Tests\Unit\Factory;

use JardisAdapter\Http\Factory\RequestFactory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

final class RequestFactoryTest extends TestCase
{
    public function testCreatesGetRequest(): void
    {
        $factory = new RequestFactory();

        $request = $factory->createRequest('GET', 'https://example.com/path');

        $this->assertInstanceOf(RequestInterface::class, $request);
        $this->assertSame('GET', $request->getMethod());
        $this->assertSame('https://example.com/path', (string) $request->getUri());
    }

    public function testCreatesPostRequest(): void
    {
        $factory = new RequestFactory();

        $request = $factory->createRequest('POST', 'https://example.com/api');

        $this->assertSame('POST', $request->getMethod());
    }
}
