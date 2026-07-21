<?php

declare(strict_types=1);

namespace JardisAdapter\Http\Message;

use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Http\Message\UriInterface;
use RuntimeException;

/** PSR-17 factory implementing all four factory interfaces. */
final class Psr17Factory implements
    RequestFactoryInterface,
    ResponseFactoryInterface,
    StreamFactoryInterface,
    UriFactoryInterface
{
    public function createRequest(string $method, $uri): RequestInterface
    {
        $uriObject = $uri instanceof UriInterface ? $uri : new Uri($uri);
        return new Request($method, $uriObject);
    }

    public function createResponse(int $code = 200, string $reasonPhrase = ''): ResponseInterface
    {
        $response = new Response($code);
        if ($reasonPhrase !== '') {
            $response = $response->withStatus($code, $reasonPhrase);
        }
        return $response;
    }

    public function createStream(string $content = ''): StreamInterface
    {
        return new Stream($content);
    }

    public function createStreamFromFile(string $filename, string $mode = 'r'): StreamInterface
    {
        $content = file_get_contents($filename);
        if ($content === false) {
            throw new RuntimeException('Unable to read file: ' . $filename);
        }
        return new Stream($content);
    }

    public function createStreamFromResource($resource): StreamInterface
    {
        $content = stream_get_contents($resource);
        if ($content === false) {
            throw new RuntimeException('Unable to read resource');
        }
        return new Stream($content);
    }

    public function createUri(string $uri = ''): UriInterface
    {
        return new Uri($uri);
    }
}
