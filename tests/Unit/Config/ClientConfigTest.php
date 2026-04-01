<?php

declare(strict_types=1);

namespace JardisAdapter\Http\Tests\Unit\Config;

use JardisAdapter\Http\Config\ClientConfig;
use PHPUnit\Framework\TestCase;

final class ClientConfigTest extends TestCase
{
    public function testCreatesWithDefaults(): void
    {
        $config = new ClientConfig();

        $this->assertSame(30, $config->timeout);
        $this->assertSame(10, $config->connectTimeout);
        $this->assertNull($config->baseUrl);
        $this->assertTrue($config->verifySsl);
        $this->assertSame([], $config->defaultHeaders);
    }

    public function testCreatesWithCustomValues(): void
    {
        $config = new ClientConfig(
            timeout: 60,
            connectTimeout: 5,
            baseUrl: 'https://api.example.com',
            verifySsl: false,
            defaultHeaders: ['X-Api-Key' => 'test-key'],
        );

        $this->assertSame(60, $config->timeout);
        $this->assertSame(5, $config->connectTimeout);
        $this->assertSame('https://api.example.com', $config->baseUrl);
        $this->assertFalse($config->verifySsl);
        $this->assertSame(['X-Api-Key' => 'test-key'], $config->defaultHeaders);
    }
}
