# McGreen Acres Newsletter Send Log

Two things for Simplenews issue mail sent from cron:

1. **Doesn't mark a recipient permanently failed over a dropped SMTP
   connection.** Stock Simplenews leaves a failed send pending, then at the end
   of the cron batch flips every pending failure to *failed, not retrying* if
   any other mail in the batch went out. One "Connection to ... has been closed
   unexpectedly" therefore cost that recipient their copy for good.
2. **Records every failed attempt** (recipient, time, retry number, error
   message) and adds a *See details* link after the "N mails sent out of M, N
   errors" text on `/node/N/simplenews`, pointing to
   `/node/N/simplenews/send-details`.

## How a failed send is handled

`RetryingMailer` replaces the `simplenews.mailer` service class (see the
service provider) and wraps `sendMail()` for issue mail (key `node`):

| Situation | Result |
| --- | --- |
| Error is a transient transport problem (dropped/refused/timed out, SMTP 4xx) | Retry immediately, up to `in_place_retries` times, waiting `retry_delay` × retry number seconds |
| Retries exhausted, nothing sent yet in this batch | Throw `AbortSendingException`: transport looks down; the row and the rest of the batch go back to pending for the next cron run |
| Retries exhausted, something already sent in this batch | Simplenews default (pending, then failed at end of batch): looks recipient-specific |
| Recipient already failed `max_total_attempts` times across cron runs | Permanently failed, so a stuck row can't loop |
| Any other error (e.g. SMTP 5xx such as a daily sending limit) | Simplenews default, untouched |

What counts as transient lives in `TransportErrorClassifier`; 5xx replies are
deliberately *not* transient so a provider limit can't mass-retry or mass-fail.

The error message comes from `SendErrorCollector`, a logger that listens for
Symfony Mailer's `Error sending email: %message` entry (the mailer swallows the
exception and doesn't return it).

## Log

Table `mcgreen_newsletter_send_attempt`: one row per failed attempt, plus a
`sent` row for a recipient that succeeded after at least one failure. Successes
on the first try are not logged. Rows are purged after `retention_days` and
when the issue node is deleted. The details page also lists recipients that
Simplenews marked failed before this module was enabled, flagged "No details
were recorded".

## Settings

`mcgreen_acres_newsletter_send_log.settings` (no UI; `drush cset`):

- `in_place_retries` (2), `retry_delay` (3 s), `max_total_attempts` (6),
  `retention_days` (180).

## Caveats

- A retry after "closed unexpectedly" can in rare cases duplicate a message if
  the server accepted it just before dropping the connection. Stock Simplenews
  has the same exposure when it retries on the next cron run.
- The retry sleeps inside the cron request, so keep `in_place_retries` ×
  `retry_delay` small relative to the Simplenews throttle and PHP time limit.
- Only recipients of the *current* spool can be shown as queued; Simplenews
  deletes finished rows (`spool_expire` is 0).

## Tests

`lando phpunit web/modules/custom/mcgreen_acres_newsletter_send_log` (needs
`web/core/tests`, which is stripped by core vendor hardening: see CLAUDE.md).
