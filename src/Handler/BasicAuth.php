<?php

declare(strict_types=1);

namespace JardisAdapter\Http\Handler;

use Psr\Http\Message\RequestInterface;

/**
 * Invokable handler that adds Basic authentication.
 */
final class BasicAuth
{
    public function __construct(
        private readonly string $user,
        private readonly string $password,
    ) {
    }

    public function __invoke(RequestInterface $request): RequestInterface
    {
        $credentials = base64_encode($this->user . ':' . $this->password);

        return $request->withHeader('Authorization', 'Basic ' . $credentials);
    }
}
