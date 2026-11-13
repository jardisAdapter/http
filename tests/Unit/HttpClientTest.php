<?php

declare(strict_types=1);

namespace JardisAdapter\Http\Tests\Unit;

use JardisAdapter\Http\Config\ClientConfig;
use JardisAdapter\Http\HttpClient;
use JardisAdapter\Http\Message\Psr17Factory;
use JardisAdapter\Http\Message\Response;
use JardisSupport\Contract\Http\HttpServiceInterface;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

final class HttpClientTest extends TestCase
{
    private Psr17Factory $factory;

    protected function setUp(): void
    {
        $this->factory = new Psr17Factory();
    }

    public function testDelegatesToTransport(): void
    {
        $capturedRequest = null;

        $transport = function (RequestInterface $request) use (&$capturedRequest) {
            $capturedRequest = $request;
            return new Response(200);
        };

        $client = $this->createClient(transport: $transport(...));
        $response = $client->sendRequest($this->factory->createRequest('GET', 'https://example.com'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('https://example.com', (string) $capturedRequest->getUri());
    }

    public function testAppliesBaseUrlTransformer(): void
    {
        $capturedRequest = null;

        $transport = function (RequestInterface $request) use (&$capturedRequest) {
            $capturedRequest = $request;
            return new Response(200);
        };

        $config = new ClientConfig(baseUrl: 'https://api.example.com/v1');
        $client = $this->createClient(config: $config, transport: $transport(...));
        $client->sendRequest($this->factory->createRequest('GET', '/users'));

        $this->assertSame('https://api.example.com/v1/users', (string) $capturedRequest->getUri());
    }

    public function testAppliesDefaultHeadersTransformer(): void
    {
        $capturedRequest = null;

        $transport = function (RequestInterface $request) use (&$capturedRequest) {
            $capturedRequest = $request;
            return new Response(200);
        };

        $config = new ClientConfig(defaultHeaders: ['X-Api-Key' => 'secret']);
        $client = $this->createClient(config: $config, transport: $transport(...));
        $client->sendRequest($this->factory->createRequest('GET', 'https://example.com'));

        $this->assertSame('secret', $capturedRequest->getHeaderLine('X-Api-Key'));
    }

    public function testAppliesAuthTransformer(): void
    {
        $capturedRequest = null;

        $transport = function (RequestInterface $request) use (&$capturedRequest) {
            $capturedRequest = $request;
            return new Response(200);
        };

        $config = new ClientConfig(bearerToken: 'my-token');
        $client = $this->createClient(config: $config, transport: $transport(...));
        $client->sendRequest($this->factory->createRequest('GET', 'https://example.com'));

        $this->assertSame('Bearer my-token', $capturedRequest->getHeaderLine('Authorization'));
    }

    public function testAppliesBasicAuthTransformer(): void
    {
        $capturedRequest = null;

        $transport = function (RequestInterface $request) use (&$capturedRequest) {
            $capturedRequest = $request;
            return new Response(200);
        };

        $config = new ClientConfig(basicUser: 'user', basicPassword: 'pass');
        $client = $this->createClient(config: $config, transport: $transport(...));
        $client->sendRequest($this->factory->createRequest('GET', 'https://example.com'));

        $expected = 'Basic ' . base64_encode('user:pass');
        $this->assertSame($expected, $capturedRequest->getHeaderLine('Authorization'));
    }

    public function testBearerTakesPrecedenceOverBasicAuth(): void
    {
        $capturedRequest = null;

        $transport = function (RequestInterface $request) use (&$capturedRequest) {
            $capturedRequest = $request;
            return new Response(200);
        };

        $config = new ClientConfig(bearerToken: 'token', basicUser: 'user', basicPassword: 'pass');
        $client = $this->createClient(config: $config, transport: $transport(...));
        $client->sendRequest($this->factory->createRequest('GET', 'https://example.com'));

        $this->assertSame('Bearer token', $capturedRequest->getHeaderLine('Authorization'));
    }

    public function testWrapsTransportWithRetry(): void
    {
        $callCount = 0;

        $transport = function () use (&$callCount) {
            $callCount++;
            return $callCount < 3 ? new Response(503) : new Response(200);
        };

        $config = new ClientConfig(maxRetries: 3, retryDelayMs: 0);
        $client = $this->createClient(config: $config, transport: $transport(...));
        $response = $client->sendRequest($this->factory->createRequest('GET', 'https://example.com'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(3, $callCount);
    }

    public function testNoRetryWhenNotConfigured(): void
    {
        $callCount = 0;

        $transport = function () use (&$callCount) {
            $callCount++;
            return new Response(500);
        };

        $client = $this->createClient(transport: $transport(...));
        $response = $client->sendRequest($this->factory->createRequest('GET', 'https://example.com'));

        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame(1, $callCount);
    }

    public function testPipelineOrder(): void
    {
        $capturedRequest = null;

        $transport = function (RequestInterface $request) use (&$capturedRequest) {
            $capturedRequest = $request;
            return new Response(200);
        };

        $config = new ClientConfig(
            baseUrl: 'https://api.example.com',
            defaultHeaders: ['Accept' => 'application/json'],
            bearerToken: 'token',
        );
        $client = $this->createClient(config: $config, transport: $transport(...));
        $client->sendRequest($this->factory->createRequest('GET', '/users'));

        $this->assertSame('https://api.example.com/users', (string) $capturedRequest->getUri());
        $this->assertSame('application/json', $capturedRequest->getHeaderLine('Accept'));
        $this->assertSame('Bearer token', $capturedRequest->getHeaderLine('Authorization'));
    }

    public function testSkipsTransformersWhenNotConfigured(): void
    {
        $capturedRequest = null;

        $transport = function (RequestInterface $request) use (&$capturedRequest) {
            $capturedRequest = $request;
            return new Response(200);
        };

        $client = $this->createClient(transport: $transport(...));
        $request = $this->factory->createRequest('GET', 'https://example.com');
        $client->sendRequest($request);

        $this->assertSame('https://example.com', (string) $capturedRequest->getUri());
        $this->assertFalse($capturedRequest->hasHeader('Authorization'));
    }

    public function testGetSendsGetRequest(): void
    {
        $capturedRequest = null;

        $transport = function (RequestInterface $request) use (&$capturedRequest) {
            $capturedRequest = $request;
            return new Response(200);
        };

        $client = $this->createClient(transport: $transport(...));
        $response = $client->get('https://example.com/users');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('GET', $capturedRequest->getMethod());
        $this->assertSame('https://example.com/users', (string) $capturedRequest->getUri());
    }

    public function testGetWithHeaders(): void
    {
        $capturedRequest = null;

        $transport = function (RequestInterface $request) use (&$capturedRequest) {
            $capturedRequest = $request;
            return new Response(200);
        };

        $client = $this->createClient(transport: $transport(...));
        $client->get('https://example.com', ['Accept' => 'text/html']);

        $this->assertSame('text/html', $capturedRequest->getHeaderLine('Accept'));
    }

    public function testPostSendsJsonBody(): void
    {
        $capturedRequest = null;

        $transport = function (RequestInterface $request) use (&$capturedRequest) {
            $capturedRequest = $request;
            return new Response(201);
        };

        $client = $this->createClient(transport: $transport(...));
        $response = $client->post('https://example.com/users', ['name' => 'John']);

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('POST', $capturedRequest->getMethod());
        $this->assertSame('application/json', $capturedRequest->getHeaderLine('Content-Type'));
        $this->assertSame('application/json', $capturedRequest->getHeaderLine('Accept'));
        $this->assertSame('{"name":"John"}', (string) $capturedRequest->getBody());
    }

    public function testPutSendsJsonBody(): void
    {
        $capturedRequest = null;

        $transport = function (RequestInterface $request) use (&$capturedRequest) {
            $capturedRequest = $request;
            return new Response(200);
        };

        $client = $this->createClient(transport: $transport(...));
        $client->put('https://example.com/users/1', ['name' => 'Jane']);

        $this->assertSame('PUT', $capturedRequest->getMethod());
        $this->assertSame('{"name":"Jane"}', (string) $capturedRequest->getBody());
    }

    public function testPatchSendsJsonBody(): void
    {
        $capturedRequest = null;

        $transport = function (RequestInterface $request) use (&$capturedRequest) {
            $capturedRequest = $request;
            return new Response(200);
        };

        $client = $this->createClient(transport: $transport(...));
        $client->patch('https://example.com/users/1', ['status' => 'active']);

        $this->assertSame('PATCH', $capturedRequest->getMethod());
        $this->assertSame('{"status":"active"}', (string) $capturedRequest->getBody());
    }

    public function testDeleteSendsDeleteRequest(): void
    {
        $capturedRequest = null;

        $transport = function (RequestInterface $request) use (&$capturedRequest) {
            $capturedRequest = $request;
            return new Response(204);
        };

        $client = $this->createClient(transport: $transport(...));
        $response = $client->delete('https://example.com/users/1');

        $this->assertSame(204, $response->getStatusCode());
        $this->assertSame('DELETE', $capturedRequest->getMethod());
    }

    public function testHeadSendsHeadRequest(): void
    {
        $capturedRequest = null;

        $transport = function (RequestInterface $request) use (&$capturedRequest) {
            $capturedRequest = $request;
            return new Response(200);
        };

        $client = $this->createClient(transport: $transport(...));
        $client->head('https://example.com/health');

        $this->assertSame('HEAD', $capturedRequest->getMethod());
    }

    public function testPostWithCustomHeaders(): void
    {
        $capturedRequest = null;

        $transport = function (RequestInterface $request) use (&$capturedRequest) {
            $capturedRequest = $request;
            return new Response(200);
        };

        $client = $this->createClient(transport: $transport(...));
        $client->post('https://example.com/api', ['key' => 'val'], ['X-Request-Id' => 'abc']);

        $this->assertSame('abc', $capturedRequest->getHeaderLine('X-Request-Id'));
        $this->assertSame('application/json', $capturedRequest->getHeaderLine('Content-Type'));
    }

    public function testConvenienceMethodsGoThroughPipeline(): void
    {
        $capturedRequest = null;

        $transport = function (RequestInterface $request) use (&$capturedRequest) {
            $capturedRequest = $request;
            return new Response(200);
        };

        $config = new ClientConfig(
            baseUrl: 'https://api.example.com',
            bearerToken: 'token',
        );
        $client = $this->createClient(config: $config, transport: $transport(...));
        $client->get('/users');

        $this->assertSame('https://api.example.com/users', (string) $capturedRequest->getUri());
        $this->assertSame('Bearer token', $capturedRequest->getHeaderLine('Authorization'));
    }

    public function testGetViaHttpServiceContract(): void
    {
        $captured = null;
        $service = $this->createService($captured, 200);

        $response = $service->get('https://example.com/items', ['X-Trace' => 'abc']);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('GET', $captured->getMethod());
        $this->assertSame('https://example.com/items', (string) $captured->getUri());
        $this->assertSame('abc', $captured->getHeaderLine('X-Trace'));
    }

    public function testDeleteViaHttpServiceContract(): void
    {
        $captured = null;
        $service = $this->createService($captured, 204);

        $response = $service->delete('https://example.com/items/1', ['X-Trace' => 'abc']);

        $this->assertSame(204, $response->getStatusCode());
        $this->assertSame('DELETE', $captured->getMethod());
        $this->assertSame('https://example.com/items/1', (string) $captured->getUri());
        $this->assertSame('abc', $captured->getHeaderLine('X-Trace'));
    }

    public function testHeadViaHttpServiceContract(): void
    {
        $captured = null;
        $service = $this->createService($captured, 200);

        $service->head('https://example.com/items', ['X-Trace' => 'abc']);

        $this->assertSame('HEAD', $captured->getMethod());
        $this->assertSame('https://example.com/items', (string) $captured->getUri());
        $this->assertSame('abc', $captured->getHeaderLine('X-Trace'));
    }

    public function testPostViaHttpServiceContract(): void
    {
        $captured = null;
        $service = $this->createService($captured, 201);

        $response = $service->post('https://example.com/items', ['name' => 'a', 'n' => 1], ['X-Trace' => 'abc']);

        $this->assertSame(201, $response->getStatusCode());
        $this->assertJsonRequest($captured, 'POST', 'https://example.com/items', ['name' => 'a', 'n' => 1]);
    }

    public function testPutViaHttpServiceContract(): void
    {
        $captured = null;
        $service = $this->createService($captured, 200);

        $service->put('https://example.com/items/1', ['name' => 'b'], ['X-Trace' => 'abc']);

        $this->assertJsonRequest($captured, 'PUT', 'https://example.com/items/1', ['name' => 'b']);
    }

    public function testPatchViaHttpServiceContract(): void
    {
        $captured = null;
        $service = $this->createService($captured, 200);

        $service->patch('https://example.com/items/1', ['name' => 'c'], ['X-Trace' => 'abc']);

        $this->assertJsonRequest($captured, 'PATCH', 'https://example.com/items/1', ['name' => 'c']);
    }

    public function testErrorStatusComesBackAsResponseViaHttpServiceContract(): void
    {
        foreach ([404, 500] as $status) {
            $captured = null;
            $service = $this->createService($captured, $status);

            $this->assertSame($status, $service->get('https://example.com/x')->getStatusCode());
            $this->assertSame($status, $service->post('https://example.com/x', ['a' => 1])->getStatusCode());
        }
    }

    private function createService(?RequestInterface &$captured, int $status): HttpServiceInterface
    {
        $transport = function (RequestInterface $request) use (&$captured, $status) {
            $captured = $request;
            return new Response($status);
        };

        return $this->createClient(transport: $transport(...));
    }

    /**
     * @param array<string, mixed> $expectedBody
     */
    private function assertJsonRequest(
        RequestInterface $request,
        string $method,
        string $uri,
        array $expectedBody,
    ): void {
        $this->assertSame($method, $request->getMethod());
        $this->assertSame($uri, (string) $request->getUri());
        $this->assertSame('abc', $request->getHeaderLine('X-Trace'));
        $this->assertSame('application/json', $request->getHeaderLine('Content-Type'));
        $this->assertSame($expectedBody, json_decode((string) $request->getBody(), true));
    }

    private function createClient(
        ?ClientConfig $config = null,
        ?\Closure $transport = null,
    ): HttpClient {
        return new HttpClient(
            requestFactory: $this->factory,
            streamFactory: $this->factory,
            responseFactory: $this->factory,
            uriFactory: $this->factory,
            config: $config ?? new ClientConfig(),
            transport: $transport,
        );
    }
}
