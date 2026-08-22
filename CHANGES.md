# Changelog — mod_playerwords

All notable changes to this plugin are documented here.
Format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

---

## [v1.0.1] — 2026-08-22

### Fixed
- Restored `db/upgrade.php`, with no upgrade steps yet — the Moodle Plugins directory
  submission validator requires this file to exist in the plugin archive even when there
  is no migration to run.

---

## [v1.0.0] — 2026-08-22

### Added
- Initial stable release: a Wordle-style vocabulary activity for Moodle. Students guess a
  hidden word within a configurable number of attempts, one letter box at a time — click any
  box to fix that letter without retyping the whole word, move between boxes with the
  Left/Right arrow keys, and type from a physical keyboard even before clicking in — with
  colour-coded and symbol feedback (colourblind-safe) and an on-screen virtual keyboard,
  including a long-press accent picker on vowel keys.
- Word sources: manual entry (letters-only, duplicate-checked against the whole pool
  regardless of source), automatic import from the course Glossary (with per-activity
  stopword filtering and multi-word concept splitting into individual guessable words,
  flagged in the management table), and AI-assisted generation via `local_aihub` (BYOK) or
  Moodle's `core_ai` subsystem, respecting the per-course "Enable AI tools" toggle. A live,
  AJAX-updated eligible-word count and an inactive-word warning help teachers keep the pool
  playable.
- A toggleable hidden-hint system: hint reveal can be turned off entirely for the activity,
  or left on (optionally at a PlayerHUD item cost).
- Optional restriction of guesses to the activity's own approved word pool, rejecting any
  other letter combination without spending an attempt.
- Optional integration with `block_playerhud`: an item can be required to start a round or to
  reveal the hint, and an item can be granted for each round won, with an anti-farming rule
  that withholds the bonus XP on activities configured for unlimited rounds. A configured cost
  is shown up front and the action disabled before the click, never just rejected after; a
  cost pointing at a deleted or foreign-course item is waived rather than locking the student
  out.
- Configurable round rules: attempt limit, word length range, optional timer, round limit
  (with a rounds-played counter) with a configurable cooldown between rounds, word mode
  (random with no immediate repeat, or a shared sequence for the whole class), and
  accent-insensitive matching with true-spelling reveal at the end of the round.
- Grading: four grading methods (highest, average, first attempt, last attempt) combined with
  binary or linear scoring, fully integrated with the Moodle gradebook and locked once a real
  grade exists.
- Top-5 ranking leaderboard (respecting separate groups) and per-student attempt history, with
  a paginated, sortable, filterable all-students report for whoever manages the activity.
- Custom activity completion rule (minimum attempts).
- First-visit onboarding: the how-to-play modal opens automatically once, site-wide, the very
  first time a student encounters the activity, and is always reachable again from the
  toolbar.
- Accessibility: WCAG AA colour contrast, non-colour visual indicators on the guess grid,
  `aria-label`s describing every cell (including its position while still empty), and a live
  region announcing state changes for screen readers.
- Backup and restore (moodle2), full Privacy API compliance, and Portuguese (pt_br)
  translation alongside English.
