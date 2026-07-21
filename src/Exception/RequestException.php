<?php

declare(strict_types=1);

namespace JardisAdapter\Http\Exception;

use Psr\Http\Client\RequestExceptionInterface;
use Psr\Http\Message\RequestInterface;

/**
 * Exception for invalid request errors (malformed URI).
 */
class RequestException extends HttpClientException implements RequestExceptionInterface
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
