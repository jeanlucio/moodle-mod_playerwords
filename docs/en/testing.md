---
layout: default
title: 🧪 Automated Tests
parent: English
nav_order: 8
---

PlayerWords ships with a PHPUnit test suite covering business logic, repository queries, web
services, and Privacy API compliance. Every CI push runs against the full matrix (Moodle 4.5 →
5.x, PostgreSQL & MariaDB).

### PHPUnit — Core Tests

| Test file | Cases | What is covered |
|-----------|------:|----------------|
| `backup_restore_test.php` | 5 | Duplicating an activity copies its words, renames the copy, rebuilds the course cache, and does not create a duplicate grade item — regression guard for a missing `prepare_activity_structure()` call; a PlayerHUD item reference survives a same-course "Duplicate activity" unchanged (the block is never part of that narrower backup); a full course backup/restore into a new course remaps the reference to the new item's id, via the `playerhud_item` restore mapping block_playerhud's own restore step registers; a reference to another course's item is dropped rather than kept pointing at the wrong course, against the real `backup_controller`/`restore_controller`, not a hand-rolled shortcut; a full course backup/restore preserves the grade/ranking scoring mode settings and both `score` and `rankingpoints` on a finished attempt |
| `cross_instance_security_test.php` | 4 | Session state, word lookups by id, attempt records, and the "my attempts" history query never leak between two different activity instances, even for the same student in the same course |
| `lib_grant_potential_test.php` | 6 | The `playerhud_grant_potential` callback discovered by PlayerHUD's own "Total XP in the game" ceiling estimate: empty for an unrecognised block instance, for an activity with no win-grant item configured, and for an unlimited activity (mirrors the anti-farming rule on the real grant); a bounded activity returns one row shaped like PlayerHUD's own item/quest breakdown entries (`qty × max_rounds × item xp`); a win-grant item belonging to a different course's block instance contributes nothing; two bounded activities in the same course each contribute their own row |
| `lib_reset_userdata_test.php` | 4 | Course reset deletes attempts and resets grades only when the checkbox is enabled, only for the target course, and the form default enables it |
| `completion/custom_completion_test.php` | 7 | Custom completion rule ("require attempts"): incomplete below threshold, complete at threshold, rule not reported as available when disabled, defined rule names, rule description includes the required count, display sort order, a still-pending reservation (round started but not finished) is not counted towards the threshold |
| `privacy/provider_test.php` | 12 | Metadata declaration; contexts by attempts; contexts by words added; list users in context (and no-op for a non-module context); export user data (and no-op for an empty contextlist); delete user data across a single and across multiple contexts; delete all users' data in a context (leaving another activity untouched, and no-op for a non-module context) |
| **Subtotal** | **38** | |

### Local Business-Logic Tests (`tests/local/`)

| Test file | Cases | What is covered |
|-----------|------:|----------------|
| `ai_word_generator_test.php` | 12 | AI response parsing (`words`/legacy `concepts` wrappers, bare list, markdown code fence stripped, malformed/non-array JSON, hint falls back to `definition`, non-array entries skipped) and untrusted-input term validation (single alphabetic word accepted; empty, multi-word, and non-alphabetic terms rejected) — all via reflection, no real AI call |
| `attempts_history_service_test.php` | 14 | Own attempt history and current grade: empty with no finished rounds; excludes a still-pending reservation; rows shown most-recent-first while the grade calculation itself uses ascending order; the computed grade matches `playerwords_calculate_user_grade()` for the configured method; grade summary hidden for an ungraded activity; word text falls back from concept to the raw word; time used formatted as m:ss; the ranking-points column is shown, formatted to 2 decimals, when ranking is enabled, and omitted entirely when it is disabled. All-students report (`get_all_history`): every student's finished attempts included with their name attached, most-recent-first by default; excludes anyone who can manage the activity, from both the report rows and the student filter dropdown; the `studentid` filter restricts to one student's own rows; sorting only accepts allow-listed columns, falling back to date instead of erroring on an unknown key; pagination returns distinct slices of the full result set |
| `gameplay_service_test.php` | 19 | Letter feedback algorithm across 9 guess/target combinations (correct, absent, present, duplicate letters, pool exhaustion); score calculation for win, loss, and decimal grades under Binary mode; Linear mode for both the grade and the ranking-points calculation (full marks on both of the first two attempts, scaled proportionally from the third attempt onward, a positive non-zero share on the last allowed attempt, degenerates to full credit on every attempt when max_attempts is 2 or fewer, zero when not completed) |
| `hud_service_test.php` | 21 | Delegates to block_playerhud's `\block_playerhud\local\external_items` API for every item operation, validating ownership against the caller's own block instance instead of reading block_playerhud's tables directly: block lookup across courses; course availability (true with a block instance, false without one, ignores another course's instance); item name resolution (empty for an item belonging to a different block instance); item list retrieval; consume items (insufficient funds, success, FIFO order, zero-quantity short-circuit, waived — not blocked — for an item belonging to a different block instance); grant items (inventory rows tagged `source='playerwords'` plus XP awarded, XP withheld when the caller flags the source as unbounded, zero-XP items never change XP either way, unknown item, foreign-instance item, and zero quantity are all no-ops) |
| `ranking_service_test.php` | 6 | Empty ranking; score-descending ordering; top-5 truncation with an outsider row for a lower-ranked current user; `SEPARATEGROUPS` filters to the student's own group; a still-pending reservation (round in progress or abandoned without finishing) is excluded from the ranking; a user who can manage the activity (editingteacher) never appears in the ranking, even with attempts of their own |
| `round_presenter_test.php` | 35 | Grid row rendering; cooldown text; feedback messages (forfeited/timed out/lost/won, varying by attempts used); ranking context; round result context (blank until finished, reveals on finish, cooldown reflects a later settings change); lobby PlayerHUD balance/cost (shown/hidden by round state, start disabled below the required quantity, enabled once the balance covers it), lobby timer info; round panel hint-button PlayerHUD balance/cost (shown/hidden by reveal state, hint disabled below the required quantity, enabled once the balance covers it), timer stays at zero before the round starts; grade-so-far summary (absent before finished, absent when ungraded, shows method and computed grade once finished, ignores a still-pending attempt); lobby grading-method info line (shown when relevant, hidden for a single-round activity, hidden when ungraded); the keyboard's Ç key only appears once the activity's own word pool needs it; rounds-played counter shown in the lobby and in the round result, using the infinity symbol for an unlimited activity and the configured limit otherwise; the PlayerHUD win-grant label is shown only on an actual win with an item configured, blank on a loss or when unconfigured |
| `round_service_test.php` | 30 | Round state transitions: word picked and `round_started` fired; guess submission (wrong, correct, out of attempts, after finish, length mismatch); forfeit; timeout (finishes once the deadline has passed, rejected before the deadline, rejected when the timer is disabled); new round; restriction notice (max rounds reached, unrestricted); `count_rounds_played` scoped to instance and user; cooldown computation (disabled, no attempts yet, expired, reflects a later settings change); recovers by picking a fresh word after the previous one was removed mid-round; `start_round` reserves an attempt row; `finish_round` completes the reservation instead of duplicating it; an abandoned round still counts towards `max_rounds`; the stale reservation is discarded when the word is removed mid-round; a won round grants the configured PlayerHUD item with XP when `max_rounds` is bounded, grants it without XP when unlimited, and a lost round never grants it; a round or hint cost pointing at a deleted item, or an item belonging to a different course, is waived rather than blocking the student forever; a cost pointing at a merely disabled (not deleted) item still blocks correctly when the balance is short — disabling is reversible, so the cost is never waived for it |
| `view_page_service_test.php` | 8 | Page-assembly branches: fresh lobby, picked word persists across calls, finished round computes a real cooldown, restriction notice shown when the round limit is reached; the toolbar's help/attempt-history URLs are always present; the forfeit action is shown only during an active round; the help modal's ranking tie-break explanation is hidden when the teacher has turned ranking off; the help modal's PlayerHUD explanation appears as soon as any one of round cost, hint cost, or win grant is configured |
| `word_normalizer_test.php` | 8 | Accent-insensitive normalisation across 8 diacritic combinations |
| `words_repository_test.php` | 40 | Word picking (empty pool, unapproved/too-short/too-long/non-letter exclusion, random mode, shared-sequence determinism and cycling, avoids the excluded word when an alternative exists, allows it back in when it is the only candidate); `get_last_played_word_id` (0 with no finished rounds, ignores a pending reservation); manual and AI word insertion; `word_exists` duplicate check (case-insensitive match, no match, scoped to instance, matches regardless of source, ignores the excluded word id when renaming); word lookup, update and delete scoped to the owning instance; bulk delete and approve; recent-words listing with glossary name join; glossary sync (multi-word concept splitting, configurable stopword filtering, hint update on resync without duplicating, orphan cleanup when an entry disappears, `glossaryid = 0` covering every course glossary, skips a concept whose text already belongs to a manual/AI word without touching that word's hint); the on-screen keyboard's Ç key is only offered when an approved word actually contains one, scoped to its own activity and ignoring unapproved words |
| **Subtotal** | **193** | |

### Web Services Tests (`tests/external/`)

| Test file | Cases | What is covered |
|-----------|------:|----------------|
| `end_round_test.php` | 4 | Forfeit finishes the round; timeout finishes the round; an invalid `reason` value is rejected; the `mod/playerwords:view` capability is required |
| `new_round_test.php` | 3 | A new round picks a fresh word; blocked when the round limit was already reached; the `mod/playerwords:view` capability is required |
| `reveal_hint_test.php` | 6 | Hint is revealed; revealing twice is idempotent; rejected once the round is finished; the `mod/playerwords:view` capability is required; an insufficient PlayerHUD item balance (a real, valid item) blocks the reveal; a cost pointing at a deleted item is waived instead |
| `start_round_test.php` | 5 | Round timer starts; rejected when already started; the `mod/playerwords:view` capability is required; an insufficient PlayerHUD item balance (a real, valid item) blocks starting; a cost pointing at a deleted item is waived instead |
| `submit_guess_test.php` | 7 | A wrong guess never reveals the word; a correct guess reveals it only once finished; a losing guess also reveals it; the `mod/playerwords:view` capability is required; `timeleft` reflects seconds remaining while in progress; `timeleft` is frozen at the moment the round finished, not the wall clock; a fractional ranking total survives the external API's return-value cleaning, against the real webservice call |
| **Subtotal** | **25** | |

| **Grand Total** | **256** | |

```bash
vendor/bin/phpunit --testsuite mod_playerwords
```

### Line coverage by class (PHPUnit + Xdebug)

| Class | Line coverage |
|-------|:-------------:|
| `completion\custom_completion` | 100% |
| `external\end_round` | 76% |
| `external\new_round` | 50% |
| `external\reveal_hint` | 59% |
| `external\start_round` | 43% |
| `external\submit_guess` | 35% |
| `local\ai_word_generator` | 26%¹ |
| `local\gameplay_service` | 95% |
| `local\hud_service` | 90%³ |
| `local\ranking_service` | 75% |
| `local\round_presenter` | 68%² |
| `local\round_service` | 67% |
| `local\view_page_service` | 65% |
| `local\word_normalizer` | 100% |
| `local\words_repository` | 82% |
| `privacy\provider` | 85% |
| **Overall** | **60%** |

¹ Undercounted by design: `ai_word_generator`'s network-calling methods (`call_ai`, `call_core_ai`,
`has_core_ai`) require a real AI provider and are intentionally not unit-tested; the
untrusted-input parsing and validation logic they depend on (`parse_words`, `is_valid_term`) is
fully covered.

² Dropped from 95% after the grading-transparency feature added `build_grading_method_info()`/
`build_grade_so_far()`: the happy path (a real gradebook item with a computed grade) is tested,
but the "no grade item yet" and "grade not yet computed" fallback branches inside
`build_grade_so_far()` are not — left as a follow-up rather than padding the suite with tests for
states the running gradebook update flow makes hard to reach.

³ Dropped from 97% after `hud_service.php` was rewritten to delegate every item operation to
block_playerhud's own `\block_playerhud\local\external_items` API instead of containing the logic
locally: the FIFO-consume and grant logic this file used to own — and its dedicated tests — now
live in block_playerhud's own test suite, so this number reflects a thinner, mostly-delegating
file rather than a coverage regression.

The `external/*` web service classes score lower on raw line percentage than their actual
behaviour coverage suggests: each one is now tested for its happy path, every rejection branch,
the capability guard, and (where applicable) the PlayerHUD insufficient-item branch — but a
capability-guard test necessarily stops at `require_capability()` and never reaches the lines
after it, so it cannot raise the percentage of a class that is mostly "lines after the guard".
