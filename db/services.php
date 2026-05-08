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
    'mod_playerwords_start_new_round' => [
        'classname'     => 'mod_playerwords\external\start_new_round',
        'description'   => 'Start a new round for a PlayerWords activity.',
        'type'          => 'write',
        'ajax'          => true,
        'capabilities'  => 'mod/playerwords:view',
        'loginrequired' => true,
    ],
];
