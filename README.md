# Moodle Activity PlayerWords

[![Moodle Plugin CI](https://github.com/jeanlucio/moodle-mod_playerwords/actions/workflows/ci.yml/badge.svg)](https://github.com/jeanlucio/moodle-mod_playerwords/actions/workflows/ci.yml)
![Moodle](https://img.shields.io/badge/Moodle-4.5%2B-orange?style=flat-square&logo=moodle&logoColor=white)
![License](https://img.shields.io/badge/License-GPLv3-blue?style=flat-square)
![Status](https://img.shields.io/badge/Status-Alpha-yellow?style=flat-square)
[![PlayerHUD Ecosystem](https://img.shields.io/badge/PlayerHUD-Ecosystem-6f42c1?style=flat-square&logo=gamepad&logoColor=white)](https://github.com/jeanlucio/moodle-block_playerhud)
![Game Activity](https://img.shields.io/badge/Role-Game_Activity-198754?style=flat-square)

[English](#english) | [Português](#português)

---

## English

**PlayerWords** is a word-guessing vocabulary activity for Moodle. Students guess a hidden word letter-by-letter within a configurable number of attempts, receiving colour-coded and symbol feedback on each guess.

The activity integrates with the course **Glossary** (words and definitions are imported automatically) and with the **PlayerHUD** gamification block (items can be required to start a round or to reveal a hint).

---

### ✨ Features

* 🟩 **Word-Guessing Gameplay:** Colour-coded + symbol feedback per letter (correct position, wrong position, absent).
* 📖 **Glossary Integration:** Import concepts from one or all course glossaries as the word pool, with definitions used as hints.
* ✍️ **Manual Word Pool:** Teachers can add, edit, approve, and delete words directly from the management page.
* 🔀 **Word Modes:** Random word per round (default) or shared sequence mode, where every student receives the same words in the same order.
* 💡 **Hidden Hint System:** Hint is hidden by default; students must explicitly reveal it (optionally at an item cost via PlayerHUD).
* 🏳️ **Give Up:** Students can forfeit the current round at any time — the correct word is revealed immediately.
* ⏱️ **Configurable Cooldown:** Minimum wait between rounds (minutes, hours, or days).
* 🔢 **Round Limit:** Teachers can cap the total number of rounds per student (1–10 or unlimited).
* 🔡 **Accent-Insensitive Matching:** Diacritics are always stripped before comparing guess and target.
* 📊 **Grading Methods:** Highest grade, average grade, first attempt, last attempt, or average over all required rounds.
* 📋 **Gradebook Integration:** Grades are written automatically on every round completion.
* ✅ **Custom Completion Rules:** Minimum attempts completed and/or minimum grade reached.
* 🏆 **Ranking Page:** Leaderboard scoped to the activity, with outsider row for students outside the top positions.
* ♿ **Accessibility:** WCAG AA contrast on all grid states; non-colour indicators (✓ correct, ~ present); `aria-label` on every cell.
* ⚡ **AJAX-Powered:** All guess submission and round transitions happen without page reload.
* 🎮 **PlayerHUD Integration (Optional):** Require inventory items to start a round or to reveal a hint.
* 📦 **Backup & Restore:** Full Moodle 2 backup/restore support, including word pool and attempts.
* 🔐 **Privacy API:** GDPR/LGPD compliant — complete data export and deletion for all stored personal data.

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
* Gamified academic courses using the PlayerHUD ecosystem
* Formative assessment and self-study reinforcement
* Engagement reinforcement strategies

---

### 🔗 PlayerHUD Ecosystem

PlayerWords integrates optionally with the PlayerHUD block:

* **PlayerHUD Block (Optional):** Configure item costs for starting a round or revealing a hint.
  👉 https://github.com/jeanlucio/moodle-block_playerhud

---

### 📦 Requirements

| Component | Version |
|-----------|---------|
| Moodle    | 4.5+    |
| PHP       | 8.1+    |

---

### 🛠️ Installation

1. Download the `.zip` file or clone this repository.
2. Extract the folder into your Moodle `mod/` directory.
3. Rename the folder to `playerwords` (if necessary).
   Final path:
   `your-moodle/mod/playerwords/`
4. Visit **Site administration > Notifications** to complete installation.
5. Add a **PlayerWords** activity to any course.

---

### 📖 Usage

1. Add a **PlayerWords** activity to your course.
2. Configure:
   * Word length range and maximum attempts
   * Cooldown between rounds and round limit
   * Word mode (random or shared sequence)
   * Grading method and gradebook settings
   * Glossary source (optional)
   * PlayerHUD item costs (optional, when PlayerHUD block is present)
3. Open the **Manage words** page to add, approve, edit, or delete words.
4. Students play directly from the activity page — guessing, revealing hints, and forfeiting rounds.
5. Grades and ranking update automatically after each round.

---

### 🧪 Automated Tests

PlayerWords ships with a PHPUnit test suite covering business logic, repository queries, and Privacy API compliance. Every CI push runs against the full matrix (Moodle 4.5 → 5.x, PostgreSQL & MariaDB).

#### PHPUnit — Unit & Integration Tests

| Test file | Cases | What is covered |
|-----------|------:|----------------|
| `gameplay_service_test.php` | 4 | Letter feedback algorithm (correct/present/absent); score calculation for win, loss, and decimal grades |
| `hud_service_test.php` | 10 | PlayerHUD block lookup across courses; item name resolution; item list retrieval; consume items (insufficient funds, success, FIFO order, zero-quantity short-circuit) |
| `word_normalizer_test.php` | 1 | Accent-insensitive normalisation across multiple diacritic combinations |
| `words_repository_test.php` | 8 | Word picking (empty pool, unapproved exclusion, too-short/too-long exclusion, random mode, shared sequence determinism, sequence cycling, non-letter char exclusion) |
| `privacy/provider_test.php` | 6 | Metadata declaration; contexts by attempts; contexts by words added; list users in context; delete user data; delete users in context |
| **Total** | **29** | |

```bash
vendor/bin/phpunit --testsuite mod_playerwords
```

---

### 🔐 Security & Compliance

* Capability-based access control (`mod/playerwords:view`, `mod/playerwords:addinstance`)
* `require_sesskey()` protection on all POST actions
* Server-side enforcement of round limits and cooldown
* Guess charset validation — only Unicode letters accepted
* Moodle External API compliant
* Privacy API fully implemented (GDPR/LGPD)

---

## 📄 License / Licença

This project is licensed under the **GNU General Public License v3 (GPLv3)**.

**Copyright:** 2026 Jean Lúcio

---

## Português

O **PlayerWords** é uma atividade de adivinhação de palavras para o Moodle. O aluno adivinha uma palavra oculta letra por letra dentro de um número configurável de tentativas, recebendo feedback visual em cores e símbolos a cada chute.

A atividade integra-se com o **Glossário** do curso (palavras e definições são importadas automaticamente) e com o bloco de gamificação **PlayerHUD** (itens podem ser exigidos para iniciar uma rodada ou revelar uma dica).

---

### ✨ Funcionalidades

* 🟩 **Jogo de adivinhação de palavras:** Feedback por letra com código de cores + símbolos (posição correta, posição errada, ausente).
* 📖 **Integração com Glossário:** Importa conceitos de um ou todos os glossários do curso como pool de palavras, usando as definições como dicas.
* ✍️ **Pool de palavras manual:** O professor pode adicionar, editar, aprovar e excluir palavras diretamente na página de gerenciamento.
* 🔀 **Modos de palavra:** Palavra aleatória por rodada (padrão) ou sequência compartilhada — todos os alunos recebem as mesmas palavras na mesma ordem.
* 💡 **Dica oculta:** A dica é escondida por padrão; o aluno precisa clicar em "Revelar dica" (com custo opcional em itens via PlayerHUD).
* 🏳️ **Desistir:** O aluno pode abandonar a rodada a qualquer momento — a palavra correta é revelada imediatamente.
* ⏱️ **Tempo de recarga configurável:** Intervalo mínimo entre rodadas (minutos, horas ou dias).
* 🔢 **Limite de rodadas:** O professor pode limitar o total de rodadas por aluno (1–10 ou ilimitado).
* 🔡 **Correspondência sem acentos:** Acentuação é sempre ignorada ao comparar chute e palavra-alvo.
* 📊 **Métodos de nota:** Maior nota, média, primeira tentativa, última tentativa ou média sobre todas as rodadas exigidas.
* 📋 **Integração com o livro de notas:** Notas gravadas automaticamente ao final de cada rodada.
* ✅ **Regras de conclusão personalizadas:** Mínimo de tentativas e/ou nota mínima atingida.
* 🏆 **Página de ranking:** Classificação por atividade, com linha de "outsider" para alunos fora das primeiras posições.
* ♿ **Acessibilidade:** Contraste WCAG AA em todos os estados da grade; indicadores não visuais (✓ correto, ~ presente); `aria-label` em cada célula.
* ⚡ **Powered por AJAX:** Envio de chutes e transições de rodada sem recarregar a página.
* 🎮 **Integração com PlayerHUD (Opcional):** Exige itens do inventário para iniciar uma rodada ou revelar a dica.
* 📦 **Backup & Restauração:** Suporte completo ao backup Moodle 2, incluindo pool de palavras e tentativas.
* 🔐 **Privacy API:** Compatível com LGPD/GDPR — exportação e exclusão completas de dados pessoais armazenados.

---

### 🎓 Finalidade Educacional

O PlayerWords foi projetado para:

* Reforçar o aprendizado de conceitos trabalhados no curso ou disciplina
* Fomentar o aprendizado lúdico e baseado em jogos
* Simplificar e tornar mais intuitivos os processos de aprendizagem e avaliação
* Colaborar para o atingimento de objetivos educacionais em cursos e disciplinas
* Fomentar metodologias ativas como a utilização de games na educação e a gamificação
* Apoiar a **prática de recuperação** — o aluno precisa lembrar o conceito antes de ver qualquer resposta, uma das técnicas com maior evidência de eficácia para retenção de longo prazo
* Estimular a **prática espaçada** — o tempo de recarga configurável entre rodadas faz o aluno retornar ao conteúdo em sessões distintas, alinhando-se aos princípios da repetição espaçada

Indicado para:

* Qualquer curso que trabalhe com conceitos expressos em palavras ou termos
* Cursos gamificados com o ecossistema PlayerHUD
* Avaliação formativa e reforço de autoestudo
* Estratégias de reforço de engajamento

---

### 🔗 Ecossistema PlayerHUD

O PlayerWords integra-se opcionalmente ao bloco PlayerHUD:

* **Bloco PlayerHUD (Opcional):** Configure custos em itens para iniciar uma rodada ou revelar a dica.
  👉 https://github.com/jeanlucio/moodle-block_playerhud

---

### 📦 Requisitos

| Componente | Versão |
|------------|--------|
| Moodle     | 4.5+   |
| PHP        | 8.1+   |

---

### 🛠️ Instalação

1. Baixe o arquivo `.zip` ou clone este repositório.
2. Extraia na pasta `mod/` do seu Moodle.
3. Renomeie para `playerwords` (se necessário).
   Caminho final:
   `seu-moodle/mod/playerwords/`
4. Acesse **Administração do site > Notificações** para concluir a instalação.
5. Adicione uma atividade **PlayerWords** a qualquer curso.

---

### 📖 Como Usar

1. Adicione uma atividade **PlayerWords** ao seu curso.
2. Configure:
   - Faixa de comprimento de palavras e máximo de tentativas
   - Tempo de recarga entre rodadas e limite de rodadas
   - Modo de palavras (aleatório ou sequência compartilhada)
   - Método de nota e configurações do livro de notas
   - Fonte de glossário (opcional)
   - Custos em itens do PlayerHUD (opcional, quando o bloco PlayerHUD está presente)
3. Acesse **Gerenciar palavras** para adicionar, aprovar, editar ou excluir palavras.
4. Os alunos jogam diretamente na página da atividade — chutando, revelando dicas e desistindo de rodadas.
5. Notas e ranking são atualizados automaticamente após cada rodada.

---

### 🧪 Testes Automatizados

O PlayerWords inclui uma suíte PHPUnit cobrindo lógica de negócio, consultas ao repositório e conformidade com a Privacy API. Todo push de CI executa a matriz completa (Moodle 4.5 → 5.x, PostgreSQL e MariaDB).

#### PHPUnit — Testes Unitários e de Integração

| Arquivo de teste | Casos | O que é coberto |
|-----------------|------:|----------------|
| `gameplay_service_test.php` | 4 | Algoritmo de feedback por letra (correto/presente/ausente); cálculo de nota para vitória, derrota e notas decimais |
| `hud_service_test.php` | 10 | Localização do bloco PlayerHUD entre cursos; resolução de nome de item; listagem de itens; consumo de itens (fundos insuficientes, sucesso, ordem FIFO, curto-circuito com quantidade zero) |
| `word_normalizer_test.php` | 1 | Normalização sem acentuação em múltiplas combinações de diacríticos |
| `words_repository_test.php` | 8 | Seleção de palavra (pool vazio, exclusão de não aprovados, exclusão por comprimento mínimo/máximo, modo aleatório, determinismo em sequência compartilhada, ciclagem da sequência, exclusão de caracteres não-letras) |
| `privacy/provider_test.php` | 6 | Declaração de metadados; contextos por tentativas; contextos por palavras adicionadas; listar usuários no contexto; excluir dados do usuário; excluir usuários no contexto |
| **Total** | **29** | |

```bash
vendor/bin/phpunit --testsuite mod_playerwords
```

---

### 🔐 Segurança e Conformidade

* Controle de acesso por capabilities (`mod/playerwords:view`, `mod/playerwords:addinstance`)
* Proteção com `require_sesskey()` em todas as ações POST
* Validação no servidor dos limites de rodadas e tempo de recarga
* Validação de charset do chute — apenas letras Unicode aceitas
* Compatível com a API externa do Moodle
* Privacy API completamente implementada (LGPD/GDPR)

---

## 📄 Licença

Este projeto é licenciado sob a **GNU General Public License v3 (GPLv3)**.

**Copyright:** 2026 Jean Lúcio
