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
 * External function tests for submit_guess.
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
 * Tests for the mod_playerwords_submit_guess web service.
 */
final class submit_guess_test extends \advanced_testcase {
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
     * Creates a playerwords instance with one approved 4-letter word and a hint.
     *
     * @param array $overrides Instance field overrides.
     * @return \stdClass Instance record with the ->cmid field added.
     */
    private function make_instance(array $overrides = []): \stdClass {
        global $DB;

        // NOTE: playerwords_add_instance() recomputes cooldown_seconds from the transient
        // cooldown_amount/cooldown_unit form fields, ignoring a cooldown_seconds override.
        $record = array_merge([
            'course'          => $this->course->id,
            'min_length'      => 4,
            'max_length'      => 4,
            'max_attempts'    => 6,
            'cooldown_amount' => 2,
            'cooldown_unit'   => 'minutes',
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
     * Puts a round in progress (word picked, timer started) for the given instance.
     *
     * @param \stdClass $instance Activity instance.
     * @return void
     */
    private function start_round_for_student(\stdClass $instance): void {
        $state = round_service::load_state($instance->cmid, $this->student->id);
        [$state] = round_service::ensure_round_state($state, $instance, $instance->cmid, $this->student->id);
        [$state] = round_service::start_round($state, $instance, $this->student->id);
        round_service::save_state($instance->cmid, $this->student->id, $state);
    }

    /**
     * Calls the mod_playerwords_submit_guess web service through the real dispatch path.
     *
     * @param int $cmid Course module id.
     * @param string $guess Guess text.
     * @return array Response shaped as ['error' => bool, 'data' => array|null, ...].
     */
    private function call_submit_guess(int $cmid, string $guess): array {
        $_POST['sesskey'] = sesskey();
        return external_api::call_external_function(
            'mod_playerwords_submit_guess',
            ['cmid' => $cmid, 'guess' => $guess]
        );
    }

    /**
     * Tests that a wrong guess never leaks the word or definition in the response.
     *
     * @covers \mod_playerwords\external\submit_guess::execute
     * @return void
     */
    public function test_wrong_guess_never_reveals_the_word(): void {
        $instance = $this->make_instance();
        $this->setUser($this->student);
        $this->start_round_for_student($instance);

        $result = $this->call_submit_guess($instance->cmid, 'casa');

        $this->assertFalse($result['error']);
        $data = $result['data'];
        $this->assertFalse($data['finished']);
        $this->assertFalse($data['roundresult']['showreveal']);
        $this->assertSame('', $data['roundresult']['revealword']);
        $this->assertSame('', $data['roundresult']['revealdefinition']);
        $this->assertCount(4, $data['feedback']);
    }

    /**
     * Tests that the word and definition are only revealed once the round actually finishes.
     *
     * @covers \mod_playerwords\external\submit_guess::execute
     * @return void
     */
    public function test_correct_guess_reveals_the_word_only_once_finished(): void {
        $instance = $this->make_instance();
        $this->setUser($this->student);
        $this->start_round_for_student($instance);

        $result = $this->call_submit_guess($instance->cmid, 'boca');

        $this->assertFalse($result['error']);
        $data = $result['data'];
        $this->assertTrue($data['finished']);
        $this->assertTrue($data['won']);
        $this->assertTrue($data['roundresult']['showreveal']);
        $this->assertSame('boca', $data['roundresult']['revealword']);
        $this->assertTrue($data['roundresult']['showdefinition']);
        $this->assertSame('dica secreta', $data['roundresult']['revealdefinition']);
        $this->assertGreaterThan(0, $data['roundresult']['cooldownuntil']);
    }

    /**
     * Tests that an out-of-attempts loss also reveals the word, and never before that.
     *
     * @covers \mod_playerwords\external\submit_guess::execute
     * @return void
     */
    public function test_losing_guess_also_reveals_the_word(): void {
        $instance = $this->make_instance(['max_attempts' => 1]);
        $this->setUser($this->student);
        $this->start_round_for_student($instance);

        $result = $this->call_submit_guess($instance->cmid, 'casa');

        $this->assertFalse($result['error']);
        $data = $result['data'];
        $this->assertTrue($data['finished']);
        $this->assertFalse($data['won']);
        $this->assertSame('boca', $data['roundresult']['revealword']);
    }

    /**
     * Tests that a user without the view capability in the module context is rejected.
     *
     * @covers \mod_playerwords\external\submit_guess::execute
     * @return void
     */
    public function test_requires_view_capability(): void {
        $instance = $this->make_instance();
        $outsider = $this->getDataGenerator()->create_user();
        $this->setUser($outsider);

        $result = $this->call_submit_guess($instance->cmid, 'boca');

        $this->assertTrue($result['error']);
    }

    /**
     * Tests that timeleft reflects seconds actually remaining while the round is
     * still in progress (timer enabled).
     *
     * @covers \mod_playerwords\external\submit_guess::execute
     * @return void
     */
    public function test_timeleft_reflects_remaining_seconds_while_in_progress(): void {
        $instance = $this->make_instance(['timer_minutes' => 2]);
        $this->setUser($this->student);
        $this->start_round_for_student($instance);

        $result = $this->call_submit_guess($instance->cmid, 'casa');

        $this->assertFalse($result['error']);
        $this->assertFalse($result['data']['finished']);
        $this->assertGreaterThan(0, $result['data']['timeleft']);
        $this->assertLessThanOrEqual(120, $result['data']['timeleft']);
    }

    /**
     * Tests that timeleft is frozen at the moment the round finished, instead of
     * continuing to tick down against the wall clock on a later call.
     *
     * @covers \mod_playerwords\external\submit_guess::execute
     * @return void
     */
    public function test_timeleft_is_frozen_after_round_finishes(): void {
        $instance = $this->make_instance(['timer_minutes' => 2]);
        $this->setUser($this->student);
        $this->start_round_for_student($instance);

        $first = $this->call_submit_guess($instance->cmid, 'boca');
        $this->assertTrue($first['data']['finished']);

        sleep(1);
        $second = $this->call_submit_guess($instance->cmid, 'boca');

        $this->assertSame($first['data']['timeleft'], $second['data']['timeleft']);
    }

    /**
     * Regression test: a fractional ranking total must survive the external API's
     * return-value cleaning. totalscore used to be declared PARAM_INT in
     * roundresult_structure(), which silently truncated any decimal value — the same
     * class of bug already caught once for the help modal's keyboardentertext field
     * (a value not declared correctly is stripped/mangled by clean_returnvalue(),
     * regardless of what the PHP context actually produced).
     *
     * @covers \mod_playerwords\external\submit_guess::execute
     * @covers \mod_playerwords\external\submit_guess::roundresult_structure
     * @return void
     */
    public function test_fractional_ranking_points_survive_the_webservice_call(): void {
        $instance = $this->make_instance([
            'show_ranking'       => 1,
            'rankingscoringmode' => PLAYERWORDS_SCORING_LINEAR,
        ]);
        $this->setUser($this->student);
        $this->start_round_for_student($instance);

        // Wrong guess first, so the winning guess lands on the 2nd of 6 attempts —
        // rankingpoints = 100 * (6 - 2 + 1) / 6 = 83.33, a genuinely fractional value.
        $this->call_submit_guess($instance->cmid, 'casa');
        $result = $this->call_submit_guess($instance->cmid, 'boca');

        $this->assertFalse($result['error']);
        $rows = $result['data']['roundresult']['rankingrows'];
        $this->assertNotEmpty($rows);
        $this->assertSame('83.33', $rows[0]['totalscore']);
    }
}
