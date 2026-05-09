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
 * English language strings for mod_playerwords.
 *
 * @package mod_playerwords
 * @copyright  2026 Jean Lúcio
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['actionscolumnlabel'] = 'Actions';
$string['addwordbutton'] = 'Add word';
$string['aigranularity'] = 'AI extraction scope';
$string['aigranularity_course'] = 'Whole course';
$string['aigranularity_forum'] = 'Specific forum';
$string['aigranularity_section'] = 'Specific topic';
$string['approvedstatus'] = 'Approved';
$string['attemptslabel'] = 'Attempts';
$string['backtogamebutton'] = 'Back to game';
$string['cell_state_absent'] = '{$a} — not in the word';
$string['cell_state_correct'] = '{$a} — correct position';
$string['cell_state_empty'] = 'Empty cell';
$string['cell_state_present'] = '{$a} — wrong position';
$string['completionattemptsgroup'] = 'Require attempts';
$string['cooldown_label'] = 'Cooldown between rounds';
$string['cooldown_unit'] = 'Unit';
$string['cooldown_unit_days'] = 'Days';
$string['cooldown_unit_hours'] = 'Hours';
$string['cooldown_unit_minutes'] = 'Minutes';
$string['cooldownactive'] = 'You can start a new round in {$a}.';
$string['cooldowncountdownlabel'] = 'Next round in';
$string['deletewordbutton'] = 'Delete';
$string['deletewordconfirm'] = 'Are you sure you want to delete this word? This action cannot be undone.';
$string['error_atleastonesource'] = 'Select at least one word source.';
$string['error_completionattempts'] = 'Required attempts must be at least 1.';
$string['error_cooldown'] = 'Cooldown must be 0 or a positive value.';
$string['error_grademethod_average_all'] = 'This grading method requires a maximum rounds limit greater than 0.';
$string['error_invalidchars'] = 'The guess must contain letters only.';
$string['error_manualwordlength'] = 'Word length must be between {$a->min} and {$a->max} characters.';
$string['error_manualwordrequired'] = 'Word is required.';
$string['error_maxattempts'] = 'Maximum attempts must be at least 1.';
$string['error_maxlength'] = 'Maximum length must be greater than or equal to minimum length.';
$string['error_minlength'] = 'Minimum length must be at least 1.';
$string['error_timerseconds'] = 'Timer must be 0 or a positive value.';
$string['event_course_module_instance_list_viewed'] = 'Course module instance list viewed';
$string['event_course_module_viewed'] = 'Course module viewed';
$string['event_round_completed'] = 'Round completed';
$string['event_round_started'] = 'Round started';
$string['feedback_forfeited'] = 'You gave up.';
$string['feedback_genius'] = 'Genius!';
$string['feedback_great'] = 'Great!';
$string['feedback_impressive'] = 'Impressive!';
$string['feedback_lost'] = 'Better luck next time.';
$string['feedback_magnificent'] = 'Magnificent!';
$string['feedback_phew'] = 'Phew!';
$string['feedback_splendid'] = 'Splendid!';
$string['forfeitbutton'] = 'Give up';
$string['forfeitconfirm'] = 'Are you sure you want to give up? The round will end and the cooldown will start.';
$string['gameplayheader'] = 'Gameplay settings';
$string['glossaryid'] = 'Glossary';
$string['glossaryid_all'] = 'All course glossaries';
$string['glossarysynced'] = 'Glossary words synced. {$a} new term(s) imported.';
$string['grademethod'] = 'Grading method';
$string['grademethod_average'] = 'Average grade';
$string['grademethod_average_all'] = 'Average over all required rounds';
$string['grademethod_first'] = 'First attempt';
$string['grademethod_highest'] = 'Highest grade';
$string['grademethod_last'] = 'Last attempt';
$string['guesslabel'] = 'Your guess';
$string['guesslengthmismatch'] = 'The guess must have exactly {$a} letters.';
$string['guessplaceholder'] = 'Type a word';
$string['hintbuttonlabel'] = 'Reveal hint';
$string['hintlabel'] = 'Hint';
$string['ignore_accents'] = 'Accept words without accents';
$string['managewordsbutton'] = 'Manage words';
$string['managewordslabel'] = 'Manual words';
$string['manualhintlabel'] = 'Hint';
$string['manualhintplaceholder'] = 'Optional hint for students';
$string['manualwordadded'] = 'Word added successfully.';
$string['manualwordlabel'] = 'Word';
$string['manualwordplaceholder'] = 'Enter a word';
$string['max_attempts'] = 'Maximum attempts per round';
$string['max_length'] = 'Maximum word length';
$string['max_rounds'] = 'Maximum rounds per student';
$string['max_rounds_unlimited'] = 'Unlimited';
$string['min_length'] = 'Minimum word length';
$string['modulename'] = 'PlayerWords';
$string['modulename_help'] = 'PlayerWords is a Wordle-style activity that challenges students to guess key terms from the course content.';
$string['modulenameplural'] = 'PlayerWords';
$string['newroundlabel'] = 'Start a new round';
$string['newroundready'] = 'Ready to play!';
$string['nogamewords'] = 'No approved words are available for this activity yet.';
$string['nowordsyet'] = 'No words have been added yet.';
$string['pendingstatus'] = 'Pending';
$string['playersourcesheader'] = 'Word sources';
$string['playerwords:addinstance'] = 'Add a new PlayerWords activity';
$string['playerwords:view'] = 'View PlayerWords activity';
$string['pluginadministration'] = 'PlayerWords administration';
$string['pluginname'] = 'PlayerWords';
$string['recentwordslabel'] = 'Word pool';
$string['revealconceptlabel'] = 'Full term:';
$string['revealdefinitionlabel'] = 'Definition:';
$string['revealwordlabel'] = 'The word was:';
$string['roundfinished'] = 'This round is already finished. Start a new round.';
$string['roundforfeited'] = 'You gave up. The round is over.';
$string['roundlimitreached'] = 'You have reached the maximum number of rounds ({$a}) for this activity.';
$string['roundlost'] = 'Round over.';
$string['roundwon'] = 'Congratulations! You guessed the word.';
$string['show_ranking'] = 'Show ranking at the end of rounds';
$string['source_ai'] = 'AI extraction';
$string['source_glossary'] = 'Glossary';
$string['source_manual'] = 'Manual insertion';
$string['sourcecolumnlabel'] = 'Source';
$string['statuscolumnlabel'] = 'Status';
$string['submitguess'] = 'Submit guess';
$string['syncglossarybutton'] = 'Sync with glossary';
$string['timer_seconds'] = 'Timer in seconds (0 to disable)';
$string['timerlabel'] = 'Time left (s):';
$string['wordcolumnlabel'] = 'Word';
$string['worddeleted'] = 'Word deleted.';
$string['wordmode'] = 'Word selection mode';
$string['wordmode_random'] = 'Random word per round';
$string['wordmode_shared'] = 'Shared sequence (all students receive the same words in the same order)';
