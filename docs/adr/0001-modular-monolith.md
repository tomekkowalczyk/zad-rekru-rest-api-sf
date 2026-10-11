# ADR-0001 — A modular monolith instead of a layered CRUD application

**Status:** accepted

## Context

The exercise is small (two entities), but it is explicitly judged on code
quality and on being "ready for easy extension". A flat `src/Entity`,
`src/Controller`, `src/Service` layout answers the first part of the brief and
fails the second: every new capability lands in the same three folders, and
nothing stops a mailer from being injected into an entity.

## Decision

The code is organised as a **modular monolith**: `Catalog`, `Notification`,
`Security` and `Shared`, each split into `Domain`, `Application` and
`Infrastructure`.

Boundaries are enforced mechanically by Deptrac (`deptrac.dist.yaml`), not by
convention:

- a module never reaches into another module's `Infrastructure`;
- `Catalog` knows the notification **contract** (`Notification\Domain`) and
  nothing about e-mail, Slack or logs;
- `Domain` must not depend on HTTP, API Platform or the mailer.

## Consequences

- More directories than the problem strictly needs. Accepted: the structure is
  the answer to "ready for extension".
- Extracting `Notification` into its own service later is a `git mv` plus a
  transport swap, because nothing in it points back at `Catalog`.
- A violation fails CI, so the architecture cannot silently erode.
