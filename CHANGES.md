# CHANGES

## v0.14.0 (2026050903)

- Word pool table on the Manage words page now supports column sorting (Word, Source, Status) — click a column header to sort ascending; click again to reverse
- Checkbox per row and a "select all" header checkbox allow multi-select; a "Delete selected" button with confirmation modal bulk-deletes checked words in one request
- Individual "Delete" buttons now use the same bulk mechanism with a pre-selected checkbox, removing the old nested-form approach
- New "Edit" button per row opens an inline edit card above the table; saving updates word text and hint without leaving the page

## v0.13.0 (2026050902)

- Add "Delete" button per word on the Manage words page; teacher must confirm before the word is permanently removed

## v0.12.1 (2026050901)

- Fix: "Word pool" on the Manage words page now shows all approved words instead of only the 20 most recent — teachers can no longer miss active candidates
- Fix: if the active word for an ongoing round is removed or unapproved mid-round, the round now resets cleanly on the next page load instead of silently switching to a different word on the same request

## v0.12.0 (2026050900)

- Add "Give up" button: player can forfeit the current round, which ends it immediately, records the attempt as a loss, and starts the cooldown — the correct word is revealed on the same end screen
- Show Wordle-style performance feedback on the end screen (Genius → Phew for wins, distinct messages for loss and forfeit)
- Show the full original concept name on the end screen when the guessed word was extracted from a multi-word glossary entry (e.g. word "Militar" → "Ditadura Militar: Regime autoritário...")
- Show cooldown countdown on the end screen ("Next round in 23h 59m 45s"), updated in real time via JavaScript
- Multi-word glossary concepts now split into individual words: each non-stopword token becomes a separate pool entry with the full definition as hint; existing single-word concepts are unaffected
- Add `concept` column to `playerwords_words` to store the original glossary concept name

## v0.11.0 (2026050811)

- Add glossary integration: teacher selects a glossary (or all course glossaries) as word source; concepts are imported into the word pool with their definitions as hints
- Sync runs automatically when the activity is saved; teacher can also trigger it manually via "Sync with glossary" button on the Manage words page
- Words outside the configured length range are skipped; re-syncing updates hints without duplicating entries

## v0.10.0 (2026050810)

- Validate guess charset: only Unicode letters accepted; digits, spaces and symbols are rejected with a clear error message

## v0.9.0 (2026050809)

- Add grading method "Average over all required rounds": grade = sum of scores ÷ max_rounds, rewarding students who complete all rounds; requires max_rounds > 0

## v0.8.0 (2026050808)

- Fix contrast on absent cells: background darkened from #6c757d to #495057 (contrast 4.1:1 → 7.0:1, WCAG AA)
- Add non-colour visual indicators: ✓ on correct cells, ~ on present cells (helps colourblind users)
- Add aria-label on every grid cell describing letter and state for screen readers

## v0.7.0 (2026050807)

- Hint is now hidden by default; student must click "Reveal hint" to see it (prepared for future PlayerHUD item cost integration)
- Hint text is never sent to the browser until the student explicitly reveals it

## v0.6.0 (2026050806)

- Show correct word and definition after every round (win or lose)
- Definition uses the hint field; glossary definitions will populate this field automatically when glossary integration is implemented

## v0.5.0 (2026050805)

- Add `wordmode` setting: teacher can choose between random word per round (default) or shared sequence mode, where all students receive the same words in the same order — round 1 is always word A for everyone, round 2 is word B, cycling back to the start when the list is exhausted

## v0.4.0 (2026050804)

- Add `grademethod` setting: highest grade, average grade, first attempt or last attempt
- Integrate with Moodle gradebook: grades are now written on every round completion
- Simplify round score: guessing the word awards full grade; failing awards 0
- Grade item created/updated on add/update instance; deleted on delete instance

## v0.3.0 (2026050803)

- Add `max_rounds` setting: teacher can limit students to 1–10 rounds or leave unlimited (default)
- Add `cooldown_seconds` setting: configurable wait between rounds in minutes, hours or days (default 1 day)
- Enforce both limits in the game page and in the `start_new_round` web service
- Add standard Moodle events: `course_module_viewed`, `course_module_instance_list_viewed`, `round_started`, `round_completed`
- Fix activity form: name field, description checkbox, and grading section now display correctly
- Fix SVG icon colour: add `mod_playerwords_is_branded()` to preserve the plugin's custom blue

## v0.2.0 (2026050802)

- Add AJAX Web Services: `mod_playerwords_submit_guess` and `mod_playerwords_start_new_round`
- Services registered via `db/services.php` with `ajax: true` for use with `core/ajax`

## v0.1.0 (2026050801)

- Initial release: Wordle-style activity with manual word source
- Configurable attempts, word length range, and optional timer
- Accent-insensitive matching option
- Custom completion rules: minimum attempts and minimum grade
- Teacher word management interface
