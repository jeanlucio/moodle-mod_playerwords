# Moodle Activity PlayerWords

[![Moodle Plugin CI](https://github.com/jeanlucio/moodle-mod_playerwords/actions/workflows/ci.yml/badge.svg)](https://github.com/jeanlucio/moodle-mod_playerwords/actions/workflows/ci.yml)
![Moodle](https://img.shields.io/badge/Moodle-4.5%2B-orange?style=flat-square&logo=moodle&logoColor=white)
![License](https://img.shields.io/badge/License-GPLv3-blue?style=flat-square)
![Status](https://img.shields.io/badge/Status-Alpha-yellow?style=flat-square)
[![PlayerGames Ecosystem](https://img.shields.io/badge/PlayerGames-Ecosystem-6f42c1?style=flat-square&logo=gamepad&logoColor=white)](https://moodle.org/plugins/browse.php?list=contributor&id=3970322)
![Game Activity](https://img.shields.io/badge/Role-Game_Activity-198754?style=flat-square)

[English](#english) | [Português](#português)

---

## English

**PlayerWords** is a word-guessing vocabulary activity for Moodle. Students guess a hidden word letter-by-letter within a configurable number of attempts, receiving colour-coded and symbol feedback on each guess.

The activity integrates with the course **Glossary** (words and definitions are imported automatically), can generate word candidates through **AI**, and integrates with the **PlayerHUD** gamification block (items can be required to start a round or to reveal a hint).

<a id="toc-en"></a>
**📑 Table of Contents**

- [✨ Features](#-features)
- [🎓 Educational Purpose](#-educational-purpose)
- [🕹️ PlayerGames Ecosystem](#-playergames-ecosystem)
- [📦 Requirements](#-requirements)
- [🛠️ Installation](#-installation)
- [📖 Usage](#-usage)
- [🧪 Automated Tests](#-automated-tests)
- [🔐 Security & Compliance](#-security--compliance)
- [📄 License / Licença](#-license--licença)

---

### ✨ Features

* 🟩 **Word-Guessing Gameplay:** Colour-coded + symbol feedback per letter (correct position, wrong position, absent).
* 📖 **Glossary Integration:** Import concepts from one or all course glossaries as the word pool, with definitions used as hints.
* 🤖 **AI Word Generation (Optional):** Generate candidate words and hints for a given topic via `local_aihub` (BYOK) or Moodle's `core_ai` fallback. Generated words are treated as untrusted input — only single-token, purely alphabetic terms within the configured length bounds are saved, and they enter the pool pending teacher approval.
* ✍️ **Manual Word Pool:** Teachers can add, edit, approve, and delete words directly from the management page.
* 🔀 **Word Modes:** Random word per round (default) or shared sequence mode, where every student receives the same words in the same order.
* 💡 **Hidden Hint System:** Hint is hidden by default; students must explicitly reveal it (optionally at an item cost via PlayerHUD).
* 🏳️ **Give Up:** Students can forfeit the current round at any time — the correct word is revealed immediately.
* ⏱️ **Configurable Cooldown:** Minimum wait between rounds (minutes, hours, or days), always recomputed from the activity's current setting — a teacher's change applies immediately, even to a cooldown already in progress.
* 🔢 **Round Limit:** Teachers can cap the total number of rounds per student (1–10 or unlimited).
* 🔡 **Accent-Insensitive Matching:** Diacritics are always stripped before comparing guess and target.
* 📊 **Grading Methods:** Highest grade, average grade, first attempt, last attempt, or average over all required rounds.
* 📋 **Gradebook Integration:** Grades are written automatically on every round completion.
* ✅ **Custom Completion Rule:** Minimum number of attempts completed, evaluated and applied immediately after each round.
* 🔄 **Course Reset Support:** "Reset course" clears student attempts and resets grades for the activity, scoped to the target course only.
* 🏆 **Ranking Page:** Leaderboard scoped to the activity, with outsider row for students outside the top positions, respecting `SEPARATEGROUPS`.
* ♿ **Accessibility:** WCAG AA contrast on all grid states; non-colour indicators (✓ correct, ~ present); `aria-label` on every cell; a live region announces state changes for screen readers.
* ⚡ **AJAX-Powered:** Every round transition (guess, hint, forfeit, timeout, start, new round) happens without a page reload.
* 🎮 **PlayerHUD Integration (Optional):** Require inventory items to start a round or to reveal a hint, with atomic FIFO consumption.
* 📦 **Backup & Restore:** Full Moodle 2 backup/restore support, including the "Duplicate activity" action, word pool, attempts, and user/glossary id remapping.
* 🔐 **Privacy API:** GDPR/LGPD compliant — complete data export and deletion for all stored personal data.

[⬆️ Back to index](#toc-en)

---

### 🎓 Educational Purpose

PlayerWords is designed to:

* Reinforce learning of concepts covered in the course or subject
* Foster playful, game-based learning experiences
* Simplify and make learning and assessment dynamics more intuitive
* Contribute to achieving educational goals across different courses and disciplines
* Promote active learning methodologies, including game-based learning and gamification
* Support **retrieval practice** — students must recall a concept from memory before seeing any answer, one of the most effective techniques for long-term retention
* Encourage **spaced practice** — the configurable recharge time between rounds brings students back to the content across multiple sessions, aligning with the principles of spaced repetition

Suitable for:

* Any course that uses concept-based terminology
* Gamified academic courses using the PlayerGames ecosystem
* Formative assessment and self-study reinforcement
* Engagement reinforcement strategies

[⬆️ Back to index](#toc-en)

---

### 🕹️ PlayerGames Ecosystem

PlayerWords is part of the **PlayerGames** gamification ecosystem for Moodle. Its main direct integration is with the PlayerHUD block:

* **PlayerHUD Block (Optional):** Configure item costs for starting a round or revealing a hint.
  👉 https://github.com/jeanlucio/moodle-block_playerhud

* **PlayerGroup (Compatible):** Standard Moodle groups — created manually or via the PlayerGroup activity — are honoured by the ranking's `SEPARATEGROUPS` filtering.
  👉 https://github.com/jeanlucio/moodle-mod_playergroup

See the author's [Moodle Plugins Directory profile](https://moodle.org/plugins/browse.php?list=contributor&id=3970322) for the full PlayerGames family.

[⬆️ Back to index](#toc-en)

---

### 📦 Requirements

| Component | Version |
|-----------|---------|
| Moodle    | 4.5+    |
| PHP       | 8.1+    |

[⬆️ Back to index](#toc-en)

---

### 🛠️ Installation

1. Download the `.zip` file or clone this repository.
2. Extract the folder into your Moodle `mod/` directory.
3. Rename the folder to `playerwords` (if necessary).
   Final path:
   `your-moodle/mod/playerwords/`
4. Visit **Site administration > Notifications** to complete installation.
5. Add a **PlayerWords** activity to any course.

[⬆️ Back to index](#toc-en)

---

### 📖 Usage

1. Add a **PlayerWords** activity to your course.
2. Configure:
   * Word length range and maximum attempts
   * Cooldown between rounds and round limit
   * Word mode (random or shared sequence)
   * Grading method and gradebook settings
   * Word sources (manual, Glossary, AI) and Glossary source (optional)
   * PlayerHUD item costs (optional, when PlayerHUD block is present)
3. Open the **Manage words** page to add, generate with AI, approve, edit, or delete words.
4. Students play directly from the activity page — guessing, revealing hints, and forfeiting rounds, with no page reload.
5. Grades and ranking update automatically after each round.

[⬆️ Back to index](#toc-en)

---

### 🧪 Automated Tests

PlayerWords ships with a PHPUnit test suite covering business logic, repository queries, web services, and Privacy API compliance. Every CI push runs against the full matrix (Moodle 4.5 → 5.x, PostgreSQL & MariaDB).

#### PHPUnit — Core Tests

| Test file | Cases | What is covered |
|-----------|------:|----------------|
| `backup_restore_test.php` | 1 | Duplicating an activity copies its words, renames the copy, rebuilds the course cache, and does not create a duplicate grade item — regression guard for a missing `prepare_activity_structure()` call |
| `cross_instance_security_test.php` | 3 | Session state, word lookups by id, and attempt records never leak between two different activity instances, even for the same student in the same course |
| `lib_reset_userdata_test.php` | 4 | Course reset deletes attempts and resets grades only when the checkbox is enabled, only for the target course, and the form default enables it |
| `completion/custom_completion_test.php` | 6 | Custom completion rule ("require attempts"): incomplete below threshold, complete at threshold, rule not reported as available when disabled, defined rule names, rule description includes the required count, display sort order |
| `privacy/provider_test.php` | 12 | Metadata declaration; contexts by attempts; contexts by words added; list users in context (and no-op for a non-module context); export user data (and no-op for an empty contextlist); delete user data across a single and across multiple contexts; delete all users' data in a context (leaving another activity untouched, and no-op for a non-module context) |
| **Subtotal** | **26** | |

#### Local Business-Logic Tests (`tests/local/`)

| Test file | Cases | What is covered |
|-----------|------:|----------------|
| `ai_word_generator_test.php` | 12 | AI response parsing (`words`/legacy `concepts` wrappers, bare list, markdown code fence stripped, malformed/non-array JSON, hint falls back to `definition`, non-array entries skipped) and untrusted-input term validation (single alphabetic word accepted; empty, multi-word, and non-alphabetic terms rejected) — all via reflection, no real AI call |
| `gameplay_service_test.php` | 12 | Letter feedback algorithm across 9 guess/target combinations (correct, absent, present, duplicate letters, pool exhaustion); score calculation for win, loss, and decimal grades |
| `hud_service_test.php` | 10 | PlayerHUD block lookup across courses; item name resolution; item list retrieval; consume items (insufficient funds, success, FIFO order, zero-quantity short-circuit) |
| `ranking_service_test.php` | 4 | Empty ranking; score-descending ordering; top-5 truncation with an outsider row for a lower-ranked current user; `SEPARATEGROUPS` filters to the student's own group |
| `round_presenter_test.php` | 19 | Grid row rendering; cooldown text; feedback messages (forfeited/timed out/lost/won, varying by attempts used); ranking context; round result context (blank until finished, reveals on finish, cooldown reflects a later settings change); lobby PlayerHUD cost hint (shown/hidden by round state), lobby timer info; round panel hint-button PlayerHUD cost (shown/hidden by reveal state), timer stays at zero before the round starts |
| `round_service_test.php` | 16 | Round state transitions: word picked and `round_started` fired; guess submission (wrong, correct, out of attempts, after finish, length mismatch); forfeit; timeout; new round; restriction notice (max rounds reached, unrestricted); cooldown computation (disabled, no attempts yet, expired, reflects a later settings change); recovers by picking a fresh word after the previous one was removed mid-round |
| `view_page_service_test.php` | 4 | Page-assembly branches: fresh lobby, picked word persists across calls, finished round computes a real cooldown, restriction notice shown when the round limit is reached |
| `word_normalizer_test.php` | 8 | Accent-insensitive normalisation across 8 diacritic combinations |
| `words_repository_test.php` | 27 | Word picking (empty pool, unapproved/too-short/too-long/non-letter exclusion, random mode, shared-sequence determinism and cycling); manual and AI word insertion; word lookup, update and delete scoped to the owning instance; bulk delete and approve; recent-words listing with glossary name join; glossary sync (multi-word concept splitting, configurable stopword filtering, hint update on resync without duplicating, orphan cleanup when an entry disappears, `glossaryid = 0` covering every course glossary) |
| **Subtotal** | **112** | |

#### Web Services Tests (`tests/external/`)

| Test file | Cases | What is covered |
|-----------|------:|----------------|
| `end_round_test.php` | 4 | Forfeit finishes the round; timeout finishes the round; an invalid `reason` value is rejected; the `mod/playerwords:view` capability is required |
| `new_round_test.php` | 3 | A new round picks a fresh word; blocked when the round limit was already reached; the `mod/playerwords:view` capability is required |
| `reveal_hint_test.php` | 5 | Hint is revealed; revealing twice is idempotent; rejected once the round is finished; the `mod/playerwords:view` capability is required; an insufficient PlayerHUD item balance blocks the reveal |
| `start_round_test.php` | 4 | Round timer starts; rejected when already started; the `mod/playerwords:view` capability is required; an insufficient PlayerHUD item balance blocks starting |
| `submit_guess_test.php` | 6 | A wrong guess never reveals the word; a correct guess reveals it only once finished; a losing guess also reveals it; the `mod/playerwords:view` capability is required; `timeleft` reflects seconds remaining while in progress; `timeleft` is frozen at the moment the round finished, not the wall clock |
| **Subtotal** | **22** | |

| **Grand Total** | **160** | |

```bash
vendor/bin/phpunit --testsuite mod_playerwords
```

**Line coverage by class (PHPUnit + Xdebug):**

| Class | Line coverage |
|-------|:-------------:|
| `completion\custom_completion` | 100% |
| `external\end_round` | 76% |
| `external\new_round` | 57% |
| `external\reveal_hint` | 59% |
| `external\start_round` | 45% |
| `external\submit_guess` | 39% |
| `local\ai_word_generator` | 26%¹ |
| `local\gameplay_service` | 95% |
| `local\hud_service` | 97% |
| `local\ranking_service` | 75% |
| `local\round_presenter` | 95% |
| `local\round_service` | 60% |
| `local\view_page_service` | 73% |
| `local\word_normalizer` | 100% |
| `local\words_repository` | 80% |
| `privacy\provider` | 94% |
| **Overall** | **62%** |

¹ Undercounted by design: `ai_word_generator`'s network-calling methods (`call_ai`, `call_core_ai`, `has_core_ai`) require a real AI provider and are intentionally not unit-tested; the untrusted-input parsing and validation logic they depend on (`parse_words`, `is_valid_term`) is fully covered.

The `external/*` web service classes score lower on raw line percentage than their actual behaviour coverage suggests: each one is now tested for its happy path, every rejection branch, the capability guard, and (where applicable) the PlayerHUD insufficient-item branch — but a capability-guard test necessarily stops at `require_capability()` and never reaches the lines after it, so it cannot raise the percentage of a class that is mostly "lines after the guard".

[⬆️ Back to index](#toc-en)

---

### 🔐 Security & Compliance

* Capability-based access control (`mod/playerwords:view`, `mod/playerwords:addinstance`)
* `require_sesskey()` protection on all POST actions; AJAX calls are validated by Moodle's `core/ajax` dispatcher
* Server-side enforcement of round limits and cooldown, always recomputed from current settings
* Guess charset validation — only Unicode letters accepted
* AI-generated words are treated as untrusted input: only single-token, alphabetic terms within the configured length bounds are saved, and they enter pending teacher approval
* Session round state is isolated per activity instance and per user — a word id or session key from one activity is never accepted by another
* Moodle External API compliant
* Privacy API fully implemented (GDPR/LGPD)

[⬆️ Back to index](#toc-en)

---

## 📄 License / Licença

This project is licensed under the **GNU General Public License v3 (GPLv3)**.

**Copyright:** 2026 Jean Lúcio

[⬆️ Back to index](#toc-en)

---

## Português

O **PlayerWords** é uma atividade de adivinhação de palavras para o Moodle. O estudante adivinha uma palavra oculta letra por letra dentro de um número configurável de tentativas, recebendo feedback visual em cores e símbolos a cada chute.

A atividade integra-se com o **Glossário** do curso (palavras e definições são importadas automaticamente), pode gerar candidatas a palavra por **IA**, e integra-se com o bloco de gamificação **PlayerHUD** (itens podem ser exigidos para iniciar uma rodada ou revelar uma dica).

<a id="toc-pt"></a>
**📑 Índice**

- [✨ Funcionalidades](#-funcionalidades)
- [🎓 Finalidade Educacional](#-finalidade-educacional)
- [🕹️ Ecossistema PlayerGames](#-ecossistema-playergames)
- [📦 Requisitos](#-requisitos)
- [🛠️ Instalação](#-instalação)
- [📖 Como Usar](#-como-usar)
- [🧪 Testes Automatizados](#-testes-automatizados)
- [🔐 Segurança e Conformidade](#-segurança-e-conformidade)
- [📄 Licença](#-licença)

---

### ✨ Funcionalidades

* 🟩 **Jogo de adivinhação de palavras:** Feedback por letra com código de cores + símbolos (posição correta, posição errada, ausente).
* 📖 **Integração com Glossário:** Importa conceitos de um ou todos os glossários do curso como pool de palavras, usando as definições como dicas.
* 🤖 **Geração de palavras por IA (Opcional):** Gera candidatas a palavra e dica para um tópico livre via `local_aihub` (chave própria) ou fallback para o `core_ai` do Moodle. A resposta é tratada como entrada não confiável — só termos de um único token, puramente alfabéticos e dentro do comprimento configurado são salvos, e entram no pool pendentes de aprovação do professor.
* ✍️ **Pool de palavras manual:** O professor pode adicionar, editar, aprovar e excluir palavras diretamente na página de gerenciamento.
* 🔀 **Modos de palavra:** Palavra aleatória por rodada (padrão) ou sequência compartilhada — todos os estudantes recebem as mesmas palavras na mesma ordem.
* 💡 **Dica oculta:** A dica é escondida por padrão; o estudante precisa clicar em "Revelar dica" (com custo opcional em itens via PlayerHUD).
* 🏳️ **Desistir:** O estudante pode abandonar a rodada a qualquer momento — a palavra correta é revelada imediatamente.
* ⏱️ **Tempo de recarga configurável:** Intervalo mínimo entre rodadas (minutos, horas ou dias), sempre recalculado a partir da configuração atual da atividade — uma mudança do professor vale imediatamente, mesmo para quem já está em cooldown.
* 🔢 **Limite de rodadas:** O professor pode limitar o total de rodadas por estudante (1–10 ou ilimitado).
* 🔡 **Correspondência sem acentos:** Acentuação é sempre ignorada ao comparar chute e palavra-alvo.
* 📊 **Métodos de nota:** Maior nota, média, primeira tentativa, última tentativa ou média sobre todas as rodadas exigidas.
* 📋 **Integração com o livro de notas:** Notas gravadas automaticamente ao final de cada rodada.
* ✅ **Regra de conclusão personalizada:** Número mínimo de tentativas realizadas, avaliada e aplicada imediatamente após cada rodada.
* 🔄 **Suporte a "Redefinir curso":** Limpa as tentativas dos estudantes e reseta as notas da atividade, restrito ao curso alvo.
* 🏆 **Página de ranking:** Classificação por atividade, com linha de "outsider" para estudantes fora das primeiras posições, respeitando `SEPARATEGROUPS`.
* ♿ **Acessibilidade:** Contraste WCAG AA em todos os estados da grade; indicadores não visuais (✓ correto, ~ presente); `aria-label` em cada célula; região viva anuncia mudanças de estado para leitor de tela.
* ⚡ **Powered por AJAX:** Toda transição de rodada (chute, dica, desistência, timeout, iniciar, nova rodada) acontece sem recarregar a página.
* 🎮 **Integração com PlayerHUD (Opcional):** Exige itens do inventário para iniciar uma rodada ou revelar a dica, com consumo atômico em ordem FIFO.
* 📦 **Backup & Restauração:** Suporte completo ao backup Moodle 2, incluindo a ação "Duplicar atividade", pool de palavras, tentativas e remapeamento de ids de usuário/glossário.
* 🔐 **Privacy API:** Compatível com LGPD/GDPR — exportação e exclusão completas de dados pessoais armazenados.

[⬆️ Voltar ao índice](#toc-pt)

---

### 🎓 Finalidade Educacional

O PlayerWords foi projetado para:

* Reforçar o aprendizado de conceitos trabalhados no curso ou disciplina
* Fomentar o aprendizado lúdico e baseado em jogos
* Simplificar e tornar mais intuitivos os processos de aprendizagem e avaliação
* Colaborar para o atingimento de objetivos educacionais em cursos e disciplinas
* Fomentar metodologias ativas como a utilização de games na educação e a gamificação
* Apoiar a **prática de recuperação** — o estudante precisa lembrar o conceito antes de ver qualquer resposta, uma das técnicas com maior evidência de eficácia para retenção de longo prazo
* Estimular a **prática espaçada** — o tempo de recarga configurável entre rodadas faz o estudante retornar ao conteúdo em sessões distintas, alinhando-se aos princípios da repetição espaçada

Indicado para:

* Qualquer curso que trabalhe com conceitos expressos em palavras ou termos
* Cursos gamificados com o ecossistema PlayerGames
* Avaliação formativa e reforço de autoestudo
* Estratégias de reforço de engajamento

[⬆️ Voltar ao índice](#toc-pt)

---

### 🕹️ Ecossistema PlayerGames

O PlayerWords faz parte do ecossistema de gamificação **PlayerGames** para Moodle. Sua principal integração direta é com o bloco PlayerHUD:

* **Bloco PlayerHUD (Opcional):** Configure custos em itens para iniciar uma rodada ou revelar a dica.
  👉 https://github.com/jeanlucio/moodle-block_playerhud

* **PlayerGroup (Compatível):** Grupos padrão do Moodle — criados manualmente ou pela atividade PlayerGroup — são respeitados pelo filtro `SEPARATEGROUPS` do ranking.
  👉 https://github.com/jeanlucio/moodle-mod_playergroup

Veja o [perfil do autor no Moodle Plugins Directory](https://moodle.org/plugins/browse.php?list=contributor&id=3970322) para a família PlayerGames completa.

[⬆️ Voltar ao índice](#toc-pt)

---

### 📦 Requisitos

| Componente | Versão |
|------------|--------|
| Moodle     | 4.5+   |
| PHP        | 8.1+   |

[⬆️ Voltar ao índice](#toc-pt)

---

### 🛠️ Instalação

1. Baixe o arquivo `.zip` ou clone este repositório.
2. Extraia na pasta `mod/` do seu Moodle.
3. Renomeie para `playerwords` (se necessário).
   Caminho final:
   `seu-moodle/mod/playerwords/`
4. Acesse **Administração do site > Notificações** para concluir a instalação.
5. Adicione uma atividade **PlayerWords** a qualquer curso.

[⬆️ Voltar ao índice](#toc-pt)

---

### 📖 Como Usar

1. Adicione uma atividade **PlayerWords** ao seu curso.
2. Configure:
   - Faixa de comprimento de palavras e máximo de tentativas
   - Tempo de recarga entre rodadas e limite de rodadas
   - Modo de palavras (aleatório ou sequência compartilhada)
   - Método de nota e configurações do livro de notas
   - Fontes de palavras (manual, Glossário, IA) e fonte de glossário (opcional)
   - Custos em itens do PlayerHUD (opcional, quando o bloco PlayerHUD está presente)
3. Acesse **Gerenciar palavras** para adicionar, gerar com IA, aprovar, editar ou excluir palavras.
4. Os estudantes jogam diretamente na página da atividade — chutando, revelando dicas e desistindo de rodadas, sem recarregar a página.
5. Notas e ranking são atualizados automaticamente após cada rodada.

[⬆️ Voltar ao índice](#toc-pt)

---

### 🧪 Testes Automatizados

O PlayerWords inclui uma suíte PHPUnit cobrindo lógica de negócio, consultas ao repositório, web services e conformidade com a Privacy API. Todo push de CI executa a matriz completa (Moodle 4.5 → 5.x, PostgreSQL e MariaDB).

#### PHPUnit — Testes Centrais

| Arquivo de teste | Casos | O que é coberto |
|-----------------|------:|----------------|
| `backup_restore_test.php` | 1 | Duplicar uma atividade copia suas palavras, renomeia a cópia, reconstrói o cache do curso, e não cria um item de nota duplicado — teste de regressão para a ausência de `prepare_activity_structure()` |
| `cross_instance_security_test.php` | 3 | Estado de sessão, busca de palavra por id e registros de tentativa nunca vazam entre duas instâncias diferentes da atividade, mesmo para o mesmo estudante no mesmo curso |
| `lib_reset_userdata_test.php` | 4 | "Redefinir curso" apaga tentativas e reseta notas só quando a opção está marcada, só para o curso alvo, e o padrão do formulário vem marcado |
| `completion/custom_completion_test.php` | 6 | Regra de conclusão customizada ("exigir tentativas"): incompleta abaixo do limite, completa no limite, regra não reportada como disponível quando desabilitada, nomes de regra definidos, descrição inclui a quantidade exigida, ordem de exibição |
| `privacy/provider_test.php` | 12 | Declaração de metadados; contextos por tentativas; contextos por palavras adicionadas; listar usuários no contexto (e no-op para contexto que não é de módulo); exportar dados do usuário (e no-op para lista de contextos vazia); excluir dados do usuário em um único e em múltiplos contextos; excluir dados de todos os usuários num contexto (sem afetar outra atividade, e no-op para contexto que não é de módulo) |
| **Subtotal** | **26** | |

#### Testes de Lógica de Negócio (`tests/local/`)

| Arquivo de teste | Casos | O que é coberto |
|-----------------|------:|----------------|
| `ai_word_generator_test.php` | 12 | Parsing da resposta de IA (wrapper `words`/legado `concepts`, lista nua, cerca de código markdown removida, JSON malformado/não-array, dica cai para `definition`, entradas não-array ignoradas) e validação de termo como entrada não confiável (palavra única alfabética aceita; termo vazio, multi-palavra e não-alfabético rejeitados) — tudo via reflection, sem chamada real de IA |
| `gameplay_service_test.php` | 12 | Algoritmo de feedback por letra em 9 combinações de chute/alvo (correto, ausente, presente, letras duplicadas, esgotamento do pool); cálculo de nota para vitória, derrota e notas decimais |
| `hud_service_test.php` | 10 | Localização do bloco PlayerHUD entre cursos; resolução de nome de item; listagem de itens; consumo de itens (fundos insuficientes, sucesso, ordem FIFO, curto-circuito com quantidade zero) |
| `ranking_service_test.php` | 4 | Ranking vazio; ordenação decrescente por pontuação; truncamento top-5 com linha de "outsider" para o usuário atual fora do top; `SEPARATEGROUPS` filtra para o grupo do próprio estudante |
| `round_presenter_test.php` | 19 | Renderização das linhas da grade; texto de cooldown; mensagens de feedback (desistiu/tempo esgotado/perdeu/venceu, variando pelas tentativas usadas); contexto de ranking; contexto de resultado da rodada (em branco até terminar, revela ao terminar, cooldown reflete mudança posterior de configuração); custo em item do PlayerHUD no lobby (exibido/oculto pelo estado da rodada), informação de temporizador no lobby; custo em item do PlayerHUD no botão de dica do painel (exibido/oculto pelo estado de revelação), temporizador permanece zerado antes da rodada iniciar |
| `round_service_test.php` | 16 | Transições de estado da rodada: palavra sorteada e `round_started` disparado; envio de chute (errado, correto, sem tentativas, após terminar, tamanho incompatível); desistência; timeout; nova rodada; aviso de restrição (limite de rodadas atingido, sem restrição); cálculo de cooldown (desabilitado, sem tentativas ainda, expirado, reflete mudança posterior de configuração); recupera sorteando palavra nova após a anterior ser removida no meio da rodada |
| `view_page_service_test.php` | 4 | Ramificações de montagem de página: lobby fresco, palavra sorteada persiste entre chamadas, rodada terminada calcula cooldown real, aviso de restrição exibido quando o limite de rodadas é atingido |
| `word_normalizer_test.php` | 8 | Normalização sem acentuação em 8 combinações de diacríticos |
| `words_repository_test.php` | 27 | Seleção de palavra (pool vazio, exclusão de não aprovados/muito curtos/muito longos/caracteres não-letra, modo aleatório, determinismo e ciclagem da sequência compartilhada); inserção de palavra manual e por IA; busca, atualização e exclusão de palavra restritas à instância dona; exclusão e aprovação em lote; listagem de palavras recentes com join do nome do glossário; sincronização de glossário (divisão de conceito multi-palavra, filtro de stopwords configuráveis, atualização de dica em re-sincronização sem duplicar, limpeza de órfãos quando uma entrada desaparece, modo `glossaryid = 0` cobrindo todos os glossários do curso) |
| **Subtotal** | **112** | |

#### Testes de Web Services (`tests/external/`)

| Arquivo de teste | Casos | O que é coberto |
|-----------------|------:|----------------|
| `end_round_test.php` | 4 | Desistência termina a rodada; timeout termina a rodada; um valor inválido de `reason` é rejeitado; a capability `mod/playerwords:view` é exigida |
| `new_round_test.php` | 3 | Nova rodada sorteia palavra nova; bloqueado quando o limite de rodadas já foi atingido; a capability `mod/playerwords:view` é exigida |
| `reveal_hint_test.php` | 5 | Dica é revelada; revelar duas vezes é idempotente; rejeitado após a rodada terminar; a capability `mod/playerwords:view` é exigida; saldo insuficiente de item do PlayerHUD bloqueia a revelação |
| `start_round_test.php` | 4 | Cronômetro da rodada inicia; rejeitado quando já iniciado; a capability `mod/playerwords:view` é exigida; saldo insuficiente de item do PlayerHUD bloqueia o início |
| `submit_guess_test.php` | 6 | Um chute errado nunca revela a palavra; um chute correto revela só quando termina; um chute perdedor também revela; a capability `mod/playerwords:view` é exigida; `timeleft` reflete os segundos restantes durante a rodada; `timeleft` fica congelado no momento em que a rodada terminou, não no relógio real |
| **Subtotal** | **22** | |

| **Total Geral** | **160** | |

```bash
vendor/bin/phpunit --testsuite mod_playerwords
```

**Cobertura de linha por classe (PHPUnit + Xdebug):**

| Classe | Cobertura de linha |
|-------|:-------------:|
| `completion\custom_completion` | 100% |
| `external\end_round` | 76% |
| `external\new_round` | 57% |
| `external\reveal_hint` | 59% |
| `external\start_round` | 45% |
| `external\submit_guess` | 39% |
| `local\ai_word_generator` | 26%¹ |
| `local\gameplay_service` | 95% |
| `local\hud_service` | 97% |
| `local\ranking_service` | 75% |
| `local\round_presenter` | 95% |
| `local\round_service` | 60% |
| `local\view_page_service` | 73% |
| `local\word_normalizer` | 100% |
| `local\words_repository` | 80% |
| `privacy\provider` | 94% |
| **Geral** | **62%** |

¹ Subcontado por natureza: os métodos que chamam rede em `ai_word_generator` (`call_ai`, `call_core_ai`, `has_core_ai`) exigem um provedor de IA real e não são testados por unidade de propósito; a lógica de parsing e validação de entrada não confiável da qual eles dependem (`parse_words`, `is_valid_term`) está totalmente coberta.

As classes de web service `external/*` mostram um percentual de linha menor do que sua cobertura real de comportamento sugere: cada uma já é testada quanto ao caminho feliz, toda ramificação de rejeição, a guarda de capability e (quando aplicável) a ramificação de item insuficiente do PlayerHUD — mas um teste de guarda de capability necessariamente para em `require_capability()` e nunca alcança as linhas depois dela, então ele não consegue elevar o percentual de uma classe que é majoritariamente "linhas depois da guarda".

[⬆️ Voltar ao índice](#toc-pt)

---

### 🔐 Segurança e Conformidade

* Controle de acesso por capabilities (`mod/playerwords:view`, `mod/playerwords:addinstance`)
* Proteção com `require_sesskey()` em todas as ações POST; chamadas AJAX são validadas pelo dispatcher `core/ajax` do Moodle
* Validação no servidor dos limites de rodadas e tempo de recarga, sempre recalculados a partir da configuração atual
* Validação de charset do chute — apenas letras Unicode aceitas
* Palavras geradas por IA são tratadas como entrada não confiável: só termos de um único token, alfabéticos e dentro do comprimento configurado são salvos, entrando pendentes de aprovação do professor
* O estado de sessão da rodada é isolado por instância de atividade e por usuário — um id de palavra ou chave de sessão de uma atividade nunca é aceito por outra
* Compatível com a API externa do Moodle
* Privacy API completamente implementada (LGPD/GDPR)

[⬆️ Voltar ao índice](#toc-pt)

---

## 📄 Licença

Este projeto é licenciado sob a **GNU General Public License v3 (GPLv3)**.

**Copyright:** 2026 Jean Lúcio

[⬆️ Voltar ao índice](#toc-pt)
