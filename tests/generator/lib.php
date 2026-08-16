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
 * Data generator for mod_playerwords.
 *
 * @package    mod_playerwords
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Data generator class for the playerwords activity module.
 */
class mod_playerwords_generator extends testing_module_generator {
    /**
     * Creates a new instance of the playerwords activity.
     *
     * @param array|\stdClass|null $record Field values for the instance.
     * @param array|null $options Module options (e.g. idnumber, section).
     * @return \stdClass Created course-module record.
     */
    public function create_instance($record = null, ?array $options = null): \stdClass {
        $record = (object)(array)$record;

        $defaults = [
            'sources'            => 1,
            'glossaryid'         => 0,
            'min_length'         => 4,
            'max_length'         => 6,
            'max_attempts'       => 6,
            'timer_seconds'      => 0,
            'show_ranking'       => 1,
            'wordmode'           => 1,
            'max_rounds'         => 0,
            'hints_enabled'      => 1,
            'cooldown_seconds'   => 86400,
            'completionattempts' => 0,
            'grade'              => 100,
            'gradepass'          => 0.0,
            'grademethod'        => 1,
            'hud_round_cost_item' => 0,
            'hud_round_cost_qty'  => 1,
            'hud_hint_cost_item'  => 0,
            'hud_hint_cost_qty'   => 1,
        ];

        foreach ($defaults as $field => $value) {
            if (!isset($record->$field)) {
                $record->$field = $value;
            }
        }

        return parent::create_instance($record, $options);
    }

    /**
     * Inserts an already-approved manual word directly into an instance's pool.
     *
     * Bypasses managewords.php and the approval flow entirely, so Behat scenarios can seed
     * a deterministic pool (e.g. a single word within the instance's length bounds) instead
     * of depending on the pseudo-random round selection.
     *
     * @param int $playerwordsid Instance id (playerwords.id, not the course module id).
     * @param string $word Game word; caller is responsible for keeping it within the
     *     instance's min_length/max_length.
     * @param string $hint Optional hint text.
     * @return \stdClass Created playerwords_words record.
     */
    public function create_word(int $playerwordsid, string $word, string $hint = ''): \stdClass {
        global $DB;

        $record = (object) [
            'playerwordsid' => $playerwordsid,
            'word'          => $word,
            'hint'          => $hint,
            'source'        => 'manual',
            'glossaryid'    => 0,
            'approved'      => 1,
            'timecreated'   => time(),
            'timemodified'  => time(),
            'addedby'       => 0,
        ];
        $record->id = $DB->insert_record('playerwords_words', $record);

        return $record;
    }

    /**
     * Inserts a finished attempt row directly, without playing a real round.
     *
     * Used to seed volume for report/ranking Behat scenarios (pagination, sorting) where
     * driving dozens of real rounds through the UI would be slow and flaky.
     *
     * @param int $playerwordsid Instance id.
     * @param int $userid Student user id.
     * @param int $wordid Word id the attempt is tied to.
     * @param array $data Optional field overrides: attempts_used, time_used, completed,
     *     score, rankingpoints, timecreated, timefinished.
     * @return \stdClass Created playerwords_attempts record.
     */
    public function create_attempt(int $playerwordsid, int $userid, int $wordid, array $data = []): \stdClass {
        global $DB;

        $now = time();
        $record = (object) array_merge([
            'playerwordsid' => $playerwordsid,
            'userid'        => $userid,
            'wordid'        => $wordid,
            'attempts_used' => 1,
            'time_used'     => 30,
            'completed'     => 1,
            'score'         => 100.0,
            'rankingpoints' => 100.0,
            'timecreated'   => $now,
            'timefinished'  => $now,
        ], $data);
        $record->id = $DB->insert_record('playerwords_attempts', $record);

        return $record;
    }
}
