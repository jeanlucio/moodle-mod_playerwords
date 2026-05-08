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

$string['addwordbutton'] = 'Adicionar palavra';
$string['aigranularity'] = 'Escopo de extração da IA';
$string['aigranularity_course'] = 'Curso inteiro';
$string['aigranularity_forum'] = 'Fórum específico';
$string['aigranularity_section'] = 'Tópico específico';
$string['approvedstatus'] = 'Aprovada';
$string['attemptslabel'] = 'Tentativas';
$string['backtogamebutton'] = 'Voltar ao jogo';
$string['completionattemptsgroup'] = 'Exigir tentativas';
$string['cooldown_label'] = 'Cooldown entre rodadas';
$string['cooldown_unit'] = 'Unidade';
$string['cooldown_unit_days'] = 'Dias';
$string['cooldown_unit_hours'] = 'Horas';
$string['cooldown_unit_minutes'] = 'Minutos';
$string['cooldownactive'] = 'Você pode iniciar uma nova rodada em {$a}.';
$string['error_atleastonesource'] = 'Selecione ao menos uma fonte de palavras.';
$string['error_completionattempts'] = 'As tentativas exigidas devem ser no mínimo 1.';
$string['error_cooldown'] = 'O cooldown deve ser 0 ou um valor positivo.';
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
$string['gameplayheader'] = 'Configurações de jogabilidade';
$string['grademethod'] = 'Método de avaliação';
$string['grademethod_average'] = 'Nota média';
$string['grademethod_first'] = 'Primeira tentativa';
$string['grademethod_highest'] = 'Nota mais alta';
$string['grademethod_last'] = 'Última tentativa';
$string['guesslabel'] = 'Seu palpite';
$string['guesslengthmismatch'] = 'O palpite deve ter exatamente {$a} letras.';
$string['guessplaceholder'] = 'Digite uma palavra';
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
$string['nogamewords'] = 'Ainda não há palavras aprovadas disponíveis para esta atividade.';
$string['nowordsyet'] = 'Ainda não há palavras cadastradas.';
$string['pendingstatus'] = 'Pendente';
$string['playersourcesheader'] = 'Fontes de palavras';
$string['playerwords:addinstance'] = 'Adicionar uma nova atividade PlayerWords';
$string['playerwords:view'] = 'Visualizar atividade PlayerWords';
$string['pluginadministration'] = 'Administração do PlayerWords';
$string['pluginname'] = 'PlayerWords';
$string['recentwordslabel'] = 'Palavras recentes';
$string['roundfinished'] = 'Esta rodada já foi finalizada. Inicie uma nova rodada.';
$string['roundlimitreached'] = 'Você atingiu o número máximo de rodadas ({$a}) para esta atividade.';
$string['roundlost'] = 'Rodada encerrada. Você pode iniciar uma nova rodada.';
$string['roundwon'] = 'Parabéns! Você acertou a palavra.';
$string['show_ranking'] = 'Exibir ranking ao final das rodadas';
$string['source_ai'] = 'Extração por IA';
$string['source_glossary'] = 'Glossário';
$string['source_manual'] = 'Inserção manual';
$string['sourcecolumnlabel'] = 'Fonte';
$string['statuscolumnlabel'] = 'Status';
$string['submitguess'] = 'Enviar palpite';
$string['timer_seconds'] = 'Temporizador em segundos (0 para desativar)';
$string['timerlabel'] = 'Tempo restante (s):';
$string['wordcolumnlabel'] = 'Palavra';
$string['wordmode'] = 'Modo de seleção de palavras';
$string['wordmode_daily'] = 'Palavra do dia (mesma palavra para todos os estudantes a cada dia)';
$string['wordmode_random'] = 'Palavra aleatória por rodada';
