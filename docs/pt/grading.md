---
layout: default
title: 🧮 Grading & Ranking
parent: Português
nav_order: 7
---

O PlayerWords calcula uma **nota** e um total de **ranking** a partir das mesmas rodadas
terminadas, mas os dois são configurados de forma totalmente independente — o professor pode
manter a nota simples e ainda assim recompensar jogadas eficientes no ranking, ou o contrário.

**Os dois são totalmente opcionais, e cada um liga/desliga por conta própria:**

* **Nota:** deixe o campo padrão `Nota` como *Nenhuma* pra rodar a atividade sem avaliação
  nenhuma — nenhuma nota é calculada ou gravada no livro de notas, e as configurações `Método de
  avaliação` / `Pontuação da nota` somem do formulário.
* **Ranking:** deixe `Mostrar ranking` como *Não* pra esconder o ranking em todo lugar — no jogo,
  na página dedicada de ranking, e na coluna extra do registro de tentativas — e a configuração
  `Pontuação do ranking` some do formulário também.

Desligar um nunca afeta o outro: uma atividade pode ter nota sem ranking, ranking sem nota, os
dois, ou nenhum dos dois.

**A pontuação por rodada** decide quanto vale uma única rodada, escolhida separadamente para a
nota e para o ranking (configurações `Pontuação da nota` / `Pontuação do ranking`, ambas com
padrão **Binária**):

| Modo | Uma rodada vencida vale... | Uma rodada perdida, desistida ou com tempo esgotado |
|---|---|---|
| **Binária** (padrão) | A nota cheia da atividade | Zero |
| **Linear** | Nota cheia nas duas primeiras tentativas, depois uma fração proporcional às tentativas poupadas: `nota × (max_attempts − tentativas_usadas + 1) / (max_attempts − 1)` | Zero |

O modo linear dá nota cheia nas duas primeiras tentativas — um segundo palpite certeiro não é
tratado como menos merecedor que um acerto de primeira, já que este é um jogo educativo não
punitivo, não uma disputa de sorte — e só a partir daí distribui as tentativas restantes
proporcionalmente, nunca zerando totalmente uma vitória: até vencer na última tentativa permitida
ainda rende uma fração positiva. Exemplo com nota máxima 100 e 6 tentativas:

| Tentativas usadas | Pontos (linear) |
|---:|---:|
| 1 | 100,00 |
| 2 | 100,00 |
| 3 | 80,00 |
| 4 | 60,00 |
| 5 | 40,00 |
| 6 | 20,00 |
| Não completou | 0,00 |

Com `Máximo de tentativas` igual a 2 ou menos, o modo Linear fica numericamente idêntico ao
Binário — toda tentativa permitida já cai dentro do platô de nota cheia.

**Combinar várias rodadas numa nota final** é uma configuração separada, `Método de avaliação`
(maior nota, média, primeira tentativa, última tentativa ou média sobre todas as rodadas
exigidas). Funciona igual independente de a pontuação por rodada acima ser Binária ou Linear: só
agrega o valor que cada rodada já registrou.

**O ranking** é a soma dos pontos de ranking de todas as rodadas terminadas de um estudante
(`SUM`), ordenado do maior para o menor; empates são desfeitos por menos tentativas usadas em
média, depois menos tempo gasto em média. Só aparece quando o professor liga "Mostrar ranking", e
nunca revela uma rodada ainda em andamento.

**Só os 5 primeiros aparecem — de propósito, não é bug:** tanto o mini-ranking dentro do jogo
quanto a página dedicada limitam a lista a 5 linhas, pra evitar expor o ranking da turma inteira
publicamente. Um estudante mais abaixo continua vendo exatamente onde está: uma linha extra,
separada por "…", mostra sua posição e pontuação reais, sem revelar a colocação de mais ninguém
abaixo do 5º lugar. Quem pode gerenciar a atividade (professor editor, gerente) nunca aparece no
ranking, mesmo que jogue a atividade — a mesma regra que exclui suas próprias tentativas do
registro de tentativas abaixo.

**"Mostrar ranking" só controla a exibição, não a coleta de dados:** os pontos de ranking são
calculados e gravados em toda rodada terminada, esteja a configuração ligada ou desligada naquele
momento. Ligá-la depois que os estudantes já jogaram revela o total acumulado desde o início da
atividade, não só os pontos ganhos a partir da mudança — nada se perde, e não é preciso
"recuperar" nada ao desligar e ligar de novo.

**Trava ao registrar a nota:** assim que a atividade registra uma nota real para qualquer
estudante, `Máximo de tentativas`, `Pontuação da nota` e `Pontuação do ranking` travam — do mesmo
jeito que o Moodle já trava o campo "Nota máxima" de uma atividade avaliada assim que existem
notas reais. Isso garante que toda rodada já registrada para aquela atividade foi pontuada sob
exatamente as mesmas regras, então a nota e o total do ranking permanecem consistentes durante
toda a vida da atividade.

**Registro de tentativas:** cada estudante pode conferir suas próprias rodadas passadas —
palavra, tentativas usadas, tempo, nota da rodada e (quando o ranking está ligado) pontos no
ranking — pela página de registro de tentativas do toolbar. Quem pode gerenciar a atividade vê
essa mesma página virar um relatório de todos os estudantes: uma tabela só, 30 linhas por página,
ordenável clicando em qualquer cabeçalho de coluna, e filtrável para um único estudante. Assim
como no ranking, nunca inclui as próprias tentativas de quem gerencia, mesmo que essa pessoa
tenha jogado a atividade.
