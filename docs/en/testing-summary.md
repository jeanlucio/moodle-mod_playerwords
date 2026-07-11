# 🧪 Automated Tests

PlayerWords ships with a PHPUnit test suite covering business logic, repository queries, web
services, and Privacy API compliance. Every CI push runs against the full matrix (Moodle 4.5 →
5.x, PostgreSQL & MariaDB).

### PHPUnit — Core Tests

| Test file | Cases |
|-----------|------:|
| `backup_restore_test.php` | 5 |
| `cross_instance_security_test.php` | 4 |
| `lib_grant_potential_test.php` | 6 |
| `lib_reset_userdata_test.php` | 4 |
| `completion/custom_completion_test.php` | 7 |
| `privacy/provider_test.php` | 12 |
| **Subtotal** | **38** |

### Local Business-Logic Tests (`tests/local/`)

| Test file | Cases |
|-----------|------:|
| `ai_word_generator_test.php` | 12 |
| `attempts_history_service_test.php` | 14 |
| `gameplay_service_test.php` | 19 |
| `hud_service_test.php` | 21 |
| `ranking_service_test.php` | 6 |
| `round_presenter_test.php` | 35 |
| `round_service_test.php` | 30 |
| `view_page_service_test.php` | 8 |
| `word_normalizer_test.php` | 8 |
| `words_repository_test.php` | 40 |
| **Subtotal** | **193** |

### Web Services Tests (`tests/external/`)

| Test file | Cases |
|-----------|------:|
| `end_round_test.php` | 4 |
| `new_round_test.php` | 3 |
| `reveal_hint_test.php` | 6 |
| `start_round_test.php` | 5 |
| `submit_guess_test.php` | 7 |
| **Subtotal** | **25** |

| **Grand Total** | **256** |

```bash
vendor/bin/phpunit --testsuite mod_playerwords
```

[Full test-by-test breakdown →]({{ '/testing.html' | relative_url }})
