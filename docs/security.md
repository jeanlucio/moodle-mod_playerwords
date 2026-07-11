# 🔐 Security & Compliance

[English](#security--compliance--english) | [Português](#security--compliance--português)

---

## Security & Compliance — English

* Capability-based access control (`mod/playerwords:view`, `mod/playerwords:addinstance`)
* `require_sesskey()` protection on all POST actions; AJAX calls are validated by Moodle's `core/ajax` dispatcher
* Server-side enforcement of round limits and cooldown, always recomputed from current settings
* Round timeout is re-validated against the server's own deadline (with a small network-latency tolerance) instead of trusting the client's countdown alone
* Guess charset validation — only Unicode letters accepted
* AI-generated words are treated as untrusted input: only single-token, alphabetic terms within the configured length bounds are saved, and they enter pending teacher approval
* Session round state is isolated per activity instance and per user — a word id or session key from one activity is never accepted by another
* Moodle External API compliant
* Privacy API fully implemented (GDPR/LGPD)

[⬆️ Back to top](#security--compliance--english)

---

## Security & Compliance — Português

* Controle de acesso por capabilities (`mod/playerwords:view`, `mod/playerwords:addinstance`)
* Proteção com `require_sesskey()` em todas as ações POST; chamadas AJAX são validadas pelo dispatcher `core/ajax` do Moodle
* Validação no servidor dos limites de rodadas e tempo de recarga, sempre recalculados a partir da configuração atual
* Timeout de rodada é revalidado contra o prazo real do servidor (com pequena tolerância de latência de rede), em vez de confiar apenas no cronômetro do cliente
* Validação de charset do chute — apenas letras Unicode aceitas
* Palavras geradas por IA são tratadas como entrada não confiável: só termos de um único token, alfabéticos e dentro do comprimento configurado são salvos, entrando pendentes de aprovação do professor
* O estado de sessão da rodada é isolado por instância de atividade e por usuário — um id de palavra ou chave de sessão de uma atividade nunca é aceito por outra
* Compatível com a API externa do Moodle
* Privacy API completamente implementada (LGPD/GDPR)

[⬆️ Back to top](#security--compliance--português)
