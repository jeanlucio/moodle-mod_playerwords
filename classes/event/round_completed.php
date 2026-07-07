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
 * Event fired when a PlayerWords round ends (won, out of attempts, or timed out).
 *
 * @package    mod_playerwords
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playerwords\event;

/**
 * Fired by round_service::finish_round() whenever a round ends — by a winning or losing
 * guess, a forfeit, or a timeout — and a playerwords_attempts record is persisted.
 *
 * Expected data:
 *   objectid  — id of the playerwords_attempts record just created
 *   context   — module context
 *   other     — [
 *                 'completed'    => bool,   // true = player guessed correctly
 *                 'score'        => float,
 *                 'attemptsused' => int,
 *                 'timeused'     => int,    // seconds
 *                 'wordid'       => int,
 *               ]
 */
class round_completed extends \core\event\base {
    #[\Override]
    protected function init(): void {
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
        $this->data['objecttable'] = 'playerwords_attempts';
    }

    #[\Override]
    public static function get_name(): string {
        return get_string('event_round_completed', 'mod_playerwords');
    }

    #[\Override]
    public function get_description(): string {
        $outcome = !empty($this->other['completed']) ? 'won' : 'did not win';
        return "The user with id '{$this->userid}' completed a round and {$outcome} in the playerwords activity " .
            "with course module id '{$this->contextinstanceid}'. " .
            "Score: {$this->other['score']}. Attempts used: {$this->other['attemptsused']}.";
    }

    #[\Override]
    public static function get_objectid_mapping(): array {
        return ['db' => 'playerwords_attempts', 'restore' => 'playerwords_attempts'];
    }
}
