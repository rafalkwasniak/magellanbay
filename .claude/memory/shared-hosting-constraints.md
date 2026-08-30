---
name: shared-hosting-constraints
description: "shop.kwasniak.org runs on shared hosting; some things are unreliable or blocked — favour lightweight, robust choices"
metadata: 
  node_type: memory
  type: project
  originSessionId: 95e26b90-4a53-4f6a-ad4d-f806f2cbc32c
---

The MyShop project runs on a shared host (CloudLinux/cPanel, user host473413). Rafał flagged that on shared hosting "sometimes something does not work or is blocked".

**Why it matters:** influences architecture choices — prefer lightweight, dependency-light, robust solutions over heavy frameworks/services that may be blocked or fail. Queue/cache/session already on `database` driver (no Redis dependency). Be cautious with anything needing long-running processes, exotic PHP extensions, or outbound ports that may be filtered.

**How to apply:** when picking packages or infra (e.g. admin panel framework, queue worker strategy, image processing, outbound API calls), weigh shared-hosting reliability. Verify a capability works on this box before committing to it. Related: [[storefront-theme-system]].

**Konkret — limit forka/wątków (EAGAIN, "Resource temporarily unavailable"):** procesy, które agresywnie forkują/odpalają pulę wątków, padają na tym hoście. Dwa znane przypadki i obejścia:
- `npm run build` (Vite/Rolldown) → prefiksuj `RAYON_NUM_THREADS=1` — patrz [[vite-build-rayon-threads]]. Twardy abort objawia się też jako SIGABRT ubijający proces nadrzędny.
- `git push` → potrafi paść na `cannot fork() for pack-objects`; ponów z `git -c pack.threads=1 push`.
