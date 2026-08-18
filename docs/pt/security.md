# 🔐 Segurança e Conformidade

* Controle de acesso por capabilities (`mod/playerwords:view`, `mod/playerwords:addinstance`)
* Proteção com `require_sesskey()` em todas as ações POST; chamadas AJAX são validadas pelo dispatcher `core/ajax` do Moodle
* Validação no servidor dos limites de rodadas e tempo de recarga, sempre recalculados a partir da configuração atual
* Timeout de rodada é revalidado contra o prazo real do servidor (com pequena tolerância de latência de rede), em vez de confiar apenas no cronômetro do cliente
* Validação de charset do chute — apenas letras Unicode aceitas
* Palavras geradas por IA são tratadas como entrada não confiável: só termos de um único token, alfabéticos e dentro do comprimento configurado são salvos, entrando pendentes de aprovação do professor
* O estado de sessão da rodada é isolado por instância de atividade e por usuário — um id de palavra ou chave de sessão de uma atividade nunca é aceito por outra
* Um palpite errado nunca vaza a palavra correta nem sua definição; a palavra só é revelada quando a rodada realmente termina
* Compatível com a API externa do Moodle
* Privacy API completamente implementada (LGPD/GDPR)

## 🔒 Divulgação de Serviço de Terceiros

A geração de palavras por IA é **opcional** e vem desativada por padrão. Quando um professor a
usa, o tema da atividade (nunca dados de estudante ou registros de tentativa) é enviado através
do [AI Hub](#aihub) (`local_aihub`) — usando a chave própria (BYOK) do usuário ou do site,
se o plugin estiver instalado — ou, como alternativa, através do subsistema de IA nativo do
Moodle (`core_ai`), que roteia para o provedor configurado pelo administrador do site. O
PlayerWords nunca contata um provedor de IA diretamente; a requisição e sua
divulgação/consentimento são de responsabilidade exclusiva do `local_aihub` ou do `core_ai`. Se
nenhum dos dois estiver instalado ou configurado, a fonte de palavras por IA fica indisponível e
todas as outras funcionalidades continuam funcionando normalmente.

* **Custo:** Nenhum é exigido pelo próprio PlayerWords. Se usada, qualquer custo é o que o
  provedor cobrar através de uma chave BYOK no `local_aihub`, ou nenhum custo via um provedor
  `core_ai` gratuito/institucional que o administrador do site já tenha configurado.
* **Chaves de API / credenciais:** Não são configuradas no PlayerWords. Obtenha e configure uma
  chave pessoal ou do site dentro do [AI Hub](#aihub) (`local_aihub`), ou peça ao
  administrador do site para configurar um provedor `core_ai`.
* **Credenciais de demonstração:** Não aplicável — nenhuma credencial é exigida para instalar ou
  usar o PlayerWords; a geração por IA é totalmente opcional.
