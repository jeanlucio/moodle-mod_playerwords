<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Strings em português do Brasil para mod_playerwords.
 *
 * @package mod_playerwords
 * @copyright  2026 Jean Lúcio
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['actionscolumnlabel'] = 'Ações';
$string['addwordbutton'] = 'Adicionar palavra';
$string['aigranularity'] = 'Escopo de extração da IA';
$string['aigranularity_course'] = 'Curso inteiro';
$string['aigranularity_forum'] = 'Fórum específico';
$string['aigranularity_section'] = 'Tópico específico';
$string['approvedstatus'] = 'Aprovada';
$string['attemptslabel'] = 'Tentativas';
$string['backtogamebutton'] = 'Voltar ao jogo';
$string['bulkdeletebutton'] = 'Excluir selecionadas';
$string['bulkdeleteconfirm'] = 'Tem certeza que deseja excluir as palavras selecionadas? Esta ação não pode ser desfeita.';
$string['bulkdeleted'] = 'Palavras excluídas.';
$string['cancelbutton'] = 'Cancelar';
$string['cell_state_absent'] = '{$a} — não está na palavra';
$string['cell_state_correct'] = '{$a} — posição correta';
$string['cell_state_empty'] = 'Célula vazia';
$string['cell_state_present'] = '{$a} — posição errada';
$string['completionattemptsgroup'] = 'Exigir tentativas';
$string['cooldown_label'] = 'Cooldown entre rodadas';
$string['cooldown_unit'] = 'Unidade';
$string['cooldown_unit_days'] = 'Dias';
$string['cooldown_unit_hours'] = 'Horas';
$string['cooldown_unit_minutes'] = 'Minutos';
$string['cooldownactive'] = 'Você pode iniciar uma nova rodada em {$a}.';
$string['cooldowncountdownlabel'] = 'Próxima rodada em';
$string['deletewordbutton'] = 'Excluir';
$string['deletewordconfirm'] = 'Tem certeza que deseja excluir esta palavra? Esta ação não pode ser desfeita.';
$string['deletewordtitle'] = 'Excluir palavra';
$string['editwordbutton'] = 'Editar';
$string['editwordlabel'] = 'Editar palavra';
$string['error_atleastonesource'] = 'Selecione ao menos uma fonte de palavras.';
$string['error_completionattempts'] = 'As tentativas exigidas devem ser no mínimo 1.';
$string['error_cooldown'] = 'O cooldown deve ser 0 ou um valor positivo.';
$string['error_grademethod_average_all'] = 'Este método de avaliação requer um limite de rodadas maior que 0.';
$string['error_invalidchars'] = 'O palpite deve conter apenas letras.';
$string['error_manualwordlength'] = 'A palavra deve ter entre {$a->min} e {$a->max} caracteres.';
$string['error_manualwordrequired'] = 'A palavra é obrigatória.';
$string['error_maxattempts'] = 'O número máximo de tentativas deve ser no mínimo 1.';
$string['error_maxlength'] = 'O tamanho máximo deve ser maior ou igual ao tamanho mínimo.';
$string['error_minlength'] = 'O tamanho mínimo deve ser no mínimo 1.';
$string['error_timerseconds'] = 'O temporizador deve ser 0 ou um valor positivo.';
$string['event_course_module_instance_list_viewed'] = 'Lista de instâncias do módulo visualizada';
$string['event_course_module_viewed'] = 'Módulo visualizado';
$string['event_round_completed'] = 'Rodada concluída';
$string['event_round_started'] = 'Rodada iniciada';
$string['feedback_forfeited'] = 'Você desistiu.';
$string['feedback_genius'] = 'Gênio!';
$string['feedback_great'] = 'Muito bem!';
$string['feedback_impressive'] = 'Impressionante!';
$string['feedback_lost'] = 'Não foi dessa vez...';
$string['feedback_magnificent'] = 'Magnífico!';
$string['feedback_phew'] = 'Ufa!';
$string['feedback_splendid'] = 'Esplêndido!';
$string['forfeitbutton'] = 'Desistir';
$string['forfeitconfirm'] = 'Tem certeza que deseja desistir? A rodada será encerrada e o cooldown iniciará.';
$string['gameplayheader'] = 'Configurações de jogabilidade';
$string['glossaryid'] = 'Glossário';
$string['glossaryid_all'] = 'Todos os glossários do curso';
$string['glossarystopwords'] = 'Palavras ignoradas nos conceitos do glossário';
$string['glossarystopwords_desc'] = 'Lista de palavras separadas por vírgula para ignorar ao dividir conceitos de glossário com múltiplas palavras em candidatas para o jogo. Deixe vazio para desativar o filtro (o tamanho mínimo de palavra configurado em cada atividade ainda se aplica). Sugestão: a, ao, aos, às, as, com, da, das, de, do, dos, e, em, entre, na, nas, nem, no, nos, o, os, ou, para, pela, pelas, pelo, pelos, por, que, se, sob, um, uma, uns, umas.';
$string['glossarysynced'] = 'Palavras do glossário sincronizadas. {$a} novo(s) termo(s) importado(s).';
$string['grademethod'] = 'Método de avaliação';
$string['grademethod_average'] = 'Nota média';
$string['grademethod_average_all'] = 'Média sobre todas as rodadas obrigatórias';
$string['grademethod_first'] = 'Primeira tentativa';
$string['grademethod_highest'] = 'Nota mais alta';
$string['grademethod_last'] = 'Última tentativa';
$string['guesslabel'] = 'Seu palpite';
$string['guesslengthmismatch'] = 'O palpite deve ter exatamente {$a} letras.';
$string['guessplaceholder'] = 'Digite uma palavra';
$string['hintbuttonlabel'] = 'Revelar dica';
$string['hintlabel'] = 'Dica';
$string['ignore_accents'] = 'Aceitar palavras sem acentos';
$string['managewordsbutton'] = 'Gerenciar palavras';
$string['managewordslabel'] = 'Palavras manuais';
$string['manualhintlabel'] = 'Dica';
$string['manualhintplaceholder'] = 'Dica opcional para os estudantes';
$string['manualwordadded'] = 'Palavra adicionada com sucesso.';
$string['manualwordlabel'] = 'Palavra';
$string['manualwordplaceholder'] = 'Digite uma palavra';
$string['max_attempts'] = 'Máximo de tentativas por rodada';
$string['max_length'] = 'Tamanho máximo da palavra';
$string['max_rounds'] = 'Máximo de rodadas por estudante';
$string['max_rounds_unlimited'] = 'Ilimitado';
$string['min_length'] = 'Tamanho mínimo da palavra';
$string['modulename'] = 'PlayerWords';
$string['modulename_help'] = 'O PlayerWords é uma atividade no estilo Wordle que desafia os estudantes a adivinharem termos-chave do conteúdo do curso.';
$string['modulenameplural'] = 'PlayerWords';
$string['newroundlabel'] = 'Iniciar nova rodada';
$string['newroundready'] = 'Pronto para jogar!';
$string['nogamewords'] = 'Ainda não há palavras aprovadas disponíveis para esta atividade.';
$string['nowordsyet'] = 'Ainda não há palavras cadastradas.';
$string['pendingstatus'] = 'Pendente';
$string['playersourcesheader'] = 'Fontes de palavras';
$string['playerwords:addinstance'] = 'Adicionar uma nova atividade PlayerWords';
$string['playerwords:view'] = 'Visualizar atividade PlayerWords';
$string['pluginadministration'] = 'Administração do PlayerWords';
$string['pluginname'] = 'PlayerWords';
$string['recentwordslabel'] = 'Banco de palavras';
$string['revealconceptlabel'] = 'Termo completo:';
$string['revealdefinitionlabel'] = 'Definição:';
$string['revealwordlabel'] = 'A palavra era:';
$string['roundfinished'] = 'Esta rodada já foi finalizada. Inicie uma nova rodada.';
$string['roundforfeited'] = 'Você desistiu. A rodada foi encerrada.';
$string['roundlimitreached'] = 'Você atingiu o número máximo de rodadas ({$a}) para esta atividade.';
$string['roundlost'] = 'Rodada encerrada.';
$string['roundwon'] = 'Parabéns! Você acertou a palavra.';
$string['savewordbutton'] = 'Salvar';
$string['selectall'] = 'Selecionar todos';
$string['selectword'] = 'Selecionar';
$string['show_ranking'] = 'Exibir ranking ao final das rodadas';
$string['source_ai'] = 'Extração por IA';
$string['source_glossary'] = 'Glossário';
$string['source_manual'] = 'Inserção manual';
$string['sourcecolumnlabel'] = 'Fonte';
$string['statuscolumnlabel'] = 'Status';
$string['submitguess'] = 'Enviar palpite';
$string['syncglossarybutton'] = 'Sincronizar com glossário';
$string['timer_seconds'] = 'Temporizador em segundos (0 para desativar)';
$string['timerlabel'] = 'Tempo restante (s):';
$string['wordcolumnlabel'] = 'Palavra';
$string['worddeleted'] = 'Palavra excluída.';
$string['wordmode'] = 'Modo de seleção de palavras';
$string['wordmode_random'] = 'Palavra aleatória por rodada';
$string['wordmode_shared'] = 'Sequência compartilhada (todos os estudantes recebem as mesmas palavras na mesma ordem)';
$string['wordupdated'] = 'Palavra atualizada.';
