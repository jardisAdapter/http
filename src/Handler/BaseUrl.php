<?php

declare(strict_types=1);

namespace JardisAdapter\Http\Handler;

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;

/**
 * Invokable handler that resolves relative URLs against a base URL.
 */
final class BaseUrl
{
    private readonly UriInterface $baseUri;

    public function __construct(string $baseUrl)
    {
        $this->baseUri = (new Psr17Factory())->createUri($baseUrl);
    }

    public function __invoke(RequestInterface $request): RequestInterface
    {
        if ($request->getUri()->getHost() !== '') {
            return $request;
        }

        $resolved = $this->baseUri->withPath(
            rtrim($this->baseUri->getPath(), '/') . '/' . ltrim($request->getUri()->getPath(), '/'),
        );

        if ($request->getUri()->getQuery() !== '') {
            $resolved = $resolved->withQuery($request->getUri()->getQuery());
        }

        return $request->withUri($resolved);
    }
}
