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
 * Unit tests for round_service.
 *
 * @package    mod_playerwords
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playerwords\local;

use mod_playerwords\event\round_completed;
use mod_playerwords\event\round_started;

/**
 * Tests for round_service — requires database.
 */
final class round_service_test extends \advanced_testcase {
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
        $this->user   = $this->getDataGenerator()->create_user();
    }

    /**
     * Creates a playerwords instance with one approved 4-letter word.
     *
     * @param array $overrides Instance field overrides.
     * @return \stdClass Instance record with the ->cmid field added.
     */
    private function make_instance(array $overrides = []): \stdClass {
        global $DB;

        // NOTE: playerwords_add_instance() recomputes cooldown_seconds/timer_seconds from
        // the transient cooldown_amount/cooldown_unit/timer_minutes form fields, ignoring
        // any cooldown_seconds/timer_seconds value passed directly — override those instead.
        $record = array_merge([
            'course'          => $this->course->id,
            'min_length'      => 4,
            'max_length'      => 4,
            'max_attempts'    => 6,
            'max_rounds'      => 0,
            'cooldown_amount' => 2,
            'cooldown_unit'   => 'minutes',
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
            'addedby'       => $this->user->id,
        ]);

        $record = $DB->get_record('playerwords', ['id' => $instance->id], '*', MUST_EXIST);
        $record->cmid = $instance->cmid;
        return $record;
    }

    /**
     * Loads state, ensures a round, and starts it, returning the ready-to-play state.
     *
     * @param \stdClass $instance Activity instance.
     * @return array [$state, $roundwordid]
     */
    private function start_ready_round(\stdClass $instance): array {
        $state = round_service::load_state($instance->cmid, $this->user->id);
        [$state, , $roundwordid] = round_service::ensure_round_state($state, $instance, $instance->cmid, $this->user->id);
        [$state] = round_service::start_round($state, $instance, $this->user->id);
        return [$state, $roundwordid];
    }

    /**
     * Tests that ensure_round_state picks the only approved word and fires round_started once.
     *
     * @covers \mod_playerwords\local\round_service::ensure_round_state
     * @return void
     */
    public function test_ensure_round_state_picks_word_and_fires_event(): void {
        $instance = $this->make_instance();
        $sink = $this->redirectEvents();

        $state = round_service::load_state($instance->cmid, $this->user->id);
        [$state, $targetword, $roundwordid] = round_service::ensure_round_state(
            $state,
            $instance,
            $instance->cmid,
            $this->user->id
        );

        $this->assertSame('boca', $targetword);
        $this->assertGreaterThan(0, $roundwordid);

        $events = array_values(array_filter($sink->get_events(), fn($e) => $e instanceof round_started));
        $this->assertCount(1, $events);
        $this->assertSame($roundwordid, $events[0]->objectid);
    }

    /**
     * Tests that a wrong guess is recorded without finishing the round.
     *
     * @covers \mod_playerwords\local\round_service::submit_guess
     * @return void
     */
    public function test_submit_guess_wrong_does_not_finish_round(): void {
        $instance = $this->make_instance();
        [$state, $roundwordid] = $this->start_ready_round($instance);

        [$state, $feedback, $notification, $notificationtype] = round_service::submit_guess(
            $state,
            $instance,
            $instance->cmid,
            $this->user->id,
            $roundwordid,
            'boca',
            'casa'
        );

        $this->assertNotNull($feedback);
        $this->assertNull($notification);
        $this->assertNull($notificationtype);
        $this->assertFalse($state['finished']);
        $this->assertSame(1, $state['attemptsused']);
    }

    /**
     * Tests that a correct guess finishes the round, sets cooldown, and fires round_completed once.
     *
     * @covers \mod_playerwords\local\round_service::submit_guess
     * @return void
     */
    public function test_submit_guess_correct_finishes_round(): void {
        global $DB;

        $instance = $this->make_instance();
        [$state, $roundwordid] = $this->start_ready_round($instance);
        $sink = $this->redirectEvents();

        [$state, $feedback, $notification, $notificationtype] = round_service::submit_guess(
            $state,
            $instance,
            $instance->cmid,
            $this->user->id,
            $roundwordid,
            'boca',
            'boca'
        );

        $this->assertSame(['correct', 'correct', 'correct', 'correct'], array_values($feedback));
        $this->assertTrue($state['finished']);
        $this->assertTrue($state['won']);
        $this->assertGreaterThan(time(), $state['cooldownuntil']);
        $this->assertSame('success', $notificationtype);
        $this->assertNotEmpty($notification);

        $attempts = $DB->get_records('playerwords_attempts', ['playerwordsid' => $instance->id]);
        $this->assertCount(1, $attempts);
        $attempt = reset($attempts);
        $this->assertSame(1, (int)$attempt->completed);

        $events = array_values(array_filter($sink->get_events(), fn($e) => $e instanceof round_completed));
        $this->assertCount(1, $events);
        $this->assertTrue($events[0]->other['completed']);
    }

    /**
     * Tests that exhausting all attempts finishes the round as a loss.
     *
     * @covers \mod_playerwords\local\round_service::submit_guess
     * @return void
     */
    public function test_submit_guess_out_of_attempts_finishes_round_as_loss(): void {
        $instance = $this->make_instance(['max_attempts' => 1]);
        [$state, $roundwordid] = $this->start_ready_round($instance);

        [$state, , $notification, $notificationtype] = round_service::submit_guess(
            $state,
            $instance,
            $instance->cmid,
            $this->user->id,
            $roundwordid,
            'boca',
            'casa'
        );

        $this->assertTrue($state['finished']);
        $this->assertFalse($state['won']);
        $this->assertSame('warning', $notificationtype);
        $this->assertNotEmpty($notification);
    }

    /**
     * Tests that an already-finished round rejects further guesses without duplicating state.
     *
     * @covers \mod_playerwords\local\round_service::submit_guess
     * @return void
     */
    public function test_submit_guess_after_finish_is_rejected(): void {
        global $DB;

        $instance = $this->make_instance();
        [$state, $roundwordid] = $this->start_ready_round($instance);
        [$state] = round_service::submit_guess(
            $state,
            $instance,
            $instance->cmid,
            $this->user->id,
            $roundwordid,
            'boca',
            'boca'
        );

        [$state, $feedback, $notification] = round_service::submit_guess(
            $state,
            $instance,
            $instance->cmid,
            $this->user->id,
            $roundwordid,
            'boca',
            'boca'
        );

        $this->assertNull($feedback);
        $this->assertNotEmpty($notification);
        $this->assertCount(1, $DB->get_records('playerwords_attempts', ['playerwordsid' => $instance->id]));
    }

    /**
     * Tests that a length mismatch is rejected without mutating state.
     *
     * @covers \mod_playerwords\local\round_service::submit_guess
     * @return void
     */
    public function test_submit_guess_length_mismatch_rejected(): void {
        $instance = $this->make_instance();
        [$state, $roundwordid] = $this->start_ready_round($instance);

        [$state, $feedback] = round_service::submit_guess(
            $state,
            $instance,
            $instance->cmid,
            $this->user->id,
            $roundwordid,
            'boca',
            'ca'
        );

        $this->assertNull($feedback);
        $this->assertSame(0, $state['attemptsused']);
    }

    /**
     * Tests that forfeit finishes the round, sets cooldown, and fires round_completed once.
     *
     * @covers \mod_playerwords\local\round_service::forfeit
     * @return void
     */
    public function test_forfeit_finishes_round(): void {
        global $DB;

        $instance = $this->make_instance();
        [$state] = $this->start_ready_round($instance);
        $sink = $this->redirectEvents();

        [$state, $notification, $notificationtype] = round_service::forfeit(
            $state,
            $instance,
            $instance->cmid,
            $this->user->id
        );

        $this->assertTrue($state['finished']);
        $this->assertTrue($state['forfeited']);
        $this->assertFalse($state['won']);
        $this->assertSame('warning', $notificationtype);
        $this->assertGreaterThan(time(), $state['cooldownuntil']);

        $attempts = $DB->get_records('playerwords_attempts', ['playerwordsid' => $instance->id]);
        $this->assertCount(1, $attempts);
        $this->assertSame(0, (int)reset($attempts)->completed);

        $events = array_values(array_filter($sink->get_events(), fn($e) => $e instanceof round_completed));
        $this->assertCount(1, $events);
        $this->assertFalse($events[0]->other['completed']);
    }

    /**
     * Tests that timeout finishes the round with the timedout flag set.
     *
     * @covers \mod_playerwords\local\round_service::timeout
     * @return void
     */
    public function test_timeout_finishes_round(): void {
        $instance = $this->make_instance();
        [$state] = $this->start_ready_round($instance);

        [$state, $notification, $notificationtype] = round_service::timeout(
            $state,
            $instance,
            $instance->cmid,
            $this->user->id
        );

        $this->assertTrue($state['finished']);
        $this->assertTrue($state['timedout']);
        $this->assertFalse($state['won']);
        $this->assertSame('warning', $notificationtype);
    }

    /**
     * Tests that new_round resets the session so the next load picks a fresh word.
     *
     * @covers \mod_playerwords\local\round_service::new_round
     * @return void
     */
    public function test_new_round_resets_state(): void {
        $instance = $this->make_instance();
        [$state] = $this->start_ready_round($instance);
        round_service::save_state($instance->cmid, $this->user->id, $state);

        round_service::new_round($instance->cmid, $this->user->id);

        $reloaded = round_service::load_state($instance->cmid, $this->user->id);
        $this->assertSame(0, $reloaded['wordid']);
        $this->assertFalse($reloaded['finished']);
    }

    /**
     * Tests that the round-count restriction is enforced once max_rounds is reached.
     *
     * @covers \mod_playerwords\local\round_service::get_round_restriction_notice
     * @return void
     */
    public function test_restriction_notice_max_rounds_reached(): void {
        global $DB;

        $instance = $this->make_instance(['max_rounds' => 1, 'cooldown_amount' => 0]);
        $DB->insert_record('playerwords_attempts', (object)[
            'playerwordsid' => $instance->id,
            'userid'        => $this->user->id,
            'wordid'        => 0,
            'attempts_used' => 1,
            'time_used'     => 5,
            'completed'     => 1,
            'score'         => 100,
            'timecreated'   => time(),
        ]);

        $notice = round_service::get_round_restriction_notice($instance, $this->user->id);
        $this->assertNotNull($notice);
    }

    /**
     * Tests that no restriction applies when limits are disabled and no attempts exist.
     *
     * @covers \mod_playerwords\local\round_service::get_round_restriction_notice
     * @return void
     */
    public function test_restriction_notice_none_when_unrestricted(): void {
        $instance = $this->make_instance(['max_rounds' => 0, 'cooldown_amount' => 0]);
        $this->assertNull(round_service::get_round_restriction_notice($instance, $this->user->id));
    }
}
