---
name: email-outbox-cron-pattern
description: "Email sending uses a DB outbox table drained by a cron command (not queue:work), extended to double as the per-order communication history"
metadata: 
  node_type: memory
  type: project
  originSessionId: 95e26b90-4a53-4f6a-ad4d-f806f2cbc32c
---

For MyShop email, Rafał endorsed the kociaczek.com.pl pattern as "the ideal solution for the products he builds": an `email_messages` outbox table drained by a short-lived cron command (`email:dispatch`), NOT a long-running `queue:work` daemon — because short cron processes are safe on CloudLinux LVE shared hosting while daemons are not. See [[shared-hosting-constraints]].

Kociaczek mechanics to reuse: insert a row to "send"; cron sends a batch (priority desc, oldest first); retry with backoff; `batch_size`/`max_attempts`/`retry_delay_minutes` in config; permanent fail sets `failed_at`; generic content model (subject, greeting, intro_lines, action CTA, outro_lines) rendered via a shared Markdown mailable.

**Adaptations our spec requires (vs kociaczek):**
- Persist the FROZEN rendered HTML at send time (spec wants exact message preview, byte-identical to what was sent).
- Add `template` (template id) and `order_id` / `shop_id` (emails are tied to orders and shops).
- The same table doubles as the spec's "Historia komunikacji" (communication history) per order — one mechanism, two jobs.

**Status:** direction agreed, details to finalize when we build the activation/registration flow (first emails). Will also need a cPanel cron entry for `email:dispatch` and a real SMTP (Rafał will provide). General principle for this host: background work runs as short scheduled commands, not daemons.
