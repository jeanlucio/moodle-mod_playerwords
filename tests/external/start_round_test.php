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
 *
 * @covers \mod_playerwords\external\start_round
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
     * Enables the course's guest-access enrolment method — precondition for the
     * guest-demo-mode regression test: mod/playerwords:view (the sole gate on this
     * write service) is granted to the guest archetype, but only reachable at all once
     * a course opts into guest access.
     *
     * @return void
     */
    private function enable_guest_access(): void {
        global $DB;
        $guestplugin = enrol_get_plugin('guest');
        $instance = $DB->get_record('enrol', ['courseid' => $this->course->id, 'enrol' => 'guest'], '*', MUST_EXIST);
        $guestplugin->update_status($instance, ENROL_INSTANCE_ENABLED);
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
        // Regression guard: a context field not also declared in roundpanel_structure() is
        // silently stripped by the external API's return-value cleaning, so this must stay
        // in sync with round_presenter::build_round_panel_context().
        $this->assertNotEmpty($result['data']['roundpanel']['keyboardentertext']);

        $state = round_service::load_state($instance->cmid, $this->student->id);
        $this->assertTrue($state['roundstarted']);
    }

    /**
     * Regression test: showcedilla was computed correctly by
     * round_presenter::build_round_panel_context() but never declared in
     * roundpanel_structure(), so the external API's return-value cleaning silently
     * stripped it — the Ç key never appeared on a fresh "Start round" click, only after
     * a full page reload (which renders server-side, bypassing the webservice schema
     * entirely). Same bug class the comment above guards for keyboardentertext.
     *
     * @return void
     */
    public function test_shows_cedilla_key_when_pool_has_one(): void {
        global $DB;

        $instance = $this->make_instance();
        $DB->insert_record('playerwords_words', (object)[
            'playerwordsid' => $instance->id,
            'word'          => 'poço',
            'concept'       => 'poço',
            'hint'          => 'buraco fundo',
            'source'        => 'manual',
            'glossaryid'    => 0,
            'approved'      => 1,
            'timecreated'   => time(),
            'addedby'       => $this->student->id,
        ]);
        $this->setUser($this->student);

        $result = $this->call_start_round($instance->cmid);

        $this->assertFalse($result['error']);
        $this->assertTrue($result['data']['success']);
        $this->assertTrue($result['data']['roundpanel']['showcedilla']);
    }

    /**
     * Tests that starting an already-started round is rejected without restarting the timer.
     *
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
     * The guest account is allowed to play a free demo through the real web service
     * dispatch path: the call succeeds (mod/playerwords:view is granted to the guest
     * archetype on purpose), but round_service::start_round() must still leave no
     * {playerwords_attempts} row behind — every guest visitor to a course shares the
     * same account, so nothing here could be safely attributed to one specific person.
     *
     * @return void
     */
    public function test_guest_can_play_demo_without_persisting(): void {
        global $DB;

        $instance = $this->make_instance();
        $this->enable_guest_access();
        $this->setGuestUser();

        $result = $this->call_start_round($instance->cmid);

        $this->assertFalse($result['error']);
        $this->assertTrue($result['data']['success']);
        $this->assertSame(0, $DB->count_records('playerwords_attempts', ['playerwordsid' => $instance->id]));
    }

    /**
     * Regression test for the max_rounds bypass: once the round limit is reached, a
     * student who calls mod_playerwords_start_round directly — skipping the UI, which
     * hides the "start" control and never lets this request happen from a real page —
     * must not be able to sort a fresh word or insert another attempt row. A brand-new
     * session (never having called new_round at all) has the exact same default state
     * (wordid=0, finished=false) as one left behind by a blocked new_round() call, so
     * this reproduces the report's PoC without needing to go through new_round first.
     *
     * @return void
     */
    public function test_blocked_when_round_limit_already_reached(): void {
        global $DB;

        $instance = $this->make_instance(['max_rounds' => 1, 'cooldown_amount' => 0]);
        $DB->insert_record('playerwords_attempts', (object)[
            'playerwordsid' => $instance->id,
            'userid'        => $this->student->id,
            'wordid'        => 0,
            'attempts_used' => 1,
            'time_used'     => 5,
            'completed'     => 1,
            'score'         => 100,
            'timecreated'   => time(),
            'timefinished'  => time(),
        ]);
        $this->setUser($this->student);

        $result = $this->call_start_round($instance->cmid);

        $this->assertFalse($result['error']);
        $this->assertFalse($result['data']['success']);

        $state = round_service::load_state($instance->cmid, $this->student->id);
        $this->assertFalse($state['roundstarted']);
        $this->assertSame(1, $DB->count_records('playerwords_attempts', ['playerwordsid' => $instance->id]));
    }

    /**
     * Tests that an insufficient PlayerHUD item balance blocks starting the round.
     *
     * @return void
     */
    public function test_hud_insufficient_item_blocks_start(): void {
        $this->skip_if_no_playerhud();
        $itemid = $this->make_hud_item();
        $instance = $this->make_instance(['hud_round_cost_item' => $itemid, 'hud_round_cost_qty' => 1]);
        $this->setUser($this->student);

        $result = $this->call_start_round($instance->cmid);

        $this->assertFalse($result['error']);
        $this->assertFalse($result['data']['success']);
        $this->assertNotEmpty($result['data']['notification']);

        $state = round_service::load_state($instance->cmid, $this->student->id);
        $this->assertFalse($state['roundstarted']);
    }

    /**
     * Tests that a round cost pointing at a PlayerHUD item that does not exist (e.g. it was
     * deleted after the activity was configured) is waived rather than blocking the round
     * forever with a broken notification.
     *
     * @return void
     */
    public function test_hud_deleted_item_waives_start_cost(): void {
        $instance = $this->make_instance(['hud_round_cost_item' => 999999, 'hud_round_cost_qty' => 1]);
        $this->setUser($this->student);

        $result = $this->call_start_round($instance->cmid);

        $this->assertFalse($result['error']);
        $this->assertTrue($result['data']['success']);

        $state = round_service::load_state($instance->cmid, $this->student->id);
        $this->assertTrue($state['roundstarted']);
    }
}
