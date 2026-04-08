<?php

declare(strict_types=1);

namespace JardisAdapter\Http\Handler;

use Psr\Http\Message\RequestInterface;

/**
 * Invokable handler that adds Bearer token authentication.
 */
final class BearerAuth
{
    public function __construct(
        private readonly string $token,
    ) {
    }

    public function __invoke(RequestInterface $request): RequestInterface
    {
        return $request->withHeader('Authorization', 'Bearer ' . $this->token);
    }
}
