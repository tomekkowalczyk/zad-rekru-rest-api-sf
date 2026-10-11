# ADR-0004 — Fan-out happens before the queue, one message per channel

**Status:** accepted

## Context

A notification goes to several channels (operation log, e-mail, later Slack or
SMS). Two shapes are possible:

1. queue **one** message and let a single handler loop over every channel;
2. resolve the channels first and queue **one message per channel**.

## Decision

Option 2. `AsyncNotificationDispatcher` asks `ChannelRegistry` which channels
support the notification and dispatches a separate `SendNotification` command
for each.

`SendNotificationHandler` deliberately lets exceptions bubble up — that is what
drives Messenger's retry policy and the `failed` transport.

## Consequences

- A broken Slack webhook cannot delay or prevent the e-mail.
- Retries are per channel: a channel that already succeeded is never re-run, so
  nobody receives a duplicate e-mail because SMS was down.
- `messenger:failed:show` names the exact channel that failed.
- Slightly more rows in `messenger_messages`. Irrelevant at this scale.

## Adding a channel

Implement `NotificationChannel`. Implementations are tagged by an
`_instanceof` rule in `config/services.yaml` and `ChannelRegistry` consumes the tag through
`#[AutowireIterator]`, so there is no registry and no configuration to edit.
