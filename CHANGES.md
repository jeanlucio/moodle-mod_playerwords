# CHANGES

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
