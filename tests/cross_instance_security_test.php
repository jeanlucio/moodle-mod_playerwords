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
 * Cross-instance isolation tests for mod_playerwords.
 *
 * @package    mod_playerwords
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playerwords;

use mod_playerwords\local\attempts_history_service;
use mod_playerwords\local\round_service;
use mod_playerwords\local\words_repository;

/**
 * Proves that round state, word lookups and attempt records never leak between two
 * different playerwords activities, even when the same student plays both and the
 * activities share the same course. The architecture relies on this by construction
 * (session state keyed by cmid+userid, word lookups scoped by playerwordsid), but no
 * test asserted it explicitly until this one.
 */
final class cross_instance_security_test extends \advanced_testcase {
    #[\Override]
    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        $this->resetAfterTest();
        require_once($CFG->dirroot . '/mod/playerwords/lib.php');
    }

    /**
     * Creates a playerwords instance with a single approved 4-letter word.
     *
     * @param \stdClass $course Course to create the instance in.
     * @param \stdClass $user User credited as the word's author.
     * @param string $word Word text.
     * @return \stdClass Instance record with the ->cmid field added.
     */
    private function make_instance(\stdClass $course, \stdClass $user, string $word): \stdClass {
        global $DB;

        $instance = $this->getDataGenerator()->create_module('playerwords', [
            'course'          => $course->id,
            'min_length'      => 4,
            'max_length'      => 4,
            'max_attempts'    => 6,
            'max_rounds'      => 0,
            'cooldown_amount' => 0,
            'cooldown_unit'   => 'minutes',
        ]);

        $DB->insert_record('playerwords_words', (object)[
            'playerwordsid' => $instance->id,
            'word'          => $word,
            'concept'       => $word,
            'hint'          => 'dica',
            'source'        => 'manual',
            'glossaryid'    => 0,
            'approved'      => 1,
            'timecreated'   => time(),
            'addedby'       => $user->id,
        ]);

        $record = $DB->get_record('playerwords', ['id' => $instance->id], '*', MUST_EXIST);
        $record->cmid = $instance->cmid;
        return $record;
    }

    /**
     * A freshly loaded state for one activity must never inherit the word picked in
     * another activity, even for the same student in the same course.
     *
     * @covers \mod_playerwords\local\round_service::load_state
     * @covers \mod_playerwords\local\round_service::ensure_round_state
     * @return void
     */
    public function test_session_state_is_isolated_per_activity(): void {
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();

        $instancea = $this->make_instance($course, $user, 'boca');
        $instanceb = $this->make_instance($course, $user, 'gato');

        $statea = round_service::load_state($instancea->cmid, $user->id);
        [$statea, $targetworda] = round_service::ensure_round_state(
            $statea,
            $instancea,
            $instancea->cmid,
            $user->id
        );
        round_service::save_state($instancea->cmid, $user->id, $statea);

        $stateb = round_service::load_state($instanceb->cmid, $user->id);

        $this->assertSame('boca', $targetworda);
        $this->assertSame(0, $stateb['wordid']);
        $this->assertSame('', $stateb['wordtext']);
    }

    /**
     * A word id that belongs to one activity must never resolve against another
     * activity's id, even when both activities live in the same course. Without this
     * guard, a client that (maliciously or by a client-side bug) sent another
     * activity's wordid alongside this activity's cmid could reveal that activity's
     * target word.
     *
     * @covers \mod_playerwords\local\words_repository::get_approved_word_by_id
     * @return void
     */
    public function test_word_lookup_is_scoped_to_its_own_activity(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();

        $instancea = $this->make_instance($course, $user, 'boca');
        $instanceb = $this->make_instance($course, $user, 'gato');

        $wordb = $DB->get_record('playerwords_words', ['playerwordsid' => $instanceb->id], '*', MUST_EXIST);

        $crosslookup = words_repository::get_approved_word_by_id((int)$wordb->id, (int)$instancea->id);
        $this->assertNull($crosslookup);

        $ownlookup = words_repository::get_approved_word_by_id((int)$wordb->id, (int)$instanceb->id);
        $this->assertNotNull($ownlookup);
        $this->assertSame('gato', $ownlookup->word);
    }

    /**
     * A round played in one activity must only ever create an attempt record for that
     * activity, never for another one owned by the same student.
     *
     * @covers \mod_playerwords\local\round_service::submit_guess
     * @return void
     */
    public function test_attempts_are_scoped_to_their_own_activity(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();

        $instancea = $this->make_instance($course, $user, 'boca');
        $instanceb = $this->make_instance($course, $user, 'gato');

        $statea = round_service::load_state($instancea->cmid, $user->id);
        [$statea, , $wordida] = round_service::ensure_round_state($statea, $instancea, $instancea->cmid, $user->id);
        [$statea] = round_service::start_round($statea, $instancea, $user->id);
        round_service::submit_guess($statea, $instancea, $instancea->cmid, $user->id, $wordida, 'boca', 'boca');

        $this->assertSame(1, $DB->count_records('playerwords_attempts', ['playerwordsid' => $instancea->id]));
        $this->assertSame(0, $DB->count_records('playerwords_attempts', ['playerwordsid' => $instanceb->id]));
    }

    /**
     * The "my attempts" history must never surface another activity's rounds, nor
     * another student's rounds within the same activity — both are filtered directly
     * in the SQL, not merely by capability, so this proves the query itself is safe.
     *
     * @covers \mod_playerwords\local\attempts_history_service::get_history
     * @return void
     */
    public function test_attempts_history_is_scoped_to_activity_and_user(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $usera = $this->getDataGenerator()->create_user();
        $userb = $this->getDataGenerator()->create_user();

        $instancea = $this->make_instance($course, $usera, 'boca');
        $instanceb = $this->make_instance($course, $usera, 'gato');
        $instancea->grade = 100;

        $DB->insert_record('playerwords_attempts', (object)[
            'playerwordsid' => $instancea->id,
            'userid'        => $usera->id,
            'wordid'        => 0,
            'attempts_used' => 1,
            'time_used'     => 5,
            'completed'     => 1,
            'score'         => 100,
            'timecreated'   => time(),
            'timefinished'  => time(),
        ]);
        // Another activity, same student — must not leak into instancea's history.
        $DB->insert_record('playerwords_attempts', (object)[
            'playerwordsid' => $instanceb->id,
            'userid'        => $usera->id,
            'wordid'        => 0,
            'attempts_used' => 1,
            'time_used'     => 5,
            'completed'     => 1,
            'score'         => 100,
            'timecreated'   => time(),
            'timefinished'  => time(),
        ]);
        // Same activity, another student — must not leak into usera's history.
        $DB->insert_record('playerwords_attempts', (object)[
            'playerwordsid' => $instancea->id,
            'userid'        => $userb->id,
            'wordid'        => 0,
            'attempts_used' => 1,
            'time_used'     => 5,
            'completed'     => 1,
            'score'         => 100,
            'timecreated'   => time(),
            'timefinished'  => time(),
        ]);

        $history = attempts_history_service::get_history($instancea, $usera->id);

        $this->assertCount(1, $history['rows']);
    }
}
