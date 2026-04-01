<?php

declare(strict_types=1);

namespace JardisAdapter\Http;

use Closure;
use JardisAdapter\Http\Config\ClientConfig;
use JardisAdapter\Http\Handler\BaseUrl;
use JardisAdapter\Http\Handler\BasicAuth;
use JardisAdapter\Http\Handler\BearerAuth;
use JardisAdapter\Http\Handler\CurlTransport;
use JardisAdapter\Http\Handler\DefaultHeaders;
use JardisAdapter\Http\Handler\Retry;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * PSR-18 HTTP client — orchestrates handler pipeline from config.
 */
final class HttpClient implements ClientInterface
{
    /** @var list<Closure(RequestInterface): RequestInterface> */
    private readonly array $transformers;

    /** @var Closure(RequestInterface, ClientConfig): ResponseInterface */
    private readonly Closure $transport;

    private readonly Psr17Factory $factory;

    /**
     * @param ?Closure(RequestInterface, ClientConfig): ResponseInterface $transport
     */
    public function __construct(
        private readonly ClientConfig $config = new ClientConfig(),
        ?Closure $transport = null,
    ) {
        $this->factory = new Psr17Factory();
        $this->transformers = $this->buildTransformers();
        $this->transport = $this->buildTransport($transport);
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        foreach ($this->transformers as $transform) {
            $request = $transform($request);
        }

        return ($this->transport)($request, $this->config);
    }

    /**
     * @param array<string, string> $headers
     */
    public function get(string $uri, array $headers = []): ResponseInterface
    {
        return $this->send('GET', $uri, $headers);
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $headers
     */
    public function post(string $uri, array $data = [], array $headers = []): ResponseInterface
    {
        return $this->sendJson('POST', $uri, $data, $headers);
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $headers
     */
    public function put(string $uri, array $data = [], array $headers = []): ResponseInterface
    {
        return $this->sendJson('PUT', $uri, $data, $headers);
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $headers
     */
    public function patch(string $uri, array $data = [], array $headers = []): ResponseInterface
    {
        return $this->sendJson('PATCH', $uri, $data, $headers);
    }

    /**
     * @param array<string, string> $headers
     */
    public function delete(string $uri, array $headers = []): ResponseInterface
    {
        return $this->send('DELETE', $uri, $headers);
    }

    /**
     * @param array<string, string> $headers
     */
    public function head(string $uri, array $headers = []): ResponseInterface
    {
        return $this->send('HEAD', $uri, $headers);
    }

    /**
     * @param array<string, string> $headers
     */
    private function send(string $method, string $uri, array $headers): ResponseInterface
    {
        $request = $this->factory->createRequest($method, $uri);

        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        return $this->sendRequest($request);
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, string> $headers
     */
    private function sendJson(
        string $method,
        string $uri,
        array $data,
        array $headers,
    ): ResponseInterface {
        $body = json_encode($data, JSON_THROW_ON_ERROR);

        $request = $this->factory->createRequest($method, $uri)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Accept', 'application/json')
            ->withBody($this->factory->createStream($body));

        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        return $this->sendRequest($request);
    }

    /**
     * @return list<Closure(RequestInterface): RequestInterface>
     */
    private function buildTransformers(): array
    {
        $transformers = [];

        if ($this->config->baseUrl !== null) {
            $transformers[] = (new BaseUrl($this->config->baseUrl))->__invoke(...);
        }

        if ($this->config->defaultHeaders !== []) {
            $transformers[] = (new DefaultHeaders($this->config->defaultHeaders))->__invoke(...);
        }

        if ($this->config->bearerToken !== null) {
            $transformers[] = (new BearerAuth($this->config->bearerToken))->__invoke(...);
        } elseif ($this->config->basicUser !== null && $this->config->basicPassword !== null) {
            $transformers[] = (new BasicAuth($this->config->basicUser, $this->config->basicPassword))->__invoke(...);
        }

        return $transformers;
    }

    /**
     * @param ?Closure(RequestInterface, ClientConfig): ResponseInterface $transport
     * @return Closure(RequestInterface, ClientConfig): ResponseInterface
     */
    private function buildTransport(?Closure $transport): Closure
    {
        $transport = $transport ?? (new CurlTransport())->__invoke(...);

        if ($this->config->maxRetries > 0) {
            $transport = (new Retry($transport, $this->config->maxRetries, $this->config->retryDelayMs))
                ->__invoke(...);
        }

        return $transport;
    }
}
