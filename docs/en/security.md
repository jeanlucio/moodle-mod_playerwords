---
layout: default
title: 🔐 Security & Compliance
parent: English
nav_order: 9
---

# 🔐 Security & Compliance

* Capability-based access control (`mod/playerwords:view`, `mod/playerwords:addinstance`)
* `require_sesskey()` protection on all POST actions; AJAX calls are validated by Moodle's `core/ajax` dispatcher
* Server-side enforcement of round limits and cooldown, always recomputed from current settings
* Round timeout is re-validated against the server's own deadline (with a small network-latency tolerance) instead of trusting the client's countdown alone
* Guess charset validation — only Unicode letters accepted
* AI-generated words are treated as untrusted input: only single-token, alphabetic terms within the configured length bounds are saved, and they enter pending teacher approval
* Session round state is isolated per activity instance and per user — a word id or session key from one activity is never accepted by another
* Moodle External API compliant
* Privacy API fully implemented (GDPR/LGPD)
