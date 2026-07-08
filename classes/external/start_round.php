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
 * External function: start the timer for the current PlayerWords round.
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
 * Leaves the lobby and starts the round timer, optionally consuming a PlayerHUD item cost.
 *
 * The target word itself is already sitting in session from the page's GET-time
 * ensure_round_state() call; this only starts the clock.
 */
class start_round extends external_api {
    /**
     * Returns parameter definitions for execute().
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
        ]);
    }

    /**
     * Starts the round timer for the current user.
     *
     * @param int $cmid Course module id.
     * @return array
     */
    public static function execute(int $cmid): array {
        global $DB, $USER;

        ['cmid' => $cmid] = self::validate_parameters(self::execute_parameters(), ['cmid' => $cmid]);

        $cm = get_coursemodule_from_id('playerwords', $cmid, 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/playerwords:view', $context);

        $instance = $DB->get_record('playerwords', ['id' => $cm->instance], '*', MUST_EXIST);
        $userid = (int)$USER->id;

        $state = round_service::load_state($cmid, $userid);
        [$state, $targetword] = round_service::ensure_round_state($state, $instance, $cmid, $userid);

        if ($targetword === '' || !empty($state['finished']) || !empty($state['roundstarted'])) {
            return [
                'success'          => false,
                'notification'     => '',
                'notificationtype' => '',
                'roundpanel'       => round_presenter::build_round_panel_context(
                    $instance,
                    $cm,
                    $state,
                    $targetword,
                    $userid
                ),
            ];
        }

        [$state, $notification, $notificationtype] = round_service::start_round($state, $instance, $userid);
        round_service::save_state($cmid, $userid, $state);

        return [
            'success'          => ($notification === null),
            'notification'     => $notification ?? '',
            'notificationtype' => $notificationtype ?? '',
            'roundpanel'       => round_presenter::build_round_panel_context(
                $instance,
                $cm,
                $state,
                $targetword,
                $userid
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
            'success'          => new external_value(PARAM_BOOL, 'Whether the round timer started'),
            'notification'     => new external_value(PARAM_TEXT, 'User-facing feedback message', VALUE_DEFAULT, ''),
            'notificationtype' => new external_value(PARAM_ALPHA, 'Notification type', VALUE_DEFAULT, ''),
            'roundpanel'       => self::roundpanel_structure(),
        ]);
    }

    /**
     * Returns the structure matching mod_playerwords/round_panel, reused by new_round too.
     *
     * @return external_single_structure
     */
    public static function roundpanel_structure(): external_single_structure {
        $ownfields = [
            'attemptslabel' => new external_value(PARAM_TEXT, 'Attempts label'),
            'attemptsused' => new external_value(PARAM_INT, 'Attempts used in this round'),
            'maxattempts' => new external_value(PARAM_INT, 'Configured maximum attempts'),
            'timerenabled' => new external_value(PARAM_BOOL, 'Whether the timer is enabled'),
            'timerlabel' => new external_value(PARAM_TEXT, 'Timer label'),
            'timeleft' => new external_value(PARAM_INT, 'Seconds remaining, 0 if timer is disabled'),
            'hintlabel' => new external_value(PARAM_TEXT, 'Hint label'),
            'hintvalue' => new external_value(PARAM_RAW, 'Hint text, empty when not revealed'),
            'showhint' => new external_value(PARAM_BOOL, 'Whether the hint is shown'),
            'canhint' => new external_value(PARAM_BOOL, 'Whether the hint can be revealed'),
            'hintbuttonlabel' => new external_value(PARAM_TEXT, 'Hint button label'),
            'hudhintcost' => new external_value(PARAM_BOOL, 'Whether revealing the hint costs a PlayerHUD item'),
            'hudhintcostlabel' => new external_value(PARAM_TEXT, 'PlayerHUD hint cost label'),
            'canaffordhint' => new external_value(PARAM_BOOL, 'Whether the user can afford to reveal the hint'),
            'rows' => new external_multiple_structure(
                new external_single_structure([
                    'letters' => new external_multiple_structure(
                        new external_single_structure([
                            'letter' => new external_value(PARAM_TEXT, 'Uppercase letter, empty when not played'),
                            'state' => new external_value(PARAM_ALPHA, 'Cell state'),
                            'arialabel' => new external_value(PARAM_TEXT, 'Accessible label for this cell'),
                        ]),
                        'Cells in this row'
                    ),
                ]),
                'Grid rows'
            ),
            'roundfinished' => new external_value(PARAM_BOOL, 'Whether the round has ended'),
            'guesslabel' => new external_value(PARAM_TEXT, 'Guess input label'),
            'guessplaceholder' => new external_value(PARAM_TEXT, 'Guess input placeholder'),
            'guessmaxlength' => new external_value(PARAM_INT, 'Maximum guess length'),
            'submitguess' => new external_value(PARAM_TEXT, 'Submit button label'),
            'forfeitlabel' => new external_value(PARAM_TEXT, 'Forfeit button label'),
            'forfeitconfirm' => new external_value(PARAM_TEXT, 'Forfeit confirmation message'),
            'keyboardlabel' => new external_value(PARAM_TEXT, 'On-screen keyboard aria label'),
            'keyboardenterlabel' => new external_value(PARAM_TEXT, 'Enter key aria label'),
            'keyboardbackspacelabel' => new external_value(PARAM_TEXT, 'Backspace key aria label'),
        ];

        return new external_single_structure($ownfields + submit_guess::roundresult_structure()->keys);
    }
}
