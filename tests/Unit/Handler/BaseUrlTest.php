<?php

declare(strict_types=1);

namespace JardisAdapter\Http\Tests\Unit\Handler;

use JardisAdapter\Http\Handler\BaseUrl;
use JardisAdapter\Http\Message\Psr17Factory;
use PHPUnit\Framework\TestCase;

final class BaseUrlTest extends TestCase
{
    private Psr17Factory $factory;

    protected function setUp(): void
    {
        $this->factory = new Psr17Factory();
    }

    public function testResolvesRelativeUrl(): void
    {
        $handler = new BaseUrl($this->factory, 'https://api.example.com/v1');
        $request = $this->factory->createRequest('GET', '/users');

        $result = $handler($request);

        $this->assertSame('https://api.example.com/v1/users', (string) $result->getUri());
    }

    public function testDoesNotOverrideAbsoluteUrl(): void
    {
        $handler = new BaseUrl($this->factory, 'https://api.example.com');
        $request = $this->factory->createRequest('GET', 'https://other.com/path');

        $result = $handler($request);

        $this->assertSame('https://other.com/path', (string) $result->getUri());
    }

    public function testPreservesQueryString(): void
    {
        $handler = new BaseUrl($this->factory, 'https://api.example.com/v1');
        $request = $this->factory->createRequest('GET', '/users?page=2&limit=10');

        $result = $handler($request);

        $this->assertSame('https://api.example.com/v1/users?page=2&limit=10', (string) $result->getUri());
    }

    public function testHandlesTrailingSlashInBaseUrl(): void
    {
        $handler = new BaseUrl($this->factory, 'https://api.example.com/v1/');
        $request = $this->factory->createRequest('GET', '/users');

        $result = $handler($request);

        $this->assertSame('https://api.example.com/v1/users', (string) $result->getUri());
    }

    public function testHandlesNoLeadingSlashInPath(): void
    {
        $handler = new BaseUrl($this->factory, 'https://api.example.com/v1');
        $request = $this->factory->createRequest('GET', 'users');

        $result = $handler($request);

        $this->assertSame('https://api.example.com/v1/users', (string) $result->getUri());
    }
}
