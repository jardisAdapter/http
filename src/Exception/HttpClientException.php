<?php

declare(strict_types=1);

namespace JardisAdapter\Http\Exception;

use Psr\Http\Client\ClientExceptionInterface;
use RuntimeException;

/**
 * Base exception for HTTP client errors.
 */
class HttpClientException extends RuntimeException implements ClientExceptionInterface
{
}
