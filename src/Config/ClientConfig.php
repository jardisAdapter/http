<?php

declare(strict_types=1);

namespace JardisAdapter\Http\Config;

/**
 * HTTP client configuration value object.
 */
final readonly class ClientConfig
{
    /**
     * @param array<string, string> $defaultHeaders
     */
    public function __construct(
        public int $timeout = 30,
        public int $connectTimeout = 10,
        public ?string $baseUrl = null,
        public bool $verifySsl = true,
        public array $defaultHeaders = [],
        public ?string $bearerToken = null,
        public ?string $basicUser = null,
        public ?string $basicPassword = null,
        public int $maxRetries = 0,
        public int $retryDelayMs = 100,
    ) {
    }
}
