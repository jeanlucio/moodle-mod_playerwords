# CHANGES

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
