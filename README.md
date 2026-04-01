# Jardis HTTP Client

![Build Status](https://github.com/jardisAdapter/http/actions/workflows/ci.yml/badge.svg)
[![License: PolyForm Shield](https://img.shields.io/badge/License-PolyForm%20Shield-blue.svg)](LICENSE.md)
[![PHP Version](https://img.shields.io/badge/PHP-%3E%3D8.2-777BB4.svg)](https://www.php.net/)
[![PHPStan Level](https://img.shields.io/badge/PHPStan-Level%208-brightgreen.svg)](phpstan.neon)
[![PSR-12](https://img.shields.io/badge/Code%20Style-PSR--12-blue.svg)](phpcs.xml)
[![PSR-18](https://img.shields.io/badge/HTTP-PSR--18-brightgreen.svg)](https://www.php-fig.org/psr/psr-18/)

> Part of the **[Jardis Business Platform](https://jardis.io)** — Enterprise-grade PHP components for Domain-Driven Design

**HTTP-Requests ohne Overhead.** Ein schlanker PSR-18 Client auf cURL-Basis — gebaut fuer DDD-Anwendungen, die externe APIs ansprechen, Webhooks senden oder Services integrieren. Kein Framework, kein Middleware-Stack, kein Dependency-Bloat. Nur was du brauchst.

---

## Warum dieser Client?

- **Zwei Klassen reichen** — `HttpClient` + `ClientConfig`, sonst nichts
- **Handler-Pipeline** — jeder Concern ein eigenes Invokable, intern vom Client orchestriert
- **Retry mit Backoff** — automatische Wiederholung bei 5xx und Netzwerk-Fehlern
- **PSR-18 kompatibel** — funktioniert mit jedem PSR-18-faehigen Code
- **96% Test Coverage** — Integration-Tests gegen echte HTTP-Requests, nicht gegen Mocks

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

$client = new HttpClient(new ClientConfig(
    baseUrl: 'https://api.example.com/v2',
));

$response = $client->get('/users');
$data = json_decode((string) $response->getBody(), true);
```

### POST mit JSON Body

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

### Custom Headers pro Request

```php
$response = $client->get('/reports', ['Accept' => 'text/csv']);
$response = $client->post('/import', $data, ['X-Request-Id' => 'abc-123']);
```

### Vollstaendig konfiguriert

```php
$client = new HttpClient(new ClientConfig(
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
$client = new HttpClient(new ClientConfig(
    bearerToken: 'eyJhbGciOiJI...',
));
// Authorization: Bearer eyJhbGciOiJI... wird automatisch gesetzt
```

### Basic Auth

```php
$client = new HttpClient(new ClientConfig(
    basicUser: 'api-user',
    basicPassword: 'secret',
));
```

---

## Retry

```php
$client = new HttpClient(new ClientConfig(
    maxRetries: 3,          // Bis zu 3 Wiederholungen bei 5xx
    retryDelayMs: 200,      // Exponentieller Backoff: 200ms, 400ms, 800ms
));
```

Wiederholt automatisch bei HTTP 5xx und Netzwerk-Fehlern (`NetworkException`). Kein Retry bei 4xx — das sind Caller-Fehler.

---

## Error Handling

Der Client wirft **keine Exceptions bei HTTP 4xx/5xx** — das sind gueltige Responses. Exceptions gibt es nur bei echten Fehlern:

| Exception | Wann |
|-----------|------|
| `NetworkException` | DNS-Fehler, Connection refused, Timeout |
| `RequestException` | Ungueltiger Request (malformed URI) |

```php
use JardisAdapter\Http\Exception\NetworkException;

try {
    $response = $client->get('/users');
} catch (NetworkException $e) {
    // Netzwerk-Problem — Retry war bereits aktiv (wenn konfiguriert)
}

if ($response->getStatusCode() >= 400) {
    // HTTP-Fehler selbst behandeln
}
```

---

## PSR-18 kompatibel

Der Client implementiert `Psr\Http\Client\ClientInterface`. Fuer volle Kontrolle ueber den Request steht `sendRequest()` zur Verfuegung:

```php
use JardisAdapter\Http\Factory\RequestFactory;

$factory = new RequestFactory();
$request = $factory->createRequest('OPTIONS', 'https://api.example.com');
$response = $client->sendRequest($request);
```

---

## Architektur

Der User sieht nur `HttpClient` + `ClientConfig`. Intern orchestriert der Client eine Pipeline aus Invokable Handlern — aufgebaut aus der Config:

```
HttpClient (Orchestrator)
  │
  │  Convenience-Methoden: get(), post(), put(), patch(), delete(), head()
  │  └── erzeugen intern PSR-7 Requests
  │
  │  Transformers (Request → Request, aus Config gebaut):
  │  ├── BaseUrl           relative URLs aufloesen
  │  ├── DefaultHeaders    Default-Headers setzen
  │  ├── BearerAuth        Bearer Token hinzufuegen
  │  └── BasicAuth         Basic Auth hinzufuegen
  │
  │  Transport (Request → Response, aus Config gebaut):
  │  ├── CurlTransport     cURL-basierter Transport
  │  └── Retry             wraps Transport mit exponentiellem Backoff
  │
  ▼
  sendRequest():
    foreach transformer → $request = $transform($request)
    return $transport($request, $config)
```

Jeder Handler ist ein **Invokable Object** (`__invoke`) — isoliert testbar, austauschbar, kombinierbar. Nur was konfiguriert ist, wird instanziiert.

### Custom Transport

Der Transport ist eine Closure — austauschbar ohne den Client zu aendern:

```php
$client = new HttpClient(
    config: new ClientConfig(),
    transport: function (RequestInterface $request, ClientConfig $config) {
        return new Response(200, [], '{"mocked": true}');
    },
);
```

---

## Jardis Foundation Integration

In einem Jardis-DDD-Projekt wird der Client automatisch ueber ENV konfiguriert:

```env
HTTP_BASE_URL=https://api.example.com
HTTP_TIMEOUT=30
HTTP_CONNECT_TIMEOUT=10
HTTP_VERIFY_SSL=true
HTTP_BEARER_TOKEN=eyJhbGciOiJI...
HTTP_MAX_RETRIES=3
HTTP_RETRY_DELAY_MS=200
```

Der `HttpClientHandler` in `JardisApp` baut den Client und registriert ihn in der ServiceRegistry. Dein Domain-Code bekommt `ClientInterface` per Injection — ohne je `HttpClient` direkt zu importieren.

---

## Development

```bash
cp .env.example .env    # Einmalig
make install             # Dependencies installieren
make phpunit             # Tests ausfuehren
make phpstan             # Statische Analyse (Level 8)
make phpcs               # Coding Standards (PSR-12)
```

---

## License

[PolyForm Shield License 1.0.0](LICENSE.md) — free for all use including commercial. Only restriction: don't build a competing framework.
