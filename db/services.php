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
 * External function definitions for mod_playerwords.
 *
 * @package    mod_playerwords
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'mod_playerwords_submit_guess' => [
        'classname'     => 'mod_playerwords\external\submit_guess',
        'description'   => 'Submit a guess for the current PlayerWords round.',
        'type'          => 'write',
        'ajax'          => true,
        'capabilities'  => 'mod/playerwords:view',
        'loginrequired' => true,
    ],
    'mod_playerwords_reveal_hint' => [
        'classname'     => 'mod_playerwords\external\reveal_hint',
        'description'   => 'Reveal the hint for the current PlayerWords round.',
        'type'          => 'write',
        'ajax'          => true,
        'capabilities'  => 'mod/playerwords:view',
        'loginrequired' => true,
    ],
    'mod_playerwords_end_round' => [
        'classname'     => 'mod_playerwords\external\end_round',
        'description'   => 'End the current PlayerWords round by forfeit or timeout.',
        'type'          => 'write',
        'ajax'          => true,
        'capabilities'  => 'mod/playerwords:view',
        'loginrequired' => true,
    ],
    'mod_playerwords_start_round' => [
        'classname'     => 'mod_playerwords\external\start_round',
        'description'   => 'Start the timer for the current PlayerWords round.',
        'type'          => 'write',
        'ajax'          => true,
        'capabilities'  => 'mod/playerwords:view',
        'loginrequired' => true,
    ],
    'mod_playerwords_new_round' => [
        'classname'     => 'mod_playerwords\external\new_round',
        'description'   => 'Reset the session so the next PlayerWords round picks a fresh word.',
        'type'          => 'write',
        'ajax'          => true,
        'capabilities'  => 'mod/playerwords:view',
        'loginrequired' => true,
    ],
    'mod_playerwords_count_eligible_words' => [
        'classname'     => 'mod_playerwords\external\count_eligible_words',
        'description'   => 'Count approved pool words within a given length range for the settings form.',
        'type'          => 'read',
        'ajax'          => true,
        'capabilities'  => 'mod/playerwords:addinstance',
        'loginrequired' => true,
    ],
    'mod_playerwords_count_glossary_candidates' => [
        'classname'     => 'mod_playerwords\external\count_glossary_candidates',
        'description'   => 'Preview how many glossary-sourced words fit a length range, before an activity exists.',
        'type'          => 'read',
        'ajax'          => true,
        'capabilities'  => 'mod/playerwords:addinstance',
        'loginrequired' => true,
    ],
];
