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
 * External function tests for start_round.
 *
 * @package    mod_playerwords
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playerwords\external;

use core_external\external_api;
use mod_playerwords\local\round_service;

/**
 * Tests for the mod_playerwords_start_round web service.
 */
final class start_round_test extends \advanced_testcase {
    /** @var \stdClass Course used by the tests. */
    private \stdClass $course;

    /** @var \stdClass Enrolled student. */
    private \stdClass $student;

    #[\Override]
    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        $this->resetAfterTest();
        require_once($CFG->dirroot . '/mod/playerwords/lib.php');
        $this->course = $this->getDataGenerator()->create_course();
        $this->student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($this->student->id, $this->course->id, 'student');
    }

    /**
     * Creates a playerwords instance with one approved word, timer enabled.
     *
     * @param array $overrides Instance field overrides.
     * @return \stdClass Instance record with the ->cmid field added.
     */
    private function make_instance(array $overrides = []): \stdClass {
        global $DB;

        $record = array_merge([
            'course'        => $this->course->id,
            'min_length'    => 4,
            'max_length'    => 4,
            'timer_minutes' => 2,
        ], $overrides);

        $instance = $this->getDataGenerator()->create_module('playerwords', $record);

        $DB->insert_record('playerwords_words', (object)[
            'playerwordsid' => $instance->id,
            'word'          => 'boca',
            'concept'       => 'boca',
            'hint'          => 'dica',
            'source'        => 'manual',
            'glossaryid'    => 0,
            'approved'      => 1,
            'timecreated'   => time(),
            'addedby'       => $this->student->id,
        ]);

        $record = $DB->get_record('playerwords', ['id' => $instance->id], '*', MUST_EXIST);
        $record->cmid = $instance->cmid;
        return $record;
    }

    /**
     * Calls the mod_playerwords_start_round web service through the real dispatch path.
     *
     * @param int $cmid Course module id.
     * @return array Response shaped as ['error' => bool, 'data' => array|null, ...].
     */
    private function call_start_round(int $cmid): array {
        $_POST['sesskey'] = sesskey();
        return external_api::call_external_function('mod_playerwords_start_round', ['cmid' => $cmid]);
    }

    /**
     * Tests that starting the round begins the timer and marks it as started.
     *
     * @covers \mod_playerwords\external\start_round::execute
     * @return void
     */
    public function test_starts_round(): void {
        $instance = $this->make_instance();
        $this->setUser($this->student);

        $result = $this->call_start_round($instance->cmid);

        $this->assertFalse($result['error']);
        $this->assertTrue($result['data']['success']);
        // The timer starts counting down between start_round() persisting starttime and this
        // response being built, so a slow run can observe 119 instead of 120. Accept both.
        $this->assertGreaterThanOrEqual(119, $result['data']['roundpanel']['timeleft']);
        $this->assertLessThanOrEqual(120, $result['data']['roundpanel']['timeleft']);
        $this->assertTrue($result['data']['roundpanel']['timerenabled']);
        $this->assertFalse($result['data']['roundpanel']['roundfinished']);

        $state = round_service::load_state($instance->cmid, $this->student->id);
        $this->assertTrue($state['roundstarted']);
    }

    /**
     * Tests that starting an already-started round is rejected without restarting the timer.
     *
     * @covers \mod_playerwords\external\start_round::execute
     * @return void
     */
    public function test_rejects_when_already_started(): void {
        $instance = $this->make_instance();
        $this->setUser($this->student);

        $this->call_start_round($instance->cmid);
        $second = $this->call_start_round($instance->cmid);

        $this->assertFalse($second['error']);
        $this->assertFalse($second['data']['success']);
    }

    /**
     * Tests that a user without the view capability in the module context is rejected.
     *
     * @covers \mod_playerwords\external\start_round::execute
     * @return void
     */
    public function test_requires_view_capability(): void {
        $instance = $this->make_instance();
        $outsider = $this->getDataGenerator()->create_user();
        $this->setUser($outsider);

        $result = $this->call_start_round($instance->cmid);

        $this->assertTrue($result['error']);
    }

    /**
     * Tests that an insufficient PlayerHUD item balance blocks starting the round.
     *
     * @covers \mod_playerwords\external\start_round::execute
     * @return void
     */
    public function test_hud_insufficient_item_blocks_start(): void {
        $instance = $this->make_instance(['hud_round_cost_item' => 999, 'hud_round_cost_qty' => 1]);
        $this->setUser($this->student);

        $result = $this->call_start_round($instance->cmid);

        $this->assertFalse($result['error']);
        $this->assertFalse($result['data']['success']);
        $this->assertNotEmpty($result['data']['notification']);

        $state = round_service::load_state($instance->cmid, $this->student->id);
        $this->assertFalse($state['roundstarted']);
    }
}
