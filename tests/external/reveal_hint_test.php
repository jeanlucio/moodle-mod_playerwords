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
 * External function tests for reveal_hint.
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
 * Tests for the mod_playerwords_reveal_hint web service.
 */
final class reveal_hint_test extends \advanced_testcase {
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
     * Creates a playerwords instance with one approved word and a hint.
     *
     * @param array $overrides Instance field overrides.
     * @return \stdClass Instance record with the ->cmid field added.
     */
    private function make_instance(array $overrides = []): \stdClass {
        global $DB;

        $record = array_merge([
            'course'     => $this->course->id,
            'min_length' => 4,
            'max_length' => 4,
        ], $overrides);

        $instance = $this->getDataGenerator()->create_module('playerwords', $record);

        $DB->insert_record('playerwords_words', (object)[
            'playerwordsid' => $instance->id,
            'word'          => 'boca',
            'concept'       => 'boca',
            'hint'          => 'dica secreta',
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
     * Skips the current test when block_playerhud is not installed.
     *
     * @return void
     */
    private function skip_if_no_playerhud(): void {
        global $DB;
        if (!$DB->get_manager()->table_exists('block_playerhud_items')) {
            $this->markTestSkipped('block_playerhud not installed.');
        }
    }

    /**
     * Inserts a genuine block_playerhud_items record (with its own block instance), so
     * cost-checking tests exercise a real item rather than a bare numeric ID.
     *
     * @return int Item ID.
     */
    private function make_hud_item(): int {
        global $DB;
        $ctx = \context_course::instance($this->course->id);
        $biid = $DB->insert_record('block_instances', (object)[
            'blockname'         => 'playerhud',
            'parentcontextid'   => $ctx->id,
            'showinsubcontexts' => 0,
            'pagetypepattern'   => 'course-view-*',
            'subpagepattern'    => null,
            'defaultregion'     => 'side-pre',
            'defaultweight'     => 0,
            'configdata'        => base64_encode(serialize(new \stdClass())),
            'timecreated'       => time(),
            'timemodified'      => time(),
        ]);
        return $DB->insert_record('block_playerhud_items', (object)[
            'blockinstanceid' => $biid,
            'name'            => 'Gold Key',
            'xp'              => 0,
            'image'           => '',
            'description'     => '',
            'enabled'         => 1,
            'secret'          => 0,
            'timecreated'     => time(),
            'timemodified'    => time(),
        ]);
    }

    /**
     * Calls the mod_playerwords_reveal_hint web service through the real dispatch path.
     *
     * @param int $cmid Course module id.
     * @return array Response shaped as ['error' => bool, 'data' => array|null, ...].
     */
    private function call_reveal_hint(int $cmid): array {
        $_POST['sesskey'] = sesskey();
        return external_api::call_external_function('mod_playerwords_reveal_hint', ['cmid' => $cmid]);
    }

    /**
     * Tests that revealing the hint returns the hint text.
     *
     * @covers \mod_playerwords\external\reveal_hint::execute
     * @return void
     */
    public function test_reveals_hint(): void {
        $instance = $this->make_instance();
        $this->setUser($this->student);
        $this->start_round($instance);

        $result = $this->call_reveal_hint($instance->cmid);

        $this->assertFalse($result['error']);
        $this->assertTrue($result['data']['success']);
        $this->assertSame('dica secreta', $result['data']['hintvalue']);
    }

    /**
     * Tests that revealing the hint twice is idempotent and does not error.
     *
     * @covers \mod_playerwords\external\reveal_hint::execute
     * @return void
     */
    public function test_reveal_hint_is_idempotent(): void {
        $instance = $this->make_instance();
        $this->setUser($this->student);
        $this->start_round($instance);

        $this->call_reveal_hint($instance->cmid);
        $second = $this->call_reveal_hint($instance->cmid);

        $this->assertFalse($second['error']);
        $this->assertTrue($second['data']['success']);
        $this->assertSame('dica secreta', $second['data']['hintvalue']);
    }

    /**
     * Tests that a finished round rejects the hint request.
     *
     * @covers \mod_playerwords\external\reveal_hint::execute
     * @return void
     */
    public function test_rejects_when_round_finished(): void {
        $instance = $this->make_instance();
        $this->setUser($this->student);

        $state = round_service::load_state($instance->cmid, $this->student->id);
        [$state] = round_service::ensure_round_state($state, $instance, $instance->cmid, $this->student->id);
        [$state] = round_service::start_round($state, $instance, $this->student->id);
        [$state] = round_service::forfeit($state, $instance, $instance->cmid, $this->student->id);
        round_service::save_state($instance->cmid, $this->student->id, $state);

        $result = $this->call_reveal_hint($instance->cmid);

        $this->assertFalse($result['error']);
        $this->assertFalse($result['data']['success']);
        $this->assertSame('', $result['data']['hintvalue']);
    }

    /**
     * Tests that a user without the view capability in the module context is rejected.
     *
     * @covers \mod_playerwords\external\reveal_hint::execute
     * @return void
     */
    public function test_requires_view_capability(): void {
        $instance = $this->make_instance();
        $outsider = $this->getDataGenerator()->create_user();
        $this->setUser($outsider);

        $result = $this->call_reveal_hint($instance->cmid);

        $this->assertTrue($result['error']);
    }

    /**
     * Tests that an insufficient PlayerHUD item balance blocks revealing the hint.
     *
     * @covers \mod_playerwords\external\reveal_hint::execute
     * @return void
     */
    public function test_hud_insufficient_item_blocks_reveal(): void {
        $this->skip_if_no_playerhud();
        $itemid = $this->make_hud_item();
        $instance = $this->make_instance(['hud_hint_cost_item' => $itemid, 'hud_hint_cost_qty' => 1]);
        $this->setUser($this->student);
        $this->start_round($instance);

        $result = $this->call_reveal_hint($instance->cmid);

        $this->assertFalse($result['error']);
        $this->assertFalse($result['data']['success']);
        $this->assertSame('', $result['data']['hintvalue']);
        $this->assertNotEmpty($result['data']['notification']);
    }

    /**
     * Tests that a hint cost pointing at a PlayerHUD item that does not exist (e.g. it was
     * deleted after the activity was configured) is waived rather than blocking the hint
     * forever with a broken notification.
     *
     * @covers \mod_playerwords\external\reveal_hint::execute
     * @return void
     */
    public function test_hud_deleted_item_waives_reveal_cost(): void {
        $instance = $this->make_instance(['hud_hint_cost_item' => 999999, 'hud_hint_cost_qty' => 1]);
        $this->setUser($this->student);
        $this->start_round($instance);

        $result = $this->call_reveal_hint($instance->cmid);

        $this->assertFalse($result['error']);
        $this->assertTrue($result['data']['success']);
        $this->assertSame('dica secreta', $result['data']['hintvalue']);
    }

    /**
     * Regression test for the timer bypass: a student who never calls
     * mod_playerwords_end_round(reason=timeout) — because the client only ever arms
     * that call when it first sees timeleft > 0, so simply reloading after the deadline
     * never fires it — must still be stopped from revealing the hint through
     * mod_playerwords_reveal_hint after the round's own deadline has passed.
     *
     * @covers \mod_playerwords\external\reveal_hint::execute
     * @return void
     */
    public function test_rejects_hint_reveal_once_deadline_has_passed(): void {
        $instance = $this->make_instance(['timer_minutes' => 1]);
        $this->setUser($this->student);

        $state = round_service::load_state($instance->cmid, $this->student->id);
        [$state] = round_service::ensure_round_state($state, $instance, $instance->cmid, $this->student->id);
        [$state] = round_service::start_round($state, $instance, $this->student->id);
        $state['starttime'] = time() - 120;
        round_service::save_state($instance->cmid, $this->student->id, $state);

        $result = $this->call_reveal_hint($instance->cmid);

        $this->assertFalse($result['error']);
        $this->assertFalse($result['data']['success']);
        $this->assertSame('', $result['data']['hintvalue']);
        $this->assertNotEmpty($result['data']['notification']);
    }

    /**
     * Starts a round for the given instance and student, and persists the state — the
     * shared setup every test above needs now that reveal_hint() requires roundstarted.
     *
     * @param \stdClass $instance Activity instance (with ->cmid).
     * @return void
     */
    private function start_round(\stdClass $instance): void {
        $state = round_service::load_state($instance->cmid, $this->student->id);
        [$state] = round_service::ensure_round_state($state, $instance, $instance->cmid, $this->student->id);
        [$state] = round_service::start_round($state, $instance, $this->student->id);
        round_service::save_state($instance->cmid, $this->student->id, $state);
    }
}
