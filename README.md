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

The activity integrates with the course **Glossary** (words and definitions are imported automatically), can generate word candidates through **AI**, and integrates with the **PlayerHUD** gamification block (items can be required to start a round or to reveal a hint, and an item can be granted for each round won).

Designed around **retrieval practice** and **spaced repetition** — well-evidenced techniques for long-term retention — it turns vocabulary review into active recall instead of passive reading.

📚 **[Full documentation](https://jeanlucio.github.io/moodle-mod_playerwords/)** — features, educational purpose, the PlayerGames ecosystem, usage guide, grading & ranking model, the full 256-case test suite, and security details.

### 🔒 Third-party Service Disclosure

AI word generation is **optional** and disabled by default. When used, the activity topic
(never student data) is sent through `local_aihub` (BYOK) or Moodle's `core_ai` subsystem —
PlayerWords never contacts an AI provider directly. Full disclosure:
[Security & Compliance](https://jeanlucio.github.io/moodle-mod_playerwords/#security).

### 📦 Requirements

| Component | Version |
|-----------|---------|
| Moodle    | 4.5+    |
| PHP       | 8.1+    |

### 🛠️ Installation

1. Download the `.zip` file or clone this repository.
2. Extract the folder into your Moodle `mod/` directory.
3. Rename the folder to `playerwords` (if necessary).
   Final path:
   `your-moodle/mod/playerwords/`
4. Visit **Site administration > Notifications** to complete installation.
5. Add a **PlayerWords** activity to any course.

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

📚 **[Documentação completa](https://jeanlucio.github.io/moodle-mod_playerwords/pt.html)** — funcionalidades, finalidade educacional, ecossistema PlayerGames, guia de uso, modelo de nota e ranking, a suíte completa de 256 testes, e detalhes de segurança.

### 🔒 Divulgação de Serviço de Terceiros

A geração de palavras por IA é **opcional** e vem desativada por padrão. Quando usada, o tema
da atividade (nunca dados de estudante) é enviado através do `local_aihub` (BYOK) ou do
subsistema `core_ai` do Moodle — o PlayerWords nunca contata um provedor de IA diretamente.
Divulgação completa:
[Segurança e Conformidade](https://jeanlucio.github.io/moodle-mod_playerwords/pt.html#security).

### 📦 Requisitos

| Componente | Versão |
|------------|--------|
| Moodle     | 4.5+   |
| PHP        | 8.1+   |

### 🛠️ Instalação

1. Baixe o arquivo `.zip` ou clone este repositório.
2. Extraia na pasta `mod/` do seu Moodle.
3. Renomeie para `playerwords` (se necessário).
   Caminho final:
   `seu-moodle/mod/playerwords/`
4. Acesse **Administração do site > Notificações** para concluir a instalação.
5. Adicione uma atividade **PlayerWords** a qualquer curso.

### 🆘 Suporte

Encontrou um bug ou tem alguma dúvida? Abra uma issue no
[rastreador de issues](https://github.com/jeanlucio/moodle-mod_playerwords/issues).

### 📄 Licença

Este projeto é licenciado sob a **GNU General Public License v3 (GPLv3)**.

**Copyright:** 2026 Jean Lúcio

### 👤 Mantenedor

Mantido por [Jean Lúcio](https://github.com/jeanlucio).

[⬆️ Voltar ao topo](#português)
