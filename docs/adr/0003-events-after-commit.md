# ADR-0003 — Domain events are published after COMMIT

**Status:** accepted

## Context

A product save must trigger notifications. The obvious hooks (`postPersist`,
`postUpdate`, or simply dispatching inside the state processor) all fire while
the transaction is still open. If the transaction then rolls back, the e-mail
has already been sent and the log already written, describing a product that
does not exist. This class of bug is close to impossible to reproduce.

## Decision

Aggregates record events in memory (`AggregateRoot::recordThat()`).
`PublishDomainEventsListener` subscribes to Doctrine's **`postFlush`** event,
collects the recorded events from the identity map and puts them on the event
bus — after the commit.

Supporting decisions:

- **Events carry identifiers only.** `ProductSaved` holds a `Uuid`; the handler
  re-reads the aggregate, so the notification can never describe a stale state.
- **One event per transaction.** `Product::recordChange()` checks
  `hasRecorded(ProductSaved::class)`, so renaming *and* repricing *and*
  recategorising in one request produces exactly one notification.
- A re-entrancy guard prevents the listener recursing when a handler flushes;
  events recorded by such a nested flush are collected in a loop rather than
  dropped.
- **Transactional outbox.** `DoctrineProductRepository::save()` flushes inside
  `wrapInTransaction()`. `postFlush` therefore fires after the flush's own
  commit but *before* the outer one, and the `SendNotification` messages the
  handler dispatches are inserted into `messenger_messages` over the same
  connection. Product row and queued notifications commit, or roll back,
  atomically. Nothing user-visible (e-mail, log entry) happens until the worker
  consumes a committed message.

## Consequences

- A rolled-back transaction notifies nobody.
- The worker builds nothing from uncommitted state: it only sees messages whose
  transaction committed.
- No dual write: a crash between the product write and the queue insert is
  impossible, because they are one transaction.
- The flip side: if the queue insert fails, the save fails too (`500`) and the
  product is not stored. Losing a write is preferable to a silently missing
  notification.
- Writes that bypass the repository (fixtures, test set-up) still publish in
  `postFlush`, but without the atomicity guarantee.
