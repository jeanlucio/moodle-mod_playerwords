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
 * External function: start a new game round.
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
use core_text;
use mod_playerwords\local\gameplay_service;
use mod_playerwords\local\word_normalizer;
use mod_playerwords\local\words_repository;

/**
 * Resets session state and picks a new target word for the activity.
 */
class start_new_round extends external_api {
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
     * Resets the round state and picks a new word.
     *
     * @param int $cmid Course module id.
     * @return array
     */
    public static function execute(int $cmid): array {
        global $DB, $SESSION, $USER;

        ['cmid' => $cmid] = self::validate_parameters(
            self::execute_parameters(),
            ['cmid' => $cmid]
        );

        $cm = get_coursemodule_from_id('playerwords', $cmid, 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        self::validate_context($context);
        require_capability('mod/playerwords:view', $context);

        $instance = $DB->get_record('playerwords', ['id' => $cm->instance], '*', MUST_EXIST);
        $userid = (int)$USER->id;
        $sessionkey = gameplay_service::build_session_key($cmid, $userid);

        if (!isset($SESSION->mod_playerwords)) {
            $SESSION->mod_playerwords = [];
        }

        $pickedword = words_repository::pick_round_word($instance);
        if (!$pickedword) {
            $SESSION->mod_playerwords[$sessionkey] = [
                'wordid'       => 0,
                'attemptsused' => 0,
                'starttime'    => 0,
                'hint'         => '',
                'rows'         => [],
                'finished'     => false,
            ];
            return [
                'wordlength'    => 0,
                'hint'          => '',
                'hastargetword' => false,
            ];
        }

        $targetword = word_normalizer::normalize($pickedword->word, !empty($instance->ignore_accents));
        $SESSION->mod_playerwords[$sessionkey] = [
            'wordid'       => (int)$pickedword->id,
            'attemptsused' => 0,
            'starttime'    => time(),
            'hint'         => $pickedword->hint ?? '',
            'rows'         => [],
            'finished'     => false,
        ];

        return [
            'wordlength'    => core_text::strlen($targetword),
            'hint'          => $pickedword->hint ?? '',
            'hastargetword' => true,
        ];
    }

    /**
     * Returns the structure of the execute() return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'wordlength'    => new external_value(
                PARAM_INT,
                'Number of letters in the new word, 0 if unavailable'
            ),
            'hint'          => new external_value(PARAM_TEXT, 'Optional hint text for the new word'),
            'hastargetword' => new external_value(PARAM_BOOL, 'Whether a word is available for the round'),
        ]);
    }
}
