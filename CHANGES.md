# Changelog — mod_playerwords

All notable changes to this plugin are documented here.
Format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

---

## [v1.0.0] — 2026-07-14

### Added
- Initial stable release: a Wordle-style vocabulary activity for Moodle. Students guess a
  hidden word letter-by-letter within a configurable number of attempts, with colour-coded
  and symbol feedback (colourblind-safe) and an on-screen virtual keyboard.
- Word sources: manual entry, automatic import from the course Glossary (with per-activity
  stopword filtering and multi-word concept splitting into individual guessable words), and
  AI-assisted generation via `local_aihub` (BYOK) or Moodle's `core_ai` subsystem, respecting
  the per-course "Enable AI tools" toggle.
- Optional integration with `block_playerhud`: an item can be required to start a round or to
  reveal a hint, and an item can be granted for each round won, with an anti-farming rule that
  withholds the bonus XP on activities configured for unlimited rounds.
- Configurable round rules: attempt limit, word length range, optional timer, round limit with
  a configurable cooldown between rounds, and accent-insensitive matching.
- Grading: four grading methods (highest, average, first attempt, last attempt) combined with
  binary or linear scoring, fully integrated with the Moodle gradebook.
- Ranking leaderboard and per-student attempt history.
- Custom activity completion rules (minimum attempts, minimum grade).
- Accessibility: WCAG AA colour contrast, non-colour visual indicators on the guess grid, and
  `aria-label`s describing every cell for screen readers.
- Backup and restore (moodle2), full Privacy API compliance, and Portuguese (pt_br)
  translation alongside English.
