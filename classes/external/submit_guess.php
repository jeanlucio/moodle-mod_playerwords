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
use mod_playerwords\local\round_presenter;
use mod_playerwords\local\round_service;

/**
 * Validates a player guess and returns per-letter Wordle-style feedback.
 *
 * All state mutation is delegated to round_service so this stays the single
 * implementation shared with the classic page render in view_page_service.
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
        global $DB, $USER;

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

        $state = round_service::load_state($cmid, $userid);
        [$state, $targetword, $roundwordid] = round_service::ensure_round_state($state, $instance, $cmid, $userid);

        [$state, $feedback, $notification, $notificationtype, $toast] = round_service::submit_guess(
            $state,
            $instance,
            $cmid,
            $userid,
            $roundwordid,
            $targetword,
            $guess
        );

        round_service::save_state($cmid, $userid, $state);

        $feedbackresult = [];
        if ($feedback !== null) {
            $lastrow = end($state['rows']);
            $feedbackresult = round_presenter::build_row_letters($lastrow['word'], $lastrow['feedback']);
        }

        $roundfinished = !empty($state['finished']);

        return [
            'feedback'         => $feedbackresult,
            'attemptsused'     => (int)$state['attemptsused'],
            'maxattempts'      => (int)$instance->max_attempts,
            'finished'         => $roundfinished,
            'won'              => !empty($state['won']),
            'timeleft'         => self::compute_timeleft($instance, $state),
            'notification'     => $notification ?? '',
            'notificationtype' => $notificationtype ?? '',
            'toast'            => $toast ?? true,
            'roundresult'      => round_presenter::build_round_result_context(
                $instance,
                $cm,
                $state,
                $userid,
                $roundfinished
            ),
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
                    'arialabel' => new external_value(PARAM_TEXT, 'Accessible label for this cell'),
                ]),
                'Per-letter feedback for the guess just submitted, empty when rejected'
            ),
            'attemptsused'     => new external_value(PARAM_INT, 'Attempts used in this round'),
            'maxattempts'      => new external_value(PARAM_INT, 'Configured maximum attempts'),
            'finished'         => new external_value(PARAM_BOOL, 'Whether the round has ended'),
            'won'              => new external_value(PARAM_BOOL, 'Whether the player guessed correctly'),
            'timeleft'         => new external_value(PARAM_INT, 'Seconds remaining, 0 if timer is disabled'),
            'notification'     => new external_value(PARAM_TEXT, 'User-facing feedback message', VALUE_DEFAULT, ''),
            'notificationtype' => new external_value(
                PARAM_ALPHA,
                'Notification type: success or warning',
                VALUE_DEFAULT,
                ''
            ),
            'toast' => new external_value(
                PARAM_BOOL,
                'Whether to show the notification as an auto-dismissing toast instead of a persistent one',
                VALUE_DEFAULT,
                true
            ),
            'roundresult' => self::roundresult_structure(),
        ]);
    }

    /**
     * Returns the structure shared by every response's post-round result fields.
     *
     * Always present, but only meaningful when the response's "finished" field is true —
     * see round_presenter::build_round_result_context() for the security invariant this
     * structure exists to make explicit and testable: the target word/definition are never
     * populated in the returned array until the round has actually finished server-side.
     *
     * @return external_single_structure
     */
    public static function roundresult_structure(): external_single_structure {
        return new external_single_structure([
            'feedbackmessage'        => new external_value(PARAM_TEXT, 'End-of-round flavour message'),
            'showreveal'             => new external_value(PARAM_BOOL, 'Whether to show the revealed word'),
            'revealword'             => new external_value(PARAM_TEXT, 'The correct word, empty until finished'),
            'revealwordlabel'        => new external_value(PARAM_TEXT, 'Label for the revealed word'),
            'showrevealconcept'      => new external_value(PARAM_BOOL, 'Whether to show the glossary concept'),
            'revealconcept'          => new external_value(PARAM_TEXT, 'Glossary concept, empty until finished'),
            'revealconceptlabel'     => new external_value(PARAM_TEXT, 'Label for the revealed concept'),
            'showdefinition'         => new external_value(PARAM_BOOL, 'Whether to show the definition/hint'),
            'revealdefinition'       => new external_value(PARAM_RAW, 'Definition or hint, empty until finished'),
            'revealdefinitionlabel'  => new external_value(PARAM_TEXT, 'Label for the revealed definition'),
            'cooldownuntil'          => new external_value(PARAM_INT, 'Cooldown expiry epoch, 0 if inactive'),
            'cooldowntext'           => new external_value(PARAM_TEXT, 'Formatted cooldown countdown text'),
            'cooldowncountdownlabel' => new external_value(PARAM_TEXT, 'Label for the cooldown countdown'),
            'cooldownactive'         => new external_value(PARAM_BOOL, 'Whether a cooldown is currently active'),
            'newroundlabel'          => new external_value(PARAM_TEXT, 'Label for the new-round action'),
            'showranking'            => new external_value(PARAM_BOOL, 'Whether ranking is enabled'),
            'rankingurl'             => new external_value(PARAM_URL, 'Full ranking page URL'),
            'rankingviewfulllabel'   => new external_value(PARAM_TEXT, 'Label for the full ranking link'),
            'rankingtitle'           => new external_value(PARAM_TEXT, 'Ranking panel title'),
            'rankingpositionlabel'   => new external_value(PARAM_TEXT, 'Ranking position column label'),
            'rankingplayerlabel'     => new external_value(PARAM_TEXT, 'Ranking player column label'),
            'rankingpointslabel'     => new external_value(PARAM_TEXT, 'Ranking points column label'),
            'rankingemptylabel'      => new external_value(PARAM_TEXT, 'Label shown when ranking has no rows'),
            'rankingrows' => new external_multiple_structure(
                new external_single_structure([
                    'position'      => new external_value(PARAM_INT, 'Rank position'),
                    'fullname'      => new external_value(PARAM_TEXT, 'Player full name'),
                    'totalscore'    => new external_value(PARAM_TEXT, 'Total score, formatted to 2 decimals'),
                    'iscurrentuser' => new external_value(PARAM_BOOL, 'Whether this row is the current user'),
                ]),
                'Top ranking rows'
            ),
            'rankinghasoutsider' => new external_value(PARAM_BOOL, 'Whether an outsider row is present'),
            'rankingoutsiderrow' => new external_single_structure([
                'position'      => new external_value(PARAM_INT, 'Rank position'),
                'fullname'      => new external_value(PARAM_TEXT, 'Player full name'),
                'totalscore'    => new external_value(PARAM_TEXT, 'Total score, formatted to 2 decimals'),
                'iscurrentuser' => new external_value(PARAM_BOOL, 'Whether this row is the current user'),
            ], 'Current user row when outside the top ranking'),
            'rankingempty' => new external_value(PARAM_BOOL, 'Whether the ranking has no rows at all'),
            'showgradesofar' => new external_value(PARAM_BOOL, 'Whether the grade-so-far summary is shown'),
            'gradesofarmessage' => new external_value(
                PARAM_TEXT,
                'Grading method and current computed grade, empty when not applicable'
            ),
            'roundsplayedlabel' => new external_value(
                PARAM_TEXT,
                'Rounds played counter, e.g. "3 / 10", empty until finished'
            ),
            'huditemgrantedlabel' => new external_value(
                PARAM_TEXT,
                'PlayerHUD item granted for winning, empty when not applicable'
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
        $reference = !empty($state['finished']) && !empty($state['endtime'])
            ? (int)$state['endtime']
            : time();
        return max(0, (int)$instance->timer_seconds - ($reference - (int)$state['starttime']));
    }
}
