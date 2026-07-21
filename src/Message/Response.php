<?php

declare(strict_types=1);

namespace JardisAdapter\Http\Message;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

/** PSR-7 Response implementation. */
final class Response implements ResponseInterface
{
    /** @var array<string, list<string>> */
    private array $headers = [];

    /** @var array<string, string> */
    private array $headerNames = [];

    private StreamInterface $body;
    private string $protocolVersion = '1.1';
    private string $reasonPhrase;

    public function __construct(
        private int $statusCode = 200,
        string $body = '',
    ) {
        $this->body = new Stream($body);
        $this->reasonPhrase = '';
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function withStatus(int $code, string $reasonPhrase = ''): ResponseInterface
    {
        $clone = clone $this;
        $clone->statusCode = $code;
        $clone->reasonPhrase = $reasonPhrase;
        return $clone;
    }

    public function getReasonPhrase(): string
    {
        return $this->reasonPhrase;
    }

    public function getProtocolVersion(): string
    {
        return $this->protocolVersion;
    }

    public function withProtocolVersion(string $version): static
    {
        $clone = clone $this;
        $clone->protocolVersion = $version;
        return $clone;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function hasHeader(string $name): bool
    {
        return isset($this->headerNames[strtolower($name)]);
    }

    public function getHeader(string $name): array
    {
        $normalized = $this->headerNames[strtolower($name)] ?? null;
        return $normalized !== null ? $this->headers[$normalized] : [];
    }

    public function getHeaderLine(string $name): string
    {
        return implode(', ', $this->getHeader($name));
    }

    public function withHeader(string $name, $value): static
    {
        $clone = clone $this;
        $normalized = strtolower($name);

        if (isset($clone->headerNames[$normalized])) {
            unset($clone->headers[$clone->headerNames[$normalized]]);
        }

        $clone->headerNames[$normalized] = $name;
        $clone->headers[$name] = array_values(is_array($value) ? $value : [$value]);
        return $clone;
    }

    public function withAddedHeader(string $name, $value): static
    {
        $clone = clone $this;
        $normalized = strtolower($name);
        $values = array_values(is_array($value) ? $value : [$value]);

        if (isset($clone->headerNames[$normalized])) {
            $existing = $clone->headerNames[$normalized];
            $clone->headers[$existing] = array_merge($clone->headers[$existing], $values);
        } else {
            $clone->headerNames[$normalized] = $name;
            $clone->headers[$name] = $values;
        }

        return $clone;
    }

    public function withoutHeader(string $name): static
    {
        $clone = clone $this;
        $normalized = strtolower($name);

        if (isset($clone->headerNames[$normalized])) {
            unset($clone->headers[$clone->headerNames[$normalized]]);
            unset($clone->headerNames[$normalized]);
        }

        return $clone;
    }

    public function getBody(): StreamInterface
    {
        return $this->body;
    }

    public function withBody(StreamInterface $body): static
    {
        $clone = clone $this;
        $clone->body = $body;
        return $clone;
    }
}
