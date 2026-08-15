# Moodle Activity PlayerWords

![Moodle](https://img.shields.io/badge/Moodle-4.5%2B-orange?style=flat&logo=moodle&logoColor=white)
![License](https://img.shields.io/badge/License-GPLv3-blue?style=flat)
![Status](https://img.shields.io/badge/Status-Stable-green?style=flat)
[![Latest Release](https://img.shields.io/github/v/release/jeanlucio/moodle-mod_playerwords?style=flat)](https://github.com/jeanlucio/moodle-mod_playerwords/releases)
[![PlayerGames Ecosystem](https://img.shields.io/badge/PlayerGames-Ecosystem-6f42c1?style=flat&logo=gamepad&logoColor=white)](https://jeanlucio.github.io/playergames/)
![Game Activity](https://img.shields.io/badge/Role-Game_Activity-198754?style=flat)
[![Author](https://img.shields.io/badge/by-Jean_Lucio-6f42c1?style=flat)](https://marketplace.moodle.com/user/984)

[![Moodle Plugin CI](https://github.com/jeanlucio/moodle-mod_playerwords/actions/workflows/ci.yml/badge.svg)](https://github.com/jeanlucio/moodle-mod_playerwords/actions/workflows/ci.yml)
[![Last Commit](https://img.shields.io/github/last-commit/jeanlucio/moodle-mod_playerwords?style=flat)](https://github.com/jeanlucio/moodle-mod_playerwords/commits)
[![Open Issues](https://img.shields.io/github/issues/jeanlucio/moodle-mod_playerwords?style=flat)](https://github.com/jeanlucio/moodle-mod_playerwords/issues)

[English](#english) | [Português](#português)

---

## English

**PlayerWords** is a word-guessing vocabulary activity for Moodle. Students guess a hidden word letter-by-letter within a configurable number of attempts, receiving colour-coded and symbol feedback on each guess.

The activity integrates with the course **Glossary** (words and definitions are imported automatically), can generate word candidates through **AI**, and integrates with the **PlayerHUD** gamification block (items can be required to start a round or to reveal a hint, and an item can be granted for each round won).

Designed around **retrieval practice** and **spaced repetition** — well-evidenced techniques for long-term retention — it turns vocabulary review into active recall instead of passive reading.

📚 **[Full documentation](https://jeanlucio.github.io/moodle-mod_playerwords/)** — features, educational purpose, the PlayerGames ecosystem, usage guide, grading & ranking model, the full 390-case test suite, and security details.

### 🔒 Third-party Service Disclosure

AI word generation is **optional** and disabled by default. When used, the activity topic
(never student data) is sent through `local_aihub` (BYOK) or Moodle's `core_ai` subsystem —
PlayerWords never contacts an AI provider directly.

* **Cost:** None required. AI generation is entirely optional; if used, any cost is whatever
  the underlying provider charges through your own `local_aihub` key, or nothing at all via a
  free/institutional `core_ai` provider the site admin may have already configured.
* **API keys:** Not configured in PlayerWords itself. Obtain and configure a personal or site
  key inside `local_aihub` (see its own documentation), or ask your site administrator to
  configure a `core_ai` provider instead.
* **Demo credentials:** Not applicable — no credentials are required to install or use
  PlayerWords; AI generation is entirely opt-in.

Full disclosure:
[Security & Compliance](https://jeanlucio.github.io/moodle-mod_playerwords/#security).

### 📦 Requirements

| Component | Version |
|-----------|---------|
| Moodle    | 4.5+    |
| PHP       | 8.1+    |

### 🛠️ Installation & Configuration

1. Download the `.zip` file or clone this repository.
2. Extract the folder into your Moodle `mod/` directory.
3. Rename the folder to `playerwords` (if necessary).
   Final path:
   `your-moodle/mod/playerwords/`
4. Visit **Site administration > Notifications** to complete installation.
5. Add a **PlayerWords** activity to any course.

This plugin has no separate site-level settings to configure after installation — every
setting is configured by the teacher when adding the activity to a course, as covered in the
[Usage](https://jeanlucio.github.io/moodle-mod_playerwords/#usage) section of the full
documentation.

### 🆘 Support

Found a bug or have a question? Open an issue on the
[issue tracker](https://github.com/jeanlucio/moodle-mod_playerwords/issues).

### 📄 License

This project is licensed under the **GNU General Public License v3 (GPLv3)**.

**Copyright:** 2026 Jean Lúcio

### 👤 Maintainer

Maintained by [Jean Lúcio](https://github.com/jeanlucio).

[⬆️ Back to top](#english)

---

## Português

O **PlayerWords** é uma atividade de adivinhação de palavras para o Moodle. O estudante adivinha uma palavra oculta letra por letra dentro de um número configurável de tentativas, recebendo feedback visual em cores e símbolos a cada chute.

A atividade integra-se com o **Glossário** do curso (palavras e definições são importadas automaticamente), pode gerar candidatas a palavra por **IA**, e integra-se com o bloco de gamificação **PlayerHUD** (itens podem ser exigidos para iniciar uma rodada ou revelar uma dica, e um item pode ser concedido a cada rodada vencida).

Baseado na **prática de recuperação** e na **repetição espaçada** — técnicas com boa evidência de eficácia para retenção de longo prazo — transforma a revisão de vocabulário em recuperação ativa da memória em vez de leitura passiva.

📚 **[Documentação completa](https://jeanlucio.github.io/moodle-mod_playerwords/pt.html)** — funcionalidades, finalidade educacional, ecossistema PlayerGames, guia de uso, modelo de nota e ranking, a suíte completa de 390 testes, e detalhes de segurança.

### 🔒 Divulgação de Serviço de Terceiros

A geração de palavras por IA é **opcional** e vem desativada por padrão. Quando usada, o tema
da atividade (nunca dados de estudante) é enviado através do `local_aihub` (BYOK) ou do
subsistema `core_ai` do Moodle — o PlayerWords nunca contata um provedor de IA diretamente.

* **Custo:** Nenhum é exigido. A geração por IA é totalmente opcional; se usada, qualquer custo
  é o que o provedor cobrar através da sua própria chave no `local_aihub`, ou nenhum custo via
  um provedor `core_ai` gratuito/institucional que o administrador do site já tenha configurado.
* **Chaves de API:** Não são configuradas no PlayerWords. Obtenha e configure uma chave pessoal
  ou do site dentro do `local_aihub` (veja a documentação própria dele), ou peça ao
  administrador do site para configurar um provedor `core_ai`.
* **Credenciais de demonstração:** Não aplicável — nenhuma credencial é exigida para instalar ou
  usar o PlayerWords; a geração por IA é totalmente opcional.

Divulgação completa:
[Segurança e Conformidade](https://jeanlucio.github.io/moodle-mod_playerwords/pt.html#security).

### 📦 Requisitos

| Componente | Versão |
|------------|--------|
| Moodle     | 4.5+   |
| PHP        | 8.1+   |

### 🛠️ Instalação e Configuração

1. Baixe o arquivo `.zip` ou clone este repositório.
2. Extraia na pasta `mod/` do seu Moodle.
3. Renomeie para `playerwords` (se necessário).
   Caminho final:
   `seu-moodle/mod/playerwords/`
4. Acesse **Administração do site > Notificações** para concluir a instalação.
5. Adicione uma atividade **PlayerWords** a qualquer curso.

Este plugin não tem configurações separadas em nível de site após a instalação — toda
configuração é feita pelo professor ao adicionar a atividade a um curso, conforme explicado na
seção [Como Usar](https://jeanlucio.github.io/moodle-mod_playerwords/pt.html#usage) da
documentação completa.

### 🆘 Suporte

Encontrou um bug ou tem alguma dúvida? Abra uma issue no
[rastreador de issues](https://github.com/jeanlucio/moodle-mod_playerwords/issues).

### 📄 Licença

Este projeto é licenciado sob a **GNU General Public License v3 (GPLv3)**.

**Copyright:** 2026 Jean Lúcio

### 👤 Mantenedor

Mantido por [Jean Lúcio](https://github.com/jeanlucio).

[⬆️ Voltar ao topo](#português)
