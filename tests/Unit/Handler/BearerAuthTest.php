<?php

declare(strict_types=1);

namespace JardisAdapter\Http\Tests\Unit\Handler;

use JardisAdapter\Http\Handler\BearerAuth;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;

final class BearerAuthTest extends TestCase
{
    public function testAddsAuthorizationHeader(): void
    {
        $auth = new BearerAuth('my-secret-token');
        $request = (new Psr17Factory())->createRequest('GET', 'https://example.com');

        $result = $auth($request);

        $this->assertSame('Bearer my-secret-token', $result->getHeaderLine('Authorization'));
    }

    public function testOverwritesExistingAuthorizationHeader(): void
    {
        $auth = new BearerAuth('new-token');
        $request = (new Psr17Factory())->createRequest('GET', 'https://example.com')
            ->withHeader('Authorization', 'Bearer old-token');

        $result = $auth($request);

        $this->assertSame('Bearer new-token', $result->getHeaderLine('Authorization'));
    }

    public function testPreservesOtherHeaders(): void
    {
        $auth = new BearerAuth('token');
        $request = (new Psr17Factory())->createRequest('GET', 'https://example.com')
            ->withHeader('Accept', 'application/json');

        $result = $auth($request);

        $this->assertSame('application/json', $result->getHeaderLine('Accept'));
        $this->assertSame('Bearer token', $result->getHeaderLine('Authorization'));
    }
}
