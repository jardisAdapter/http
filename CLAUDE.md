# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Skills

Folge den JardisCore Skills: [CLAUDE.md](~/.claude/CLAUDE.md)

## Project

PSR-18 HTTP Client auf cURL-Basis. Teil der Jardis Business Platform (Adapter Layer).

- **Namespace:** `JardisAdapter\Http\`
- **PHP:** >= 8.2, `declare(strict_types=1)`, PHPStan Level 8, PSR-12
- **PSR-Standards:** PSR-18 (Client), PSR-17 (Factories), PSR-7 (Messages via nyholm/psr7)

## Commands

Alle Befehle laufen im Docker-Container (`make` nutzt `docker compose run phpcli`).

```bash
cp .env.example .env          # Einmalig: .env anlegen (Pflicht, Makefile inkludiert .env)
make install                  # composer install
make phpunit                  # Alle Tests (Unit + Integration)
make phpunit-coverage         # Tests mit Coverage-Text
make phpstan                  # Statische Analyse (Level 8)
make phpcs                    # PSR-12 Coding Standards
make install-hooks            # Git Pre-Commit/Pre-Push Hooks aktivieren
```

Kein `make start` noetig — keine externen Services (kein Redis, kein MySQL).

## Architecture

**User-API:** Nur `HttpClient` + `ClientConfig`. Convenience-Methoden `get()`, `post()`, `put()`, `patch()`, `delete()`, `head()`. PSR-18 `sendRequest()` fuer volle Kontrolle. `RequestFactory` ist ein internes Detail.

**Intern:** Handler-Pipeline aus Invokable Objects (`__invoke()`). Der `HttpClient` ist ein reiner Orchestrator — er baut aus der Config eine Pipeline und piped den Request durch:

```
HttpClient (Orchestrator)
  │
  │  Convenience: get/post/put/patch/delete/head
  │  └── erzeugen intern PSR-7 Requests via Psr17Factory
  │
  │  Transformers (Request → Request):
  │  ├── Handler/BaseUrl            resolves relative URLs
  │  ├── Handler/DefaultHeaders     setzt Default-Headers
  │  ├── Handler/BearerAuth         setzt Bearer Token
  │  └── Handler/BasicAuth          setzt Basic Auth
  │
  │  Transport (Request → Response):
  │  ├── Handler/CurlTransport      cURL-basierter Transport
  │  └── Handler/Retry              wraps Transport mit Retry + Backoff
  │
  ▼
  sendRequest():
    foreach transformer → $request = $transform($request)
    return $transport($request, $config)
```

Nur was konfiguriert ist, wird instanziiert. HttpClient hat null Business-Logik.

- `Config/ClientConfig` — Readonly VO: timeout, baseUrl, verifySsl, defaultHeaders, bearerToken, basicUser/Password, maxRetries, retryDelayMs
- `Factory/` — PSR-17 Factories (internes Detail), delegieren an `nyholm/psr7`
- `Exception/` — PSR-18 Exception-Hierarchie (HttpClientException → NetworkException | RequestException)

## Testing

Integration-Tests nutzen PHP Built-in Server (`tests/Fixtures/test-server.php`) — kein Docker-Service noetig. Der Server wird pro Test in `setUp()` gestartet und in `tearDown()` gestoppt.

Unit-Tests injizieren Transport als Closure um echte HTTP-Calls zu vermeiden. Jeder Handler hat eigene isolierte Unit-Tests.
