# 🧪 Automated Tests

PlayerWords ships with a PHPUnit test suite covering business logic, repository queries, web
services, and Privacy API compliance. Every CI push runs against the full matrix (Moodle 4.5 →
5.x, PostgreSQL & MariaDB).

### PHPUnit — Core Tests

| Test file | Cases |
|-----------|------:|
| `backup_restore_test.php` | 6 |
| `cross_instance_security_test.php` | 4 |
| `lib_grant_potential_test.php` | 6 |
| `lib_reset_userdata_test.php` | 4 |
| `completion/custom_completion_test.php` | 7 |
| `privacy/provider_test.php` | 28 |
| **Subtotal** | **55** |

### Local Business-Logic Tests (`tests/local/`)

| Test file | Cases |
|-----------|------:|
| `ai_word_generator_test.php` | 14 |
| `attempts_history_service_test.php` | 18 |
| `gameplay_service_test.php` | 19 |
| `hud_service_test.php` | 26 |
| `intro_service_test.php` | 5 |
| `ranking_service_test.php` | 7 |
| `round_presenter_test.php` | 38 |
| `round_service_test.php` | 58 |
| `view_page_service_test.php` | 18 |
| `word_normalizer_test.php` | 16 |
| `words_repository_test.php` | 62 |
| **Subtotal** | **281** |

### Web Services Tests (`tests/external/`)

| Test file | Cases |
|-----------|------:|
| `count_eligible_words_test.php` | 6 |
| `count_glossary_candidates_test.php` | 4 |
| `end_round_test.php` | 6 |
| `new_round_test.php` | 3 |
| `reveal_hint_test.php` | 7 |
| `start_round_test.php` | 7 |
| `submit_guess_test.php` | 9 |
| **Subtotal** | **42** |

| **Grand Total** | **378** |

```bash
vendor/bin/phpunit --testsuite mod_playerwords
```

**Overall line coverage** (`moodle-coverage`, PHPUnit + Xdebug): **87%**.

[Full test-by-test breakdown and coverage table →]({{ '/testing.html' | relative_url }})
