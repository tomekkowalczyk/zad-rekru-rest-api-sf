# ADR-0006 — API Platform resources are DTOs, not entities

**Status:** accepted

## Context

API Platform's shortest path is `#[ApiResource]` on the Doctrine entity. It is
also the path that welds the public HTTP contract to the database schema and
fills the domain with serializer and validation metadata.

## Decision

Dedicated resources — `ProductResource`, `CategoryResource`, `MoneyPayload`,
`CategoryReference` — with `stateOptions: new Options(entityClass: …)`.
Providers decorate the built-in Doctrine providers (preserving pagination via
`TraversablePaginator`) and map entities to resources; processors translate HTTP
intent into domain method calls.

Related choices:

- Categories are referenced by their **business code** (`categoryCodes` on
  write, `categories` on read) rather than by IRI — the code is the stable,
  human-meaningful identifier, and it keeps payloads readable.
- Read and write serializer groups are disjoint, so timestamps and identifiers
  cannot be mass-assigned.

## Consequences

- Two mapping classes per aggregate. In exchange the domain stays clean, and the
  state processors are the single place where HTTP meets the domain.
- Renaming a column does not break the API; changing the API does not require a
  migration.
