<?php

declare(strict_types=1);

namespace JardisAdapter\Http\Handler;

use Closure;
use JardisAdapter\Http\Config\ClientConfig;
use JardisAdapter\Http\Exception\HttpClientException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Invokable handler that wraps a transport with retry logic.
 */
final class Retry
{
    /** @var Closure(RequestInterface, ClientConfig): ResponseInterface */
    private readonly Closure $transport;

    /**
     * @param Closure(RequestInterface, ClientConfig): ResponseInterface $transport
     */
    public function __construct(
        Closure $transport,
        private readonly int $maxRetries,
        private readonly int $delayMs,
    ) {
        $this->transport = $transport;
    }

    public function __invoke(RequestInterface $request, ClientConfig $config): ResponseInterface
    {
        $attempt = 0;
        $lastResponse = null;

        while ($attempt <= $this->maxRetries) {
            try {
                $response = ($this->transport)($request, $config);

                if ($attempt < $this->maxRetries && $response->getStatusCode() >= 500) {
                    $lastResponse = $response;
                    $this->delay($attempt);
                    $attempt++;
                    continue;
                }

                return $response;
            } catch (HttpClientException $e) {
                if ($attempt >= $this->maxRetries) {
                    throw $e;
                }

                $this->delay($attempt);
                $attempt++;
            }
        }

        /** @var ResponseInterface $lastResponse */
        return $lastResponse;
    }

    private function delay(int $attempt): void
    {
        $delayMs = $this->delayMs * (2 ** $attempt);
        usleep($delayMs * 1000);
    }
}
