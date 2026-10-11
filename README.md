# Product catalogue API

REST API for a product catalogue: products belong to at least one category,
timestamps maintain themselves, and every saved product fans out notifications
(operation log + e-mail) **asynchronously** through a database-backed queue.

Built with **PHP 8.5**, **Symfony 7.4**, **API Platform 4**, **MySQL 8.0**,
**DDEV**, and guarded by PHPUnit, Behat, Infection (mutation testing), PHPStan
(level `max`), PHP-CS-Fixer, Deptrac and Rector.

---

## Table of contents

- [Quick start](#quick-start)
- [Everyday commands](#everyday-commands)
- [Using the API](#using-the-api)
- [Architecture](#architecture)
- [Notifications](#notifications)
- [Security](#security)
- [Quality gate](#quality-gate)
- [Testing](#testing)
- [Design decisions](#design-decisions)
- [Troubleshooting](#troubleshooting)

---

## Quick start

### Requirements

- [DDEV](https://ddev.readthedocs.io/) ≥ 1.24
- Docker (or OrbStack / Colima)

Nothing else: PHP, Composer, MySQL and Mailpit all live inside DDEV.

### Installation

```bash
make install
```

That single target is equivalent to:

```bash
ddev start                                                   # containers: web (PHP 8.5), db (MySQL 8.0), mailpit
ddev composer install
ddev exec php bin/console lexik:jwt:generate-keypair --skip-if-exists
ddev exec php bin/console doctrine:migrations:migrate --no-interaction
ddev exec php bin/console doctrine:fixtures:load --no-interaction
```

Then open:

| What | URL |
| --- | --- |
| API entrypoint | <https://rest-api-sf.ddev.site/api> |
| Swagger UI | <https://rest-api-sf.ddev.site/api/docs> |
| OpenAPI (JSON) | <https://rest-api-sf.ddev.site/api/docs.jsonopenapi> |
| Mailpit (captured e-mails) | `ddev mailpit` |

> DDEV prints the real base URL on `ddev start`. If port 80 was taken, the
> project may be served from a different port — always trust `ddev describe`.

### The notification worker

A dedicated `worker` container (`.ddev/docker-compose.worker.yaml`) runs
`messenger:consume async` and starts automatically with the project. To watch
it, or to drain the queue by hand:

```bash
ddev logs -s worker -f                 # follow the worker
make consume                           # drain the queue once, in the foreground
```

### Seeded accounts

| E-mail | Password | Roles |
| --- | --- | --- |
| `admin@example.com` | `admin` | `ROLE_ADMIN`, `ROLE_USER` |
| `reader@example.com` | `reader` | `ROLE_USER` |

Create more with:

```bash
ddev exec php bin/console app:user:create you@example.com 's3cret' --admin
```

---

## Everyday commands

```bash
make help        # lists every target
make qa          # the full quality gate — identical to CI
make test        # PHPUnit + Behat
make mutation    # Infection; HTML report in var/infection/
make cs          # applies the coding standard
make fixtures    # reloads the development data set
make migrate     # applies pending migrations
make mail        # opens Mailpit
```

---

## Using the API

### 1. Obtain a token

```bash
curl -sX POST https://rest-api-sf.ddev.site/api/login \
  -H 'Content-Type: application/json' \
  -d '{"email":"admin@example.com","password":"admin"}'
```

```json
{ "token": "eyJ0eXAiOiJKV1QiLCJhbGc..." }
```

```bash
TOKEN=$(curl -sX POST https://rest-api-sf.ddev.site/api/login \
  -H 'Content-Type: application/json' \
  -d '{"email":"admin@example.com","password":"admin"}' | jq -r .token)
```

### 2. Create a category

```bash
curl -sX POST https://rest-api-sf.ddev.site/api/categories \
  -H "Authorization: Bearer $TOKEN" \
  -H 'Content-Type: application/ld+json' \
  -d '{"code":"ELECTRO"}'
```

### 3. Create a product

Categories are referenced by their **business code**, not by IRI — the code is
the stable, human-meaningful identifier of a category.

```bash
curl -sX POST https://rest-api-sf.ddev.site/api/products \
  -H "Authorization: Bearer $TOKEN" \
  -H 'Content-Type: application/ld+json' \
  -d '{
        "name": "Mechanical keyboard",
        "price": { "amount": 34999, "currency": "PLN" },
        "categoryCodes": ["ELECTRO", "GAMING"]
      }'
```

```json
{
  "@id": "/api/products/0192f1b4-...",
  "id": "0192f1b4-...",
  "name": "Mechanical keyboard",
  "price": { "amount": 34999, "currency": "PLN" },
  "categories": [
    { "id": "...", "code": "ELECTRO" },
    { "id": "...", "code": "GAMING" }
  ],
  "createdAt": "2026-10-08T18:04:11+00:00",
  "updatedAt": "2026-10-08T18:04:11+00:00"
}
```

**Prices are integers in minor units** (`34999` = `349.99 PLN`). Floating point
numbers never touch money.

### Endpoints

| Method | Path | Required role |
| --- | --- | --- |
| `POST` | `/api/login` | — (public) |
| `GET` | `/api/products`, `/api/products/{id}` | `ROLE_USER` |
| `POST` / `PUT` / `PATCH` / `DELETE` | `/api/products[/{id}]` | `ROLE_ADMIN` |
| `GET` | `/api/categories`, `/api/categories/{id}` | `ROLE_USER` |
| `POST` / `PUT` / `PATCH` / `DELETE` | `/api/categories[/{id}]` | `ROLE_ADMIN` |

### Validation rules

| Rule | Response |
| --- | --- |
| A product must belong to **at least one** category | `422` |
| Category code is **unique** (ignoring surrounding whitespace and case) and **≤ 10 characters** | `422` (`409` if a concurrent request wins the race) |
| Referenced category codes must exist (matched ignoring surrounding whitespace and case) | `422` |
| A category still assigned to products cannot be deleted | `409` |
| Price amount between 0 and 4 294 967 295 minor units, currency is a valid ISO 4217 code | `422` |
| `{id}` must be a UUID | `404` otherwise |
| Timestamps are server-controlled | read-only in the API |

Errors follow RFC 7807 (`application/problem+json`).

---

## Architecture

A **modular monolith**: four modules, each sliced into the classic three
layers. Boundaries are not a convention — Deptrac fails the build when they
are crossed.

```
src/
├── Catalog/                   # the business capability of this exercise
│   ├── Domain/                #   entities, value objects, events, repository interfaces
│   ├── Application/           #   use cases, event handlers
│   └── Infrastructure/        #   Doctrine, API Platform, validators, fixtures
├── Notification/              # delivery of notifications (generic, reusable)
│   ├── Domain/                #   Notification, NotificationChannel, NotificationDispatcher
│   ├── Application/           #   dispatcher, queue command + handler, channel registry
│   └── Infrastructure/        #   OperationLogChannel, EmailChannel, OperationLog entity
├── Security/                  # users, JWT, OpenAPI documentation of the token endpoint
└── Shared/                    # AggregateRoot, Money, timestamps, domain-event publishing
```

### Allowed dependencies

```
Catalog\Infrastructure ──▶ Catalog\Application ──▶ Catalog\Domain ──▶ Shared\Domain
                                     │
                                     └──▶ Notification\Domain          (the contract only)

Notification\Infrastructure ──▶ Notification\Domain ◀── Notification\Application
```

- `Catalog` depends on the **notification contract**, never on a channel.
- No module depends on another module's `Infrastructure`.
- `Domain` may use Doctrine mapping attributes (a deliberate trade-off,
  see [ADR-0002](docs/adr/0002-persistence-annotated-domain.md)) but is
  forbidden from touching HTTP, API Platform or the mailer.

Run `make deptrac` to verify.

### Write flow

```
HTTP POST /api/products
        │
        ▼
ProductStateProcessor            Infrastructure — translates HTTP into domain calls
        │
        ▼
Product (aggregate root)         invariants enforced; records ProductSaved
        │
        ▼
EntityManager::wrapInTransaction() → flush()
        │
        ▼
PublishDomainEventsListener      postFlush → queue insert shares the product's transaction
        │
        ▼
NotifyOnProductSavedHandler      builds the notification payload
        │
        ▼
AsyncNotificationDispatcher      fan-out: one queue message *per channel*
        │
        ▼
messenger_messages (MySQL)       the queue is a database table
        │
        ▼ (worker container)
SendNotificationHandler ──▶ OperationLogChannel  → operation_log + Monolog
                        └─▶ EmailChannel         → Mailpit
```

---

## Notifications

### Adding a channel (Slack, SMS, webhook…)

One class. No configuration, no registry to edit:

```php
namespace App\Notification\Infrastructure\Channel;

use App\Notification\Domain\Notification;
use App\Notification\Domain\NotificationChannel;

final readonly class SlackChannel implements NotificationChannel
{
    public function name(): string
    {
        return 'slack';
    }

    public function supports(Notification $notification): bool
    {
        return 'product.saved' === $notification->type;
    }

    public function send(Notification $notification): void
    {
        // POST to the Slack webhook…
    }
}
```

Every `NotificationChannel` implementation is tagged `app.notification_channel`
by an `_instanceof` rule in `config/services.yaml` (keeping the domain
interface free of container attributes), and `ChannelRegistry` receives every tagged service through `#[AutowireIterator]`.
The new channel is picked up on the next cache warm-up.

### Why one message per channel?

`AsyncNotificationDispatcher` resolves the channels **before** queueing and emits
a separate `SendNotification` command for each. The consequences matter:

- a failing Slack webhook never blocks the e-mail,
- retries are per channel, so a successful channel is not re-executed,
- the failure transport tells you exactly which channel broke.

`SendNotificationHandler` deliberately lets exceptions bubble up — that is what
drives Messenger's retry policy (3 attempts, exponential back-off) and the
`failed` transport.

### Inspecting the queue

```bash
ddev mysql -e 'SELECT queue_name, created_at, delivered_at FROM messenger_messages;'
ddev exec php bin/console messenger:failed:show
ddev exec php bin/console messenger:failed:retry --force
ddev mysql -e 'SELECT channel, type, subject, created_at FROM operation_log ORDER BY created_at DESC LIMIT 10;'
```

---

## Security

| Measure | Where |
| --- | --- |
| **JWT** (RS256, Lexik) | `config/packages/security.yaml` |
| Stateless firewall, no sessions | `config/packages/framework.yaml` |
| Role-based access per operation | `security:` attributes on the API resources |
| **Login throttling** — 5 attempts/min | `login_throttling` in `security.yaml` |
| **Rate limiting** of the API — 300 req/min per identity (per IP when anonymous), answered with `429` and `X-RateLimit-*` headers | `ApiRateLimitSubscriber` + `framework.rate_limiter` |
| **CORS** allow-list | `config/packages/nelmio_cors.yaml` |
| Hardening headers (`X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy`, HSTS) | `SecurityHeadersSubscriber` |
| Pagination hard limits (client cannot request 10 000 items) | `config/packages/api_platform.yaml` |
| Write/read serialization groups — mass assignment is impossible | `ProductResource`, `CategoryResource` |
| Private keys never committed | `.gitignore` (`config/jwt/*.pem`) |
| Audit trail of every product change | `operation_log` |

Further hardening for production, deliberately out of scope here: refresh
tokens with rotation, API keys for machine clients, WAF/mTLS at the edge,
`symfony/secrets` or a KMS for the JWT passphrase.

---

## Quality gate

`make qa` (and CI) runs, in order:

| Tool | Purpose | Configuration |
| --- | --- | --- |
| **PHP-CS-Fixer** | coding standard (`@PER-CS`, `@Symfony`, `@PHP84Migration`) | `.php-cs-fixer.dist.php` |
| **PHPStan** | static analysis, **level `max`**, with Doctrine/Symfony/PHPUnit extensions | `phpstan.dist.neon` |
| **Deptrac** | module and layer boundaries | `deptrac.dist.yaml` |
| **Rector** | automated refactoring, dry-run — fails on drift | `rector.php` |
| **PHPUnit** | unit + functional tests | `phpunit.dist.xml` |
| **Behat** | acceptance tests against the real HTTP stack | `behat.dist.php` |
| **Infection** | mutation testing of `src/` against PHPUnit — **MSI 100 %** required | `infection.json5` |

All seven are green on `main`; CI (`.github/workflows/ci.yml`) runs every step
even when an earlier one fails, so one run reports every problem.

---

## Testing

```bash
ddev composer test:unit      # PHPUnit
ddev composer test:behat     # Behat
ddev composer test           # both
ddev composer mutation       # Infection (mutation testing)
```

### Mutation testing

Coverage says a line *ran*; Infection checks that a test would *notice* if it
were wrong. It mutates `src/` (flips conditions, drops calls, shifts
boundaries) and reruns the covering PHPUnit tests — each mutant must make at
least one test fail. The gate requires an MSI of 100 %: a surviving mutant
means either a missing assertion or a provably equivalent mutation, which is
then ignored in `infection.json5` with a comment explaining why.

Xdebug ships with DDEV but stays disabled; Infection loads it only for its
initial coverage run. Doctrine mapping attributes are excluded from mutation
because no test *executes* them — `DatabaseSchemaTest` guards them instead by
comparing the migrated schema with the mapping.

### What is covered

**Unit** (`tests/Unit/`) — the notification pipeline is the focus, as requested:

- `AsyncNotificationDispatcherTest` — fan-out, channel filtering via
  `supports()`, unknown channel handling, no message for zero channels.
- `SendNotificationHandlerTest` — delivery, and the deliberate propagation of
  failures so Messenger can retry.
- `EmailChannelTest` / `OperationLogChannelTest` — recipients, subject,
  payload, persistence.
- `ProductTest` / `CategoryTest` — aggregate invariants, event de-duplication.
- `ProductSavedNotificationFactoryTest`, `MoneyTest`.

**Functional** (`tests/Functional/NotificationPipelineTest.php`) — proves the
whole chain: `flush()` → `postFlush` → event bus → queue, including that a
rolled-back transaction queues nothing. `TransactionalOutboxTest` proves the
reverse: a failing queue insert rolls the product back.
`ProductQueryBudgetTest` keeps the product list free of N+1 queries.

**Acceptance** (`features/`) — 19 scenarios over the real HTTP stack: CRUD,
validation (`422`), authentication (`401`), authorisation (`403`), hardening
headers, and the queue being filled after a write.

Tests run against a dedicated `db_test` database inside a transaction that is
rolled back after every test (`dama/doctrine-test-bundle`), and the queue is
swapped for `in-memory://`, so the suite is fast and order-independent.

---

## Design decisions

Short ADRs live in [`docs/adr/`](docs/adr/). The highlights:

**`Product` is an aggregate root.** It has no public setters; `rename()`,
`changePrice()` and `replaceCategories()` are the only ways in, and each of them
upholds the "at least one category" invariant. `Category` is a separate
aggregate referenced by code — products and categories have different
life-cycles, so one transaction should not lock both trees.

**Domain events are published after the flush, inside the save transaction.**
`PublishDomainEventsListener` hooks into `postFlush`, and the repository wraps
the flush in `wrapInTransaction()`, so the queued notifications are committed
atomically with the product (a transactional outbox on the shared database). A
rolled-back save never sends an e-mail, and a committed one never loses it.

**Events carry identifiers only.** `ProductSaved` holds a `Uuid`; the handler
re-reads the aggregate. The notification can never describe a stale state, and
the queued payload stays small.

**One notification per transaction.** `Product::recordChange()` checks
`hasRecorded(ProductSaved::class)`, so changing the name *and* the price *and*
the categories in one request still produces exactly one notification.

**API resources are DTOs, not entities.** `ProductResource` is a dedicated
read/write model, so the HTTP contract can evolve independently of the database
schema, and no API Platform attribute leaks into the domain.

**Money is a value object in minor units.** `Money(34999, 'PLN')`, never a
float.

---

## Troubleshooting

**No e-mails in Mailpit.** The queue is asynchronous — check the worker
(`ddev logs -s worker`) or drain it manually with `make consume`. Messages that
exhausted their retries sit in the dead-letter queue: inspect them with
`messenger:failed:show` and replay with `messenger:failed:retry --force`.

Note that Mailpit lives in the **web** container, so `MAILER_DSN` is
`smtp://web:1025` — the worker is a separate container and `localhost` would not
reach it.

**`401 Unauthorized` on every request.** The key pair is missing:
`ddev exec php bin/console lexik:jwt:generate-keypair --skip-if-exists`.

**`SQLSTATE[HY000] [1049] Unknown database 'db_test'`.** The test database is
created by a DDEV post-start hook — run `ddev restart`.

**Behat sees an empty queue.** `ApiContext` calls `$client->disableReboot()` for
exactly this reason; a rebooted kernel gets a fresh in-memory transport.

**A different base URL than in this README.** `ddev describe` always shows the
truth; DDEV falls back to alternative ports when 80/443 are taken.
