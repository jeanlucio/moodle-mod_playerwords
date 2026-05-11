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
 * Service to build PlayerWords view page state.
 *
 * @package mod_playerwords
 * @copyright  2026 Jean Lúcio
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playerwords\local;

use context_module;
use core_text;
use moodle_url;

/**
 * Handles actions and template context of view.php.
 */
class view_page_service {
    /**
     * Builds full page data for view rendering.
     *
     * @param \stdClass $cm Course module.
     * @param \stdClass $instance Activity instance.
     * @param context_module $context Module context.
     * @param int $userid Current user id.
     * @return array
     */
    public static function build_page_data(
        \stdClass $cm,
        \stdClass $instance,
        context_module $context,
        int $userid
    ): array {
        $canmanagewords = has_capability('mod/playerwords:addinstance', $context);
        $notification = null;
        $notificationtype = null;

        $state = self::load_state((int)$cm->id, $userid);
        $targetword = '';
        $roundwordid = 0;

        if (optional_param('newround', 0, PARAM_BOOL)) {
            require_sesskey();
            self::flag_round_finished((int)$cm->id, $userid);
            redirect(new moodle_url('/mod/playerwords/view.php', ['id' => $cm->id]));
        }

        if ((int)$state['wordid'] === 0 || !empty($state['finished'])) {
            $restrictionnotice = self::get_round_restriction_notice($instance, $userid);
            if ($restrictionnotice !== null) {
                self::save_state((int)$cm->id, $userid, $state);
                if (
                    !empty($state['finished']) &&
                    (int)($state['wordid'] ?? 0) > 0 &&
                    (int)($state['cooldownuntil'] ?? 0) > time()
                ) {
                    $templatectx = self::build_template_context(
                        $cm,
                        $instance,
                        $state,
                        $state['wordtext'] ?? '',
                        $canmanagewords
                    );
                    return [
                        'notification'     => null,
                        'notificationtype' => null,
                        'templatecontext'  => $templatectx,
                        'cooldownuntil'    => (int)($state['cooldownuntil'] ?? 0),
                        'timeleft'         => 0,
                        'timertotal'       => 0,
                    ];
                }
                $templatectx = self::build_template_context($cm, $instance, $state, '', $canmanagewords);
                $templatectx['nogamewords'] = $restrictionnotice;
                return [
                    'notification'     => null,
                    'notificationtype' => null,
                    'templatecontext'  => $templatectx,
                    'cooldownuntil'    => 0,
                    'timeleft'         => 0,
                    'timertotal'       => 0,
                ];
            }
        }

        [$state, $targetword, $roundwordid] = self::ensure_round_state($state, $instance, $userid);

        if (optional_param('revealhint', 0, PARAM_BOOL)) {
            require_sesskey();
            $state['hintrevealed'] = true;
        }

        if (optional_param('submitguess', 0, PARAM_BOOL)) {
            require_sesskey();
            [$state, $notification, $notificationtype] = self::handle_guess_submission(
                $state,
                $instance,
                $userid,
                $roundwordid,
                $targetword
            );
        }

        if (optional_param('forfeit', 0, PARAM_BOOL)) {
            require_sesskey();
            [$state, $notification, $notificationtype] = self::handle_forfeit($state, $instance, $userid);
        }

        self::save_state((int)$cm->id, $userid, $state);

        $templatecontext = self::build_template_context(
            $cm,
            $instance,
            $state,
            $targetword,
            $canmanagewords
        );

        return [
            'notification'     => $notification,
            'notificationtype' => $notificationtype,
            'templatecontext'  => $templatecontext,
            'cooldownuntil'    => (int)($state['cooldownuntil'] ?? 0),
            'timeleft'         => (int)($templatecontext['timeleft'] ?? 0),
            'timertotal'       => (int)$instance->timer_seconds,
        ];
    }

    /**
     * Gets session state, creating defaults when missing.
     *
     * @param int $cmid Course module id.
     * @param int $userid User id.
     * @return array
     */
    private static function load_state(int $cmid, int $userid): array {
        global $SESSION;

        $sessionkey = gameplay_service::build_session_key($cmid, $userid);
        if (!isset($SESSION->mod_playerwords)) {
            $SESSION->mod_playerwords = [];
        }
        if (!isset($SESSION->mod_playerwords[$sessionkey])) {
            $SESSION->mod_playerwords[$sessionkey] = [
                'wordid'       => 0,
                'wordtext'     => '',
                'concept'      => '',
                'attemptsused' => 0,
                'starttime'    => 0,
                'hint'         => '',
                'hintrevealed' => false,
                'rows'         => [],
                'finished'     => false,
                'won'          => false,
                'forfeited'    => false,
                'cooldownuntil' => 0,
            ];
        }

        return $SESSION->mod_playerwords[$sessionkey];
    }

    /**
     * Persists session state.
     *
     * @param int $cmid Course module id.
     * @param int $userid User id.
     * @param array $state Current state.
     * @return void
     */
    private static function save_state(int $cmid, int $userid, array $state): void {
        global $SESSION;

        $sessionkey = gameplay_service::build_session_key($cmid, $userid);
        $SESSION->mod_playerwords[$sessionkey] = $state;
    }

    /**
     * Marks current round as finished.
     *
     * @param int $cmid Course module id.
     * @param int $userid User id.
     * @return void
     */
    private static function flag_round_finished(int $cmid, int $userid): void {
        global $SESSION;

        $sessionkey = gameplay_service::build_session_key($cmid, $userid);
        if (!isset($SESSION->mod_playerwords[$sessionkey])) {
            return;
        }
        $SESSION->mod_playerwords[$sessionkey]['finished'] = true;
    }

    /**
     * Ensures round target word is loaded in state.
     *
     * @param array $state Current state.
     * @param \stdClass $instance Activity instance.
     * @param int $userid User id.
     * @return array
     */
    private static function ensure_round_state(array $state, \stdClass $instance, int $userid): array {
        global $DB;

        $targetword = '';
        $roundwordid = 0;
        $wordremoved = false;

        if ((int)$state['wordid'] > 0 && empty($state['finished'])) {
            $roundwordid = (int)$state['wordid'];
            $wordrecord = words_repository::get_approved_word_by_id($roundwordid, (int)$instance->id);
            if ($wordrecord) {
                $targetword = word_normalizer::normalize($wordrecord->word, !empty($instance->ignore_accents));
                $state['hint'] = $wordrecord->hint ?? '';
                $state['concept'] = $wordrecord->concept ?? '';
            } else {
                // Word was removed or unapproved mid-round; reset so the next load picks a fresh word.
                $state['wordid'] = 0;
                $state['attemptsused'] = 0;
                $state['rows'] = [];
                $wordremoved = true;
            }
        }

        if (!$wordremoved && ($targetword === '' || !empty($state['finished']))) {
            $completedround = $DB->count_records('playerwords_attempts', [
                'playerwordsid' => $instance->id,
                'userid' => $userid,
            ]);
            $pickedword = words_repository::pick_round_word($instance, $completedround);
            if ($pickedword) {
                $targetword = word_normalizer::normalize($pickedword->word, !empty($instance->ignore_accents));
                $roundwordid = (int)$pickedword->id;
                $state['wordid'] = $roundwordid;
                $state['wordtext'] = $pickedword->word;
                $state['concept'] = $pickedword->concept ?? '';
                $state['attemptsused'] = 0;
                $state['starttime'] = time();
                $state['hint'] = $pickedword->hint ?? '';
                $state['hintrevealed'] = false;
                $state['rows'] = [];
                $state['finished'] = false;
                $state['won'] = false;
                $state['forfeited'] = false;
                $state['cooldownuntil'] = 0;
            }
        }

        return [$state, $targetword, $roundwordid];
    }

    /**
     * Handles one guess submission.
     *
     * @param array $state Current session state.
     * @param \stdClass $instance Activity instance.
     * @param int $userid User id.
     * @param int $roundwordid Current word id.
     * @param string $targetword Current normalized target word.
     * @return array
     */
    private static function handle_guess_submission(
        array $state,
        \stdClass $instance,
        int $userid,
        int $roundwordid,
        string $targetword
    ): array {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/playerwords/lib.php');

        if ($targetword === '') {
            return [$state, get_string('nogamewords', 'mod_playerwords'), 'warning'];
        }

        if ((int)$state['attemptsused'] >= (int)$instance->max_attempts || !empty($state['finished'])) {
            return [$state, get_string('roundfinished', 'mod_playerwords'), 'warning'];
        }

        $guess = required_param('guess', PARAM_TEXT);
        $normalizedguess = word_normalizer::normalize($guess, !empty($instance->ignore_accents));
        $targetlength = core_text::strlen($targetword);
        $guesslength = core_text::strlen($normalizedguess);

        if (!preg_match('/^[\p{L}]+$/u', $normalizedguess)) {
            return [$state, get_string('error_invalidchars', 'mod_playerwords'), 'warning'];
        }

        if ($guesslength !== $targetlength) {
            $message = get_string('guesslengthmismatch', 'mod_playerwords', $targetlength);
            return [$state, $message, 'warning'];
        }

        $state['attemptsused']++;
        $feedback = gameplay_service::build_letter_feedback($normalizedguess, $targetword);
        $state['rows'][] = [
            'word' => $normalizedguess,
            'feedback' => $feedback,
        ];

        $iscompleted = ($normalizedguess === $targetword);
        $outofattempts = ((int)$state['attemptsused'] >= (int)$instance->max_attempts);
        $outoftime = false;
        if ((int)$instance->timer_seconds > 0) {
            $elapsed = time() - (int)$state['starttime'];
            $outoftime = $elapsed >= (int)$instance->timer_seconds;
        }

        if (!($iscompleted || $outofattempts || $outoftime)) {
            return [$state, null, null];
        }

        $state['finished'] = true;
        $state['won'] = $iscompleted;
        $state['forfeited'] = false;
        if ((int)$instance->cooldown_seconds > 0) {
            $state['cooldownuntil'] = time() + (int)$instance->cooldown_seconds;
        }
        $timeused = max(0, time() - (int)$state['starttime']);
        $score = gameplay_service::calculate_round_score(
            $instance,
            (int)$state['attemptsused'],
            $timeused,
            $iscompleted
        );

        $attempt = (object)[
            'playerwordsid' => $instance->id,
            'userid' => $userid,
            'wordid' => $roundwordid,
            'attempts_used' => (int)$state['attemptsused'],
            'time_used' => $timeused,
            'completed' => $iscompleted ? 1 : 0,
            'score' => $score,
            'timecreated' => time(),
        ];
        $DB->insert_record('playerwords_attempts', $attempt);
        playerwords_update_grades($instance, $userid);

        if ($iscompleted) {
            return [$state, get_string('roundwon', 'mod_playerwords'), 'success'];
        }

        return [$state, get_string('roundlost', 'mod_playerwords'), 'warning'];
    }

    /**
     * Handles a forfeit submission: marks the round as lost without an extra attempt.
     *
     * @param array $state Current session state.
     * @param \stdClass $instance Activity instance.
     * @param int $userid User id.
     * @return array [$state, $notification, $notificationtype]
     */
    private static function handle_forfeit(
        array $state,
        \stdClass $instance,
        int $userid
    ): array {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/playerwords/lib.php');

        if (empty($state['wordid']) || !empty($state['finished'])) {
            return [$state, get_string('roundfinished', 'mod_playerwords'), 'warning'];
        }

        $state['finished'] = true;
        $state['won'] = false;
        $state['forfeited'] = true;
        if ((int)$instance->cooldown_seconds > 0) {
            $state['cooldownuntil'] = time() + (int)$instance->cooldown_seconds;
        }

        $timeused = max(0, time() - (int)$state['starttime']);
        $DB->insert_record('playerwords_attempts', (object)[
            'playerwordsid' => $instance->id,
            'userid'        => $userid,
            'wordid'        => (int)$state['wordid'],
            'attempts_used' => (int)$state['attemptsused'],
            'time_used'     => $timeused,
            'completed'     => 0,
            'score'         => 0.0,
            'timecreated'   => time(),
        ]);
        playerwords_update_grades($instance, $userid);

        return [$state, get_string('roundforfeited', 'mod_playerwords'), 'warning'];
    }

    /**
     * Returns the Wordle-style feedback message for the end screen.
     *
     * @param array $state Session state.
     * @return string
     */
    private static function build_feedback_message(array $state): string {
        if (empty($state['finished'])) {
            return '';
        }
        if (!empty($state['forfeited'])) {
            return get_string('feedback_forfeited', 'mod_playerwords');
        }
        if (!empty($state['won'])) {
            $keys = [
                'feedback_genius', 'feedback_magnificent', 'feedback_impressive',
                'feedback_splendid', 'feedback_great', 'feedback_phew',
            ];
            $index = min(max(0, (int)$state['attemptsused'] - 1), count($keys) - 1);
            return get_string($keys[$index], 'mod_playerwords');
        }
        return get_string('feedback_lost', 'mod_playerwords');
    }

    /**
     * Returns a formatted countdown string, or empty if no cooldown is active.
     *
     * @param array $state Session state.
     * @return string
     */
    private static function build_cooldown_text(array $state): string {
        $until = (int)($state['cooldownuntil'] ?? 0);
        if ($until <= 0) {
            return '';
        }
        $remaining = $until - time();
        return $remaining > 0 ? format_time($remaining) : '';
    }

    /**
     * Builds template context array.
     *
     * @param \stdClass $cm Course module.
     * @param \stdClass $instance Activity instance.
     * @param array $state Session state.
     * @param string $targetword Current target.
     * @param bool $canmanagewords Whether user can manage words.
     * @return array
     */
    private static function build_template_context(
        \stdClass $cm,
        \stdClass $instance,
        array $state,
        string $targetword,
        bool $canmanagewords
    ): array {
        $rows = self::build_rows($state, $targetword, (int)$instance->max_attempts);
        $timeleft = 0;
        if ((int)$instance->timer_seconds > 0 && !empty($state['starttime'])) {
            $timeleft = max(0, (int)$instance->timer_seconds - (time() - (int)$state['starttime']));
        }

        $concept = $state['concept'] ?? '';
        $wordtext = $state['wordtext'] ?? '';
        $showrevealconcept = !empty($state['finished'])
            && $concept !== ''
            && core_text::strtolower($concept) !== core_text::strtolower($wordtext);

        return [
            'cmid' => $cm->id,
            'sesskey' => sesskey(),
            'hastargetword' => ($targetword !== ''),
            'nogamewords' => get_string('nogamewords', 'mod_playerwords'),
            'attemptslabel' => get_string('attemptslabel', 'mod_playerwords'),
            'attemptsused' => (int)$state['attemptsused'],
            'maxattempts' => (int)$instance->max_attempts,
            'timerenabled' => ((int)$instance->timer_seconds > 0),
            'timerlabel' => get_string('timerlabel', 'mod_playerwords'),
            'timeleft' => $timeleft,
            'hintlabel' => get_string('hintlabel', 'mod_playerwords'),
            'hintvalue' => !empty($state['hintrevealed']) ? ($state['hint'] ?? '') : '',
            'showhint' => !empty($state['hintrevealed']) && !empty($state['hint']),
            'canhint' => !empty($state['hint']) && empty($state['hintrevealed']) && empty($state['finished']),
            'hintbuttonlabel' => get_string('hintbuttonlabel', 'mod_playerwords'),
            'rows' => $rows,
            'roundfinished' => !empty($state['finished']),
            'guesslabel' => get_string('guesslabel', 'mod_playerwords'),
            'guessplaceholder' => get_string('guessplaceholder', 'mod_playerwords'),
            'guessmaxlength' => ($targetword !== '') ? core_text::strlen($targetword) : (int)$instance->max_length,
            'submitguess' => get_string('submitguess', 'mod_playerwords'),
            'forfeitlabel' => get_string('forfeitbutton', 'mod_playerwords'),
            'forfeitconfirm' => get_string('forfeitconfirm', 'mod_playerwords'),
            'newroundlabel' => get_string('newroundlabel', 'mod_playerwords'),
            'newroundready' => get_string('newroundready', 'mod_playerwords'),
            'cooldowncountdownlabel' => get_string('cooldowncountdownlabel', 'mod_playerwords'),
            'cooldowntext' => self::build_cooldown_text($state),
            'feedbackmessage' => self::build_feedback_message($state),
            'canmanagewords' => $canmanagewords,
            'managewordsbutton' => get_string('managewordsbutton', 'mod_playerwords'),
            'managewordsurl' => (new moodle_url('/mod/playerwords/managewords.php', ['id' => $cm->id]))->out(false),
            'showreveal' => !empty($state['finished']) && !empty($state['wordtext']),
            'revealword' => $wordtext,
            'revealwordlabel' => get_string('revealwordlabel', 'mod_playerwords'),
            'showrevealconcept' => $showrevealconcept,
            'revealconcept' => $concept,
            'revealconceptlabel' => get_string('revealconceptlabel', 'mod_playerwords'),
            'showdefinition' => !empty($state['finished']) && !empty($state['hint']),
            'revealdefinition' => $state['hint'] ?? '',
            'revealdefinitionlabel' => get_string('revealdefinitionlabel', 'mod_playerwords'),
            'keyboardlabel' => get_string('keyboard_label', 'mod_playerwords'),
            'keyboardenterlabel' => get_string('keyboard_enter', 'mod_playerwords'),
            'keyboardbackspacelabel' => get_string('keyboard_backspace', 'mod_playerwords'),
            'cooldownactive' => !empty($state['finished']) && ((int)($state['cooldownuntil'] ?? 0) > time()),
        ];
    }

    /**
     * Returns a restriction message if the user cannot start a new round, null otherwise.
     *
     * @param \stdClass $instance Activity instance.
     * @param int $userid User id.
     * @return string|null
     */
    private static function get_round_restriction_notice(\stdClass $instance, int $userid): ?string {
        global $DB;

        if ((int)$instance->max_rounds > 0) {
            $roundsplayed = $DB->count_records('playerwords_attempts', [
                'playerwordsid' => $instance->id,
                'userid'        => $userid,
            ]);
            if ($roundsplayed >= (int)$instance->max_rounds) {
                return get_string('roundlimitreached', 'mod_playerwords', $instance->max_rounds);
            }
        }

        if ((int)$instance->cooldown_seconds > 0) {
            $lastattempttime = $DB->get_field_sql(
                "SELECT MAX(timecreated) FROM {playerwords_attempts}"
                . " WHERE playerwordsid = :pid AND userid = :uid",
                ['pid' => $instance->id, 'uid' => $userid]
            );
            if (!empty($lastattempttime)) {
                $remaining = (int)$instance->cooldown_seconds - (time() - (int)$lastattempttime);
                if ($remaining > 0) {
                    return get_string('cooldownactive', 'mod_playerwords', format_time($remaining));
                }
            }
        }

        return null;
    }

    /**
     * Builds grid rows for template.
     *
     * @param array $state Session state.
     * @param string $targetword Target word.
     * @param int $maxattempts Maximum attempts.
     * @return array
     */
    private static function build_rows(array $state, string $targetword, int $maxattempts): array {
        $rows = [];
        for ($i = 0; $i < $maxattempts; $i++) {
            $rowletters = [];
            $rowstate = $state['rows'][$i] ?? null;
            if ($rowstate) {
                $letters = preg_split('//u', $rowstate['word'], -1, PREG_SPLIT_NO_EMPTY);
                foreach ($letters as $index => $letter) {
                    $cellstate = $rowstate['feedback'][$index] ?? 'absent';
                    $upperletter = core_text::strtoupper($letter);
                    $rowletters[] = [
                        'letter' => s($upperletter),
                        'state' => $cellstate,
                        'arialabel' => get_string('cell_state_' . $cellstate, 'mod_playerwords', $upperletter),
                    ];
                }
            } else if ($targetword !== '') {
                for ($j = 0; $j < core_text::strlen($targetword); $j++) {
                    $rowletters[] = [
                        'letter' => '',
                        'state' => 'empty',
                        'arialabel' => get_string('cell_state_empty', 'mod_playerwords'),
                    ];
                }
            }
            $rows[] = ['letters' => $rowletters];
        }

        return $rows;
    }
}
