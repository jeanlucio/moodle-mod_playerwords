# 🧪 Testes Automatizados

O PlayerWords inclui uma suíte PHPUnit cobrindo lógica de negócio, consultas ao repositório, web
services e conformidade com a Privacy API. Todo push de CI executa a matriz completa (Moodle
4.5 → 5.x, PostgreSQL e MariaDB).

### PHPUnit — Testes Centrais

| Arquivo de teste | Casos |
|-----------------|------:|
| `backup_restore_test.php` | 5 |
| `cross_instance_security_test.php` | 4 |
| `lib_grant_potential_test.php` | 6 |
| `lib_reset_userdata_test.php` | 4 |
| `completion/custom_completion_test.php` | 7 |
| `privacy/provider_test.php` | 14 |
| **Subtotal** | **40** |

### Testes de Lógica de Negócio (`tests/local/`)

| Arquivo de teste | Casos |
|-----------------|------:|
| `ai_word_generator_test.php` | 12 |
| `attempts_history_service_test.php` | 14 |
| `gameplay_service_test.php` | 19 |
| `hud_service_test.php` | 22 |
| `intro_service_test.php` | 5 |
| `ranking_service_test.php` | 6 |
| `round_presenter_test.php` | 35 |
| `round_service_test.php` | 30 |
| `view_page_service_test.php` | 16 |
| `word_normalizer_test.php` | 16 |
| `words_repository_test.php` | 56 |
| **Subtotal** | **231** |

### Testes de Web Services (`tests/external/`)

| Arquivo de teste | Casos |
|-----------------|------:|
| `count_eligible_words_test.php` | 5 |
| `count_glossary_candidates_test.php` | 4 |
| `end_round_test.php` | 4 |
| `new_round_test.php` | 3 |
| `reveal_hint_test.php` | 6 |
| `start_round_test.php` | 5 |
| `submit_guess_test.php` | 7 |
| **Subtotal** | **34** |

| **Total Geral** | **305** |

```bash
vendor/bin/phpunit --testsuite mod_playerwords
```

**Cobertura de linhas geral** (`moodle-coverage`, PHPUnit + Xdebug): **63%**.

[Ver o detalhamento completo de cada teste e a tabela de cobertura →]({{ '/testing-pt.html' | relative_url }})
