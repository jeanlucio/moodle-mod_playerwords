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
 * External function: submit a player guess.
 *
 * @package    mod_playerwords
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playerwords\external;

use context_module;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use core_text;
use mod_playerwords\local\gameplay_service;
use mod_playerwords\local\word_normalizer;
use mod_playerwords\local\words_repository;

/**
 * Validates a player guess and returns per-letter Wordle-style feedback.
 */
class submit_guess extends external_api {
    /**
     * Returns parameter definitions for execute().
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid'  => new external_value(PARAM_INT, 'Course module id'),
            'guess' => new external_value(PARAM_TEXT, 'Player guess text'),
        ]);
    }

    /**
     * Validates a player guess and returns per-letter feedback.
     *
     * @param int $cmid Course module id.
     * @param string $guess Player guess.
     * @return array
     */
    public static function execute(int $cmid, string $guess): array {
        global $DB, $SESSION, $USER;

        [
            'cmid'  => $cmid,
            'guess' => $guess,
        ] = self::validate_parameters(
            self::execute_parameters(),
            ['cmid' => $cmid, 'guess' => $guess]
        );

        $cm = get_coursemodule_from_id('playerwords', $cmid, 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/playerwords:view', $context);

        $instance = $DB->get_record('playerwords', ['id' => $cm->instance], '*', MUST_EXIST);
        $userid = (int)$USER->id;
        $sessionkey = gameplay_service::build_session_key($cmid, $userid);

        if (!isset($SESSION->mod_playerwords[$sessionkey])) {
            return self::build_error_result(
                get_string('nogamewords', 'mod_playerwords'),
                'warning',
                0,
                false
            );
        }

        $state = $SESSION->mod_playerwords[$sessionkey];

        if (!empty($state['finished']) || (int)$state['attemptsused'] >= (int)$instance->max_attempts) {
            return self::build_error_result(
                get_string('roundfinished', 'mod_playerwords'),
                'warning',
                (int)$state['attemptsused'],
                !empty($state['finished'])
            );
        }

        $wordrecord = words_repository::get_approved_word_by_id(
            (int)$state['wordid'],
            (int)$instance->id
        );
        if (!$wordrecord) {
            return self::build_error_result(
                get_string('nogamewords', 'mod_playerwords'),
                'warning',
                0,
                false
            );
        }

        $targetword = word_normalizer::normalize($wordrecord->word, !empty($instance->ignore_accents));
        $normalizedguess = word_normalizer::normalize($guess, !empty($instance->ignore_accents));
        $targetlength = core_text::strlen($targetword);

        if (core_text::strlen($normalizedguess) !== $targetlength) {
            return self::build_error_result(
                get_string('guesslengthmismatch', 'mod_playerwords', $targetlength),
                'warning',
                (int)$state['attemptsused'],
                false
            );
        }

        $state['attemptsused']++;
        $feedback = gameplay_service::build_letter_feedback($normalizedguess, $targetword);

        $feedbackresult = [];
        foreach (preg_split('//u', $normalizedguess, -1, PREG_SPLIT_NO_EMPTY) as $index => $letter) {
            $feedbackresult[] = [
                'letter' => core_text::strtoupper($letter),
                'state'  => $feedback[$index] ?? 'absent',
            ];
        }

        $state['rows'][] = ['word' => $normalizedguess, 'feedback' => $feedback];

        $iscompleted = ($normalizedguess === $targetword);
        $outofattempts = ((int)$state['attemptsused'] >= (int)$instance->max_attempts);
        $outoftime = false;
        if ((int)$instance->timer_seconds > 0) {
            $elapsed = time() - (int)$state['starttime'];
            $outoftime = $elapsed >= (int)$instance->timer_seconds;
        }

        $score = 0.0;
        $notification = '';
        $notificationtype = '';

        if ($iscompleted || $outofattempts || $outoftime) {
            $state['finished'] = true;
            $timeused = max(0, time() - (int)$state['starttime']);
            $score = gameplay_service::calculate_round_score(
                $instance,
                (int)$state['attemptsused'],
                $timeused,
                $iscompleted
            );
            $attemptid = $DB->insert_record('playerwords_attempts', (object)[
                'playerwordsid' => $instance->id,
                'userid'        => $userid,
                'wordid'        => (int)$state['wordid'],
                'attempts_used' => (int)$state['attemptsused'],
                'time_used'     => $timeused,
                'completed'     => $iscompleted ? 1 : 0,
                'score'         => $score,
                'timecreated'   => time(),
            ]);
            $event = \mod_playerwords\event\round_completed::create([
                'objectid' => $attemptid,
                'context'  => $context,
                'other'    => [
                    'completed'    => $iscompleted,
                    'score'        => $score,
                    'attemptsused' => (int)$state['attemptsused'],
                    'timeused'     => $timeused,
                    'wordid'       => (int)$state['wordid'],
                ],
            ]);
            $event->trigger();
            $notification = $iscompleted
                ? get_string('roundwon', 'mod_playerwords')
                : get_string('roundlost', 'mod_playerwords');
            $notificationtype = $iscompleted ? 'success' : 'warning';
        }

        $SESSION->mod_playerwords[$sessionkey] = $state;

        return [
            'feedback'         => $feedbackresult,
            'attemptsused'     => (int)$state['attemptsused'],
            'finished'         => !empty($state['finished']),
            'won'              => $iscompleted,
            'score'            => $score,
            'timeleft'         => self::compute_timeleft($instance, $state),
            'notification'     => $notification,
            'notificationtype' => $notificationtype,
        ];
    }

    /**
     * Returns the structure of the execute() return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'feedback' => new external_multiple_structure(
                new external_single_structure([
                    'letter' => new external_value(PARAM_TEXT, 'Uppercase letter'),
                    'state'  => new external_value(PARAM_ALPHA, 'Cell state: correct, present or absent'),
                ]),
                'Per-letter feedback'
            ),
            'attemptsused'     => new external_value(PARAM_INT, 'Attempts used in this round'),
            'finished'         => new external_value(PARAM_BOOL, 'Whether the round has ended'),
            'won'              => new external_value(PARAM_BOOL, 'Whether the player guessed correctly'),
            'score'            => new external_value(PARAM_FLOAT, 'Score for this round'),
            'timeleft'         => new external_value(PARAM_INT, 'Seconds remaining, 0 if timer is disabled'),
            'notification'     => new external_value(PARAM_TEXT, 'User-facing feedback message'),
            'notificationtype' => new external_value(
                PARAM_ALPHA,
                'Notification type: success or warning',
                VALUE_DEFAULT,
                ''
            ),
        ]);
    }

    /**
     * Computes seconds remaining for the current round.
     *
     * @param \stdClass $instance Activity instance.
     * @param array $state Session state.
     * @return int
     */
    private static function compute_timeleft(\stdClass $instance, array $state): int {
        if ((int)$instance->timer_seconds <= 0 || empty($state['starttime'])) {
            return 0;
        }
        return max(0, (int)$instance->timer_seconds - (time() - (int)$state['starttime']));
    }

    /**
     * Builds an error response without modifying session state.
     *
     * @param string $message Notification message.
     * @param string $type Notification type.
     * @param int $attemptsused Current attempts used.
     * @param bool $finished Whether the round is already finished.
     * @return array
     */
    private static function build_error_result(
        string $message,
        string $type,
        int $attemptsused,
        bool $finished
    ): array {
        return [
            'feedback'         => [],
            'attemptsused'     => $attemptsused,
            'finished'         => $finished,
            'won'              => false,
            'score'            => 0.0,
            'timeleft'         => 0,
            'notification'     => $message,
            'notificationtype' => $type,
        ];
    }
}
