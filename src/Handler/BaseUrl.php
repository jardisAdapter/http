<?php

declare(strict_types=1);

namespace JardisAdapter\Http\Handler;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Http\Message\UriInterface;

/**
 * Invokable handler that resolves relative URLs against a base URL.
 */
final class BaseUrl
{
    private readonly UriInterface $baseUri;

    public function __construct(UriFactoryInterface $uriFactory, string $baseUrl)
    {
        $this->baseUri = $uriFactory->createUri($baseUrl);
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
