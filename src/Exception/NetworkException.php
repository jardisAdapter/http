<?php

declare(strict_types=1);

namespace JardisAdapter\Http\Exception;

use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestInterface;

/**
 * Exception for network-level errors (DNS, connection, timeout).
 */
class NetworkException extends HttpClientException implements NetworkExceptionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getRequest(): RequestInterface
    {
        return $this->request;
    }
}
