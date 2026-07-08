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
 * Unit tests for attempts_history_service.
 *
 * @package    mod_playerwords
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playerwords\local;

/**
 * Tests for attempts_history_service — requires database.
 */
final class attempts_history_service_test extends \advanced_testcase {
    /** @var \stdClass Course used by the tests. */
    private \stdClass $course;

    /** @var \stdClass Student used by the tests. */
    private \stdClass $user;

    #[\Override]
    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        $this->resetAfterTest();
        require_once($CFG->dirroot . '/mod/playerwords/lib.php');
        $this->course = $this->getDataGenerator()->create_course();
        $this->user = $this->getDataGenerator()->create_user();
    }

    /**
     * Creates a playerwords instance.
     *
     * @param array $overrides Instance field overrides.
     * @return \stdClass
     */
    private function make_instance(array $overrides = []): \stdClass {
        global $DB;

        $record = array_merge([
            'course'          => $this->course->id,
            'cooldown_amount' => 0,
        ], $overrides);

        $instance = $this->getDataGenerator()->create_module('playerwords', $record);
        return $DB->get_record('playerwords', ['id' => $instance->id], '*', MUST_EXIST);
    }

    /**
     * Inserts a finished or pending attempt record.
     *
     * @param \stdClass $instance Activity instance.
     * @param \stdClass $user User who played.
     * @param float $score Score for the attempt.
     * @param int $wordid Word id the attempt was played with, 0 for none.
     * @param bool $finished Whether the round is finished.
     * @param int $timeoffset Seconds to add to time(), used to control ordering.
     * @return void
     */
    private function add_attempt(
        \stdClass $instance,
        \stdClass $user,
        float $score,
        int $wordid = 0,
        bool $finished = true,
        int $timeoffset = 0
    ): void {
        global $DB;

        $DB->insert_record('playerwords_attempts', (object)[
            'playerwordsid' => $instance->id,
            'userid'        => $user->id,
            'wordid'        => $wordid,
            'attempts_used' => $finished ? 2 : 0,
            'time_used'     => $finished ? 65 : 0,
            'completed'     => $finished && $score > 0 ? 1 : 0,
            'score'         => $score,
            'timecreated'   => time() + $timeoffset,
            'timefinished'  => $finished ? (time() + $timeoffset) : 0,
        ]);
    }

    /**
     * An activity with no finished attempts yields an empty history and no grade.
     *
     * @covers \mod_playerwords\local\attempts_history_service::get_history
     * @return void
     */
    public function test_get_history_is_empty_without_finished_attempts(): void {
        $instance = $this->make_instance(['grade' => 100]);

        $history = attempts_history_service::get_history($instance, $this->user->id);

        $this->assertTrue($history['isempty']);
        $this->assertSame([], $history['rows']);
        $this->assertFalse($history['showgrade']);
    }

    /**
     * A still-pending reservation (round in progress, or abandoned without ever
     * finishing) is excluded from the history, mirroring ranking_service.
     *
     * @covers \mod_playerwords\local\attempts_history_service::get_history
     * @return void
     */
    public function test_get_history_excludes_pending_attempts(): void {
        $instance = $this->make_instance(['grade' => 100]);
        $this->add_attempt($instance, $this->user, 100, 0, true);
        $this->add_attempt($instance, $this->user, 0, 0, false);

        $history = attempts_history_service::get_history($instance, $this->user->id);

        $this->assertCount(1, $history['rows']);
    }

    /**
     * Rows are shown most-recent-first, the reverse of the ASC order used for the
     * grade calculation.
     *
     * @covers \mod_playerwords\local\attempts_history_service::get_history
     * @return void
     */
    public function test_get_history_rows_are_most_recent_first(): void {
        $instance = $this->make_instance(['grade' => 100, 'grademethod' => PLAYERWORDS_GRADE_FIRST]);
        $this->add_attempt($instance, $this->user, 30, 0, true, -20);
        $this->add_attempt($instance, $this->user, 70, 0, true, -10);

        $history = attempts_history_service::get_history($instance, $this->user->id);

        $this->assertSame('70.00', $history['rows'][0]['score']);
        $this->assertSame('30.00', $history['rows'][1]['score']);
    }

    /**
     * The current grade matches playerwords_calculate_user_grade() for the instance's
     * configured grading method — the whole point of this service is to never
     * duplicate that aggregation logic.
     *
     * @covers \mod_playerwords\local\attempts_history_service::get_history
     * @return void
     */
    public function test_get_history_grade_matches_calculate_user_grade(): void {
        $instance = $this->make_instance(['grade' => 100, 'grademethod' => PLAYERWORDS_GRADE_HIGHEST]);
        $this->add_attempt($instance, $this->user, 40, 0, true, -20);
        $this->add_attempt($instance, $this->user, 90, 0, true, -10);

        $history = attempts_history_service::get_history($instance, $this->user->id);

        $this->assertTrue($history['showgrade']);
        $this->assertSame('90.00', $history['grade']);
        $this->assertSame('100.00', $history['maxgrade']);
    }

    /**
     * The grade summary is hidden entirely for an ungraded instance, even with
     * finished attempts on record — there is nothing meaningful to show.
     *
     * @covers \mod_playerwords\local\attempts_history_service::get_history
     * @return void
     */
    public function test_get_history_hides_grade_when_ungraded(): void {
        $instance = $this->make_instance(['grade' => 0]);
        $this->add_attempt($instance, $this->user, 0);

        $history = attempts_history_service::get_history($instance, $this->user->id);

        $this->assertFalse($history['showgrade']);
    }

    /**
     * The word column prefers the joined word's concept, falling back to its raw
     * word text when no concept was recorded (e.g. a manually added word).
     *
     * @covers \mod_playerwords\local\attempts_history_service::get_history
     * @return void
     */
    public function test_get_history_row_shows_word_text(): void {
        global $DB;

        $instance = $this->make_instance(['grade' => 100]);
        $wordid = $DB->insert_record('playerwords_words', (object)[
            'playerwordsid' => $instance->id,
            'word'          => 'boca',
            'concept'       => null,
            'hint'          => '',
            'source'        => 'manual',
            'glossaryid'    => 0,
            'approved'      => 1,
            'timecreated'   => time(),
            'addedby'       => $this->user->id,
        ]);
        $this->add_attempt($instance, $this->user, 100, $wordid);

        $history = attempts_history_service::get_history($instance, $this->user->id);

        $this->assertSame('boca', $history['rows'][0]['word']);
    }

    /**
     * Time used is formatted as m:ss for display.
     *
     * @covers \mod_playerwords\local\attempts_history_service::get_history
     * @return void
     */
    public function test_get_history_row_formats_time_used(): void {
        $instance = $this->make_instance(['grade' => 100]);
        $this->add_attempt($instance, $this->user, 100);

        $history = attempts_history_service::get_history($instance, $this->user->id);

        $this->assertSame('1:05', $history['rows'][0]['timeused']);
    }
}
