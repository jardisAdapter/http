<?php

declare(strict_types=1);

namespace JardisAdapter\Http\Tests\Unit\Handler;

use JardisAdapter\Http\Handler\DefaultHeaders;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;

final class DefaultHeadersTest extends TestCase
{
    private Psr17Factory $factory;

    protected function setUp(): void
    {
        $this->factory = new Psr17Factory();
    }

    public function testAddsDefaultHeaders(): void
    {
        $handler = new DefaultHeaders([
            'X-Api-Key' => 'secret',
            'Accept' => 'application/json',
        ]);
        $request = $this->factory->createRequest('GET', 'https://example.com');

        $result = $handler($request);

        $this->assertSame('secret', $result->getHeaderLine('X-Api-Key'));
        $this->assertSame('application/json', $result->getHeaderLine('Accept'));
    }

    public function testDoesNotOverrideExistingHeaders(): void
    {
        $handler = new DefaultHeaders(['Accept' => 'application/json']);
        $request = $this->factory->createRequest('GET', 'https://example.com')
            ->withHeader('Accept', 'text/html');

        $result = $handler($request);

        $this->assertSame('text/html', $result->getHeaderLine('Accept'));
    }

    public function testMixesExistingAndDefaultHeaders(): void
    {
        $handler = new DefaultHeaders([
            'Accept' => 'application/json',
            'X-Api-Key' => 'secret',
        ]);
        $request = $this->factory->createRequest('GET', 'https://example.com')
            ->withHeader('Accept', 'text/html');

        $result = $handler($request);

        $this->assertSame('text/html', $result->getHeaderLine('Accept'));
        $this->assertSame('secret', $result->getHeaderLine('X-Api-Key'));
    }
}
