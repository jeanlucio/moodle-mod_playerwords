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
 * External function: reveal the hint for the current PlayerWords round.
 *
 * @package    mod_playerwords
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playerwords\external;

use context_module;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use mod_playerwords\local\round_service;

/**
 * Reveals the round hint, optionally consuming a PlayerHUD item cost.
 */
class reveal_hint extends external_api {
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
     * Reveals the hint for the current round.
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

        if ($targetword === '' || !empty($state['finished'])) {
            return self::result(false, '', get_string('roundfinished', 'mod_playerwords'), 'warning');
        }

        if (!empty($state['hintrevealed'])) {
            // Idempotent: already revealed, never charge the PlayerHUD cost twice.
            return self::result(true, $state['hint'] ?? '', '', '');
        }

        if (empty($state['hint'])) {
            return self::result(false, '', '', '');
        }

        [$state, $notification, $notificationtype] = round_service::reveal_hint($state, $instance, $userid);
        round_service::save_state($cmid, $userid, $state);

        return self::result(
            empty($notification),
            !empty($state['hintrevealed']) ? ($state['hint'] ?? '') : '',
            $notification ?? '',
            $notificationtype ?? ''
        );
    }

    /**
     * Returns the structure of the execute() return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success'          => new external_value(PARAM_BOOL, 'Whether the hint is now revealed'),
            'hintvalue'        => new external_value(PARAM_RAW, 'Hint text, empty when not revealed'),
            'notification'     => new external_value(PARAM_TEXT, 'User-facing feedback message', VALUE_DEFAULT, ''),
            'notificationtype' => new external_value(PARAM_ALPHA, 'Notification type', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Builds one result array matching execute_returns().
     *
     * @param bool $success Whether the hint is revealed.
     * @param string $hintvalue Hint text, empty when not revealed.
     * @param string $notification Notification message.
     * @param string $notificationtype Notification type.
     * @return array
     */
    private static function result(bool $success, string $hintvalue, string $notification, string $notificationtype): array {
        return [
            'success'          => $success,
            'hintvalue'        => $hintvalue,
            'notification'     => $notification,
            'notificationtype' => $notificationtype,
        ];
    }
}
