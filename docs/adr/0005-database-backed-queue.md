# ADR-0005 — The queue is a database table

**Status:** accepted

## Context

Notifications must be asynchronous. The usual answers are RabbitMQ, Redis or
SQS; each adds a service to run, monitor and back up.

## Decision

Messenger's **Doctrine transport** (`doctrine://default?queue_name=notifications`),
stored in `messenger_messages` in the same MySQL instance as the business data.
A dedicated DDEV `worker` container consumes it.

Retries: 3 attempts, exponential back-off, then the `failed` transport.

## Consequences

- Zero extra infrastructure; one `ddev start` boots everything.
- The queue lives in the same database and on the same connection, so the
  queue insert shares the product's transaction — a transactional outbox for
  free (ADR-0003). Swapping to an external broker would give that up and need
  a dedicated outbox table.
- Lower throughput than a dedicated broker, and polling instead of push. For a
  product catalogue this is irrelevant; the transport is one line of
  configuration to change if it ever is not.
- Tests swap it for `in-memory://`, so assertions about the queue stay fast and
  deterministic.
