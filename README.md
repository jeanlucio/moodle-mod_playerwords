# Moodle Activity PlayerWords

[![Moodle Plugin CI](https://github.com/jeanlucio/moodle-mod_playerwords/actions/workflows/ci.yml/badge.svg)](https://github.com/jeanlucio/moodle-mod_playerwords/actions/workflows/ci.yml)
![Moodle](https://img.shields.io/badge/Moodle-4.5%2B-orange?style=flat-square&logo=moodle&logoColor=white)
![License](https://img.shields.io/badge/License-GPLv3-blue?style=flat-square)
![Status](https://img.shields.io/badge/Status-Alpha-yellow?style=flat-square)
[![PlayerGames Ecosystem](https://img.shields.io/badge/PlayerGames-Ecosystem-6f42c1?style=flat-square&logo=gamepad&logoColor=white)](https://moodle.org/plugins/browse.php?list=contributor&id=3970322)
![Game Activity](https://img.shields.io/badge/Role-Game_Activity-198754?style=flat-square)

[English](#english) | [Português](#português)

---

## English

**PlayerWords** is a word-guessing vocabulary activity for Moodle. Students guess a hidden word letter-by-letter within a configurable number of attempts, receiving colour-coded and symbol feedback on each guess.

The activity integrates with the course **Glossary** (words and definitions are imported automatically), can generate word candidates through **AI**, and integrates with the **PlayerHUD** gamification block (items can be required to start a round or to reveal a hint, and an item can be granted for each round won).

<a id="toc-en"></a>
**📑 Table of Contents**

- [✨ Features](#-features)
- [🎓 Educational Purpose](#-educational-purpose)
- [🕹️ PlayerGames Ecosystem](#-playergames-ecosystem)
- [📦 Requirements](#-requirements)
- [🛠️ Installation](#-installation)
- [📖 Usage](#-usage)
- [🧮 Grading & Ranking](#-grading--ranking)
- [🧪 Automated Tests](#-automated-tests)
- [🔐 Security & Compliance](#-security--compliance)
- [📄 License / Licença](#-license--licença)

---

### ✨ Features

* 🟩 **Word-Guessing Gameplay:** Colour-coded + symbol feedback per letter (correct position, wrong position, absent).
* 📖 **Glossary Integration:** Import concepts from one or all course glossaries as the word pool, with definitions used as hints.
* 🤖 **AI Word Generation (Optional):** Generate candidate words and hints for a given topic via `local_aihub` (BYOK) or Moodle's `core_ai` fallback. Generated words are treated as untrusted input — only single-token, purely alphabetic terms within the configured length bounds are saved, and they enter the pool pending teacher approval.
* ✍️ **Manual Word Pool:** Teachers can add, edit, approve, and delete words directly from the management page.
* 🔀 **Word Modes:** Random word per round (default) or shared sequence mode, where every student receives the same words in the same order.
* 🎲 **Word Rotation:** In random mode, the same word never repeats on the very next round for a student, unless it is the only word left in the pool.
* 💡 **Hidden Hint System:** Hint is hidden by default; students must explicitly reveal it (optionally at an item cost via PlayerHUD).
* 🏳️ **Give Up:** Students can forfeit the current round at any time — the correct word is revealed immediately.
* ⏱️ **Configurable Cooldown:** Minimum wait between rounds (minutes, hours, or days), always recomputed from the activity's current setting — a teacher's change applies immediately, even to a cooldown already in progress.
* 🔢 **Round Limit:** Teachers can cap the total number of rounds per student (1–10 or unlimited). Students see a rounds-played counter (e.g. "3 / 10" or "3 / ∞") both in the lobby and after each round.
* 🛡️ **Round-Limit Integrity:** A round abandoned mid-play (closed tab, lost session) still counts against the round limit — reserved the moment it starts, not only once it finishes, so it can never grant a free re-roll.
* 🔡 **Accent-Insensitive Matching:** Diacritics are always stripped before comparing guess and target.
* 📊 **Grading Methods:** Highest grade, average grade, first attempt, last attempt, or average over all required rounds.
* ⚖️ **Configurable Scoring Mode:** Choose Binary (all-or-nothing) or Linear (proportional to attempts spared) independently for the grade and for the ranking — see [🧮 Grading & Ranking](#-grading--ranking). Locked once the activity has recorded a real grade, so every round is guaranteed to be scored under the same rules.
* 🧮 **Grading Transparency:** Students see the active grading method before playing and their live computed grade after each round, the same way mod_quiz communicates its own grading method.
* 📋 **Gradebook Integration:** Grades are written automatically on every round completion.
* ✅ **Custom Completion Rule:** Minimum number of attempts completed, evaluated and applied immediately after each round.
* 🔄 **Course Reset Support:** "Reset course" clears student attempts and resets grades for the activity, scoped to the target course only.
* 🏆 **Ranking Page:** Leaderboard scoped to the activity, with outsider row for students outside the top positions, respecting `SEPARATEGROUPS`.
* 📋 **Attempt History:** Students can review every finished round of their own — word, attempts used, time, score, and date — plus their currently computed grade, at any time via the toolbar.
* ❓ **In-Game Help:** A dedicated help page explains the letter-feedback colours, attempts, hints, timer, and the activity's grading method.
* ♿ **Accessibility:** WCAG AA contrast on all grid states; non-colour indicators (✓ correct, ~ present); `aria-label` on every cell; a live region announces state changes for screen readers.
* ⚡ **AJAX-Powered:** Every round transition (guess, hint, forfeit, timeout, start, new round) happens without a page reload.
* 🎮 **PlayerHUD Integration (Optional):** Require inventory items to start a round or to reveal a hint, with atomic FIFO consumption. The student's current balance against the required quantity is always shown up front, and the action is disabled — not just rejected after the click — when they can't afford it; a cost pointing at a deleted or another course's item is waived rather than locking the student out. Can also **grant** an item for each round won; matching PlayerHUD's own anti-farming rule, no XP is awarded from that item while the activity allows unlimited rounds — the item is still delivered, just without XP — and the potential win-grant XP is reflected in PlayerHUD's own "Total XP in the game" ceiling estimate.
* 🛡️ **Safe Cross-Course Integration:** Every PlayerHUD item reference is validated against the course's own block instance, never a stale or another course's item — even after backup/restore or course duplication. Settings preserve a disabled or deleted item as a clearly labelled option instead of silently resetting the field.
* 📦 **Backup & Restore:** Full Moodle 2 backup/restore support, including the "Duplicate activity" action, word pool, attempts, user/glossary id remapping, and safe PlayerHUD item remapping (dropped rather than kept pointing at another course's item when it isn't part of the same restore).
* 🔐 **Privacy API:** GDPR/LGPD compliant — complete data export and deletion for all stored personal data.

[⬆️ Back to index](#toc-en)

---

### 🎓 Educational Purpose

PlayerWords is designed to:

* Reinforce learning of concepts covered in the course or subject
* Foster playful, game-based learning experiences
* Simplify and make learning and assessment dynamics more intuitive
* Contribute to achieving educational goals across different courses and disciplines
* Promote active learning methodologies, including game-based learning and gamification
* Support **retrieval practice** — students must recall a concept from memory before seeing any answer, one of the most effective techniques for long-term retention
* Encourage **spaced practice** — the configurable recharge time between rounds brings students back to the content across multiple sessions, aligning with the principles of spaced repetition

Suitable for:

* Any course that uses concept-based terminology
* Gamified academic courses using the PlayerGames ecosystem
* Formative assessment and self-study reinforcement
* Engagement reinforcement strategies

[⬆️ Back to index](#toc-en)

---

### 🕹️ PlayerGames Ecosystem

PlayerWords is part of the **PlayerGames** gamification ecosystem for Moodle. Its main direct integration is with the PlayerHUD block:

* **PlayerHUD Block (Optional):** Configure item costs for starting a round or revealing a hint, and an item grant for each round won.
  👉 https://github.com/jeanlucio/moodle-block_playerhud

* **PlayerGroup (Compatible):** Standard Moodle groups — created manually or via the PlayerGroup activity — are honoured by the ranking's `SEPARATEGROUPS` filtering.
  👉 https://github.com/jeanlucio/moodle-mod_playergroup

See the author's [Moodle Plugins Directory profile](https://moodle.org/plugins/browse.php?list=contributor&id=3970322) for the full PlayerGames family.

[⬆️ Back to index](#toc-en)

---

### 📦 Requirements

| Component | Version |
|-----------|---------|
| Moodle    | 4.5+    |
| PHP       | 8.1+    |

[⬆️ Back to index](#toc-en)

---

### 🛠️ Installation

1. Download the `.zip` file or clone this repository.
2. Extract the folder into your Moodle `mod/` directory.
3. Rename the folder to `playerwords` (if necessary).
   Final path:
   `your-moodle/mod/playerwords/`
4. Visit **Site administration > Notifications** to complete installation.
5. Add a **PlayerWords** activity to any course.

[⬆️ Back to index](#toc-en)

---

### 📖 Usage

1. Add a **PlayerWords** activity to your course.
2. Configure:
   * Word length range and maximum attempts
   * Cooldown between rounds and round limit
   * Word mode (random or shared sequence)
   * Grading method and gradebook settings
   * Word sources (manual, Glossary, AI) and Glossary source (optional)
   * PlayerHUD item costs and win grant (optional, when PlayerHUD block is present)
3. Open the **Manage words** page to add, generate with AI, approve, edit, or delete words.
4. Students play directly from the activity page — guessing, revealing hints, and forfeiting rounds, with no page reload. The page's own toolbar gives access to the rules (help), attempt history, and the ranking.
5. Grades and ranking update automatically after each round.

[⬆️ Back to index](#toc-en)

---

### 🧮 Grading & Ranking

PlayerWords computes a **grade** and a **ranking** total from the same finished rounds, but the two are configured completely independently — a teacher can keep the grade simple while still rewarding efficient play in the ranking, or the other way around.

**Both are entirely optional, and each is switched on or off on its own:**

* **Grade:** leave the standard `Grade` field set to *None* to run the activity fully ungraded — no grade is ever computed or written to the gradebook, and the `Grading method` / `Grade scoring` settings disappear from the form.
* **Ranking:** leave `Show ranking` set to *No* to hide the ranking everywhere — in-game, on the dedicated ranking page, and the extra column in the attempt history — and the `Ranking scoring` setting disappears from the form too.

Turning one off never affects the other: an activity can be graded with no ranking, ranked with no grade, both, or neither.

**Per-round scoring** decides how much a single round is worth, chosen separately for the grade and for the ranking (`Grade scoring` / `Ranking scoring` settings, both default to **Binary**):

| Mode | A won round is worth... | A lost, forfeited, or timed-out round |
|---|---|---|
| **Binary** (default) | The full activity grade | Zero |
| **Linear** | A share of the full grade proportional to attempts spared: `grade × (max_attempts − attempts_used + 1) / max_attempts` | Zero |

Linear rewards guessing in fewer attempts, but never fully zeroes out a win — even winning on the very last allowed attempt still earns a small positive share. Example with a 100-point grade and 6 maximum attempts:

| Attempts used | Linear points |
|---:|---:|
| 1 | 100.00 |
| 2 | 83.33 |
| 3 | 66.67 |
| 4 | 50.00 |
| 5 | 33.33 |
| 6 | 16.67 |
| Not completed | 0.00 |

**Combining several rounds into one final grade** is a separate setting, `Grading method` (highest grade, average grade, first attempt, last attempt, or average over all required rounds — see [📖 Usage](#-usage)). It works the same regardless of whether the per-round scoring above is Binary or Linear: it only ever aggregates whatever value each round already recorded.

**The ranking** is the sum of every finished round's ranking points for a student (`SUM`), ordered highest first; ties are broken by fewer attempts used on average, then less time spent on average. It only appears when the teacher enables "Show ranking", and never reveals a round still in progress.

**Locked once graded:** the moment the activity records a real grade for any student, `Maximum attempts`, `Grade scoring` and `Ranking scoring` all lock — the same way Moodle already locks a graded activity's own "Maximum grade" field once real grades exist. This guarantees every round ever recorded for that activity was scored under the exact same rules, so the grade and the ranking total both stay internally consistent for the activity's whole lifetime.

**Attempt history:** each student can review their own past rounds — word, attempts used, time, grade score and (when ranking is enabled) ranking points — from the toolbar's attempt-history page.

[⬆️ Back to index](#toc-en)

---

### 🧪 Automated Tests

PlayerWords ships with a PHPUnit test suite covering business logic, repository queries, web services, and Privacy API compliance. Every CI push runs against the full matrix (Moodle 4.5 → 5.x, PostgreSQL & MariaDB).

#### PHPUnit — Core Tests

| Test file | Cases | What is covered |
|-----------|------:|----------------|
| `backup_restore_test.php` | 5 | Duplicating an activity copies its words, renames the copy, rebuilds the course cache, and does not create a duplicate grade item — regression guard for a missing `prepare_activity_structure()` call; a PlayerHUD item reference survives a same-course "Duplicate activity" unchanged (the block is never part of that narrower backup); a full course backup/restore into a new course remaps the reference to the new item's id, via the `playerhud_item` restore mapping block_playerhud's own restore step registers; a reference to another course's item is dropped rather than kept pointing at the wrong course, against the real `backup_controller`/`restore_controller`, not a hand-rolled shortcut; a full course backup/restore preserves the grade/ranking scoring mode settings and both `score` and `rankingpoints` on a finished attempt |
| `cross_instance_security_test.php` | 4 | Session state, word lookups by id, attempt records, and the "my attempts" history query never leak between two different activity instances, even for the same student in the same course |
| `lib_grant_potential_test.php` | 6 | The `playerhud_grant_potential` callback discovered by PlayerHUD's own "Total XP in the game" ceiling estimate: empty for an unrecognised block instance, for an activity with no win-grant item configured, and for an unlimited activity (mirrors the anti-farming rule on the real grant); a bounded activity returns one row shaped like PlayerHUD's own item/quest breakdown entries (`qty × max_rounds × item xp`); a win-grant item belonging to a different course's block instance contributes nothing; two bounded activities in the same course each contribute their own row |
| `lib_reset_userdata_test.php` | 4 | Course reset deletes attempts and resets grades only when the checkbox is enabled, only for the target course, and the form default enables it |
| `completion/custom_completion_test.php` | 7 | Custom completion rule ("require attempts"): incomplete below threshold, complete at threshold, rule not reported as available when disabled, defined rule names, rule description includes the required count, display sort order, a still-pending reservation (round started but not finished) is not counted towards the threshold |
| `privacy/provider_test.php` | 12 | Metadata declaration; contexts by attempts; contexts by words added; list users in context (and no-op for a non-module context); export user data (and no-op for an empty contextlist); delete user data across a single and across multiple contexts; delete all users' data in a context (leaving another activity untouched, and no-op for a non-module context) |
| **Subtotal** | **38** | |

#### Local Business-Logic Tests (`tests/local/`)

| Test file | Cases | What is covered |
|-----------|------:|----------------|
| `ai_word_generator_test.php` | 12 | AI response parsing (`words`/legacy `concepts` wrappers, bare list, markdown code fence stripped, malformed/non-array JSON, hint falls back to `definition`, non-array entries skipped) and untrusted-input term validation (single alphabetic word accepted; empty, multi-word, and non-alphabetic terms rejected) — all via reflection, no real AI call |
| `attempts_history_service_test.php` | 9 | Own attempt history and current grade: empty with no finished rounds; excludes a still-pending reservation; rows shown most-recent-first while the grade calculation itself uses ascending order; the computed grade matches `playerwords_calculate_user_grade()` for the configured method; grade summary hidden for an ungraded activity; word text falls back from concept to the raw word; time used formatted as m:ss; the ranking-points column is shown, formatted to 2 decimals, when ranking is enabled, and omitted entirely when it is disabled |
| `gameplay_service_test.php` | 18 | Letter feedback algorithm across 9 guess/target combinations (correct, absent, present, duplicate letters, pool exhaustion); score calculation for win, loss, and decimal grades under Binary mode; Linear mode for both the grade and the ranking-points calculation (full marks on the first attempt, a positive non-zero share on the last allowed attempt, zero when not completed) |
| `hud_service_test.php` | 21 | Delegates to block_playerhud's `\block_playerhud\local\external_items` API for every item operation, validating ownership against the caller's own block instance instead of reading block_playerhud's tables directly: block lookup across courses; course availability (true with a block instance, false without one, ignores another course's instance); item name resolution (empty for an item belonging to a different block instance); item list retrieval; consume items (insufficient funds, success, FIFO order, zero-quantity short-circuit, waived — not blocked — for an item belonging to a different block instance); grant items (inventory rows tagged `source='playerwords'` plus XP awarded, XP withheld when the caller flags the source as unbounded, zero-XP items never change XP either way, unknown item, foreign-instance item, and zero quantity are all no-ops) |
| `ranking_service_test.php` | 5 | Empty ranking; score-descending ordering; top-5 truncation with an outsider row for a lower-ranked current user; `SEPARATEGROUPS` filters to the student's own group; a still-pending reservation (round in progress or abandoned without finishing) is excluded from the ranking |
| `round_presenter_test.php` | 35 | Grid row rendering; cooldown text; feedback messages (forfeited/timed out/lost/won, varying by attempts used); ranking context; round result context (blank until finished, reveals on finish, cooldown reflects a later settings change); lobby PlayerHUD balance/cost (shown/hidden by round state, start disabled below the required quantity, enabled once the balance covers it), lobby timer info; round panel hint-button PlayerHUD balance/cost (shown/hidden by reveal state, hint disabled below the required quantity, enabled once the balance covers it), timer stays at zero before the round starts; grade-so-far summary (absent before finished, absent when ungraded, shows method and computed grade once finished, ignores a still-pending attempt); lobby grading-method info line (shown when relevant, hidden for a single-round activity, hidden when ungraded); the keyboard's Ç key only appears once the activity's own word pool needs it; rounds-played counter shown in the lobby and in the round result, using the infinity symbol for an unlimited activity and the configured limit otherwise; the PlayerHUD win-grant label is shown only on an actual win with an item configured, blank on a loss or when unconfigured |
| `round_service_test.php` | 30 | Round state transitions: word picked and `round_started` fired; guess submission (wrong, correct, out of attempts, after finish, length mismatch); forfeit; timeout (finishes once the deadline has passed, rejected before the deadline, rejected when the timer is disabled); new round; restriction notice (max rounds reached, unrestricted); `count_rounds_played` scoped to instance and user; cooldown computation (disabled, no attempts yet, expired, reflects a later settings change); recovers by picking a fresh word after the previous one was removed mid-round; `start_round` reserves an attempt row; `finish_round` completes the reservation instead of duplicating it; an abandoned round still counts towards `max_rounds`; the stale reservation is discarded when the word is removed mid-round; a won round grants the configured PlayerHUD item with XP when `max_rounds` is bounded, grants it without XP when unlimited, and a lost round never grants it; a round or hint cost pointing at a deleted item, or an item belonging to a different course, is waived rather than blocking the student forever; a cost pointing at a merely disabled (not deleted) item still blocks correctly when the balance is short — disabling is reversible, so the cost is never waived for it |
| `view_page_service_test.php` | 6 | Page-assembly branches: fresh lobby, picked word persists across calls, finished round computes a real cooldown, restriction notice shown when the round limit is reached; the toolbar's help/attempt-history URLs are always present; the forfeit action is shown only during an active round |
| `word_normalizer_test.php` | 8 | Accent-insensitive normalisation across 8 diacritic combinations |
| `words_repository_test.php` | 35 | Word picking (empty pool, unapproved/too-short/too-long/non-letter exclusion, random mode, shared-sequence determinism and cycling, avoids the excluded word when an alternative exists, allows it back in when it is the only candidate); `get_last_played_word_id` (0 with no finished rounds, ignores a pending reservation); manual and AI word insertion; word lookup, update and delete scoped to the owning instance; bulk delete and approve; recent-words listing with glossary name join; glossary sync (multi-word concept splitting, configurable stopword filtering, hint update on resync without duplicating, orphan cleanup when an entry disappears, `glossaryid = 0` covering every course glossary); the on-screen keyboard's Ç key is only offered when an approved word actually contains one, scoped to its own activity and ignoring unapproved words |
| **Subtotal** | **179** | |

#### Web Services Tests (`tests/external/`)

| Test file | Cases | What is covered |
|-----------|------:|----------------|
| `end_round_test.php` | 4 | Forfeit finishes the round; timeout finishes the round; an invalid `reason` value is rejected; the `mod/playerwords:view` capability is required |
| `new_round_test.php` | 3 | A new round picks a fresh word; blocked when the round limit was already reached; the `mod/playerwords:view` capability is required |
| `reveal_hint_test.php` | 6 | Hint is revealed; revealing twice is idempotent; rejected once the round is finished; the `mod/playerwords:view` capability is required; an insufficient PlayerHUD item balance (a real, valid item) blocks the reveal; a cost pointing at a deleted item is waived instead |
| `start_round_test.php` | 5 | Round timer starts; rejected when already started; the `mod/playerwords:view` capability is required; an insufficient PlayerHUD item balance (a real, valid item) blocks starting; a cost pointing at a deleted item is waived instead |
| `submit_guess_test.php` | 7 | A wrong guess never reveals the word; a correct guess reveals it only once finished; a losing guess also reveals it; the `mod/playerwords:view` capability is required; `timeleft` reflects seconds remaining while in progress; `timeleft` is frozen at the moment the round finished, not the wall clock; a fractional ranking total survives the external API's return-value cleaning, against the real webservice call |
| **Subtotal** | **25** | |

| **Grand Total** | **242** | |

```bash
vendor/bin/phpunit --testsuite mod_playerwords
```

**Line coverage by class (PHPUnit + Xdebug):**

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

¹ Undercounted by design: `ai_word_generator`'s network-calling methods (`call_ai`, `call_core_ai`, `has_core_ai`) require a real AI provider and are intentionally not unit-tested; the untrusted-input parsing and validation logic they depend on (`parse_words`, `is_valid_term`) is fully covered.

² Dropped from 95% after the grading-transparency feature added `build_grading_method_info()`/`build_grade_so_far()`: the happy path (a real gradebook item with a computed grade) is tested, but the "no grade item yet" and "grade not yet computed" fallback branches inside `build_grade_so_far()` are not — left as a follow-up rather than padding the suite with tests for states the running gradebook update flow makes hard to reach.

³ Dropped from 97% after `hud_service.php` was rewritten to delegate every item operation to block_playerhud's own `\block_playerhud\local\external_items` API instead of containing the logic locally: the FIFO-consume and grant logic this file used to own — and its dedicated tests — now live in block_playerhud's own test suite, so this number reflects a thinner, mostly-delegating file rather than a coverage regression.

The `external/*` web service classes score lower on raw line percentage than their actual behaviour coverage suggests: each one is now tested for its happy path, every rejection branch, the capability guard, and (where applicable) the PlayerHUD insufficient-item branch — but a capability-guard test necessarily stops at `require_capability()` and never reaches the lines after it, so it cannot raise the percentage of a class that is mostly "lines after the guard".

[⬆️ Back to index](#toc-en)

---

### 🔐 Security & Compliance

* Capability-based access control (`mod/playerwords:view`, `mod/playerwords:addinstance`)
* `require_sesskey()` protection on all POST actions; AJAX calls are validated by Moodle's `core/ajax` dispatcher
* Server-side enforcement of round limits and cooldown, always recomputed from current settings
* Round timeout is re-validated against the server's own deadline (with a small network-latency tolerance) instead of trusting the client's countdown alone
* Guess charset validation — only Unicode letters accepted
* AI-generated words are treated as untrusted input: only single-token, alphabetic terms within the configured length bounds are saved, and they enter pending teacher approval
* Session round state is isolated per activity instance and per user — a word id or session key from one activity is never accepted by another
* Moodle External API compliant
* Privacy API fully implemented (GDPR/LGPD)

[⬆️ Back to index](#toc-en)

---

## 📄 License / Licença

This project is licensed under the **GNU General Public License v3 (GPLv3)**.

**Copyright:** 2026 Jean Lúcio

[⬆️ Back to index](#toc-en)

---

## Português

O **PlayerWords** é uma atividade de adivinhação de palavras para o Moodle. O estudante adivinha uma palavra oculta letra por letra dentro de um número configurável de tentativas, recebendo feedback visual em cores e símbolos a cada chute.

A atividade integra-se com o **Glossário** do curso (palavras e definições são importadas automaticamente), pode gerar candidatas a palavra por **IA**, e integra-se com o bloco de gamificação **PlayerHUD** (itens podem ser exigidos para iniciar uma rodada ou revelar uma dica, e um item pode ser concedido a cada rodada vencida).

<a id="toc-pt"></a>
**📑 Índice**

- [✨ Funcionalidades](#-funcionalidades)
- [🎓 Finalidade Educacional](#-finalidade-educacional)
- [🕹️ Ecossistema PlayerGames](#-ecossistema-playergames)
- [📦 Requisitos](#-requisitos)
- [🛠️ Instalação](#-instalação)
- [📖 Como Usar](#-como-usar)
- [🧮 Nota e Ranking](#-nota-e-ranking)
- [🧪 Testes Automatizados](#-testes-automatizados)
- [🔐 Segurança e Conformidade](#-segurança-e-conformidade)
- [📄 Licença](#-licença)

---

### ✨ Funcionalidades

* 🟩 **Jogo de adivinhação de palavras:** Feedback por letra com código de cores + símbolos (posição correta, posição errada, ausente).
* 📖 **Integração com Glossário:** Importa conceitos de um ou todos os glossários do curso como pool de palavras, usando as definições como dicas.
* 🤖 **Geração de palavras por IA (Opcional):** Gera candidatas a palavra e dica para um tópico livre via `local_aihub` (chave própria) ou fallback para o `core_ai` do Moodle. A resposta é tratada como entrada não confiável — só termos de um único token, puramente alfabéticos e dentro do comprimento configurado são salvos, e entram no pool pendentes de aprovação do professor.
* ✍️ **Pool de palavras manual:** O professor pode adicionar, editar, aprovar e excluir palavras diretamente na página de gerenciamento.
* 🔀 **Modos de palavra:** Palavra aleatória por rodada (padrão) ou sequência compartilhada — todos os estudantes recebem as mesmas palavras na mesma ordem.
* 🎲 **Rotação de palavras:** No modo aleatório, a mesma palavra nunca se repete na rodada seguinte para um estudante, a menos que seja a única palavra restante no pool.
* 💡 **Dica oculta:** A dica é escondida por padrão; o estudante precisa clicar em "Revelar dica" (com custo opcional em itens via PlayerHUD).
* 🏳️ **Desistir:** O estudante pode abandonar a rodada a qualquer momento — a palavra correta é revelada imediatamente.
* ⏱️ **Tempo de recarga configurável:** Intervalo mínimo entre rodadas (minutos, horas ou dias), sempre recalculado a partir da configuração atual da atividade — uma mudança do professor vale imediatamente, mesmo para quem já está em cooldown.
* 🔢 **Limite de rodadas:** O professor pode limitar o total de rodadas por estudante (1–10 ou ilimitado). O estudante vê um contador de rodadas jogadas (ex.: "3 / 10" ou "3 / ∞") no lobby e após cada rodada.
* 🛡️ **Integridade do limite de rodadas:** Uma rodada abandonada no meio (aba fechada, sessão perdida) continua contando para o limite — reservada assim que começa, não só quando termina, então nunca dá um reroll de graça.
* 🔡 **Correspondência sem acentos:** Acentuação é sempre ignorada ao comparar chute e palavra-alvo.
* 📊 **Métodos de nota:** Maior nota, média, primeira tentativa, última tentativa ou média sobre todas as rodadas exigidas.
* ⚖️ **Modo de pontuação configurável:** Escolha Binária (tudo ou nada) ou Linear (proporcional às tentativas poupadas) de forma independente para a nota e para o ranking — veja [🧮 Nota e Ranking](#-nota-e-ranking). Trava assim que a atividade registra uma nota real, garantindo que toda rodada seja pontuada sob as mesmas regras.
* 🧮 **Transparência de avaliação:** O estudante vê o método de avaliação ativo antes de jogar e sua nota atual computada após cada rodada, do mesmo jeito que o Quiz do Moodle comunica seu método de avaliação.
* 📋 **Integração com o livro de notas:** Notas gravadas automaticamente ao final de cada rodada.
* ✅ **Regra de conclusão personalizada:** Número mínimo de tentativas realizadas, avaliada e aplicada imediatamente após cada rodada.
* 🔄 **Suporte a "Redefinir curso":** Limpa as tentativas dos estudantes e reseta as notas da atividade, restrito ao curso alvo.
* 🏆 **Página de ranking:** Classificação por atividade, com linha de "outsider" para estudantes fora das primeiras posições, respeitando `SEPARATEGROUPS`.
* 📋 **Registro de tentativas:** O estudante pode conferir cada rodada já concluída — palavra, tentativas usadas, tempo, nota e data — além da sua nota atual computada, a qualquer momento pelo toolbar.
* ❓ **Ajuda no jogo:** Uma página de ajuda dedicada explica as cores do feedback das letras, tentativas, dicas, temporizador e o método de avaliação da atividade.
* ♿ **Acessibilidade:** Contraste WCAG AA em todos os estados da grade; indicadores não visuais (✓ correto, ~ presente); `aria-label` em cada célula; região viva anuncia mudanças de estado para leitor de tela.
* ⚡ **Powered por AJAX:** Toda transição de rodada (chute, dica, desistência, timeout, iniciar, nova rodada) acontece sem recarregar a página.
* 🎮 **Integração com PlayerHUD (Opcional):** Exige itens do inventário para iniciar uma rodada ou revelar a dica, com consumo atômico em ordem FIFO. O saldo atual do estudante em relação à quantidade exigida é sempre mostrado antes da ação, e o botão fica desabilitado — não só rejeitado depois do clique — quando falta item; um custo que aponta pra um item excluído ou de outro curso é dispensado em vez de travar o estudante. Também pode **conceder** um item a cada rodada vencida; seguindo a mesma regra antifarm do próprio PlayerHUD, nenhum XP é concedido por esse item enquanto a atividade permitir rodadas ilimitadas — o item continua sendo entregue, só sem XP — e o XP potencial dessa concessão é refletido no "Total XP no jogo" do próprio PlayerHUD.
* 🛡️ **Integração segura entre cursos:** Toda referência a item do PlayerHUD é validada contra a instância do bloco do próprio curso, nunca um item obsoleto ou de outro curso — mesmo depois de backup/restauração ou duplicação de curso. As configurações preservam um item desabilitado ou excluído como uma opção claramente rotulada, em vez de zerar o campo silenciosamente.
* 📦 **Backup & Restauração:** Suporte completo ao backup Moodle 2, incluindo a ação "Duplicar atividade", pool de palavras, tentativas, remapeamento de ids de usuário/glossário, e remapeamento seguro de itens do PlayerHUD (descartado, em vez de mantido apontando pro item de outro curso, quando não faz parte da mesma restauração).
* 🔐 **Privacy API:** Compatível com LGPD/GDPR — exportação e exclusão completas de dados pessoais armazenados.

[⬆️ Voltar ao índice](#toc-pt)

---

### 🎓 Finalidade Educacional

O PlayerWords foi projetado para:

* Reforçar o aprendizado de conceitos trabalhados no curso ou disciplina
* Fomentar o aprendizado lúdico e baseado em jogos
* Simplificar e tornar mais intuitivos os processos de aprendizagem e avaliação
* Colaborar para o atingimento de objetivos educacionais em cursos e disciplinas
* Fomentar metodologias ativas como a utilização de games na educação e a gamificação
* Apoiar a **prática de recuperação** — o estudante precisa lembrar o conceito antes de ver qualquer resposta, uma das técnicas com maior evidência de eficácia para retenção de longo prazo
* Estimular a **prática espaçada** — o tempo de recarga configurável entre rodadas faz o estudante retornar ao conteúdo em sessões distintas, alinhando-se aos princípios da repetição espaçada

Indicado para:

* Qualquer curso que trabalhe com conceitos expressos em palavras ou termos
* Cursos gamificados com o ecossistema PlayerGames
* Avaliação formativa e reforço de autoestudo
* Estratégias de reforço de engajamento

[⬆️ Voltar ao índice](#toc-pt)

---

### 🕹️ Ecossistema PlayerGames

O PlayerWords faz parte do ecossistema de gamificação **PlayerGames** para Moodle. Sua principal integração direta é com o bloco PlayerHUD:

* **Bloco PlayerHUD (Opcional):** Configure custos em itens para iniciar uma rodada ou revelar a dica, e uma concessão de item por rodada vencida.
  👉 https://github.com/jeanlucio/moodle-block_playerhud

* **PlayerGroup (Compatível):** Grupos padrão do Moodle — criados manualmente ou pela atividade PlayerGroup — são respeitados pelo filtro `SEPARATEGROUPS` do ranking.
  👉 https://github.com/jeanlucio/moodle-mod_playergroup

Veja o [perfil do autor no Moodle Plugins Directory](https://moodle.org/plugins/browse.php?list=contributor&id=3970322) para a família PlayerGames completa.

[⬆️ Voltar ao índice](#toc-pt)

---

### 📦 Requisitos

| Componente | Versão |
|------------|--------|
| Moodle     | 4.5+   |
| PHP        | 8.1+   |

[⬆️ Voltar ao índice](#toc-pt)

---

### 🛠️ Instalação

1. Baixe o arquivo `.zip` ou clone este repositório.
2. Extraia na pasta `mod/` do seu Moodle.
3. Renomeie para `playerwords` (se necessário).
   Caminho final:
   `seu-moodle/mod/playerwords/`
4. Acesse **Administração do site > Notificações** para concluir a instalação.
5. Adicione uma atividade **PlayerWords** a qualquer curso.

[⬆️ Voltar ao índice](#toc-pt)

---

### 📖 Como Usar

1. Adicione uma atividade **PlayerWords** ao seu curso.
2. Configure:
   - Faixa de comprimento de palavras e máximo de tentativas
   - Tempo de recarga entre rodadas e limite de rodadas
   - Modo de palavras (aleatório ou sequência compartilhada)
   - Método de nota e configurações do livro de notas
   - Fontes de palavras (manual, Glossário, IA) e fonte de glossário (opcional)
   - Custos em itens do PlayerHUD e concessão por vitória (opcional, quando o bloco PlayerHUD está presente)
3. Acesse **Gerenciar palavras** para adicionar, gerar com IA, aprovar, editar ou excluir palavras.
4. Os estudantes jogam diretamente na página da atividade — chutando, revelando dicas e desistindo de rodadas, sem recarregar a página. O toolbar da própria página dá acesso às regras (ajuda), ao registro de tentativas e ao ranking.
5. Notas e ranking são atualizados automaticamente após cada rodada.

[⬆️ Voltar ao índice](#toc-pt)

---

### 🧮 Nota e Ranking

O PlayerWords calcula uma **nota** e um total de **ranking** a partir das mesmas rodadas terminadas, mas os dois são configurados de forma totalmente independente — o professor pode manter a nota simples e ainda assim recompensar jogadas eficientes no ranking, ou o contrário.

**Os dois são totalmente opcionais, e cada um liga/desliga por conta própria:**

* **Nota:** deixe o campo padrão `Nota` como *Nenhuma* pra rodar a atividade sem avaliação nenhuma — nenhuma nota é calculada ou gravada no livro de notas, e as configurações `Método de avaliação` / `Pontuação da nota` somem do formulário.
* **Ranking:** deixe `Mostrar ranking` como *Não* pra esconder o ranking em todo lugar — no jogo, na página dedicada de ranking, e na coluna extra do registro de tentativas — e a configuração `Pontuação do ranking` some do formulário também.

Desligar um nunca afeta o outro: uma atividade pode ter nota sem ranking, ranking sem nota, os dois, ou nenhum dos dois.

**A pontuação por rodada** decide quanto vale uma única rodada, escolhida separadamente para a nota e para o ranking (configurações `Pontuação da nota` / `Pontuação do ranking`, ambas com padrão **Binária**):

| Modo | Uma rodada vencida vale... | Uma rodada perdida, desistida ou com tempo esgotado |
|---|---|---|
| **Binária** (padrão) | A nota cheia da atividade | Zero |
| **Linear** | Uma fração da nota cheia proporcional às tentativas poupadas: `nota × (max_attempts − tentativas_usadas + 1) / max_attempts` | Zero |

O modo linear recompensa acertar em menos tentativas, mas nunca zera totalmente uma vitória — até vencer na última tentativa permitida ainda rende uma fração positiva. Exemplo com nota máxima 100 e 6 tentativas:

| Tentativas usadas | Pontos (linear) |
|---:|---:|
| 1 | 100,00 |
| 2 | 83,33 |
| 3 | 66,67 |
| 4 | 50,00 |
| 5 | 33,33 |
| 6 | 16,67 |
| Não completou | 0,00 |

**Combinar várias rodadas numa nota final** é uma configuração separada, `Método de avaliação` (maior nota, média, primeira tentativa, última tentativa ou média sobre todas as rodadas exigidas — veja [📖 Como Usar](#-como-usar)). Funciona igual independente de a pontuação por rodada acima ser Binária ou Linear: só agrega o valor que cada rodada já registrou.

**O ranking** é a soma dos pontos de ranking de todas as rodadas terminadas de um estudante (`SUM`), ordenado do maior para o menor; empates são desfeitos por menos tentativas usadas em média, depois menos tempo gasto em média. Só aparece quando o professor liga "Mostrar ranking", e nunca revela uma rodada ainda em andamento.

**Trava ao registrar a nota:** assim que a atividade registra uma nota real para qualquer estudante, `Máximo de tentativas`, `Pontuação da nota` e `Pontuação do ranking` travam — do mesmo jeito que o Moodle já trava o campo "Nota máxima" de uma atividade avaliada assim que existem notas reais. Isso garante que toda rodada já registrada para aquela atividade foi pontuada sob exatamente as mesmas regras, então a nota e o total do ranking permanecem consistentes durante toda a vida da atividade.

**Registro de tentativas:** cada estudante pode conferir suas próprias rodadas passadas — palavra, tentativas usadas, tempo, nota da rodada e (quando o ranking está ligado) pontos no ranking — pela página de registro de tentativas do toolbar.

[⬆️ Voltar ao índice](#toc-pt)

---

### 🧪 Testes Automatizados

O PlayerWords inclui uma suíte PHPUnit cobrindo lógica de negócio, consultas ao repositório, web services e conformidade com a Privacy API. Todo push de CI executa a matriz completa (Moodle 4.5 → 5.x, PostgreSQL e MariaDB).

#### PHPUnit — Testes Centrais

| Arquivo de teste | Casos | O que é coberto |
|-----------------|------:|----------------|
| `backup_restore_test.php` | 5 | Duplicar uma atividade copia suas palavras, renomeia a cópia, reconstrói o cache do curso, e não cria um item de nota duplicado — teste de regressão para a ausência de `prepare_activity_structure()`; uma referência a item do PlayerHUD sobrevive intacta a um "Duplicar atividade" no mesmo curso (o bloco nunca faz parte desse backup mais estreito); um backup/restauração de curso completo pra um curso novo remapeia a referência pro id novo do item, via o mapeamento `playerhud_item` que o próprio passo de restauração do block_playerhud registra; uma referência a item de outro curso é descartada em vez de manter apontando pro curso errado — contra o `backup_controller`/`restore_controller` real, não um atalho simulado; um backup/restauração de curso completo preserva os modos de pontuação de nota/ranking e tanto `score` quanto `rankingpoints` de uma tentativa terminada |
| `cross_instance_security_test.php` | 4 | Estado de sessão, busca de palavra por id, registros de tentativa e a consulta do "registro de tentativas" nunca vazam entre duas instâncias diferentes da atividade, mesmo para o mesmo estudante no mesmo curso |
| `lib_grant_potential_test.php` | 6 | O callback `playerhud_grant_potential` descoberto pelo teto de "Total XP no jogo" do próprio PlayerHUD: vazio pra uma instância de bloco não reconhecida, pra uma atividade sem item de recompensa configurado, e pra uma atividade ilimitada (reflete a mesma regra antifarm da concessão real); uma atividade limitada retorna uma linha no mesmo formato das entradas de item/missão do próprio PlayerHUD (`qtd × rodadas máximas × xp do item`); um item de recompensa pertencente à instância de bloco de outro curso não contribui nada; duas atividades limitadas no mesmo curso contribuem cada uma com sua própria linha |
| `lib_reset_userdata_test.php` | 4 | "Redefinir curso" apaga tentativas e reseta notas só quando a opção está marcada, só para o curso alvo, e o padrão do formulário vem marcado |
| `completion/custom_completion_test.php` | 7 | Regra de conclusão customizada ("exigir tentativas"): incompleta abaixo do limite, completa no limite, regra não reportada como disponível quando desabilitada, nomes de regra definidos, descrição inclui a quantidade exigida, ordem de exibição, uma reserva ainda pendente (rodada iniciada mas não terminada) não conta para o limite |
| `privacy/provider_test.php` | 12 | Declaração de metadados; contextos por tentativas; contextos por palavras adicionadas; listar usuários no contexto (e no-op para contexto que não é de módulo); exportar dados do usuário (e no-op para lista de contextos vazia); excluir dados do usuário em um único e em múltiplos contextos; excluir dados de todos os usuários num contexto (sem afetar outra atividade, e no-op para contexto que não é de módulo) |
| **Subtotal** | **38** | |

#### Testes de Lógica de Negócio (`tests/local/`)

| Arquivo de teste | Casos | O que é coberto |
|-----------------|------:|----------------|
| `ai_word_generator_test.php` | 12 | Parsing da resposta de IA (wrapper `words`/legado `concepts`, lista nua, cerca de código markdown removida, JSON malformado/não-array, dica cai para `definition`, entradas não-array ignoradas) e validação de termo como entrada não confiável (palavra única alfabética aceita; termo vazio, multi-palavra e não-alfabético rejeitados) — tudo via reflection, sem chamada real de IA |
| `attempts_history_service_test.php` | 9 | Registro de tentativas e nota atual do próprio estudante: vazio sem rodadas terminadas; exclui uma reserva ainda pendente; linhas exibidas da mais recente para a mais antiga, enquanto o cálculo da nota usa ordem crescente; a nota computada bate com `playerwords_calculate_user_grade()` para o método configurado; resumo de nota oculto em atividade não avaliada; texto da palavra cai do conceito para a palavra crua; tempo usado formatado como m:ss; a coluna de pontos no ranking aparece, formatada com 2 casas decimais, quando o ranking está ligado, e é omitida por completo quando está desligado |
| `gameplay_service_test.php` | 18 | Algoritmo de feedback por letra em 9 combinações de chute/alvo (correto, ausente, presente, letras duplicadas, esgotamento do pool); cálculo de nota para vitória, derrota e notas decimais no modo Binário; modo Linear tanto pro cálculo da nota quanto do ranking (nota cheia na primeira tentativa, fração positiva não-zero na última tentativa permitida, zero quando não completou) |
| `hud_service_test.php` | 21 | Delega pra API `\block_playerhud\local\external_items` do block_playerhud em toda operação de item, validando pertencimento contra a instância de bloco do próprio chamador em vez de ler as tabelas do PlayerHUD direto: localização do bloco PlayerHUD entre cursos; disponibilidade por curso (verdadeiro com instância do bloco, falso sem instância, ignora instância de outro curso); resolução de nome de item (vazio pra item de outra instância de bloco); listagem de itens; consumo de itens (fundos insuficientes, sucesso, ordem FIFO, curto-circuito com quantidade zero, dispensado — não bloqueado — pra item de outra instância de bloco); concessão de itens (linhas de inventário com `source='playerwords'` mais XP concedido, XP retido quando a chamada sinaliza origem sem limite, item sem XP nunca altera XP em nenhum dos casos, item inexistente, item de instância estranha e quantidade zero são todos no-op) |
| `ranking_service_test.php` | 5 | Ranking vazio; ordenação decrescente por pontuação; truncamento top-5 com linha de "outsider" para o usuário atual fora do top; `SEPARATEGROUPS` filtra para o grupo do próprio estudante; uma reserva ainda pendente (rodada em andamento ou abandonada sem terminar) é excluída do ranking |
| `round_presenter_test.php` | 35 | Renderização das linhas da grade; texto de cooldown; mensagens de feedback (desistiu/tempo esgotado/perdeu/venceu, variando pelas tentativas usadas); contexto de ranking; contexto de resultado da rodada (em branco até terminar, revela ao terminar, cooldown reflete mudança posterior de configuração); saldo/custo em item do PlayerHUD no lobby (exibido/oculto pelo estado da rodada, início desabilitado abaixo da quantidade exigida, habilitado quando o saldo cobre), informação de temporizador no lobby; saldo/custo em item do PlayerHUD no botão de dica do painel (exibido/oculto pelo estado de revelação, dica desabilitada abaixo da quantidade exigida, habilitada quando o saldo cobre), temporizador permanece zerado antes da rodada iniciar; resumo de nota até agora (ausente antes de terminar, ausente quando não avaliada, mostra método e nota computada ao terminar, ignora uma tentativa ainda pendente); linha de informação do método de avaliação no lobby (exibida quando relevante, oculta em atividade de rodada única, oculta quando não avaliada); a tecla Ç do teclado só aparece quando o próprio banco de palavras da atividade precisa dela; contador de rodadas jogadas exibido no lobby e no resultado da rodada, usando o símbolo de infinito numa atividade ilimitada e o limite configurado nos demais casos; o rótulo de item concedido pelo PlayerHUD só aparece numa vitória de verdade com item configurado, em branco numa derrota ou quando não configurado |
| `round_service_test.php` | 30 | Transições de estado da rodada: palavra sorteada e `round_started` disparado; envio de chute (errado, correto, sem tentativas, após terminar, tamanho incompatível); desistência; timeout (termina após o prazo passar, rejeitado antes do prazo, rejeitado quando o temporizador está desabilitado); nova rodada; aviso de restrição (limite de rodadas atingido, sem restrição); `count_rounds_played` restrito à instância e ao usuário; cálculo de cooldown (desabilitado, sem tentativas ainda, expirado, reflete mudança posterior de configuração); recupera sorteando palavra nova após a anterior ser removida no meio da rodada; `start_round` reserva uma linha de tentativa; `finish_round` completa a reserva em vez de duplicá-la; uma rodada abandonada continua contando para `max_rounds`; a reserva obsoleta é descartada quando a palavra é removida no meio da rodada; uma rodada vencida concede o item do PlayerHUD configurado com XP quando `max_rounds` é limitado, concede sem XP quando ilimitado, e uma rodada perdida nunca concede; um custo de rodada ou dica apontando pra um item excluído, ou de outro curso, é dispensado em vez de travar o estudante pra sempre; um custo apontando pra um item só desabilitado (não excluído) continua bloqueando corretamente quando o saldo está curto — desabilitar é reversível, então o custo nunca é dispensado por causa disso |
| `view_page_service_test.php` | 6 | Ramificações de montagem de página: lobby fresco, palavra sorteada persiste entre chamadas, rodada terminada calcula cooldown real, aviso de restrição exibido quando o limite de rodadas é atingido; as URLs de ajuda/registro de tentativas do toolbar sempre estão presentes; a ação de desistir só aparece durante uma rodada ativa |
| `word_normalizer_test.php` | 8 | Normalização sem acentuação em 8 combinações de diacríticos |
| `words_repository_test.php` | 35 | Seleção de palavra (pool vazio, exclusão de não aprovados/muito curtos/muito longos/caracteres não-letra, modo aleatório, determinismo e ciclagem da sequência compartilhada, evita a palavra excluída quando há alternativa, permite-a de volta quando é a única candidata); `get_last_played_word_id` (0 sem rodadas terminadas, ignora uma reserva pendente); inserção de palavra manual e por IA; busca, atualização e exclusão de palavra restritas à instância dona; exclusão e aprovação em lote; listagem de palavras recentes com join do nome do glossário; sincronização de glossário (divisão de conceito multi-palavra, filtro de stopwords configuráveis, atualização de dica em re-sincronização sem duplicar, limpeza de órfãos quando uma entrada desaparece, modo `glossaryid = 0` cobrindo todos os glossários do curso); a tecla Ç do teclado virtual só é oferecida quando uma palavra aprovada realmente contém uma, restrito à própria atividade e ignorando palavras não aprovadas |
| **Subtotal** | **179** | |

#### Testes de Web Services (`tests/external/`)

| Arquivo de teste | Casos | O que é coberto |
|-----------------|------:|----------------|
| `end_round_test.php` | 4 | Desistência termina a rodada; timeout termina a rodada; um valor inválido de `reason` é rejeitado; a capability `mod/playerwords:view` é exigida |
| `new_round_test.php` | 3 | Nova rodada sorteia palavra nova; bloqueado quando o limite de rodadas já foi atingido; a capability `mod/playerwords:view` é exigida |
| `reveal_hint_test.php` | 6 | Dica é revelada; revelar duas vezes é idempotente; rejeitado após a rodada terminar; a capability `mod/playerwords:view` é exigida; saldo insuficiente de item do PlayerHUD (item real, válido) bloqueia a revelação; um custo apontando pra um item excluído é dispensado em vez disso |
| `start_round_test.php` | 5 | Cronômetro da rodada inicia; rejeitado quando já iniciado; a capability `mod/playerwords:view` é exigida; saldo insuficiente de item do PlayerHUD (item real, válido) bloqueia o início; um custo apontando pra um item excluído é dispensado em vez disso |
| `submit_guess_test.php` | 7 | Um chute errado nunca revela a palavra; um chute correto revela só quando termina; um chute perdedor também revela; a capability `mod/playerwords:view` é exigida; `timeleft` reflete os segundos restantes durante a rodada; `timeleft` fica congelado no momento em que a rodada terminou, não no relógio real; um total de ranking fracionado sobrevive à limpeza de retorno da API externa, contra a chamada real da webservice |
| **Subtotal** | **25** | |

| **Total Geral** | **242** | |

```bash
vendor/bin/phpunit --testsuite mod_playerwords
```

**Cobertura de linha por classe (PHPUnit + Xdebug):**

| Classe | Cobertura de linha |
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
| **Geral** | **60%** |

¹ Subcontado por natureza: os métodos que chamam rede em `ai_word_generator` (`call_ai`, `call_core_ai`, `has_core_ai`) exigem um provedor de IA real e não são testados por unidade de propósito; a lógica de parsing e validação de entrada não confiável da qual eles dependem (`parse_words`, `is_valid_term`) está totalmente coberta.

² Caiu de 95% depois que a funcionalidade de transparência de avaliação adicionou `build_grading_method_info()`/`build_grade_so_far()`: o caminho feliz (um item de nota real com nota computada) é testado, mas as ramificações de fallback "ainda sem item de nota" e "nota ainda não computada" dentro de `build_grade_so_far()` não são — deixado como próximo passo, em vez de inflar a suíte com testes para estados que o fluxo real de atualização do livro de notas dificulta alcançar.

³ Caiu de 97% depois que `hud_service.php` foi reescrito pra delegar toda operação de item pra API `\block_playerhud\local\external_items` do próprio block_playerhud, em vez de conter a lógica localmente: a lógica de consumo FIFO e concessão que esse arquivo tinha antes — e os testes dedicados dela — agora moram na suíte de testes do próprio block_playerhud, então esse número reflete um arquivo mais fino, majoritariamente delegando, não uma regressão de cobertura.

As classes de web service `external/*` mostram um percentual de linha menor do que sua cobertura real de comportamento sugere: cada uma já é testada quanto ao caminho feliz, toda ramificação de rejeição, a guarda de capability e (quando aplicável) a ramificação de item insuficiente do PlayerHUD — mas um teste de guarda de capability necessariamente para em `require_capability()` e nunca alcança as linhas depois dela, então ele não consegue elevar o percentual de uma classe que é majoritariamente "linhas depois da guarda".

[⬆️ Voltar ao índice](#toc-pt)

---

### 🔐 Segurança e Conformidade

* Controle de acesso por capabilities (`mod/playerwords:view`, `mod/playerwords:addinstance`)
* Proteção com `require_sesskey()` em todas as ações POST; chamadas AJAX são validadas pelo dispatcher `core/ajax` do Moodle
* Validação no servidor dos limites de rodadas e tempo de recarga, sempre recalculados a partir da configuração atual
* Timeout de rodada é revalidado contra o prazo real do servidor (com pequena tolerância de latência de rede), em vez de confiar apenas no cronômetro do cliente
* Validação de charset do chute — apenas letras Unicode aceitas
* Palavras geradas por IA são tratadas como entrada não confiável: só termos de um único token, alfabéticos e dentro do comprimento configurado são salvos, entrando pendentes de aprovação do professor
* O estado de sessão da rodada é isolado por instância de atividade e por usuário — um id de palavra ou chave de sessão de uma atividade nunca é aceito por outra
* Compatível com a API externa do Moodle
* Privacy API completamente implementada (LGPD/GDPR)

[⬆️ Voltar ao índice](#toc-pt)

---

## 📄 Licença

Este projeto é licenciado sob a **GNU General Public License v3 (GPLv3)**.

**Copyright:** 2026 Jean Lúcio

[⬆️ Voltar ao índice](#toc-pt)
