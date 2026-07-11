# ✨ Features

[English](#features-english) | [Português](#features-português)

---

## Features — English

* 🟩 **Word-Guessing Gameplay:** Colour-coded + symbol feedback per letter (correct position, wrong position, absent).
* 📖 **Glossary Integration:** Import concepts from one or all course glossaries as the word pool, with definitions used as hints.
* 🤖 **AI Word Generation (Optional):** Generate candidate words and hints for a given topic via `local_aihub` (BYOK) or Moodle's `core_ai` fallback. Generated words are treated as untrusted input — only single-token, purely alphabetic terms within the configured length bounds are saved, and they enter the pool pending teacher approval.
* ✍️ **Manual Word Pool:** Teachers can add, edit, approve, and delete words directly from the management page.
* 🔀 **Word Modes:** Random word per round (default) or shared sequence mode, where every student receives the same words in the same order.
* 🎲 **Word Rotation:** In random mode, the same word never repeats on the very next round for a student, unless it is the only word left in the pool.
* 🚫 **Duplicate Prevention:** The same word text can only exist once per activity pool, regardless of which source added it — a manual entry blocks a colliding glossary import (and vice versa), so random selection is never skewed toward one accidentally-duplicated word.
* 💡 **Hidden Hint System:** Hint is hidden by default; students must explicitly reveal it (optionally at an item cost via PlayerHUD).
* 🏳️ **Give Up:** Students can forfeit the current round at any time — the correct word is revealed immediately.
* ⏱️ **Configurable Cooldown:** Minimum wait between rounds (minutes, hours, or days), always recomputed from the activity's current setting — a teacher's change applies immediately, even to a cooldown already in progress.
* 🔢 **Round Limit:** Teachers can cap the total number of rounds per student (1–10 or unlimited). Students see a rounds-played counter (e.g. "3 / 10" or "3 / ∞") both in the lobby and after each round.
* 🛡️ **Round-Limit Integrity:** A round abandoned mid-play (closed tab, lost session) still counts against the round limit — reserved the moment it starts, not only once it finishes, so it can never grant a free re-roll.
* 🔡 **Accent-Insensitive Matching:** Diacritics are always stripped before comparing guess and target.
* 📊 **Grading Methods:** Highest grade, average grade, first attempt, last attempt, or average over all required rounds.
* ⚖️ **Configurable Scoring Mode:** Choose Binary (all-or-nothing) or Linear (proportional to attempts spared) independently for the grade and for the ranking — see [Grading & Ranking](grading.html). Locked once the activity has recorded a real grade, so every round is guaranteed to be scored under the same rules.
* 🧮 **Grading Transparency:** Students see the active grading method before playing and their live computed grade after each round, the same way mod_quiz communicates its own grading method.
* 📋 **Gradebook Integration:** Grades are written automatically on every round completion.
* ✅ **Custom Completion Rule:** Minimum number of attempts completed, evaluated and applied immediately after each round.
* 🔄 **Course Reset Support:** "Reset course" clears student attempts and resets grades for the activity, scoped to the target course only.
* 🏆 **Top 5 Ranking:** Leaderboard scoped to the activity, deliberately capped to the top 5 — never a public ranking of the whole class — with an outsider row so a lower-ranked student still sees their own real position. Respects `SEPARATEGROUPS`.
* 📋 **Attempt History:** Students can review every finished round of their own — word, attempts used, time, score, and date — plus their currently computed grade, at any time via the toolbar. Whoever can manage the activity sees every student's history instead, in one paginated, sortable, per-student-filterable report.
* ❓ **In-Game Help:** A dedicated help page explains the letter-feedback colours, attempts, hints, timer, and the activity's grading method.
* ♿ **Accessibility:** WCAG AA contrast on all grid states; non-colour indicators (✓ correct, ~ present); `aria-label` on every cell; a live region announces state changes for screen readers.
* ⚡ **AJAX-Powered:** Every round transition (guess, hint, forfeit, timeout, start, new round) happens without a page reload.
* 🎮 **PlayerHUD Integration (Optional):** Require inventory items to start a round or to reveal a hint, with atomic FIFO consumption. The student's current balance against the required quantity is always shown up front, and the action is disabled — not just rejected after the click — when they can't afford it; a cost pointing at a deleted or another course's item is waived rather than locking the student out. Can also **grant** an item for each round won; matching PlayerHUD's own anti-farming rule, no XP is awarded from that item while the activity allows unlimited rounds — the item is still delivered, just without XP — and the potential win-grant XP is reflected in PlayerHUD's own "Total XP in the game" ceiling estimate.
* 🛡️ **Safe Cross-Course Integration:** Every PlayerHUD item reference is validated against the course's own block instance, never a stale or another course's item — even after backup/restore or course duplication. Settings preserve a disabled or deleted item as a clearly labelled option instead of silently resetting the field.
* 📦 **Backup & Restore:** Full Moodle 2 backup/restore support, including the "Duplicate activity" action, word pool, attempts, user/glossary id remapping, and safe PlayerHUD item remapping (dropped rather than kept pointing at another course's item when it isn't part of the same restore).
* 🔐 **Privacy API:** GDPR/LGPD compliant — complete data export and deletion for all stored personal data.

[⬆️ Back to top](#features-english)

---

## Features — Português

* 🟩 **Jogo de adivinhação de palavras:** Feedback por letra com código de cores + símbolos (posição correta, posição errada, ausente).
* 📖 **Integração com Glossário:** Importa conceitos de um ou todos os glossários do curso como pool de palavras, usando as definições como dicas.
* 🤖 **Geração de palavras por IA (Opcional):** Gera candidatas a palavra e dica para um tópico livre via `local_aihub` (chave própria) ou fallback para o `core_ai` do Moodle. A resposta é tratada como entrada não confiável — só termos de um único token, puramente alfabéticos e dentro do comprimento configurado são salvos, e entram no pool pendentes de aprovação do professor.
* ✍️ **Pool de palavras manual:** O professor pode adicionar, editar, aprovar e excluir palavras diretamente na página de gerenciamento.
* 🔀 **Modos de palavra:** Palavra aleatória por rodada (padrão) ou sequência compartilhada — todos os estudantes recebem as mesmas palavras na mesma ordem.
* 🎲 **Rotação de palavras:** No modo aleatório, a mesma palavra nunca se repete na rodada seguinte para um estudante, a menos que seja a única palavra restante no pool.
* 🚫 **Prevenção de duplicatas:** O mesmo texto de palavra só pode existir uma vez no pool da atividade, não importa qual fonte a adicionou — uma palavra manual bloqueia uma importação do glossário que colida com ela (e vice-versa), então o sorteio nunca fica enviesado por uma palavra duplicada sem querer.
* 💡 **Dica oculta:** A dica é escondida por padrão; o estudante precisa clicar em "Revelar dica" (com custo opcional em itens via PlayerHUD).
* 🏳️ **Desistir:** O estudante pode abandonar a rodada a qualquer momento — a palavra correta é revelada imediatamente.
* ⏱️ **Tempo de recarga configurável:** Intervalo mínimo entre rodadas (minutos, horas ou dias), sempre recalculado a partir da configuração atual da atividade — uma mudança do professor vale imediatamente, mesmo para quem já está em cooldown.
* 🔢 **Limite de rodadas:** O professor pode limitar o total de rodadas por estudante (1–10 ou ilimitado). O estudante vê um contador de rodadas jogadas (ex.: "3 / 10" ou "3 / ∞") no lobby e após cada rodada.
* 🛡️ **Integridade do limite de rodadas:** Uma rodada abandonada no meio (aba fechada, sessão perdida) continua contando para o limite — reservada assim que começa, não só quando termina, então nunca dá um reroll de graça.
* 🔡 **Correspondência sem acentos:** Acentuação é sempre ignorada ao comparar chute e palavra-alvo.
* 📊 **Métodos de nota:** Maior nota, média, primeira tentativa, última tentativa ou média sobre todas as rodadas exigidas.
* ⚖️ **Modo de pontuação configurável:** Escolha Binária (tudo ou nada) ou Linear (proporcional às tentativas poupadas) de forma independente para a nota e para o ranking — veja [Nota e Ranking](grading.html). Trava assim que a atividade registra uma nota real, garantindo que toda rodada seja pontuada sob as mesmas regras.
* 🧮 **Transparência de avaliação:** O estudante vê o método de avaliação ativo antes de jogar e sua nota atual computada após cada rodada, do mesmo jeito que o Quiz do Moodle comunica seu método de avaliação.
* 📋 **Integração com o livro de notas:** Notas gravadas automaticamente ao final de cada rodada.
* ✅ **Regra de conclusão personalizada:** Número mínimo de tentativas realizadas, avaliada e aplicada imediatamente após cada rodada.
* 🔄 **Suporte a "Redefinir curso":** Limpa as tentativas dos estudantes e reseta as notas da atividade, restrito ao curso alvo.
* 🏆 **Ranking Top 5:** Classificação por atividade, limitada de propósito aos 5 primeiros — nunca um ranking público da turma inteira — com uma linha extra ("outsider") pra um estudante mais abaixo ver sua posição real. Respeita `SEPARATEGROUPS`.
* 📋 **Registro de tentativas:** O estudante pode conferir cada rodada já concluída — palavra, tentativas usadas, tempo, nota e data — além da sua nota atual computada, a qualquer momento pelo toolbar. Quem pode gerenciar a atividade vê o registro de todos os estudantes em vez do próprio, numa tabela paginada, ordenável e filtrável por estudante.
* ❓ **Ajuda no jogo:** Uma página de ajuda dedicada explica as cores do feedback das letras, tentativas, dicas, temporizador e o método de avaliação da atividade.
* ♿ **Acessibilidade:** Contraste WCAG AA em todos os estados da grade; indicadores não visuais (✓ correto, ~ presente); `aria-label` em cada célula; região viva anuncia mudanças de estado para leitor de tela.
* ⚡ **Powered por AJAX:** Toda transição de rodada (chute, dica, desistência, timeout, iniciar, nova rodada) acontece sem recarregar a página.
* 🎮 **Integração com PlayerHUD (Opcional):** Exige itens do inventário para iniciar uma rodada ou revelar a dica, com consumo atômico em ordem FIFO. O saldo atual do estudante em relação à quantidade exigida é sempre mostrado antes da ação, e o botão fica desabilitado — não só rejeitado depois do clique — quando falta item; um custo que aponta pra um item excluído ou de outro curso é dispensado em vez de travar o estudante. Também pode **conceder** um item a cada rodada vencida; seguindo a mesma regra antifarm do próprio PlayerHUD, nenhum XP é concedido por esse item enquanto a atividade permitir rodadas ilimitadas — o item continua sendo entregue, só sem XP — e o XP potencial dessa concessão é refletido no "Total XP no jogo" do próprio PlayerHUD.
* 🛡️ **Integração segura entre cursos:** Toda referência a item do PlayerHUD é validada contra a instância do bloco do próprio curso, nunca um item obsoleto ou de outro curso — mesmo depois de backup/restauração ou duplicação de curso. As configurações preservam um item desabilitado ou excluído como uma opção claramente rotulada, em vez de zerar o campo silenciosamente.
* 📦 **Backup & Restauração:** Suporte completo ao backup Moodle 2, incluindo a ação "Duplicar atividade", pool de palavras, tentativas, remapeamento de ids de usuário/glossário, e remapeamento seguro de itens do PlayerHUD (descartado, em vez de mantido apontando pro item de outro curso, quando não faz parte da mesma restauração).
* 🔐 **Privacy API:** Compatível com LGPD/GDPR — exportação e exclusão completas de dados pessoais armazenados.

[⬆️ Back to top](#features-português)
