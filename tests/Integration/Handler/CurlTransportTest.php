<?php

declare(strict_types=1);

namespace JardisAdapter\Http\Tests\Integration\Handler;

use JardisAdapter\Http\Config\ClientConfig;
use JardisAdapter\Http\Exception\NetworkException;
use JardisAdapter\Http\Handler\CurlTransport;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;

final class CurlTransportTest extends TestCase
{
    private CurlTransport $transport;
    private Psr17Factory $factory;

    /** @var resource|null */
    private $serverProcess;

    private string $serverHost = '127.0.0.1';
    private int $serverPort = 18923;

    protected function setUp(): void
    {
        $this->transport = new CurlTransport();
        $this->factory = new Psr17Factory();
        $this->startServer();
    }

    protected function tearDown(): void
    {
        $this->stopServer();
    }

    public function testSendsGetRequestAndReturnsResponse(): void
    {
        $request = $this->factory->createRequest('GET', $this->baseUrl('/get'));

        $response = ($this->transport)($request, new ClientConfig());

        $this->assertSame(200, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true);
        $this->assertSame('GET', $body['method']);
        $this->assertSame('/get', $body['uri']);
    }

    public function testSendsPostRequestWithBody(): void
    {
        $body = $this->factory->createStream('{"name":"test"}');
        $request = $this->factory->createRequest('POST', $this->baseUrl('/post'))
            ->withHeader('Content-Type', 'application/json')
            ->withBody($body);

        $response = ($this->transport)($request, new ClientConfig());

        $this->assertSame(200, $response->getStatusCode());
        $responseBody = json_decode((string) $response->getBody(), true);
        $this->assertSame('POST', $responseBody['method']);
        $this->assertSame('{"name":"test"}', $responseBody['body']);
    }

    public function testReturnsResponseHeaders(): void
    {
        $request = $this->factory->createRequest('GET', $this->baseUrl('/headers'));

        $response = ($this->transport)($request, new ClientConfig());

        $this->assertSame('test-value', $response->getHeaderLine('X-Custom-Header'));
    }

    public function testReturnsNon200StatusCodes(): void
    {
        $request = $this->factory->createRequest('GET', $this->baseUrl('/status/404'));

        $response = ($this->transport)($request, new ClientConfig());

        $this->assertSame(404, $response->getStatusCode());
    }

    public function testSendsRequestHeaders(): void
    {
        $request = $this->factory->createRequest('GET', $this->baseUrl('/echo-headers'))
            ->withHeader('X-Test', 'hello');

        $response = ($this->transport)($request, new ClientConfig());

        $body = json_decode((string) $response->getBody(), true);
        $this->assertSame('hello', $body['headers']['X-Test'] ?? null);
    }

    public function testThrowsNetworkExceptionForUnreachableHost(): void
    {
        $request = $this->factory->createRequest('GET', 'http://192.0.2.1:1/unreachable');
        $config = new ClientConfig(timeout: 1, connectTimeout: 1);

        $this->expectException(NetworkException::class);

        ($this->transport)($request, $config);
    }

    public function testAppliesSslVerificationSetting(): void
    {
        $request = $this->factory->createRequest('GET', $this->baseUrl('/get'));
        $config = new ClientConfig(verifySsl: false);

        $response = ($this->transport)($request, $config);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testSendsPutRequest(): void
    {
        $request = $this->factory->createRequest('PUT', $this->baseUrl('/put'))
            ->withBody($this->factory->createStream('update'));

        $response = ($this->transport)($request, new ClientConfig());

        $body = json_decode((string) $response->getBody(), true);
        $this->assertSame('PUT', $body['method']);
    }

    public function testSendsDeleteRequest(): void
    {
        $request = $this->factory->createRequest('DELETE', $this->baseUrl('/delete'));

        $response = ($this->transport)($request, new ClientConfig());

        $body = json_decode((string) $response->getBody(), true);
        $this->assertSame('DELETE', $body['method']);
    }

    private function baseUrl(string $path): string
    {
        return sprintf('http://%s:%d%s', $this->serverHost, $this->serverPort, $path);
    }

    private function startServer(): void
    {
        $router = __DIR__ . '/../../Fixtures/test-server.php';
        $cmd = sprintf(
            'php -S %s:%d %s',
            $this->serverHost,
            $this->serverPort,
            $router,
        );

        $this->serverProcess = proc_open(
            $cmd,
            [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']],
            $pipes,
        );

        usleep(200_000);
    }

    private function stopServer(): void
    {
        if (is_resource($this->serverProcess)) {
            proc_terminate($this->serverProcess);
            proc_close($this->serverProcess);
        }
    }
}
