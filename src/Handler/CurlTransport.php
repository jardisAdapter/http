<?php

declare(strict_types=1);

namespace JardisAdapter\Http\Handler;

use JardisAdapter\Http\Config\ClientConfig;
use JardisAdapter\Http\Exception\NetworkException;
use JardisAdapter\Http\Exception\RequestException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Invokable cURL transport handler.
 */
final class CurlTransport
{
    public function __construct(
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly StreamFactoryInterface $streamFactory,
    ) {
    }

    public function __invoke(RequestInterface $request, ClientConfig $config): ResponseInterface
    {
        $handle = curl_init();

        if ($handle === false) {
            throw new RequestException($request, 'Failed to initialize cURL handle');
        }

        try {
            $this->setOptions($handle, $request, $config);
            $responseHeaders = [];

            curl_setopt(
                $handle,
                CURLOPT_HEADERFUNCTION,
                function ($ch, string $headerLine) use (&$responseHeaders): int {
                    $length = strlen($headerLine);
                    $parts = explode(':', $headerLine, 2);

                    if (count($parts) === 2) {
                        $name = trim($parts[0]);
                        $value = trim($parts[1]);
                        $responseHeaders[$name][] = $value;
                    }

                    return $length;
                },
            );

            $body = curl_exec($handle);

            if ($body === false) {
                $errno = curl_errno($handle);
                $error = curl_error($handle);

                throw $this->createException($request, $errno, $error);
            }

            $statusCode = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);

            return $this->buildResponse($statusCode, $responseHeaders, (string) $body);
        } finally {
            curl_close($handle);
        }
    }

    private function setOptions(\CurlHandle $handle, RequestInterface $request, ClientConfig $config): void
    {
        $options = [
            CURLOPT_URL => (string) $request->getUri() ?: '/',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $request->getMethod() ?: 'GET',
            CURLOPT_TIMEOUT => $config->timeout,
            CURLOPT_CONNECTTIMEOUT => $config->connectTimeout,
            CURLOPT_SSL_VERIFYPEER => $config->verifySsl,
            CURLOPT_SSL_VERIFYHOST => $config->verifySsl ? 2 : 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        ];

        $headers = [];
        foreach ($request->getHeaders() as $name => $values) {
            $headers[] = $name . ': ' . implode(', ', $values);
        }

        if ($headers !== []) {
            $options[CURLOPT_HTTPHEADER] = $headers;
        }

        $body = (string) $request->getBody();
        if ($body !== '') {
            $options[CURLOPT_POSTFIELDS] = $body;
        }

        curl_setopt_array($handle, $options);
    }

    /**
     * @param array<string, list<string>> $headers
     */
    private function buildResponse(int $statusCode, array $headers, string $body): ResponseInterface
    {
        $response = $this->responseFactory->createResponse($statusCode);

        foreach ($headers as $name => $values) {
            foreach ($values as $value) {
                $response = $response->withAddedHeader($name, $value);
            }
        }

        return $response->withBody($this->streamFactory->createStream($body));
    }

    private function createException(
        RequestInterface $request,
        int $errno,
        string $error,
    ): NetworkException|RequestException {
        $networkErrors = [
            CURLE_COULDNT_RESOLVE_HOST,
            CURLE_COULDNT_RESOLVE_PROXY,
            CURLE_COULDNT_CONNECT,
            CURLE_OPERATION_TIMEDOUT,
            CURLE_SSL_CONNECT_ERROR,
            CURLE_GOT_NOTHING,
            CURLE_SEND_ERROR,
            CURLE_RECV_ERROR,
        ];

        if (in_array($errno, $networkErrors, true)) {
            return new NetworkException($request, $error, $errno);
        }

        return new RequestException($request, $error, $errno);
    }
}
