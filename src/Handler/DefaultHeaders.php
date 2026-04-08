<?php

declare(strict_types=1);

namespace JardisAdapter\Http\Handler;

use Psr\Http\Message\RequestInterface;

/**
 * Invokable handler that applies default headers to requests.
 */
final class DefaultHeaders
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        private readonly array $headers,
    ) {
    }

    public function __invoke(RequestInterface $request): RequestInterface
    {
        foreach ($this->headers as $name => $value) {
            if (!$request->hasHeader($name)) {
                $request = $request->withHeader($name, $value);
            }
        }

        return $request;
    }
}
