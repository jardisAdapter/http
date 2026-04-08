# Jardis HTTP Client

![Build Status](https://github.com/jardisAdapter/http/actions/workflows/ci.yml/badge.svg)
[![License: PolyForm Shield](https://img.shields.io/badge/License-PolyForm%20Shield-blue.svg)](LICENSE.md)
[![PHP Version](https://img.shields.io/badge/PHP-%3E%3D8.2-777BB4.svg)](https://www.php.net/)
[![PHPStan Level](https://img.shields.io/badge/PHPStan-Level%208-brightgreen.svg)](phpstan.neon)
[![PSR-12](https://img.shields.io/badge/Code%20Style-PSR--12-blue.svg)](phpcs.xml)
[![PSR-18](https://img.shields.io/badge/HTTP-PSR--18-brightgreen.svg)](https://www.php-fig.org/psr/psr-18/)

> Part of the **[Jardis Business Platform](https://jardis.io)** — Enterprise-grade PHP components for Domain-Driven Design

**HTTP requests without overhead.** A lean PSR-18 client built on cURL — designed for DDD applications that call external APIs, send webhooks, or integrate services. No framework, no middleware stack, no dependency bloat. Just what you need.

---

## Why This Client?

- **Two classes to learn** — `HttpClient` + `ClientConfig`. Includes its own PSR-7/PSR-17 implementation — zero external dependencies
- **Handler pipeline** — each concern is its own invokable, orchestrated internally by the client
- **Retry with backoff** — automatic retry on 5xx and network errors
- **PSR-18 compatible** — works with any PSR-18-capable code
- **96% test coverage** — integration tests against real HTTP requests, not mocks

---

## Installation

```bash
composer require jardisadapter/http
```

---

## Quick Start

### GET Request

```php
use JardisAdapter\Http\HttpClient;
use JardisAdapter\Http\Config\ClientConfig;

use JardisAdapter\Http\Message\Psr17Factory;

$psr17 = new Psr17Factory();
$client = new HttpClient($psr17, $psr17, $psr17, $psr17, new ClientConfig(
    baseUrl: 'https://api.example.com/v2',
));

$response = $client->get('/users');
$data = json_decode((string) $response->getBody(), true);
```

### POST with JSON Body

```php
$response = $client->post('/users', [
    'name' => 'John Doe',
    'email' => 'john@example.com',
]);
```

### PUT, PATCH, DELETE

```php
$client->put('/users/1', ['name' => 'Jane Doe']);
$client->patch('/users/1', ['status' => 'active']);
$client->delete('/users/1');
```

### Custom Headers per Request

```php
$response = $client->get('/reports', ['Accept' => 'text/csv']);
$response = $client->post('/import', $data, ['X-Request-Id' => 'abc-123']);
```

### Fully Configured

```php
$psr17 = new Psr17Factory();
$client = new HttpClient($psr17, $psr17, $psr17, $psr17, new ClientConfig(
    baseUrl: 'https://api.example.com/v2',
    timeout: 10,
    connectTimeout: 5,
    verifySsl: true,
    defaultHeaders: ['Accept' => 'application/json'],
    bearerToken: 'eyJhbGciOiJI...',
    maxRetries: 3,
    retryDelayMs: 200,
));

$response = $client->get('/users');
$response = $client->post('/orders', ['product' => 'Widget', 'quantity' => 3]);
```

---

## Authentication

### Bearer Token

```php
$psr17 = new Psr17Factory();
$client = new HttpClient($psr17, $psr17, $psr17, $psr17, new ClientConfig(
    bearerToken: 'eyJhbGciOiJI...',
));
// Authorization: Bearer eyJhbGciOiJI... is set automatically
```

### Basic Auth

```php
$psr17 = new Psr17Factory();
$client = new HttpClient($psr17, $psr17, $psr17, $psr17, new ClientConfig(
    basicUser: 'api-user',
    basicPassword: 'secret',
));
```

---

## Retry

```php
$psr17 = new Psr17Factory();
$client = new HttpClient($psr17, $psr17, $psr17, $psr17, new ClientConfig(
    maxRetries: 3,          // Up to 3 retries on 5xx
    retryDelayMs: 200,      // Exponential backoff: 200ms, 400ms, 800ms
));
```

Automatically retries on HTTP 5xx and transport errors (`HttpClientException`, which covers both `NetworkException` and `RequestException`). No retry on 4xx — those are caller errors.

---

## Error Handling

The client does **not throw exceptions on HTTP 4xx/5xx** — those are valid responses. Exceptions are only thrown for actual errors:

| Exception | When |
|-----------|------|
| `NetworkException` | DNS failure, connection refused, timeout |
| `RequestException` | Invalid request (malformed URI) |

```php
use JardisAdapter\Http\Exception\NetworkException;

try {
    $response = $client->get('/users');
} catch (NetworkException $e) {
    // Network problem — retry was already active (if configured)
}

if ($response->getStatusCode() >= 400) {
    // Handle HTTP errors yourself
}
```

---

## PSR-18 Compatible

The client implements `Psr\Http\Client\ClientInterface`. For full control over the request, use `sendRequest()`:

```php
use JardisAdapter\Http\Message\Psr17Factory;

$factory = new Psr17Factory();
$request = $factory->createRequest('OPTIONS', 'https://api.example.com');
$response = $client->sendRequest($request);
```

---

## Architecture

The user only sees `HttpClient` + `ClientConfig`. Internally, the client orchestrates a pipeline of invokable handlers — built from the config:

```
HttpClient (Orchestrator)
  │
  │  Convenience methods: get(), post(), put(), patch(), delete(), head()
  │  └── internally create PSR-7 requests
  │
  │  Transformers (Request → Request, built from config):
  │  ├── BaseUrl           resolve relative URLs
  │  ├── DefaultHeaders    set default headers
  │  ├── BearerAuth        add bearer token
  │  └── BasicAuth         add basic auth
  │
  │  Transport (Request → Response, built from config):
  │  ├── CurlTransport     cURL-based transport
  │  └── Retry             wraps transport with exponential backoff
  │
  ▼
  sendRequest():
    foreach transformer → $request = $transform($request)
    return $transport($request, $config)
```

Each handler is an **invokable object** (`__invoke`) — independently testable, replaceable, composable. Only what is configured gets instantiated.

### Custom Transport

The transport is a closure — replaceable without changing the client:

```php
$psr17 = new Psr17Factory();
$client = new HttpClient(
    requestFactory: $psr17,
    streamFactory: $psr17,
    responseFactory: $psr17,
    uriFactory: $psr17,
    config: new ClientConfig(),
    transport: function (RequestInterface $request, ClientConfig $config) use ($psr17) {
        return $psr17->createResponse(200)
            ->withBody($psr17->createStream('{"mocked": true}'));
    },
);
```

---

## Jardis Foundation Integration

In a Jardis DDD project, the client is automatically configured via ENV:

```env
HTTP_BASE_URL=https://api.example.com
HTTP_TIMEOUT=30
HTTP_CONNECT_TIMEOUT=10
HTTP_VERIFY_SSL=true
HTTP_BEARER_TOKEN=eyJhbGciOiJI...
HTTP_MAX_RETRIES=3
HTTP_RETRY_DELAY_MS=200
```

The `HttpClientHandler` in `JardisApp` builds the client and registers it in the ServiceRegistry. Your domain code receives `ClientInterface` via injection — without ever importing `HttpClient` directly.

---

## Development

```bash
cp .env.example .env    # One-time setup
make install             # Install dependencies
make phpunit             # Run tests
make phpstan             # Static analysis (Level 8)
make phpcs               # Coding standards (PSR-12)
```

---

## License

[PolyForm Shield License 1.0.0](LICENSE.md) — free for all use including commercial. Only restriction: don't build a competing framework.
