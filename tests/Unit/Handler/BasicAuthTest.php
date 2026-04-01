<?php

declare(strict_types=1);

namespace JardisAdapter\Http\Tests\Unit\Handler;

use JardisAdapter\Http\Handler\BasicAuth;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;

final class BasicAuthTest extends TestCase
{
    public function testAddsBasicAuthorizationHeader(): void
    {
        $auth = new BasicAuth('user', 'pass');
        $request = (new Psr17Factory())->createRequest('GET', 'https://example.com');

        $result = $auth($request);

        $expected = 'Basic ' . base64_encode('user:pass');
        $this->assertSame($expected, $result->getHeaderLine('Authorization'));
    }

    public function testHandlesSpecialCharactersInCredentials(): void
    {
        $auth = new BasicAuth('user@domain.com', 'p@ss:w0rd!');
        $request = (new Psr17Factory())->createRequest('GET', 'https://example.com');

        $result = $auth($request);

        $expected = 'Basic ' . base64_encode('user@domain.com:p@ss:w0rd!');
        $this->assertSame($expected, $result->getHeaderLine('Authorization'));
    }
}
