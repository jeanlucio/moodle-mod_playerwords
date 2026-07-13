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
$string['activewordscount'] = 'Active words in this game: {$a}.';
$string['addwordbutton'] = 'Add word';
$string['aigeneratebutton'] = 'Generate with AI';
$string['aigeneratecount'] = 'Number of words';
$string['aigeneratednone'] = 'No eligible words were generated for this topic.';
$string['aigeneratedsaved'] = '{$a} word(s) generated and pending review.';
$string['aigenerateerror'] = 'Word generation failed. Check the AI settings in site administration.';
$string['aigeneratetitle'] = 'Generate words with AI';
$string['aigeneratetopic'] = 'Topic';
$string['aiusage'] = 'Word generation - {$a}';
$string['approvedstatus'] = 'Approved';
$string['attemptslabel'] = 'Attempts';
$string['backtogamebutton'] = 'Back to game';
$string['bulkapprovebutton'] = 'Approve selected';
$string['bulkapprovebuttontitle'] = 'Approve selected words';
$string['bulkapproveconfirm'] = 'Are you sure you want to approve the selected words? They will become available in the game immediately.';
$string['bulkapproved'] = 'Words approved.';
$string['bulkdeletebutton'] = 'Delete selected';
$string['bulkdeleteconfirm'] = 'Are you sure you want to delete the selected words? This action cannot be undone.';
$string['bulkdeleted'] = 'Words deleted.';
$string['cancelbutton'] = 'Cancel';
$string['cell_state_absent'] = '{$a} — not in the word';
$string['cell_state_correct'] = '{$a} — correct position';
$string['cell_state_empty'] = 'Empty cell';
$string['cell_state_present'] = '{$a} — wrong position';
$string['completionattempts_desc'] = 'Make at least {$a} attempt(s)';
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
$string['deletewordtitle'] = 'Delete word';
$string['drawcountcolumnlabel'] = 'Times drawn';
$string['editwordbutton'] = 'Edit';
$string['editwordlabel'] = 'Edit word';
$string['eligiblewordscount'] = 'Eligible words in the pool for this range: {$a}.';
$string['error_atleastonesource'] = 'Select at least one word source.';
$string['error_completionattempts'] = 'Required attempts must be at least 1.';
$string['error_cooldown'] = 'Cooldown must be 0 or a positive value.';
$string['error_grademethod_average_all'] = 'This grading method requires the maximum rounds per student setting to not be Unlimited.';
$string['error_hud_cost_qty'] = 'Quantity must be at least 1.';
$string['error_invalidchars'] = 'The guess must contain letters only.';
$string['error_manualwordduplicate'] = 'This word already exists in this activity\'s word pool.';
$string['error_manualwordinvalidchars'] = 'Word must contain letters only — no numbers, spaces or special characters (such as hyphens, apostrophes or punctuation).';
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
$string['feedback_timeout'] = 'Time is up!';
$string['forfeitbutton'] = 'Give up';
$string['forfeitconfirm'] = 'Are you sure you want to give up? The round will end and the cooldown will start.';
$string['gameplayheader'] = 'Gameplay settings';
$string['glossaryid'] = 'Glossary';
$string['glossaryid_all'] = 'All course glossaries';
$string['glossarystopwords'] = 'Glossary concept stopwords';
$string['glossarystopwords_desc'] = 'Comma-separated list of words to ignore when splitting multi-word glossary concepts into game candidates. Leave empty to disable filtering (the minimum word length set on each activity still applies). Suggested list: a, an, and, as, at, by, for, from, if, in, into, is, it, its, not, of, on, or, so, the, this, to, up, was, with.';
$string['glossarysynced'] = 'Glossary words synced. {$a} new term(s) imported.';
$string['glossarywordscount'] = '{$a} words from the glossary source fit this range.';
$string['grademethod'] = 'Grading method';
$string['grademethod_average'] = 'Average grade';
$string['grademethod_average_all'] = 'Average over all required rounds';
$string['grademethod_first'] = 'First attempt';
$string['grademethod_help'] = 'Determines how the final grade is calculated from a student\'s round attempts: <ul><li><strong>Highest grade:</strong> the best score among all attempts.</li><li><strong>Average grade:</strong> the mean of only the attempts actually made.</li><li><strong>First attempt:</strong> the score of the first attempt only.</li><li><strong>Last attempt:</strong> the score of the most recent attempt only.</li><li><strong>Average over all required rounds:</strong> the sum of the attempt scores divided by the configured maximum rounds, so any round not attempted counts as zero. Requires the maximum rounds per student setting to not be Unlimited.</li></ul>';
$string['grademethod_highest'] = 'Highest grade';
$string['grademethod_last'] = 'Last attempt';
$string['gradescoringmode'] = 'Grade scoring';
$string['gradescoringmode_help'] = 'Determines how many points a single round is worth, before grade aggregation is applied: <ul><li><strong>Binary:</strong> the full activity grade if the round was won, regardless of how many attempts were used; zero otherwise.</li><li><strong>Linear:</strong> the first two attempts both earn the full grade; from the third attempt onward, won rounds are worth a fraction of the full grade proportional to attempts spared — the fewer attempts used, the more points earned. A round using the maximum allowed attempts still earns some points; not completing the round always earns zero.</li></ul> Locked once the activity has recorded a real grade for any student.';
$string['gradesofar'] = '{$a->method}: {$a->mygrade} / {$a->maxgrade}.';
$string['gradingmethodinfo'] = 'Grading method: {$a}';
$string['guesslabel'] = 'Your guess';
$string['guesslengthmismatch'] = 'The guess must have exactly {$a} letters.';
$string['guessplaceholder'] = 'Type a word';
$string['help_attempts'] = 'You have a limited number of attempts per round, shown at the top of the board.';
$string['help_grading'] = 'Grading method for this activity: {$a}.';
$string['help_hint'] = 'A hint may be available, depending on how the teacher configured the activity.';
$string['help_hud'] = 'This activity may require PlayerHUD items to start a round or reveal a hint, and can reward you with an item for winning a round. Any required balance is always shown up front.';
$string['help_intro'] = 'Guess the hidden word within the allowed attempts. After each guess, each letter is coloured to show how close you are.';
$string['help_keyboard'] = 'You can also type using your device\'s physical keyboard.';
$string['help_legend_absent'] = 'Grey: the letter is not in the word.';
$string['help_legend_correct'] = 'Blue: the letter is correct and in the right position.';
$string['help_legend_present'] = 'Yellow: the letter is in the word but in the wrong position.';
$string['help_ranking'] = 'The ranking adds up your points from every finished round. Ties are broken by fewer attempts used on average, then less time spent on average.';
$string['help_reviewhint'] = 'You can review these instructions anytime by clicking the help icon at the top of the game.';
$string['help_timer'] = 'Some activities have a time limit per round, shown as a countdown.';
$string['help_title'] = 'How to play';
$string['hintbuttonlabel'] = 'Reveal hint';
$string['hintlabel'] = 'Hint';
$string['hud_balancecost'] = 'Costs {$a->required}× {$a->item} (you have {$a->available}).';
$string['hud_costlabel'] = '{$a->qty}× {$a->item}';
$string['hud_grantedlabel'] = 'You received {$a->qty}× {$a->item}!';
$string['hud_header'] = 'PlayerHUD Integration';
$string['hud_hint_cost_item'] = 'Item to reveal hint';
$string['hud_hint_cost_qty'] = 'Quantity to reveal hint';
$string['hud_insufficient_hint'] = 'Not enough {$a} to reveal the hint.';
$string['hud_insufficient_round'] = 'Not enough {$a} to start a round.';
$string['hud_item_deleted'] = 'Deleted item (please reconfigure)';
$string['hud_item_disabled'] = '{$a} (disabled)';
$string['hud_noitem'] = 'Disabled (no cost)';
$string['hud_round_cost_item'] = 'Item to start a round';
$string['hud_round_cost_qty'] = 'Quantity to start a round';
$string['hud_win_grant_item'] = 'Item to grant per round won';
$string['hud_win_grant_item_help'] = 'The student receives this item each time they win a round. To match PlayerHUD\'s own anti-farming rule, no XP is awarded from this item when Maximum rounds per student is Unlimited — the item is still granted, just without XP.';
$string['hud_win_grant_qty'] = 'Quantity to grant per round won';
$string['inactivewords_invalidchars'] = 'Contains a character the game cannot use ({$a->count}): {$a->words}.';
$string['inactivewords_length'] = 'Outside the current length range ({$a->count}): {$a->words}.';
$string['inactivewords_title'] = 'Inactive words in this game:';
$string['keyboard_backspace'] = 'Delete last letter';
$string['keyboard_enter'] = 'Submit';
$string['keyboard_enter_text'] = 'Enter';
$string['keyboard_label'] = 'Virtual keyboard';
$string['lobby_timerinfo'] = 'You have {$a} to guess the word.';
$string['managewords_fragmentedconcept_warning'] = 'This word is one of several extracted from the same glossary concept ("{$a}") — review whether it makes sense on its own.';
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
$string['myattempts_allstudents'] = 'All students';
$string['myattempts_attempts'] = 'Attempts';
$string['myattempts_date'] = 'Date';
$string['myattempts_empty'] = 'You have not finished any rounds yet.';
$string['myattempts_empty_report'] = 'No student has finished a round yet.';
$string['myattempts_filterbutton'] = 'Filter';
$string['myattempts_rankingpoints'] = 'Ranking points';
$string['myattempts_report_title'] = 'Attempt history — All students';
$string['myattempts_score'] = 'Score';
$string['myattempts_student'] = 'Student';
$string['myattempts_time'] = 'Time';
$string['myattempts_title'] = 'My attempts';
$string['myattempts_word'] = 'Word';
$string['newroundlabel'] = 'Start a new round';
$string['nogamewords'] = 'No approved words are available for this activity yet.';
$string['nowordsyet'] = 'No words have been added yet.';
$string['pendingstatus'] = 'Pending';
$string['playersourcesheader'] = 'Word sources';
$string['playerwords:addinstance'] = 'Add a new PlayerWords activity';
$string['playerwords:view'] = 'View PlayerWords activity';
$string['pluginadministration'] = 'PlayerWords administration';
$string['pluginname'] = 'PlayerWords';
$string['privacy:attempts'] = 'Round attempts';
$string['privacy:metadata:playerwords_attempts'] = 'Stores each round attempt made by a student, including result and time used.';
$string['privacy:metadata:playerwords_attempts:attempts_used'] = 'Number of guesses used in the round.';
$string['privacy:metadata:playerwords_attempts:completed'] = 'Whether the student guessed the word correctly.';
$string['privacy:metadata:playerwords_attempts:playerwordsid'] = 'ID of the PlayerWords activity.';
$string['privacy:metadata:playerwords_attempts:score'] = 'Score awarded for the round.';
$string['privacy:metadata:playerwords_attempts:time_used'] = 'Time spent on the round, in seconds.';
$string['privacy:metadata:playerwords_attempts:timecreated'] = 'Time when the round attempt was created.';
$string['privacy:metadata:playerwords_attempts:timefinished'] = 'Time when the round attempt was finished, if it was.';
$string['privacy:metadata:playerwords_attempts:userid'] = 'ID of the student who played the round.';
$string['privacy:metadata:playerwords_attempts:wordid'] = 'ID of the word used in the round.';
$string['privacy:metadata:playerwords_words'] = 'Stores words added to the activity pool, recording who added each word.';
$string['privacy:metadata:playerwords_words:addedby'] = 'ID of the user who added the word.';
$string['privacy:metadata:preference:seenintro'] = 'Whether the automatic how-to-play introduction has already been shown to you once, on any PlayerWords activity on this site.';
$string['privacy:words'] = 'Words added';
$string['ranking_back'] = 'Back to game';
$string['ranking_empty'] = 'No rounds completed yet.';
$string['ranking_player'] = 'Player';
$string['ranking_points'] = 'Points';
$string['ranking_position'] = 'Position';
$string['ranking_tiebreak'] = 'Ties are broken by fewer attempts used on average, then less time spent on average.';
$string['ranking_title'] = 'Ranking';
$string['ranking_viewfull'] = 'Top 5';
$string['rankingscoringmode'] = 'Ranking scoring';
$string['rankingscoringmode_help'] = 'Determines how many points a single round contributes to the ranking total: <ul><li><strong>Binary:</strong> the full activity grade if the round was won, regardless of how many attempts were used; zero otherwise.</li><li><strong>Linear:</strong> the first two attempts both earn the full grade; from the third attempt onward, won rounds contribute a fraction of the full grade proportional to attempts spared — the fewer attempts used, the more points earned.</li></ul> Locked once the activity has recorded a real grade for any student, to keep every round comparable on the same scale.';
$string['recentwordslabel'] = 'Word pool';
$string['resetplayerwordsattempts'] = 'Delete PlayerWords round attempts';
$string['revealconceptlabel'] = 'Full term:';
$string['revealdefinitionlabel'] = 'Definition:';
$string['revealwordlabel'] = 'The word was:';
$string['roundfinished'] = 'This round is already finished. Start a new round.';
$string['roundforfeited'] = 'You gave up. The round is over.';
$string['roundlimitreached'] = 'You have reached the maximum number of rounds ({$a}) for this activity.';
$string['roundlost'] = 'Round over.';
$string['roundnottimedout'] = 'The timer has not run out yet.';
$string['roundsplayed'] = 'Rounds played: {$a->played} / {$a->max}.';
$string['roundtimeout'] = 'Time is up. The round is over.';
$string['roundwon'] = 'Congratulations! You guessed the word.';
$string['savewordbutton'] = 'Save';
$string['scoringmode_binary'] = 'Binary (all or nothing)';
$string['scoringmode_linear'] = 'Linear (proportional to attempts spared)';
$string['scoringmode_locked'] = 'Because this activity has already recorded a real grade for at least one student, the maximum attempts and the two scoring mode settings can no longer be changed — changing them afterwards would make past and future rounds count on different scales.';
$string['selectall'] = 'Select all';
$string['selectword'] = 'Select';
$string['show_ranking'] = 'Show ranking';
$string['source_ai'] = 'AI extraction';
$string['source_glossary'] = 'Glossary';
$string['source_manual'] = 'Manual insertion';
$string['sourcecolumnlabel'] = 'Source';
$string['startround'] = 'Start round';
$string['statuscolumnlabel'] = 'Status';
$string['submitguess'] = 'Submit guess';
$string['syncglossarybutton'] = 'Sync with glossary';
$string['timer_minutes'] = 'Timer in minutes (0 to disable)';
$string['timer_seconds'] = 'Timer in seconds (0 to disable)';
$string['timerlabel'] = 'Time left:';
$string['toolbarhelp'] = 'Help';
$string['toolbarmyattempts'] = 'Attempt history';
$string['wordcolumnlabel'] = 'Word';
$string['worddeleted'] = 'Word deleted.';
$string['wordmode'] = 'Word selection mode';
$string['wordmode_random'] = 'Random word per round';
$string['wordmode_shared'] = 'Shared sequence (all students receive the same words in the same order)';
$string['wordupdated'] = 'Word updated.';
