---
layout: default
title: 🔐 Security & Compliance
parent: Português
nav_order: 9
---

# 🔐 Security & Compliance

* Controle de acesso por capabilities (`mod/playerwords:view`, `mod/playerwords:addinstance`)
* Proteção com `require_sesskey()` em todas as ações POST; chamadas AJAX são validadas pelo dispatcher `core/ajax` do Moodle
* Validação no servidor dos limites de rodadas e tempo de recarga, sempre recalculados a partir da configuração atual
* Timeout de rodada é revalidado contra o prazo real do servidor (com pequena tolerância de latência de rede), em vez de confiar apenas no cronômetro do cliente
* Validação de charset do chute — apenas letras Unicode aceitas
* Palavras geradas por IA são tratadas como entrada não confiável: só termos de um único token, alfabéticos e dentro do comprimento configurado são salvos, entrando pendentes de aprovação do professor
* O estado de sessão da rodada é isolado por instância de atividade e por usuário — um id de palavra ou chave de sessão de uma atividade nunca é aceito por outra
* Compatível com a API externa do Moodle
* Privacy API completamente implementada (LGPD/GDPR)
