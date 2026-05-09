# Escopo do Projeto — mod_playerwords (PlayerWords)

## 1. Contextualização

O PlayerWords é um plugin de atividade para o Moodle (`mod_playerwords`) que une gamificação
à revisão de conteúdo acadêmico. Inspirado na mecânica do Wordle, desafia estudantes a
adivinharem termos e conceitos-chave do curso.

Integra a suíte de gamificação Player (PlayerHUD, PlayerGames, PlayerRaid, PlayerPuzzle),
compartilhando identidade visual e retroalimentando o ecossistema com pontos, rankings e
status de conclusão.

---

## 2. Estado atual (v0.8.0)

### Implementado

**Núcleo do jogo**
- Grid estilo Wordle com feedback por célula (correto / presente / ausente)
- Tentativas configuráveis, comprimento mín/máx de palavra, temporizador opcional
- Aceitar palavras sem acento (matching insensível a diacríticos)
- Modo de seleção de palavras: aleatório por rodada ou sequência compartilhada
  (todos os alunos recebem as mesmas palavras na mesma ordem por número de rodada)
- Revelação da palavra correta + definição ao final de cada rodada (vitória ou derrota)
- Dica oculta por padrão; botão "Revelar dica" sob demanda (preparado para custo em item PlayerHUD)

**Configurações do professor**
- `max_rounds` — limite de rodadas por aluno (1–10 ou ilimitado)
- `cooldown_seconds` — intervalo entre rodadas (minutos / horas / dias)
- `wordmode` — aleatório ou sequência compartilhada
- `grademethod` — nota mais alta, média, primeira ou última tentativa

**Gradebook**
- Integração completa: acertar = nota cheia; errar = 0
- Grade item criado/atualizado/deletado junto com a atividade

**Infraestrutura Moodle**
- Web Services AJAX (`submit_guess`, `start_new_round`)
- Eventos Moodle (`course_module_viewed`, `round_started`, `round_completed`)
- Regras de conclusão customizadas (mínimo de tentativas)
- Ícone branded (SVG com cor preservada)

**Acessibilidade**
- Contraste WCAG AA em todas as células (incluindo cinza ausente: 7.0:1)
- Indicadores visuais não-dependentes de cor: ✓ no correto, ~ no presente
- `aria-label` em cada célula descrevendo letra e estado para leitores de tela

**Gerenciamento de palavras**
- Interface do professor para inserção manual com dica/definição opcional
- Fonte manual: aprovação automática

### Pendente

| Funcionalidade | Prioridade |
|---|---|
| Fonte: Glossário (importar termos do `mod_glossary` do curso) | Alta |
| Fonte: IA via `local_playergames` (dependência opcional) | Média |
| Ranking / Leaderboard | Média |
| Teclado virtual (AMD / mobile) | Baixa |
| Timer visual no frontend | Baixa |
| Privacy API (`classes/privacy/provider.php`) | Alta (LGPD) |
| Backup/Restore com mapeamento de IDs | Média |

---

## 3. Motor de Captação de Palavras

As fontes funcionam individualmente ou em conjunto (ex: Glossário + Manual no mesmo banco).

| Fonte | Descrição | Estado |
|---|---|---|
| Manual | Professor cadastra palavras e dicas diretamente na atividade | ✅ Implementado |
| Glossário | Importa termos e definições do módulo nativo de Glossário do curso | ❌ Pendente |
| IA | Geração via `local_playergames\cartridge\ai_generator` (dependência opcional) | ❌ Pendente |

**Fluxo da fonte Glossário:**
1. Professor vincula um Glossário do curso à atividade
2. PlayerWords lê as entradas aprovadas e importa para `playerwords_words`
3. A definição do Glossário é gravada no campo `hint` (exibida pós-rodada)

**Fluxo da fonte IA:**
1. PlayerWords verifica em runtime se `local_playergames\cartridge\ai_generator` existe
2. Se sim: exibe botão "Gerar com IA" na tela de gerenciamento de palavras
3. O gerador produz `{ term, definition }` e salva diretamente em `playerwords_words`
4. Professor revisa e aprova antes de as palavras entrarem no jogo

---

## 4. Dinâmica do Jogo

- **Modos de seleção:** aleatório por rodada (padrão) ou sequência compartilhada por atividade
- **Sequência compartilhada:** índice determinístico por `crc32(instanceid + wordid)`, cicla
  silenciosamente quando o banco de palavras se esgota
- **Linhas do grid:** configurável via `max_attempts`
- **Temporizador:** limite de tempo opcional por rodada (`timer_seconds`)
- **Diacríticos:** toggle para aceitar "nocao" como "noção"
- **Limites de tamanho:** comprimento mínimo e máximo das palavras sorteadas
- **Cooldown:** intervalo mínimo entre rodadas (configurable em minutos/horas/dias)
- **Máximo de rodadas:** limite por aluno (ou ilimitado)

---

## 5. Interface e UX

- **Teclado virtual interativo:** otimizado para mobile ❌ Pendente
- **Paleta acessível:** cinza escuro (ausente) / amarelo (posição incorreta) / azul (posição correta) ✅
- **Indicadores não-cromáticos:** ✓ no correto, ~ no presente ✅
- **Dica sob demanda:** botão "Revelar dica" (preparado para custo PlayerHUD) ✅
- **Revelação pós-rodada:** palavra correta + definição exibidas após vitória ou derrota ✅
- **Ranking (Leaderboard):** exibição opcional ao final do jogo ❌ Pendente

---

## 6. Segurança

- **Palavra nunca no cliente:** a palavra-alvo fica apenas no `$SESSION` server-side;
  o template nunca recebe o texto da palavra durante o jogo ✅
- **Dica nunca no HTML até ser revelada:** `hintvalue` só é incluído no HTML após
  `hintrevealed = true` na sessão ✅
- **Definição pós-rodada oculta:** o bloco `{{#showdefinition}}` só é renderizado
  quando `roundfinished = true` (Mustache server-side) ✅
- **Validação server-side:** `require_capability()` em todos os Web Services ✅
- **CSRF:** `require_sesskey()` em todos os POSTs destrutivos ✅

---

## 7. Dependências

| Plugin | Tipo | Quando |
|---|---|---|
| `local_playergames` | **Opcional** (runtime check) | Habilita botão "Gerar com IA" se instalado |
| `mod_glossary` | **Opcional** (runtime check) | Habilita importação do Glossário se presente no curso |
| `block_playerhud` | **Opcional** (futuro) | Custo de item para revelar dica; disparo de XP |

Verificação em runtime (sem dependência declarada em `version.php`):
```php
if (class_exists('local_playergames\cartridge\ai_generator')) {
    // exibe opção de geração com IA
}
```

---

## 8. Banco de Dados

```
mdl_playerwords
  id, course, name, intro, introformat,
  sources (bitmask: 1=manual | 2=glossary | 4=ai),
  aigranularity (course | section | forum),
  min_length, max_length, max_attempts, timer_seconds,
  ignore_accents, show_ranking,
  wordmode (1=random | 2=shared),
  max_rounds, cooldown_seconds,
  completionattempts,
  grade, gradepass, grademethod (1=highest | 2=avg | 3=first | 4=last),
  timecreated, timemodified

mdl_playerwords_words
  id, playerwordsid, word, hint, source (manual|glossary|ai),
  approved, timecreated, addedby

mdl_playerwords_attempts
  id, playerwordsid, userid, wordid,
  attempts_used, time_used, completed, score, timecreated
```

---

## 9. Estrutura de Diretórios

```
mod/playerwords/
├── version.php                              ✅ v0.8.0
├── index.php                                ✅
├── view.php                                 ✅
├── managewords.php                          ✅
├── lib.php                                  ✅
├── mod_form.php                             ✅
├── styles.css                               ✅ Acessível, WCAG AA
├── CHANGES.md                               ✅
├── SCOPE.md                                 ✅
├── db/
│   ├── install.xml                          ✅
│   ├── upgrade.php                          ✅ até 2026050808
│   ├── access.php                           ✅
│   ├── services.php                         ✅
│   └── events.php                           ✅
├── classes/
│   ├── external/
│   │   ├── submit_guess.php                 ✅
│   │   └── start_new_round.php              ✅
│   ├── local/
│   │   ├── gameplay_service.php             ✅
│   │   ├── view_page_service.php            ✅
│   │   ├── word_normalizer.php              ✅
│   │   └── words_repository.php            ✅
│   ├── event/
│   │   ├── course_module_viewed.php         ✅
│   │   ├── course_module_instance_list_viewed.php ✅
│   │   ├── round_started.php                ✅
│   │   └── round_completed.php              ✅
│   └── privacy/
│       └── provider.php                     ❌ Pendente (LGPD)
├── backup/moodle2/                          ❌ Pendente
├── lang/
│   ├── en/playerwords.php                   ✅
│   └── pt_br/playerwords.php                ✅
├── templates/
│   ├── game.mustache                        ✅
│   └── managewords.mustache                 ✅
├── pix/
│   └── icon.svg                             ✅
└── amd/
    ├── src/game.js                          ❌ Pendente
    └── build/                               ❌ Pendente
```

---

## 10. Decisões de Arquitetura

| Decisão | Motivo |
|---|---|
| Estado de jogo em `$SESSION` server-side | Palavra-alvo nunca trafega para o cliente |
| Dica só enviada ao HTML após `hintrevealed` | Preparação para custo PlayerHUD; previne uso como trapaça |
| Definição pós-rodada via Mustache server-side | `{{#showdefinition}}` só renderizado quando `roundfinished`; invisível durante o jogo |
| IA como dependência **opcional** via `class_exists()` | Professor instala só o que precisa; PlayerWords funciona sem IA |
| IA grava diretamente em `playerwords_words` | Fluxo mais simples quando professor não usa Glossário |
| Glossário como fonte independente da IA | Professor pode importar de glossário existente sem precisar do `local_playergames` |
| `local_playergames` como HUB de IA | Lógica de geração centralizada; outros plugins reutilizam sem duplicar código |
| Sequência compartilhada via `crc32` | Determinístico por atividade, sem armazenar a ordem no banco |
| `classes/local/` em vez de `locallib.php` | Autoloading Moodle via namespace; evita monólito |
| Uma classe por função externa em `classes/external/` | Padrão Moodle moderno; responsabilidade única |
| Constantes com `define()` em vez de `const` | Compatibilidade com escopo global fora de namespace |
