# 🧪 Automated Tests

[English](#english) | [Português](#português)

---

## English

PlayerWords ships with a PHPUnit test suite covering business logic, repository queries, web
services, and Privacy API compliance. Every CI push runs against the full matrix (Moodle 4.5 →
5.x, PostgreSQL & MariaDB).

### PHPUnit — Core Tests

| Test file | Cases | What is covered |
|-----------|------:|----------------|
| `backup_restore_test.php` | 5 | Duplicating an activity copies its words, renames the copy, rebuilds the course cache, and does not create a duplicate grade item — regression guard for a missing `prepare_activity_structure()` call; a PlayerHUD item reference survives a same-course "Duplicate activity" unchanged (the block is never part of that narrower backup); a full course backup/restore into a new course remaps the reference to the new item's id, via the `playerhud_item` restore mapping block_playerhud's own restore step registers; a reference to another course's item is dropped rather than kept pointing at the wrong course, against the real `backup_controller`/`restore_controller`, not a hand-rolled shortcut; a full course backup/restore preserves the grade/ranking scoring mode settings and both `score` and `rankingpoints` on a finished attempt |
| `cross_instance_security_test.php` | 4 | Session state, word lookups by id, attempt records, and the "my attempts" history query never leak between two different activity instances, even for the same student in the same course |
| `lib_grant_potential_test.php` | 6 | The `playerhud_grant_potential` callback discovered by PlayerHUD's own "Total XP in the game" ceiling estimate: empty for an unrecognised block instance, for an activity with no win-grant item configured, and for an unlimited activity (mirrors the anti-farming rule on the real grant); a bounded activity returns one row shaped like PlayerHUD's own item/quest breakdown entries (`qty × max_rounds × item xp`); a win-grant item belonging to a different course's block instance contributes nothing; two bounded activities in the same course each contribute their own row |
| `lib_reset_userdata_test.php` | 4 | Course reset deletes attempts and resets grades only when the checkbox is enabled, only for the target course, and the form default enables it |
| `completion/custom_completion_test.php` | 7 | Custom completion rule ("require attempts"): incomplete below threshold, complete at threshold, rule not reported as available when disabled, defined rule names, rule description includes the required count, display sort order, a still-pending reservation (round started but not finished) is not counted towards the threshold |
| `privacy/provider_test.php` | 12 | Metadata declaration; contexts by attempts; contexts by words added; list users in context (and no-op for a non-module context); export user data (and no-op for an empty contextlist); delete user data across a single and across multiple contexts; delete all users' data in a context (leaving another activity untouched, and no-op for a non-module context) |
| **Subtotal** | **38** | |

### Local Business-Logic Tests (`tests/local/`)

| Test file | Cases | What is covered |
|-----------|------:|----------------|
| `ai_word_generator_test.php` | 12 | AI response parsing (`words`/legacy `concepts` wrappers, bare list, markdown code fence stripped, malformed/non-array JSON, hint falls back to `definition`, non-array entries skipped) and untrusted-input term validation (single alphabetic word accepted; empty, multi-word, and non-alphabetic terms rejected) — all via reflection, no real AI call |
| `attempts_history_service_test.php` | 14 | Own attempt history and current grade: empty with no finished rounds; excludes a still-pending reservation; rows shown most-recent-first while the grade calculation itself uses ascending order; the computed grade matches `playerwords_calculate_user_grade()` for the configured method; grade summary hidden for an ungraded activity; word text falls back from concept to the raw word; time used formatted as m:ss; the ranking-points column is shown, formatted to 2 decimals, when ranking is enabled, and omitted entirely when it is disabled. All-students report (`get_all_history`): every student's finished attempts included with their name attached, most-recent-first by default; excludes anyone who can manage the activity, from both the report rows and the student filter dropdown; the `studentid` filter restricts to one student's own rows; sorting only accepts allow-listed columns, falling back to date instead of erroring on an unknown key; pagination returns distinct slices of the full result set |
| `gameplay_service_test.php` | 19 | Letter feedback algorithm across 9 guess/target combinations (correct, absent, present, duplicate letters, pool exhaustion); score calculation for win, loss, and decimal grades under Binary mode; Linear mode for both the grade and the ranking-points calculation (full marks on both of the first two attempts, scaled proportionally from the third attempt onward, a positive non-zero share on the last allowed attempt, degenerates to full credit on every attempt when max_attempts is 2 or fewer, zero when not completed) |
| `hud_service_test.php` | 21 | Delegates to block_playerhud's `\block_playerhud\local\external_items` API for every item operation, validating ownership against the caller's own block instance instead of reading block_playerhud's tables directly: block lookup across courses; course availability (true with a block instance, false without one, ignores another course's instance); item name resolution (empty for an item belonging to a different block instance); item list retrieval; consume items (insufficient funds, success, FIFO order, zero-quantity short-circuit, waived — not blocked — for an item belonging to a different block instance); grant items (inventory rows tagged `source='playerwords'` plus XP awarded, XP withheld when the caller flags the source as unbounded, zero-XP items never change XP either way, unknown item, foreign-instance item, and zero quantity are all no-ops) |
| `ranking_service_test.php` | 6 | Empty ranking; score-descending ordering; top-5 truncation with an outsider row for a lower-ranked current user; `SEPARATEGROUPS` filters to the student's own group; a still-pending reservation (round in progress or abandoned without finishing) is excluded from the ranking; a user who can manage the activity (editingteacher) never appears in the ranking, even with attempts of their own |
| `round_presenter_test.php` | 35 | Grid row rendering; cooldown text; feedback messages (forfeited/timed out/lost/won, varying by attempts used); ranking context; round result context (blank until finished, reveals on finish, cooldown reflects a later settings change); lobby PlayerHUD balance/cost (shown/hidden by round state, start disabled below the required quantity, enabled once the balance covers it), lobby timer info; round panel hint-button PlayerHUD balance/cost (shown/hidden by reveal state, hint disabled below the required quantity, enabled once the balance covers it), timer stays at zero before the round starts; grade-so-far summary (absent before finished, absent when ungraded, shows method and computed grade once finished, ignores a still-pending attempt); lobby grading-method info line (shown when relevant, hidden for a single-round activity, hidden when ungraded); the keyboard's Ç key only appears once the activity's own word pool needs it; rounds-played counter shown in the lobby and in the round result, using the infinity symbol for an unlimited activity and the configured limit otherwise; the PlayerHUD win-grant label is shown only on an actual win with an item configured, blank on a loss or when unconfigured |
| `round_service_test.php` | 30 | Round state transitions: word picked and `round_started` fired; guess submission (wrong, correct, out of attempts, after finish, length mismatch); forfeit; timeout (finishes once the deadline has passed, rejected before the deadline, rejected when the timer is disabled); new round; restriction notice (max rounds reached, unrestricted); `count_rounds_played` scoped to instance and user; cooldown computation (disabled, no attempts yet, expired, reflects a later settings change); recovers by picking a fresh word after the previous one was removed mid-round; `start_round` reserves an attempt row; `finish_round` completes the reservation instead of duplicating it; an abandoned round still counts towards `max_rounds`; the stale reservation is discarded when the word is removed mid-round; a won round grants the configured PlayerHUD item with XP when `max_rounds` is bounded, grants it without XP when unlimited, and a lost round never grants it; a round or hint cost pointing at a deleted item, or an item belonging to a different course, is waived rather than blocking the student forever; a cost pointing at a merely disabled (not deleted) item still blocks correctly when the balance is short — disabling is reversible, so the cost is never waived for it |
| `view_page_service_test.php` | 8 | Page-assembly branches: fresh lobby, picked word persists across calls, finished round computes a real cooldown, restriction notice shown when the round limit is reached; the toolbar's help/attempt-history URLs are always present; the forfeit action is shown only during an active round; the help modal's ranking tie-break explanation is hidden when the teacher has turned ranking off; the help modal's PlayerHUD explanation appears as soon as any one of round cost, hint cost, or win grant is configured |
| `word_normalizer_test.php` | 8 | Accent-insensitive normalisation across 8 diacritic combinations |
| `words_repository_test.php` | 40 | Word picking (empty pool, unapproved/too-short/too-long/non-letter exclusion, random mode, shared-sequence determinism and cycling, avoids the excluded word when an alternative exists, allows it back in when it is the only candidate); `get_last_played_word_id` (0 with no finished rounds, ignores a pending reservation); manual and AI word insertion; `word_exists` duplicate check (case-insensitive match, no match, scoped to instance, matches regardless of source, ignores the excluded word id when renaming); word lookup, update and delete scoped to the owning instance; bulk delete and approve; recent-words listing with glossary name join; glossary sync (multi-word concept splitting, configurable stopword filtering, hint update on resync without duplicating, orphan cleanup when an entry disappears, `glossaryid = 0` covering every course glossary, skips a concept whose text already belongs to a manual/AI word without touching that word's hint); the on-screen keyboard's Ç key is only offered when an approved word actually contains one, scoped to its own activity and ignoring unapproved words |
| **Subtotal** | **193** | |

### Web Services Tests (`tests/external/`)

| Test file | Cases | What is covered |
|-----------|------:|----------------|
| `end_round_test.php` | 4 | Forfeit finishes the round; timeout finishes the round; an invalid `reason` value is rejected; the `mod/playerwords:view` capability is required |
| `new_round_test.php` | 3 | A new round picks a fresh word; blocked when the round limit was already reached; the `mod/playerwords:view` capability is required |
| `reveal_hint_test.php` | 6 | Hint is revealed; revealing twice is idempotent; rejected once the round is finished; the `mod/playerwords:view` capability is required; an insufficient PlayerHUD item balance (a real, valid item) blocks the reveal; a cost pointing at a deleted item is waived instead |
| `start_round_test.php` | 5 | Round timer starts; rejected when already started; the `mod/playerwords:view` capability is required; an insufficient PlayerHUD item balance (a real, valid item) blocks starting; a cost pointing at a deleted item is waived instead |
| `submit_guess_test.php` | 7 | A wrong guess never reveals the word; a correct guess reveals it only once finished; a losing guess also reveals it; the `mod/playerwords:view` capability is required; `timeleft` reflects seconds remaining while in progress; `timeleft` is frozen at the moment the round finished, not the wall clock; a fractional ranking total survives the external API's return-value cleaning, against the real webservice call |
| **Subtotal** | **25** | |

| **Grand Total** | **256** | |

```bash
vendor/bin/phpunit --testsuite mod_playerwords
```

### Line coverage by class (PHPUnit + Xdebug)

| Class | Line coverage |
|-------|:-------------:|
| `completion\custom_completion` | 100% |
| `external\end_round` | 76% |
| `external\new_round` | 50% |
| `external\reveal_hint` | 59% |
| `external\start_round` | 43% |
| `external\submit_guess` | 35% |
| `local\ai_word_generator` | 26%¹ |
| `local\gameplay_service` | 95% |
| `local\hud_service` | 90%³ |
| `local\ranking_service` | 75% |
| `local\round_presenter` | 68%² |
| `local\round_service` | 67% |
| `local\view_page_service` | 65% |
| `local\word_normalizer` | 100% |
| `local\words_repository` | 82% |
| `privacy\provider` | 85% |
| **Overall** | **60%** |

¹ Undercounted by design: `ai_word_generator`'s network-calling methods (`call_ai`, `call_core_ai`,
`has_core_ai`) require a real AI provider and are intentionally not unit-tested; the
untrusted-input parsing and validation logic they depend on (`parse_words`, `is_valid_term`) is
fully covered.

² Dropped from 95% after the grading-transparency feature added `build_grading_method_info()`/
`build_grade_so_far()`: the happy path (a real gradebook item with a computed grade) is tested,
but the "no grade item yet" and "grade not yet computed" fallback branches inside
`build_grade_so_far()` are not — left as a follow-up rather than padding the suite with tests for
states the running gradebook update flow makes hard to reach.

³ Dropped from 97% after `hud_service.php` was rewritten to delegate every item operation to
block_playerhud's own `\block_playerhud\local\external_items` API instead of containing the logic
locally: the FIFO-consume and grant logic this file used to own — and its dedicated tests — now
live in block_playerhud's own test suite, so this number reflects a thinner, mostly-delegating
file rather than a coverage regression.

The `external/*` web service classes score lower on raw line percentage than their actual
behaviour coverage suggests: each one is now tested for its happy path, every rejection branch,
the capability guard, and (where applicable) the PlayerHUD insufficient-item branch — but a
capability-guard test necessarily stops at `require_capability()` and never reaches the lines
after it, so it cannot raise the percentage of a class that is mostly "lines after the guard".

[⬆️ Back to top](#english)

---

## Português

O PlayerWords inclui uma suíte PHPUnit cobrindo lógica de negócio, consultas ao repositório, web
services e conformidade com a Privacy API. Todo push de CI executa a matriz completa (Moodle
4.5 → 5.x, PostgreSQL e MariaDB).

### PHPUnit — Testes Centrais

| Arquivo de teste | Casos | O que é coberto |
|-----------------|------:|----------------|
| `backup_restore_test.php` | 5 | Duplicar uma atividade copia suas palavras, renomeia a cópia, reconstrói o cache do curso, e não cria um item de nota duplicado — teste de regressão para a ausência de `prepare_activity_structure()`; uma referência a item do PlayerHUD sobrevive intacta a um "Duplicar atividade" no mesmo curso (o bloco nunca faz parte desse backup mais estreito); um backup/restauração de curso completo pra um curso novo remapeia a referência pro id novo do item, via o mapeamento `playerhud_item` que o próprio passo de restauração do block_playerhud registra; uma referência a item de outro curso é descartada em vez de manter apontando pro curso errado — contra o `backup_controller`/`restore_controller` real, não um atalho simulado; um backup/restauração de curso completo preserva os modos de pontuação de nota/ranking e tanto `score` quanto `rankingpoints` de uma tentativa terminada |
| `cross_instance_security_test.php` | 4 | Estado de sessão, busca de palavra por id, registros de tentativa e a consulta do "registro de tentativas" nunca vazam entre duas instâncias diferentes da atividade, mesmo para o mesmo estudante no mesmo curso |
| `lib_grant_potential_test.php` | 6 | O callback `playerhud_grant_potential` descoberto pelo teto de "Total XP no jogo" do próprio PlayerHUD: vazio pra uma instância de bloco não reconhecida, pra uma atividade sem item de recompensa configurado, e pra uma atividade ilimitada (reflete a mesma regra antifarm da concessão real); uma atividade limitada retorna uma linha no mesmo formato das entradas de item/missão do próprio PlayerHUD (`qtd × rodadas máximas × xp do item`); um item de recompensa pertencente à instância de bloco de outro curso não contribui nada; duas atividades limitadas no mesmo curso contribuem cada uma com sua própria linha |
| `lib_reset_userdata_test.php` | 4 | "Redefinir curso" apaga tentativas e reseta notas só quando a opção está marcada, só para o curso alvo, e o padrão do formulário vem marcado |
| `completion/custom_completion_test.php` | 7 | Regra de conclusão customizada ("exigir tentativas"): incompleta abaixo do limite, completa no limite, regra não reportada como disponível quando desabilitada, nomes de regra definidos, descrição inclui a quantidade exigida, ordem de exibição, uma reserva ainda pendente (rodada iniciada mas não terminada) não conta para o limite |
| `privacy/provider_test.php` | 12 | Declaração de metadados; contextos por tentativas; contextos por palavras adicionadas; listar usuários no contexto (e no-op para contexto que não é de módulo); exportar dados do usuário (e no-op para lista de contextos vazia); excluir dados do usuário em um único e em múltiplos contextos; excluir dados de todos os usuários num contexto (sem afetar outra atividade, e no-op para contexto que não é de módulo) |
| **Subtotal** | **38** | |

### Testes de Lógica de Negócio (`tests/local/`)

| Arquivo de teste | Casos | O que é coberto |
|-----------------|------:|----------------|
| `ai_word_generator_test.php` | 12 | Parsing da resposta de IA (wrapper `words`/legado `concepts`, lista nua, cerca de código markdown removida, JSON malformado/não-array, dica cai para `definition`, entradas não-array ignoradas) e validação de termo como entrada não confiável (palavra única alfabética aceita; termo vazio, multi-palavra e não-alfabético rejeitados) — tudo via reflection, sem chamada real de IA |
| `attempts_history_service_test.php` | 14 | Registro de tentativas e nota atual do próprio estudante: vazio sem rodadas terminadas; exclui uma reserva ainda pendente; linhas exibidas da mais recente para a mais antiga, enquanto o cálculo da nota usa ordem crescente; a nota computada bate com `playerwords_calculate_user_grade()` para o método configurado; resumo de nota oculto em atividade não avaliada; texto da palavra cai do conceito para a palavra crua; tempo usado formatado como m:ss; a coluna de pontos no ranking aparece, formatada com 2 casas decimais, quando o ranking está ligado, e é omitida por completo quando está desligado. Relatório de todos os estudantes (`get_all_history`): tentativas terminadas de todos os estudantes, com o nome de cada um anexado, da mais recente para a mais antiga por padrão; exclui quem pode gerenciar a atividade, tanto das linhas do relatório quanto do dropdown de filtro; o filtro `studentid` restringe às linhas de um único estudante; a ordenação só aceita colunas da lista permitida, caindo para data em vez de gerar erro numa chave desconhecida; a paginação retorna fatias distintas do conjunto total de resultados |
| `gameplay_service_test.php` | 19 | Algoritmo de feedback por letra em 9 combinações de chute/alvo (correto, ausente, presente, letras duplicadas, esgotamento do pool); cálculo de nota para vitória, derrota e notas decimais no modo Binário; modo Linear tanto pro cálculo da nota quanto do ranking (nota cheia nas duas primeiras tentativas, escala proporcionalmente a partir da terceira, fração positiva não-zero na última tentativa permitida, degenera pra nota cheia em toda tentativa quando max_attempts é 2 ou menos, zero quando não completou) |
| `hud_service_test.php` | 21 | Delega pra API `\block_playerhud\local\external_items` do block_playerhud em toda operação de item, validando pertencimento contra a instância de bloco do próprio chamador em vez de ler as tabelas do PlayerHUD direto: localização do bloco PlayerHUD entre cursos; disponibilidade por curso (verdadeiro com instância do bloco, falso sem instância, ignora instância de outro curso); resolução de nome de item (vazio pra item de outra instância de bloco); listagem de itens; consumo de itens (fundos insuficientes, sucesso, ordem FIFO, curto-circuito com quantidade zero, dispensado — não bloqueado — pra item de outra instância de bloco); concessão de itens (linhas de inventário com `source='playerwords'` mais XP concedido, XP retido quando a chamada sinaliza origem sem limite, item sem XP nunca altera XP em nenhum dos casos, item inexistente, item de instância estranha e quantidade zero são todos no-op) |
| `ranking_service_test.php` | 6 | Ranking vazio; ordenação decrescente por pontuação; truncamento top-5 com linha de "outsider" para o usuário atual fora do top; `SEPARATEGROUPS` filtra para o grupo do próprio estudante; uma reserva ainda pendente (rodada em andamento ou abandonada sem terminar) é excluída do ranking; um usuário que pode gerenciar a atividade (professor editor) nunca aparece no ranking, mesmo com tentativas próprias |
| `round_presenter_test.php` | 35 | Renderização das linhas da grade; texto de cooldown; mensagens de feedback (desistiu/tempo esgotado/perdeu/venceu, variando pelas tentativas usadas); contexto de ranking; contexto de resultado da rodada (em branco até terminar, revela ao terminar, cooldown reflete mudança posterior de configuração); saldo/custo em item do PlayerHUD no lobby (exibido/oculto pelo estado da rodada, início desabilitado abaixo da quantidade exigida, habilitado quando o saldo cobre), informação de temporizador no lobby; saldo/custo em item do PlayerHUD no botão de dica do painel (exibido/oculto pelo estado de revelação, dica desabilitada abaixo da quantidade exigida, habilitada quando o saldo cobre), temporizador permanece zerado antes da rodada iniciar; resumo de nota até agora (ausente antes de terminar, ausente quando não avaliada, mostra método e nota computada ao terminar, ignora uma tentativa ainda pendente); linha de informação do método de avaliação no lobby (exibida quando relevante, oculta em atividade de rodada única, oculta quando não avaliada); a tecla Ç do teclado só aparece quando o próprio banco de palavras da atividade precisa dela; contador de rodadas jogadas exibido no lobby e no resultado da rodada, usando o símbolo de infinito numa atividade ilimitada e o limite configurado nos demais casos; o rótulo de item concedido pelo PlayerHUD só aparece numa vitória de verdade com item configurado, em branco numa derrota ou quando não configurado |
| `round_service_test.php` | 30 | Transições de estado da rodada: palavra sorteada e `round_started` disparado; envio de chute (errado, correto, sem tentativas, após terminar, tamanho incompatível); desistência; timeout (termina após o prazo passar, rejeitado antes do prazo, rejeitado quando o temporizador está desabilitado); nova rodada; aviso de restrição (limite de rodadas atingido, sem restrição); `count_rounds_played` restrito à instância e ao usuário; cálculo de cooldown (desabilitado, sem tentativas ainda, expirado, reflete mudança posterior de configuração); recupera sorteando palavra nova após a anterior ser removida no meio da rodada; `start_round` reserva uma linha de tentativa; `finish_round` completa a reserva em vez de duplicá-la; uma rodada abandonada continua contando para `max_rounds`; a reserva obsoleta é descartada quando a palavra é removida no meio da rodada; uma rodada vencida concede o item do PlayerHUD configurado com XP quando `max_rounds` é limitado, concede sem XP quando ilimitado, e uma rodada perdida nunca concede; um custo de rodada ou dica apontando pra um item excluído, ou de outro curso, é dispensado em vez de travar o estudante pra sempre; um custo apontando pra um item só desabilitado (não excluído) continua bloqueando corretamente quando o saldo está curto — desabilitar é reversível, então o custo nunca é dispensado por causa disso |
| `view_page_service_test.php` | 8 | Ramificações de montagem de página: lobby fresco, palavra sorteada persiste entre chamadas, rodada terminada calcula cooldown real, aviso de restrição exibido quando o limite de rodadas é atingido; as URLs de ajuda/registro de tentativas do toolbar sempre estão presentes; a ação de desistir só aparece durante uma rodada ativa; a explicação de critério de desempate do ranking some da ajuda quando o professor desliga o ranking; a explicação do PlayerHUD na ajuda aparece assim que qualquer um entre custo de rodada, custo de dica ou recompensa por vitória está configurado |
| `word_normalizer_test.php` | 8 | Normalização sem acentuação em 8 combinações de diacríticos |
| `words_repository_test.php` | 40 | Seleção de palavra (pool vazio, exclusão de não aprovados/muito curtos/muito longos/caracteres não-letra, modo aleatório, determinismo e ciclagem da sequência compartilhada, evita a palavra excluída quando há alternativa, permite-a de volta quando é a única candidata); `get_last_played_word_id` (0 sem rodadas terminadas, ignora uma reserva pendente); inserção de palavra manual e por IA; checagem de duplicata `word_exists` (correspondência case-insensitive, sem correspondência, restrita à instância, corresponde independente da fonte, ignora o próprio id ao renomear); busca, atualização e exclusão de palavra restritas à instância dona; exclusão e aprovação em lote; listagem de palavras recentes com join do nome do glossário; sincronização de glossário (divisão de conceito multi-palavra, filtro de stopwords configuráveis, atualização de dica em re-sincronização sem duplicar, limpeza de órfãos quando uma entrada desaparece, modo `glossaryid = 0` cobrindo todos os glossários do curso, pula um conceito cujo texto já pertence a uma palavra manual/IA sem alterar a dica dela); a tecla Ç do teclado virtual só é oferecida quando uma palavra aprovada realmente contém uma, restrito à própria atividade e ignorando palavras não aprovadas |
| **Subtotal** | **193** | |

### Testes de Web Services (`tests/external/`)

| Arquivo de teste | Casos | O que é coberto |
|-----------------|------:|----------------|
| `end_round_test.php` | 4 | Desistência termina a rodada; timeout termina a rodada; um valor inválido de `reason` é rejeitado; a capability `mod/playerwords:view` é exigida |
| `new_round_test.php` | 3 | Nova rodada sorteia palavra nova; bloqueado quando o limite de rodadas já foi atingido; a capability `mod/playerwords:view` é exigida |
| `reveal_hint_test.php` | 6 | Dica é revelada; revelar duas vezes é idempotente; rejeitado após a rodada terminar; a capability `mod/playerwords:view` é exigida; saldo insuficiente de item do PlayerHUD (item real, válido) bloqueia a revelação; um custo apontando pra um item excluído é dispensado em vez disso |
| `start_round_test.php` | 5 | Cronômetro da rodada inicia; rejeitado quando já iniciado; a capability `mod/playerwords:view` é exigida; saldo insuficiente de item do PlayerHUD (item real, válido) bloqueia o início; um custo apontando pra um item excluído é dispensado em vez disso |
| `submit_guess_test.php` | 7 | Um chute errado nunca revela a palavra; um chute correto revela só quando termina; um chute perdedor também revela; a capability `mod/playerwords:view` é exigida; `timeleft` reflete os segundos restantes durante a rodada; `timeleft` fica congelado no momento em que a rodada terminou, não no relógio real; um total de ranking fracionado sobrevive à limpeza de retorno da API externa, contra a chamada real da webservice |
| **Subtotal** | **25** | |

| **Total Geral** | **256** | |

```bash
vendor/bin/phpunit --testsuite mod_playerwords
```

### Cobertura de linha por classe (PHPUnit + Xdebug)

| Classe | Cobertura de linha |
|-------|:-------------:|
| `completion\custom_completion` | 100% |
| `external\end_round` | 76% |
| `external\new_round` | 50% |
| `external\reveal_hint` | 59% |
| `external\start_round` | 43% |
| `external\submit_guess` | 35% |
| `local\ai_word_generator` | 26%¹ |
| `local\gameplay_service` | 95% |
| `local\hud_service` | 90%³ |
| `local\ranking_service` | 75% |
| `local\round_presenter` | 68%² |
| `local\round_service` | 67% |
| `local\view_page_service` | 65% |
| `local\word_normalizer` | 100% |
| `local\words_repository` | 82% |
| `privacy\provider` | 85% |
| **Geral** | **60%** |

¹ Subcontado por natureza: os métodos que chamam rede em `ai_word_generator` (`call_ai`,
`call_core_ai`, `has_core_ai`) exigem um provedor de IA real e não são testados por unidade de
propósito; a lógica de parsing e validação de entrada não confiável da qual eles dependem
(`parse_words`, `is_valid_term`) está totalmente coberta.

² Caiu de 95% depois que a funcionalidade de transparência de avaliação adicionou
`build_grading_method_info()`/`build_grade_so_far()`: o caminho feliz (um item de nota real com
nota computada) é testado, mas as ramificações de fallback "ainda sem item de nota" e "nota ainda
não computada" dentro de `build_grade_so_far()` não são — deixado como próximo passo, em vez de
inflar a suíte com testes para estados que o fluxo real de atualização do livro de notas dificulta
alcançar.

³ Caiu de 97% depois que `hud_service.php` foi reescrito pra delegar toda operação de item pra API
`\block_playerhud\local\external_items` do próprio block_playerhud, em vez de conter a lógica
localmente: a lógica de consumo FIFO e concessão que esse arquivo tinha antes — e os testes
dedicados dela — agora moram na suíte de testes do próprio block_playerhud, então esse número
reflete um arquivo mais fino, majoritariamente delegando, não uma regressão de cobertura.

As classes de web service `external/*` mostram um percentual de linha menor do que sua cobertura
real de comportamento sugere: cada uma já é testada quanto ao caminho feliz, toda ramificação de
rejeição, a guarda de capability e (quando aplicável) a ramificação de item insuficiente do
PlayerHUD — mas um teste de guarda de capability necessariamente para em `require_capability()` e
nunca alcança as linhas depois dela, então ele não consegue elevar o percentual de uma classe que
é majoritariamente "linhas depois da guarda".

[⬆️ Back to top](#português)
