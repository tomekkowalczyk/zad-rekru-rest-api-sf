# Copilot instructions

This project follows the working agreement in [`AGENTS.md`](../AGENTS.md).
Read it before proposing changes; the essentials are repeated below.

## Environment

Everything runs in DDEV. Never run `php`, `composer` or `vendor/bin/*` directly
on the host.

```bash
ddev composer qa        # full quality gate — must be green
ddev composer test      # PHPUnit + Behat
ddev composer mutation  # Infection, MSI 100 % required
make help               # every available target
```

## Architecture

Modular monolith: `src/<Module>/{Domain,Application,Infrastructure}` for the
modules `Catalog`, `Notification`, `Security` and `Shared`. Dependencies between
modules and layers are enforced by Deptrac (`deptrac.dist.yaml`) — suggestions
that cross a boundary will fail CI.

- `Domain` holds business rules and must not reference HTTP, API Platform or the
  mailer. Doctrine mapping attributes are the single allowed exception.
- API Platform resources are DTOs (`ProductResource`, `CategoryResource`), never
  the Doctrine entities.
- `Catalog` depends on the notification *contract*, never on a channel.

Rationale for each of these lives in `docs/adr/`.

## Style

- `declare(strict_types=1);` everywhere; `final` classes; promoted, `readonly`
  properties where possible.
- Entities expose intent-revealing methods (`rename()`, `changePrice()`), never
  setters.
- Money is `Money` in minor units — never a `float`.
- Timestamps come from `TimestampableTrait`; do not set them manually.
- Global classes stay fully qualified (`\DateTimeImmutable`).
- Comments explain *why*. English only.

## Quality gate

PHPStan runs at level `max` and Deptrac reports zero violations. Do not suggest
lowering a level, adding a blanket `ignoreErrors`, relaxing an architecture rule
or skipping a test in order to make a build pass.

New behaviour needs a test: unit tests for domain rules and the notification
pipeline, Behat for anything visible over HTTP.
