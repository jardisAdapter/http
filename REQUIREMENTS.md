# REQUIREMENTS — jardisadapter/http v1.0

## Zweck

Schlanker PSR-18 HTTP Client auf Basis von cURL. Macht HTTP-Requests aus DDD-Anwendungen heraus — API-Calls, Webhooks, externe Services. Kein Framework, kein Overhead.

## User-API

Der User kennt nur zwei Klassen: `HttpClient` und `ClientConfig`. Alles wird ueber die Config gesteuert — keine Decorator-Verschachtelung, kein Handler-Wissen, keine Factory noetig.

```php
$client = new HttpClient(new ClientConfig(
    baseUrl: 'https://api.example.com/v2',
    bearerToken: 'eyJhbGciOiJI...',
    maxRetries: 3,
));

$response = $client->get('/users');
$response = $client->post('/users', ['name' => 'John']);
$response = $client->put('/users/1', ['name' => 'Jane']);
$response = $client->patch('/users/1', ['status' => 'active']);
$response = $client->delete('/users/1');
$response = $client->head('/health');
```

PSR-18 `sendRequest()` steht fuer volle Kontrolle zur Verfuegung. `RequestFactory` ist ein internes Detail.

## Features v1.0

### HTTP Client (PSR-18)

- Implementiert `Psr\Http\Client\ClientInterface` (PSR-18)
- cURL als einziges Transport-Backend
- Convenience-Methoden: `get()`, `post()`, `put()`, `patch()`, `delete()`, `head()`
- `post()`, `put()`, `patch()` senden automatisch JSON (Content-Type + Accept Header)
- Alle Methoden akzeptieren optionale Custom Headers pro Request
- PSR-18 `sendRequest()` fuer volle Kontrolle (OPTIONS, custom Requests)
- Timeout-Konfiguration (connect + request)
- Follow-Redirects (konfigurierbar, max Redirects)
- SSL/TLS Verification (Standard: an, abschaltbar fuer Development)

### Request Factory (intern)

- Factory fuer PSR-7 Request-Objekte (PSR-17 `Psr\Http\Message\RequestFactoryInterface`)
- Factory fuer PSR-7 Stream-Objekte (PSR-17 `Psr\Http\Message\StreamFactoryInterface`)
- Delegiert an `nyholm/psr7` als suggested Dependency
- Wird intern von den Convenience-Methoden genutzt — kein User-Facing

### Authentication

- Bearer Token Authentication (Header-basiert)
- Basic Authentication (Username/Password → Base64)
- Konfiguriert ueber `ClientConfig` — intern als Handler implementiert
- Bearer hat Vorrang wenn beide konfiguriert sind

### Retry

- Automatische Wiederholung bei HTTP 5xx und Netzwerk-Fehlern
- Exponentieller Backoff (konfigurierbar: `retryDelayMs`)
- Konfigurierbar: `maxRetries` (Default: 0 = kein Retry)
- Intern als Handler implementiert, der den Transport wraps

### Request Configuration

- Default Headers (pro Client-Instanz konfigurierbar)
- Base-URL Support (relative URLs werden gegen Base-URL aufgeloest)
- JSON Body Helper: Array → JSON-encoded Body + Content-Type Header

### Response Handling

- Status Code Zugriff
- Header Zugriff
- Body als String, Stream oder JSON-decoded Array
- Keine automatische Exception bei 4xx/5xx — der Caller entscheidet

## Abgrenzung — Was v1.0 NICHT kann

- **Kein async/parallel** — Nur synchrone Requests. Async kommt fruehestens in v2.
- **Kein Custom Retry-Filter** — Retry bei 5xx + NetworkException. Custom retryOn-Closures sind kein User-Feature.
- **Kein Cookie Management** — Kein Cookie-Jar. Cookies nur manuell ueber Header.
- **Kein File Upload mit Multipart** — Kein `multipart/form-data` Builder. Raw Body reicht fuer v1.
- **Kein HTTP/2** — cURL kann es, aber v1.0 beschraenkt sich auf HTTP/1.1.
- **Kein Caching** — Kein HTTP-Cache (ETag, Last-Modified). Caching ist Sache des Callers.
- **Kein Logging** — Der Client loggt nicht selbst. Logging waere ein weiterer Handler.

## PSR-Standards

| PSR | Interface | Rolle |
|-----|-----------|-------|
| PSR-18 | `ClientInterface` | HTTP Client — wird implementiert |
| PSR-17 | `RequestFactoryInterface`, `StreamFactoryInterface` | Request/Stream Factory — wird implementiert |
| PSR-7 | `RequestInterface`, `ResponseInterface`, `StreamInterface`, `UriInterface` | Message Objects — via nyholm/psr7 |

## Dependencies

### Required

| Package | Zweck |
|---------|-------|
| `psr/http-client ^1.0` | PSR-18 ClientInterface |
| `psr/http-message ^2.0` | PSR-7 Message Interfaces |
| `ext-curl` | cURL Transport |
| `ext-json` | JSON Body encoding/decoding |

### Suggested

| Package | Zweck |
|---------|-------|
| `nyholm/psr7 ^1.8` | PSR-7/PSR-17 Implementierung (in require-dev, suggested fuer Production) |

## Contracts (jardissupport/contract)

Folgende Interfaces werden in `JardisSupport\Contract\Http\` definiert:

| Interface | Beschreibung |
|-----------|-------------|
| `HttpClientInterface` | Extends `Psr\Http\Client\ClientInterface` — Marker fuer Jardis-eigene Clients |

Kein eigener umfangreicher Contract-Layer — PSR-18/17/7 sind die Contracts.

## Foundation-Integration

### Hook in DomainApp (existiert bereits)

```php
protected function httpClient(): ClientInterface|false|null
{
    return null;
}
```

### Handler in JardisApp

```php
// JardisApp ueberschreibt:
protected function httpClient(): ClientInterface|false|null
{
    return (new HttpClientHandler())($this->env(...));
}
```

### Handler-Klasse: `HttpClientHandler`

```php
class HttpClientHandler
{
    public function __invoke(callable $env): ClientInterface|false|null
    {
        if (!class_exists(\JardisAdapter\Http\HttpClient::class)) {
            return null;
        }

        $client = new HttpClient(new ClientConfig(
            baseUrl: $env('HTTP_BASE_URL'),
            timeout: (int) ($env('HTTP_TIMEOUT') ?? 30),
            connectTimeout: (int) ($env('HTTP_CONNECT_TIMEOUT') ?? 10),
            verifySsl: ($env('HTTP_VERIFY_SSL') ?? 'true') !== 'false',
            bearerToken: $env('HTTP_BEARER_TOKEN'),
            basicUser: $env('HTTP_BASIC_USER'),
            basicPassword: $env('HTTP_BASIC_PASSWORD'),
            maxRetries: (int) ($env('HTTP_MAX_RETRIES') ?? 0),
            retryDelayMs: (int) ($env('HTTP_RETRY_DELAY_MS') ?? 100),
        ));

        return $client;
    }
}
```

### ENV-Variablen

```env
# HTTP Client
HTTP_BASE_URL=https://api.example.com    # Optional: Base URL fuer relative Requests
HTTP_TIMEOUT=30                           # Request Timeout in Sekunden (Default: 30)
HTTP_CONNECT_TIMEOUT=10                   # Connect Timeout in Sekunden (Default: 10)
HTTP_VERIFY_SSL=true                      # SSL Verification (Default: true)

# Authentication (optional, eines von beiden)
HTTP_BEARER_TOKEN=eyJhbGciOiJI...         # Bearer Token
HTTP_BASIC_USER=api-user                  # Basic Auth Username
HTTP_BASIC_PASSWORD=secret                # Basic Auth Password

# Retry (optional)
HTTP_MAX_RETRIES=3                        # Anzahl Wiederholungen bei 5xx (Default: 0)
HTTP_RETRY_DELAY_MS=200                   # Basis-Delay in ms, exponentiell (Default: 100)
```

### Drei-Zustands-Semantik

| Rueckgabe | Bedeutung |
|----------|-----------|
| `HttpClient` | Client aktiv, in ServiceRegistry geteilt |
| `null` | Package nicht installiert oder keine Konfiguration — Fallback auf SharedRegistry |
| `false` | HTTP Client explizit deaktiviert |

## Architektur

```
HttpClient (Orchestrator, implements ClientInterface)
  │
  │  Convenience-Methoden:
  │  ├── get(uri, headers?)         GET Request
  │  ├── post(uri, data?, headers?) POST mit JSON Body
  │  ├── put(uri, data?, headers?)  PUT mit JSON Body
  │  ├── patch(uri, data?, headers?) PATCH mit JSON Body
  │  ├── delete(uri, headers?)      DELETE Request
  │  ├── head(uri, headers?)        HEAD Request
  │  └── sendRequest(PSR-7)         PSR-18 fuer volle Kontrolle
  │
  │  Transformers (Request → Request, aus Config gebaut):
  │  ├── BaseUrl              relative URLs gegen Base-URL aufloesen
  │  ├── DefaultHeaders       Default-Headers setzen
  │  ├── BearerAuth           Bearer Token hinzufuegen
  │  └── BasicAuth            Basic Auth hinzufuegen
  │
  │  Transport (Request → Response, aus Config gebaut):
  │  ├── CurlTransport        cURL-basierter Transport
  │  └── Retry                wraps Transport mit Retry + exponentiellem Backoff
  │
  ├── Factory/ (intern, kein User-Facing)
  │   ├── RequestFactory       PSR-17 RequestFactory → nyholm/psr7
  │   └── StreamFactory        PSR-17 StreamFactory → nyholm/psr7
  │
  ├── Config/
  │   └── ClientConfig         Readonly VO — alle Konfiguration an einem Ort
  │
  └── Exception/
      ├── HttpClientException  implements ClientExceptionInterface
      ├── NetworkException     implements NetworkExceptionInterface
      └── RequestException     implements RequestExceptionInterface
```

### Handler-Pattern

Alle aktiven Komponenten unter `Handler/` sind **Invokable Objects** (`__invoke()`):

| Handler | Typ | Signatur |
|---------|-----|----------|
| `BaseUrl` | Transformer | `(RequestInterface): RequestInterface` |
| `DefaultHeaders` | Transformer | `(RequestInterface): RequestInterface` |
| `BearerAuth` | Transformer | `(RequestInterface): RequestInterface` |
| `BasicAuth` | Transformer | `(RequestInterface): RequestInterface` |
| `CurlTransport` | Transport | `(RequestInterface, ClientConfig): ResponseInterface` |
| `Retry` | Transport-Wrapper | `(RequestInterface, ClientConfig): ResponseInterface` |

Der `HttpClient` baut die Handler-Pipeline einmalig im Constructor aus der `ClientConfig`. Nur was konfiguriert ist, wird instanziiert. Der Client selbst hat null Business-Logik — nur Orchestrierung.

## Error Handling

| Exception | Trigger |
|-----------|---------|
| `Psr\Http\Client\ClientExceptionInterface` | Basis fuer alle Client-Fehler |
| `Psr\Http\Client\NetworkExceptionInterface` | Netzwerk-Fehler (DNS, Connection refused, Timeout) |
| `Psr\Http\Client\RequestExceptionInterface` | Ungueltiger Request (malformed URI, etc.) |

Keine Exception bei HTTP 4xx/5xx — das sind gueltige Responses.
Bei konfiguriertem Retry werden 5xx und NetworkException automatisch wiederholt.

## Layer Rules

| Layer | Regel |
|-------|-------|
| **Domain** | Importiert niemals HTTP-Client direkt |
| **Application** | Erhaelt `ClientInterface` via Injection. Nutzt es fuer externe API-Calls |
| **Infrastructure** | Konfiguriert Client via `ClientConfig` und ENV |
