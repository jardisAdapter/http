<?php

declare(strict_types=1);

namespace JardisAdapter\Http\Tests\Unit\Factory;

use JardisAdapter\Http\Factory\StreamFactory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;

final class StreamFactoryTest extends TestCase
{
    public function testCreatesStreamFromString(): void
    {
        $factory = new StreamFactory();

        $stream = $factory->createStream('hello world');

        $this->assertInstanceOf(StreamInterface::class, $stream);
        $this->assertSame('hello world', (string) $stream);
    }

    public function testCreatesEmptyStream(): void
    {
        $factory = new StreamFactory();

        $stream = $factory->createStream();

        $this->assertSame('', (string) $stream);
    }
}
