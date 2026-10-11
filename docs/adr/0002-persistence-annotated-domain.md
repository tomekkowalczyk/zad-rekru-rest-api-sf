# ADR-0002 — Doctrine mapping attributes live on the domain entities

**Status:** accepted

## Context

A purist DDD setup keeps the domain free of every framework reference and maps
it with XML, or maps separate persistence models and translates between them.
Both options cost indirection.

## Decision

Domain entities (`Product`, `Category`, `User`) carry Doctrine **mapping**
attributes. They carry nothing else: no API Platform resources, no serializer
groups, no validation constraints that belong to the HTTP contract.

The HTTP layer has its own models — `ProductResource`, `CategoryResource`,
`MoneyPayload` — mapped by explicit mappers, so the public contract and the
database schema can evolve independently.

## Consequences

- One pragmatic framework dependency in the domain, limited to `Doctrine\ORM`
  and encoded as an explicit exception in the Deptrac ruleset — the exception is
  visible rather than implicit.
- Everything else stays out: the domain cannot accidentally depend on HTTP.
- Swapping the ORM would require touching the entities. Judged far less likely
  than the HTTP contract changing, which costs nothing.
