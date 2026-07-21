<?php

declare(strict_types=1);

namespace JardisAdapter\Http\Message;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

/** PSR-7 Request implementation. */
final class Request implements RequestInterface
{
    /** @var array<string, list<string>> */
    private array $headers = [];

    /** @var array<string, string> */
    private array $headerNames = [];

    private StreamInterface $body;
    private string $protocolVersion = '1.1';
    private string $requestTarget = '';

    public function __construct(
        private string $method,
        private UriInterface $uri,
    ) {
        $this->body = new Stream();

        $host = $uri->getHost();
        if ($host !== '') {
            $this->headerNames['host'] = 'Host';
            $this->headers['Host'] = [$host];
        }
    }

    public function getRequestTarget(): string
    {
        if ($this->requestTarget !== '') {
            return $this->requestTarget;
        }

        $target = $this->uri->getPath();
        if ($target === '') {
            $target = '/';
        }

        $query = $this->uri->getQuery();
        if ($query !== '') {
            $target .= '?' . $query;
        }

        return $target;
    }

    public function withRequestTarget(string $requestTarget): RequestInterface
    {
        $clone = clone $this;
        $clone->requestTarget = $requestTarget;
        return $clone;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function withMethod(string $method): RequestInterface
    {
        $clone = clone $this;
        $clone->method = $method;
        return $clone;
    }

    public function getUri(): UriInterface
    {
        return $this->uri;
    }

    public function withUri(UriInterface $uri, bool $preserveHost = false): RequestInterface
    {
        $clone = clone $this;
        $clone->uri = $uri;

        if (!$preserveHost && $uri->getHost() !== '') {
            $clone->headerNames['host'] = 'Host';
            $clone->headers['Host'] = [$uri->getHost()];
        }

        return $clone;
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

        // Remove old header with same normalized name
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
