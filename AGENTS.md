# Agent instructions

Guidance for AI coding agents (Copilot, Claude, Cursor…) working in this
repository. Humans are welcome to read it too — it is simply the project's
working agreement.

## What this project is

A REST API for a product catalogue, built as a **modular monolith** on PHP 8.5,
Symfony 7.4, API Platform 4 and MySQL 8.0, running inside DDEV. Product saves
fan out notifications asynchronously through a database-backed queue.

Read `README.md` first, then `docs/adr/` — the ADRs explain *why* the code looks
the way it does, and most review comments are already answered there.

## Golden rules

1. **Everything runs inside DDEV.** Never invoke `php`, `composer` or
   `vendor/bin/*` on the host — prefix with `ddev exec` / `ddev composer`, or
   use the `make` targets.
2. **`make qa` must be green before you call a task finished.** It is exactly
   what CI runs: PHP-CS-Fixer, PHPStan `max`, Deptrac, Rector, PHPUnit, Behat,
   Infection (MSI 100 %).
3. **Do not weaken the quality gate.** Lowering the PHPStan level, adding a
   blanket `ignoreErrors`, relaxing a Deptrac rule or skipping a test to make a
   build pass is never the fix. Narrow, commented exceptions are acceptable when
   justified.
4. **Do not commit** unless explicitly asked.
5. **Never commit secrets.** `config/jwt/*.pem` is git-ignored; keep it that way.

## Where code goes

```
src/<Module>/Domain/            business rules, entities, value objects, events,
                                repository *interfaces* — no HTTP, no API Platform,
                                no mailer (Doctrine mapping attributes are the one
                                allowed exception, see ADR-0002)
src/<Module>/Application/       use cases, message handlers, orchestration
src/<Module>/Infrastructure/    Doctrine, API Platform, validators, channels, console
```

Modules: `Catalog` (the business capability), `Notification` (generic delivery),
`Security` (users and JWT), `Shared` (aggregate root, `Money`, timestamps,
domain-event publishing).

Allowed dependencies are declared in `deptrac.dist.yaml`. If a change needs a
new edge in that graph, that is a design decision — raise it, do not quietly add
the layer to the ruleset.

## Conventions

- `declare(strict_types=1);` in every file.
- Classes are `final` unless they are deliberately designed for extension.
- Constructor property promotion; `readonly` wherever the state does not change.
- Entities expose **intent-revealing methods**, never public setters. If you
  need to change a `Product`, add a method that upholds its invariants.
- No `float` for money — `Money` stores minor units.
- Timestamps are set by `TimestampableTrait` through Doctrine lifecycle
  callbacks, driven by `Symfony\Component\Clock`. Never set them by hand, and
  never expose them as writable in the API.
- Global classes stay fully qualified (`\DateTimeImmutable`); this matches both
  the coding standard and the Rector configuration.
- Comments explain *why*, not *what*. The coding standard and the type system
  already describe *what*.
- Documentation, code, comments and commit messages are written in English.

## Common tasks

**Add a notification channel** — one class in
`src/Notification/Infrastructure/Channel/` implementing `NotificationChannel`.
No registry to edit: implementations are tagged by an `_instanceof` rule in
`config/services.yaml` and `ChannelRegistry` consumes the tag. Add a unit test next to the existing channel
tests. See ADR-0004.

**Add a field to a product** — the domain entity *and* `ProductResource` *and*
`ProductMapper` *and* a migration. The resource is a DTO on purpose (ADR-0006);
do not shortcut by exposing the entity.

**Change the HTTP contract** — update the resource, the mapper, the Behat
feature, and the README's endpoint table.

**Change the schema** — generate a migration
(`ddev exec php bin/console make:migration`), never edit an applied one, and
check `doctrine:schema:validate` afterwards.

## Testing expectations

- New behaviour needs a test. Bug fixes need a test that fails before the fix.
- Unit tests for domain rules and the notification pipeline; Behat for anything
  visible over HTTP.
- Use `self::createStub()` rather than `createMock()` when no expectations are
  configured — PHPUnit 13 emits a notice otherwise.
- Infection requires an MSI of 100 %. Kill a surviving mutant with an
  assertion; only a provably equivalent mutant may be ignored in
  `infection.json5`, with a comment saying why. Lowering the threshold counts as
  weakening the gate.
- Behat contexts must keep `$client->disableReboot()`; a rebooted kernel loses
  the in-memory queue the assertions rely on.

## Gotchas discovered the hard way

- Behat 4 requires `behat.dist.php`; YAML configuration files are ignored.
- `#[Target('commandBus')]` does not work for the *default* Messenger bus —
  inject plain `MessageBusInterface`.
- API Platform sets its own `X-Frame-Options`, so `SecurityHeadersSubscriber`
  only fills in headers that are absent.
- The DDEV `db` user cannot create databases; `db_test` is created by the
  `post-start` hook in `.ddev/config.testdb.yaml`.
- Files written to the host's `/tmp` are invisible inside the container — keep
  scratch files under the project directory.
