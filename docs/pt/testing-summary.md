# 🧪 Testes Automatizados

O PlayerWords inclui uma suíte PHPUnit cobrindo lógica de negócio, consultas ao repositório, web
services e conformidade com a Privacy API, além de uma suíte Behat cobrindo o jogo, a integração
com o PlayerHUD e os relatórios de ponta a ponta num navegador real. Todo push de CI executa a
matriz completa (Moodle 4.5 → 5.x, PostgreSQL e MariaDB).

### PHPUnit — Testes Centrais

| Arquivo de teste | Casos |
|-----------------|------:|
| `backup_restore_test.php` | 6 |
| `cross_instance_security_test.php` | 4 |
| `lib_grant_potential_test.php` | 6 |
| `lib_reset_userdata_test.php` | 4 |
| `lib_supports_test.php` | 2 |
| `completion/custom_completion_test.php` | 7 |
| `privacy/provider_test.php` | 30 |
| `lib_update_grades_test.php` | 2 |
| `mod_form_test.php` | 3 |
| `lib_grade_item_update_test.php` | 2 |
| **Subtotal** | **66** |

### Testes de Lógica de Negócio (`tests/local/`)

| Arquivo de teste | Casos |
|-----------------|------:|
| `ai_word_generator_test.php` | 17 |
| `attempts_history_service_test.php` | 23 |
| `gameplay_service_test.php` | 20 |
| `hud_service_test.php` | 28 |
| `intro_service_test.php` | 5 |
| `ranking_service_test.php` | 9 |
| `round_presenter_test.php` | 46 |
| `round_service_test.php` | 61 |
| `view_page_service_test.php` | 22 |
| `word_normalizer_test.php` | 16 |
| `words_repository_test.php` | 65 |
| **Subtotal** | **312** |

### Testes de Web Services (`tests/external/`)

| Arquivo de teste | Casos |
|-----------------|------:|
| `count_eligible_words_test.php` | 6 |
| `count_glossary_candidates_test.php` | 5 |
| `end_round_test.php` | 6 |
| `new_round_test.php` | 5 |
| `reveal_hint_test.php` | 8 |
| `start_round_test.php` | 7 |
| `submit_guess_test.php` | 10 |
| **Subtotal** | **47** |

| **Total Geral** | **425** |

```bash
vendor/bin/phpunit --testsuite mod_playerwords
```

**Cobertura de linhas geral** (`moodle-coverage`, PHPUnit + Xdebug): **90%**.

### Behat — Testes de Ponta a Ponta

| Arquivo de feature | Cenários |
|----------------------|----------:|
| `mod_playerwords_smoke.feature` | 1 |
| `mod_playerwords_gameplay.feature` | 7 |
| `mod_playerwords_playerhud.feature` | 4 |
| `mod_playerwords_reports.feature` | 5 |
| `mod_playerwords_settings.feature` | 4 |
| `mod_playerwords_toolbar.feature` | 9 |
| **Subtotal** | **30** |

[Ver o detalhamento completo de cada teste e a tabela de cobertura →]({{ '/testing-pt.html' | relative_url }})
