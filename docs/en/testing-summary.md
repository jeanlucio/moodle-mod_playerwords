# 🧪 Automated Tests

PlayerWords ships with a PHPUnit test suite covering business logic, repository queries, web
services, and Privacy API compliance, plus a Behat suite covering gameplay, PlayerHUD
integration, and reports end-to-end in a real browser. Every CI push runs against the full
matrix (Moodle 4.5 → 5.x, PostgreSQL & MariaDB).

### PHPUnit — Core Tests

| Test file | Cases |
|-----------|------:|
| `backup_restore_test.php` | 6 |
| `cross_instance_security_test.php` | 4 |
| `lib_grant_potential_test.php` | 6 |
| `lib_reset_userdata_test.php` | 4 |
| `lib_supports_test.php` | 2 |
| `completion/custom_completion_test.php` | 7 |
| `privacy/provider_test.php` | 30 |
| `lib_update_grades_test.php` | 2 |
| `mod_form_test.php` | 3 |
| **Subtotal** | **64** |

### Local Business-Logic Tests (`tests/local/`)

| Test file | Cases |
|-----------|------:|
| `ai_word_generator_test.php` | 17 |
| `attempts_history_service_test.php` | 23 |
| `gameplay_service_test.php` | 20 |
| `hud_service_test.php` | 27 |
| `intro_service_test.php` | 5 |
| `ranking_service_test.php` | 9 |
| `round_presenter_test.php` | 46 |
| `round_service_test.php` | 61 |
| `view_page_service_test.php` | 22 |
| `word_normalizer_test.php` | 16 |
| `words_repository_test.php` | 65 |
| **Subtotal** | **311** |

### Web Services Tests (`tests/external/`)

| Test file | Cases |
|-----------|------:|
| `count_eligible_words_test.php` | 6 |
| `count_glossary_candidates_test.php` | 5 |
| `end_round_test.php` | 6 |
| `new_round_test.php` | 5 |
| `reveal_hint_test.php` | 8 |
| `start_round_test.php` | 7 |
| `submit_guess_test.php` | 10 |
| **Subtotal** | **47** |

| **Grand Total** | **422** |

```bash
vendor/bin/phpunit --testsuite mod_playerwords
```

**Overall line coverage** (`moodle-coverage`, PHPUnit + Xdebug): **89%**.

### Behat — End-to-End Tests

| Feature file | Scenarios |
|---------------|----------:|
| `mod_playerwords_smoke.feature` | 1 |
| `mod_playerwords_gameplay.feature` | 7 |
| `mod_playerwords_playerhud.feature` | 4 |
| `mod_playerwords_reports.feature` | 5 |
| `mod_playerwords_settings.feature` | 4 |
| `mod_playerwords_toolbar.feature` | 9 |
| **Subtotal** | **30** |

[Full test-by-test breakdown and coverage table →]({{ '/testing.html' | relative_url }})
