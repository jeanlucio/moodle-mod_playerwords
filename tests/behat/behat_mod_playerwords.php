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
 * Step definitions for mod_playerwords Behat tests.
 *
 * @package    mod_playerwords
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:disable moodle.Files.RequireLogin.Missing

use Behat\Gherkin\Node\TableNode;

/**
 * Custom Behat step definitions for the PlayerWords activity.
 */
class behat_mod_playerwords extends behat_base {
    /**
     * Seeds approved manual words directly into an instance's pool.
     *
     * Bypasses managewords.php and the approval flow, so a scenario can rely on a
     * deterministic pool (e.g. a single word within the instance's length bounds) instead
     * of the pseudo-random round selection.
     *
     * @param string $activityname PlayerWords activity name.
     * @param TableNode $data Table with a required "word" column and an optional "hint" one.
     * @Given the following PlayerWords words exist in activity :activityname:
     */
    public function the_following_playerwords_words_exist_in_activity(string $activityname, TableNode $data): void {
        $playerwordsid = $this->get_playerwords_id($activityname);
        $generator = behat_util::get_data_generator()->get_plugin_generator('mod_playerwords');

        foreach ($data->getHash() as $row) {
            $generator->create_word($playerwordsid, $row['word'], $row['hint'] ?? '');
        }
    }

    /**
     * Seeds finished attempt rows directly, without playing a real round.
     *
     * Used to fill the teacher attempt report or the ranking with enough rows to exercise
     * pagination, sorting and tie-breaking — driving dozens of real rounds through the UI
     * would be slow and flaky. Rows without an explicit "created" column get a strictly
     * decreasing timestamp (one minute apart, in table order) so "sort by date" scenarios
     * have a deterministic order to assert against.
     *
     * @param string $activityname PlayerWords activity name.
     * @param TableNode $data Table with columns: user, word, attemptsused, timeused,
     *     completed, score, rankingpoints, created (all but user and word are optional).
     * @Given the following PlayerWords attempts exist in activity :activityname:
     */
    public function the_following_playerwords_attempts_exist_in_activity(string $activityname, TableNode $data): void {
        global $DB;

        $playerwordsid = $this->get_playerwords_id($activityname);
        $generator = behat_util::get_data_generator()->get_plugin_generator('mod_playerwords');
        $now = time();

        foreach ($data->getHash() as $index => $row) {
            $userid = $DB->get_field('user', 'id', ['username' => $row['user']], MUST_EXIST);
            $wordid = $DB->get_field_sql(
                'SELECT id FROM {playerwords_words} WHERE playerwordsid = :pwid AND word = :word',
                ['pwid' => $playerwordsid, 'word' => $row['word']],
                MUST_EXIST
            );

            $columnmap = [
                'attemptsused'  => 'attempts_used',
                'timeused'      => 'time_used',
                'completed'     => 'completed',
                'score'         => 'score',
                'rankingpoints' => 'rankingpoints',
            ];
            $overrides = [];
            foreach ($columnmap as $column => $field) {
                if (isset($row[$column]) && $row[$column] !== '') {
                    $overrides[$field] = $row[$column];
                }
            }
            $created = (isset($row['created']) && $row['created'] !== '') ? (int) $row['created'] : ($now - $index * 60);
            $overrides['timecreated'] = $created;
            $overrides['timefinished'] = $created;

            $generator->create_attempt($playerwordsid, (int) $userid, (int) $wordid, $overrides);
        }
    }

    /**
     * Resolves a PlayerWords activity name to its instance id.
     *
     * @param string $activityname Activity name as configured in the instance.
     * @return int
     */
    private function get_playerwords_id(string $activityname): int {
        global $DB;

        return (int) $DB->get_field('playerwords', 'id', ['name' => $activityname], MUST_EXIST);
    }
}
