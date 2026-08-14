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
     * Inserts a block_instances record for block_playerhud in the given course context.
     *
     * @param \stdClass $course Course object.
     * @return int Block instance ID.
     */
    private function make_block_instance(\stdClass $course): int {
        global $DB;
        $ctx = \context_course::instance($course->id);
        return $DB->insert_record('block_instances', (object)[
            'blockname'        => 'playerhud',
            'parentcontextid'  => $ctx->id,
            'showinsubcontexts' => 0,
            'pagetypepattern'  => 'course-view-*',
            'subpagepattern'   => null,
            'defaultregion'    => 'side-pre',
            'defaultweight'    => 0,
            'configdata'       => base64_encode(serialize(new \stdClass())),
            'timecreated'      => time(),
            'timemodified'     => time(),
        ]);
    }

    /**
     * Inserts a block_playerhud_items record for the given block instance.
     *
     * @param int $blockinstanceid Block instance ID.
     * @param int $xp              XP awarded per unit collected, 0 for none.
     * @return int Item ID.
     */
    private function make_item(int $blockinstanceid, int $xp = 0): int {
        global $DB;
        return $DB->insert_record('block_playerhud_items', (object)[
            'blockinstanceid' => $blockinstanceid,
            'name'            => 'Gold Key',
            'xp'              => $xp,
            'image'           => '',
            'description'     => '',
            'enabled'         => 1,
            'secret'          => 0,
            'timecreated'     => time(),
            'timemodified'    => time(),
        ]);
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
     * Regression test for the round-cost bypass: a client that calls submit_guess()
     * before start_round() — skipping the "Iniciar rodada" button, the only place a
     * configured PlayerHUD round cost is actually charged — must be rejected, even with
     * a correct guess for the word already sitting in session. The round stays
     * unfinished and unscored so a repeat with start_round() first still works normally.
     *
     * @covers \mod_playerwords\local\round_service::submit_guess
     * @return void
     */
    public function test_submit_guess_rejected_when_round_not_started(): void {
        $instance = $this->make_instance();
        $state = round_service::load_state($instance->cmid, $this->user->id);
        [$state, $targetword, $roundwordid] = round_service::ensure_round_state(
            $state,
            $instance,
            $instance->cmid,
            $this->user->id
        );
        $this->assertFalse($state['roundstarted']);

        [$state, $feedback, $notification, $notificationtype] = round_service::submit_guess(
            $state,
            $instance,
            $instance->cmid,
            $this->user->id,
            $roundwordid,
            $targetword,
            'boca'
        );

        $this->assertNull($feedback);
        $this->assertNotEmpty($notification);
        $this->assertSame('warning', $notificationtype);
        $this->assertFalse($state['finished']);
        $this->assertSame(0, $state['attemptsused']);
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
        $this->assertGreaterThan(time(), round_service::compute_cooldown_until($instance, $this->user->id));
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
        $this->assertGreaterThan(time(), round_service::compute_cooldown_until($instance, $this->user->id));

        $attempts = $DB->get_records('playerwords_attempts', ['playerwordsid' => $instance->id]);
        $this->assertCount(1, $attempts);
        $this->assertSame(0, (int)reset($attempts)->completed);

        $events = array_values(array_filter($sink->get_events(), fn($e) => $e instanceof round_completed));
        $this->assertCount(1, $events);
        $this->assertFalse($events[0]->other['completed']);
    }

    /**
     * Regression test: forfeiting a word that was only ever armed at page-load time
     * (ensure_round_state(), before "Iniciar rodada" is ever clicked) must be rejected
     * — otherwise a student could burn one of their max_rounds, and trigger the
     * cooldown, on a round they never actually played.
     *
     * @covers \mod_playerwords\local\round_service::forfeit
     * @return void
     */
    public function test_forfeit_rejected_when_round_not_started(): void {
        global $DB;

        $instance = $this->make_instance();
        $state = round_service::load_state($instance->cmid, $this->user->id);
        [$state] = round_service::ensure_round_state($state, $instance, $instance->cmid, $this->user->id);
        $this->assertFalse($state['roundstarted']);

        [$state, $notification] = round_service::forfeit($state, $instance, $instance->cmid, $this->user->id);

        $this->assertNotEmpty($notification);
        $this->assertFalse($state['finished']);
        $this->assertSame(0, $DB->count_records('playerwords_attempts', ['playerwordsid' => $instance->id]));
    }

    /**
     * Tests that timeout finishes the round with the timedout flag set, once the
     * configured deadline has genuinely passed.
     *
     * @covers \mod_playerwords\local\round_service::timeout
     * @return void
     */
    public function test_timeout_finishes_round(): void {
        $instance = $this->make_instance(['timer_minutes' => 1]);
        [$state] = $this->start_ready_round($instance);
        $state['starttime'] = time() - 120;

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
     * Tests that a timeout claim is rejected server-side when the configured deadline
     * has not actually passed yet — the client's own countdown is never trusted alone.
     *
     * @covers \mod_playerwords\local\round_service::timeout
     * @return void
     */
    public function test_timeout_rejected_before_deadline(): void {
        $instance = $this->make_instance(['timer_minutes' => 5]);
        [$state] = $this->start_ready_round($instance);

        [$state, $notification, $notificationtype] = round_service::timeout(
            $state,
            $instance,
            $instance->cmid,
            $this->user->id
        );

        $this->assertFalse($state['finished']);
        $this->assertSame('warning', $notificationtype);
        $this->assertNotEmpty($notification);
    }

    /**
     * Tests that a timeout claim is rejected when the activity has no timer configured
     * at all — there is no deadline to have run out.
     *
     * @covers \mod_playerwords\local\round_service::timeout
     * @return void
     */
    public function test_timeout_rejected_when_timer_disabled(): void {
        $instance = $this->make_instance();
        [$state] = $this->start_ready_round($instance);
        $state['starttime'] = time() - 3600;

        [$state] = round_service::timeout($state, $instance, $instance->cmid, $this->user->id);

        $this->assertFalse($state['finished']);
    }

    /**
     * Regression test: a word armed at page-load time but never started (starttime
     * stays 0) must reject timeout() outright, not fall through to the deadline check
     * — with starttime=0, that check's own deadline sits in the remote past and would
     * otherwise pass unconditionally, defeating the anti-forgery tolerance window it
     * documents.
     *
     * @covers \mod_playerwords\local\round_service::timeout
     * @return void
     */
    public function test_timeout_rejected_when_round_not_started(): void {
        global $DB;

        $instance = $this->make_instance(['timer_minutes' => 5]);
        $state = round_service::load_state($instance->cmid, $this->user->id);
        [$state] = round_service::ensure_round_state($state, $instance, $instance->cmid, $this->user->id);
        $this->assertFalse($state['roundstarted']);
        $this->assertSame(0, $state['starttime']);

        [$state, $notification] = round_service::timeout($state, $instance, $instance->cmid, $this->user->id);

        $this->assertNotEmpty($notification);
        $this->assertFalse($state['finished']);
        $this->assertSame(0, $DB->count_records('playerwords_attempts', ['playerwordsid' => $instance->id]));
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
            'timefinished'  => time(),
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

    /**
     * Regression test for the max_rounds/cooldown bypass: ensure_round_state() must
     * refuse to pick a word once get_round_restriction_notice() reports a restriction,
     * even when the session state already looks like a fresh lobby (wordid=0,
     * finished=false) — the exact shape a blocked new_round() call, or a brand-new
     * session, leaves behind. Before this guard, a direct call to start_round or
     * submit_guess from that state would sort a word and insert an attempt row past
     * max_rounds, ignoring the cooldown entirely.
     *
     * @covers \mod_playerwords\local\round_service::ensure_round_state
     * @return void
     */
    public function test_ensure_round_state_refuses_new_word_when_restricted(): void {
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
            'timefinished'  => time(),
        ]);

        // Simulates the state a fresh session, or a blocked new_round(), leaves behind.
        $state = round_service::load_state($instance->cmid, $this->user->id);

        [$state, $targetword, $roundwordid] = round_service::ensure_round_state(
            $state,
            $instance,
            $instance->cmid,
            $this->user->id
        );

        $this->assertSame('', $targetword);
        $this->assertSame(0, $roundwordid);
        $this->assertSame(0, $state['wordid']);
        $this->assertSame(1, round_service::count_rounds_played($instance, $this->user->id));
    }

    /**
     * Tests that count_rounds_played counts only this instance's attempts for this user.
     *
     * @covers \mod_playerwords\local\round_service::count_rounds_played
     * @return void
     */
    public function test_count_rounds_played(): void {
        global $DB;

        $instance = $this->make_instance(['max_rounds' => 0, 'cooldown_amount' => 0]);
        $otheruser = $this->getDataGenerator()->create_user();

        $this->assertSame(0, round_service::count_rounds_played($instance, $this->user->id));

        foreach ([$this->user->id, $this->user->id, $otheruser->id] as $userid) {
            $DB->insert_record('playerwords_attempts', (object)[
                'playerwordsid' => $instance->id,
                'userid'        => $userid,
                'wordid'        => 0,
                'attempts_used' => 1,
                'time_used'     => 5,
                'completed'     => 1,
                'score'         => 100,
                'timecreated'   => time(),
                'timefinished'  => time(),
            ]);
        }

        $this->assertSame(2, round_service::count_rounds_played($instance, $this->user->id));
    }

    /**
     * Tests that no cooldown applies when the setting is disabled, even with a recent attempt.
     *
     * @covers \mod_playerwords\local\round_service::compute_cooldown_until
     * @return void
     */
    public function test_compute_cooldown_until_disabled(): void {
        global $DB;

        $instance = $this->make_instance(['cooldown_amount' => 0]);
        $DB->insert_record('playerwords_attempts', (object)[
            'playerwordsid' => $instance->id,
            'userid'        => $this->user->id,
            'wordid'        => 0,
            'attempts_used' => 1,
            'time_used'     => 5,
            'completed'     => 1,
            'score'         => 100,
            'timecreated'   => time(),
            'timefinished'  => time(),
        ]);

        $this->assertSame(0, round_service::compute_cooldown_until($instance, $this->user->id));
    }

    /**
     * Tests that no cooldown applies when the player has never attempted the activity.
     *
     * @covers \mod_playerwords\local\round_service::compute_cooldown_until
     * @return void
     */
    public function test_compute_cooldown_until_no_attempts_yet(): void {
        $instance = $this->make_instance(['cooldown_amount' => 2, 'cooldown_unit' => 'minutes']);
        $this->assertSame(0, round_service::compute_cooldown_until($instance, $this->user->id));
    }

    /**
     * Tests that a cooldown already expired by elapsed time returns 0.
     *
     * @covers \mod_playerwords\local\round_service::compute_cooldown_until
     * @return void
     */
    public function test_compute_cooldown_until_expired_by_time(): void {
        global $DB;

        $instance = $this->make_instance(['cooldown_amount' => 1, 'cooldown_unit' => 'minutes']);
        $DB->insert_record('playerwords_attempts', (object)[
            'playerwordsid' => $instance->id,
            'userid'        => $this->user->id,
            'wordid'        => 0,
            'attempts_used' => 1,
            'time_used'     => 5,
            'completed'     => 1,
            'score'         => 100,
            'timecreated'   => time() - 120,
            'timefinished'  => time() - 120,
        ]);

        $this->assertSame(0, round_service::compute_cooldown_until($instance, $this->user->id));
    }

    /**
     * Tests that changing cooldown_seconds after an attempt already happened takes effect
     * immediately on the next call — never cached from the moment the round finished, the
     * same way mod_quiz's inter-attempt delay always uses its current setting.
     *
     * @covers \mod_playerwords\local\round_service::compute_cooldown_until
     * @return void
     */
    public function test_compute_cooldown_until_reflects_a_later_settings_change(): void {
        global $DB;

        $instance = $this->make_instance(['cooldown_amount' => 1, 'cooldown_unit' => 'days']);
        $DB->insert_record('playerwords_attempts', (object)[
            'playerwordsid' => $instance->id,
            'userid'        => $this->user->id,
            'wordid'        => 0,
            'attempts_used' => 1,
            'time_used'     => 5,
            'completed'     => 1,
            'score'         => 100,
            'timecreated'   => time(),
            'timefinished'  => time(),
        ]);

        $this->assertGreaterThan(time() + 3600, round_service::compute_cooldown_until($instance, $this->user->id));

        // The teacher disables the cooldown entirely.
        $DB->set_field('playerwords', 'cooldown_seconds', 0, ['id' => $instance->id]);
        $instance = $DB->get_record('playerwords', ['id' => $instance->id], '*', MUST_EXIST);

        $this->assertSame(0, round_service::compute_cooldown_until($instance, $this->user->id));
    }

    /**
     * When the previously picked word is removed or unapproved mid-round (e.g. a
     * teacher deletes it from the pool), the next ensure_round_state() call discards
     * the stale reference and picks a fresh word instead of returning an empty target.
     *
     * @covers \mod_playerwords\local\round_service::ensure_round_state
     * @return void
     */
    public function test_ensure_round_state_recovers_when_word_removed_mid_round(): void {
        global $DB;

        $instance = $this->make_instance();
        $DB->insert_record('playerwords_words', (object)[
            'playerwordsid' => $instance->id,
            'word'          => 'mesa',
            'concept'       => 'mesa',
            'hint'          => 'dica',
            'source'        => 'manual',
            'glossaryid'    => 0,
            'approved'      => 1,
            'timecreated'   => time(),
            'addedby'       => $this->user->id,
        ]);

        $state = round_service::load_state($instance->cmid, $this->user->id);
        [$state, , $firstwordid] = round_service::ensure_round_state($state, $instance, $instance->cmid, $this->user->id);
        round_service::save_state($instance->cmid, $this->user->id, $state);

        $DB->delete_records('playerwords_words', ['id' => $firstwordid]);

        // The first call after removal only resets the stale state — by design, the
        // comment in ensure_round_state() says "so the next load picks a fresh word".
        $state = round_service::load_state($instance->cmid, $this->user->id);
        [$state, $resetword] = round_service::ensure_round_state($state, $instance, $instance->cmid, $this->user->id);
        $this->assertSame('', $resetword);
        $this->assertSame(0, $state['wordid']);

        [$state, $secondword, $secondwordid] = round_service::ensure_round_state(
            $state,
            $instance,
            $instance->cmid,
            $this->user->id
        );

        $this->assertNotSame('', $secondword);
        $this->assertNotSame($firstwordid, $secondwordid);
        $this->assertSame(0, $state['attemptsused']);
    }

    /**
     * start_round() reserves an attempt row the moment the round genuinely begins,
     * before the player has made any guess — so abandoning it from here on still
     * spends one of the student's max_rounds instead of a closed tab granting a
     * free re-roll.
     *
     * @covers \mod_playerwords\local\round_service::start_round
     * @return void
     */
    public function test_start_round_reserves_attempt_row(): void {
        global $DB;

        $instance = $this->make_instance();
        [$state] = $this->start_ready_round($instance);

        $this->assertGreaterThan(0, $state['attemptid']);
        $attempts = $DB->get_records('playerwords_attempts', ['playerwordsid' => $instance->id]);
        $this->assertCount(1, $attempts);
        $reserved = reset($attempts);
        $this->assertSame(0, (int)$reserved->timefinished);
        $this->assertSame(1, round_service::count_rounds_played($instance, $this->user->id));
    }

    /**
     * Regression test for the parallel-session bypass: a word can sit armed in a
     * session's state (ensure_round_state() already picked it) for a while before the
     * student ever clicks "Iniciar rodada". If, in the meantime, a second session for
     * the same user reaches max_rounds — e.g. two open tabs, one finishing rounds
     * while the other's lobby still holds a stale armed word — start_round() must
     * refuse to commit the reservation for the first session too, instead of trusting
     * that ensure_round_state() already checked the restriction (it only checks when
     * a NEW word is picked, not when an already-armed one is reused).
     *
     * @covers \mod_playerwords\local\round_service::start_round
     * @return void
     */
    public function test_start_round_revalidates_restriction_for_a_word_armed_before_the_limit_hit(): void {
        global $DB;

        $instance = $this->make_instance(['max_rounds' => 1, 'cooldown_amount' => 0]);
        $state = round_service::load_state($instance->cmid, $this->user->id);
        [$state] = round_service::ensure_round_state($state, $instance, $instance->cmid, $this->user->id);
        $this->assertNotSame(0, $state['wordid'], 'word must be armed before the limit is reached');

        // Simulates a second, concurrent session for the same user finishing a round
        // in the meantime, reaching max_rounds — without going through the first
        // session's own (stale) $state at all.
        $DB->insert_record('playerwords_attempts', (object)[
            'playerwordsid' => $instance->id,
            'userid'        => $this->user->id,
            'wordid'        => 0,
            'attempts_used' => 1,
            'time_used'     => 5,
            'completed'     => 1,
            'score'         => 100,
            'timecreated'   => time(),
            'timefinished'  => time(),
        ]);

        [$state, $notification] = round_service::start_round($state, $instance, $this->user->id);

        $this->assertNotNull($notification);
        $this->assertFalse($state['roundstarted']);
        $this->assertSame(0, $state['attemptid']);
        $this->assertSame(1, $DB->count_records('playerwords_attempts', ['playerwordsid' => $instance->id]));
    }

    /**
     * finish_round() completes the reservation start_round() made instead of inserting
     * a second row — exactly one attempt row exists per round, whether it is still
     * pending or already finished.
     *
     * @covers \mod_playerwords\local\round_service::submit_guess
     * @return void
     */
    public function test_finish_round_completes_reservation_instead_of_duplicating(): void {
        global $DB;

        $instance = $this->make_instance();
        [$state, $roundwordid] = $this->start_ready_round($instance);
        $reservedid = $state['attemptid'];

        [$state] = round_service::submit_guess(
            $state,
            $instance,
            $instance->cmid,
            $this->user->id,
            $roundwordid,
            'boca',
            'boca'
        );

        $this->assertSame(0, $state['attemptid']);
        $attempts = $DB->get_records('playerwords_attempts', ['playerwordsid' => $instance->id]);
        $this->assertCount(1, $attempts);
        $finished = reset($attempts);
        $this->assertSame($reservedid, (int)$finished->id);
        $this->assertGreaterThan(0, (int)$finished->timefinished);
    }

    /**
     * A round abandoned right after starting (session lost before ever finishing) still
     * spends one of the student's max_rounds: the very next round is blocked once the
     * limit is reached, even though the abandoned round was never actually finished.
     *
     * @covers \mod_playerwords\local\round_service::start_round
     * @covers \mod_playerwords\local\round_service::get_round_restriction_notice
     * @return void
     */
    public function test_abandoned_round_counts_towards_max_rounds(): void {
        $instance = $this->make_instance(['max_rounds' => 1, 'cooldown_amount' => 0]);

        // The round is started (reserved) but the student never finishes it — the
        // session is simply never saved past this point, exactly like a closed tab.
        $this->start_ready_round($instance);

        $this->assertNotNull(round_service::get_round_restriction_notice($instance, $this->user->id));
    }

    /**
     * When the reserved word is removed or unapproved mid-round (e.g. a teacher deletes
     * it from the pool), the stale reservation is discarded rather than silently
     * spending one of the student's max_rounds for a round they never got to play.
     *
     * @covers \mod_playerwords\local\round_service::ensure_round_state
     * @return void
     */
    public function test_ensure_round_state_discards_reservation_when_word_removed_mid_round(): void {
        global $DB;

        $instance = $this->make_instance();
        [$state, $roundwordid] = $this->start_ready_round($instance);
        round_service::save_state($instance->cmid, $this->user->id, $state);
        $this->assertSame(1, round_service::count_rounds_played($instance, $this->user->id));

        $DB->delete_records('playerwords_words', ['id' => $roundwordid]);

        $state = round_service::load_state($instance->cmid, $this->user->id);
        [$state] = round_service::ensure_round_state($state, $instance, $instance->cmid, $this->user->id);

        $this->assertSame(0, $state['attemptid']);
        $this->assertSame(0, round_service::count_rounds_played($instance, $this->user->id));
    }

    /**
     * Tests that winning a round with a bounded max_rounds grants the configured
     * PlayerHUD item together with its XP — a finite round limit is the same "bounded
     * source" case block_playerhud itself allows XP for on its own drops.
     *
     * @covers \mod_playerwords\local\round_service::submit_guess
     * @return void
     */
    public function test_submit_guess_correct_grants_item_with_xp_when_bounded(): void {
        global $DB;
        $this->skip_if_no_playerhud();

        $biid = $this->make_block_instance($this->course);
        $itemid = $this->make_item($biid, 30);
        $instance = $this->make_instance([
            'max_rounds' => 5,
            'hud_win_grant_item' => $itemid,
            'hud_win_grant_qty' => 2,
        ]);
        [$state, $roundwordid] = $this->start_ready_round($instance);

        round_service::submit_guess($state, $instance, $instance->cmid, $this->user->id, $roundwordid, 'boca', 'boca');

        $this->assertSame(2, $DB->count_records('block_playerhud_inventory', [
            'userid' => $this->user->id,
            'itemid' => $itemid,
        ]));
        $currentxp = $DB->get_field('block_playerhud_user', 'currentxp', [
            'blockinstanceid' => $biid,
            'userid'          => $this->user->id,
        ]);
        $this->assertSame(60, (int)$currentxp);
    }

    /**
     * Tests that winning a round on an activity with Unlimited rounds still grants the
     * item, but withholds its XP — the anti-farming safeguard this feature needs to
     * match PlayerHUD's own "infinite drop gives no XP" rule.
     *
     * @covers \mod_playerwords\local\round_service::submit_guess
     * @return void
     */
    public function test_submit_guess_correct_grants_item_without_xp_when_unlimited(): void {
        global $DB;
        $this->skip_if_no_playerhud();

        $biid = $this->make_block_instance($this->course);
        $itemid = $this->make_item($biid, 30);
        // The max_rounds override is omitted — make_instance() defaults it to 0 (unlimited).
        $instance = $this->make_instance([
            'hud_win_grant_item' => $itemid,
            'hud_win_grant_qty' => 2,
        ]);
        [$state, $roundwordid] = $this->start_ready_round($instance);

        round_service::submit_guess($state, $instance, $instance->cmid, $this->user->id, $roundwordid, 'boca', 'boca');

        $this->assertSame(2, $DB->count_records('block_playerhud_inventory', [
            'userid' => $this->user->id,
            'itemid' => $itemid,
        ]));
        $currentxp = $DB->get_field('block_playerhud_user', 'currentxp', [
            'blockinstanceid' => $biid,
            'userid'          => $this->user->id,
        ]);
        $this->assertSame(0, (int)$currentxp);
    }

    /**
     * Tests that a lost round never grants the win item, regardless of configuration.
     *
     * @covers \mod_playerwords\local\round_service::submit_guess
     * @return void
     */
    public function test_submit_guess_wrong_does_not_grant_item(): void {
        global $DB;
        $this->skip_if_no_playerhud();

        $biid = $this->make_block_instance($this->course);
        $itemid = $this->make_item($biid, 30);
        $instance = $this->make_instance([
            'max_attempts' => 1,
            'hud_win_grant_item' => $itemid,
            'hud_win_grant_qty' => 2,
        ]);
        [$state, $roundwordid] = $this->start_ready_round($instance);

        round_service::submit_guess($state, $instance, $instance->cmid, $this->user->id, $roundwordid, 'boca', 'casa');

        $this->assertSame(0, $DB->count_records('block_playerhud_inventory', ['userid' => $this->user->id]));
    }

    /**
     * A round cost pointing at a PlayerHUD item that no longer exists is waived instead of
     * blocking the student forever — a deleted item can never be restocked, so charging for
     * it would be a permanent lockout. Mirrors round_presenter::build_hud_cost_info(), which
     * already hides the cost badge in this same case.
     *
     * @covers \mod_playerwords\local\round_service::start_round
     * @return void
     */
    public function test_start_round_waives_cost_when_item_deleted(): void {
        $this->skip_if_no_playerhud();

        $instance = $this->make_instance(['hud_round_cost_item' => 999999, 'hud_round_cost_qty' => 1]);
        $state = round_service::load_state($instance->cmid, $this->user->id);
        [$state] = round_service::ensure_round_state($state, $instance, $instance->cmid, $this->user->id);

        [$state, $notification] = round_service::start_round($state, $instance, $this->user->id);

        $this->assertNull($notification);
        $this->assertTrue($state['roundstarted']);
    }

    /**
     * A round cost pointing at a PlayerHUD item belonging to a different course's block
     * instance is waived, the same as a deleted item — the cross-course leak external_items
     * exists to prevent (block_playerhud_items.id is a single site-wide sequence, so a stale
     * or misconfigured ID could otherwise silently charge against another course's economy).
     * This course has its own PlayerHUD block instance too, proving the rejection is about
     * this specific item's ownership, not merely "no PlayerHUD available in this course".
     *
     * @covers \mod_playerwords\local\round_service::start_round
     * @return void
     */
    public function test_start_round_waives_cost_when_item_belongs_to_other_course(): void {
        $this->skip_if_no_playerhud();

        $this->make_block_instance($this->course);
        $othercourse = $this->getDataGenerator()->create_course();
        $otherbiid = $this->make_block_instance($othercourse);
        $itemid = $this->make_item($otherbiid);

        $instance = $this->make_instance(['hud_round_cost_item' => $itemid, 'hud_round_cost_qty' => 1]);
        $state = round_service::load_state($instance->cmid, $this->user->id);
        [$state] = round_service::ensure_round_state($state, $instance, $instance->cmid, $this->user->id);

        [$state, $notification] = round_service::start_round($state, $instance, $this->user->id);

        $this->assertNull($notification);
        $this->assertTrue($state['roundstarted']);
    }

    /**
     * A hint cost pointing at a PlayerHUD item that no longer exists is waived, same
     * rationale as test_start_round_waives_cost_when_item_deleted().
     *
     * @covers \mod_playerwords\local\round_service::reveal_hint
     * @return void
     */
    public function test_reveal_hint_waives_cost_when_item_deleted(): void {
        $this->skip_if_no_playerhud();

        $instance = $this->make_instance(['hud_hint_cost_item' => 999999, 'hud_hint_cost_qty' => 1]);
        [$state] = $this->start_ready_round($instance);

        [$state, $notification] = round_service::reveal_hint($state, $instance, $this->user->id);

        $this->assertNull($notification);
        $this->assertTrue($state['hintrevealed']);
    }

    /**
     * A round cost pointing at a disabled (not deleted) item still blocks the student when
     * their balance is short. Disabling is reversible, so the cost is deliberately not
     * waived here — only a deleted item (permanently unobtainable) gets that treatment.
     *
     * @covers \mod_playerwords\local\round_service::start_round
     * @return void
     */
    public function test_start_round_still_blocks_when_item_disabled_and_insufficient(): void {
        global $DB;
        $this->skip_if_no_playerhud();

        $biid = $this->make_block_instance($this->course);
        $itemid = $this->make_item($biid);
        $DB->set_field('block_playerhud_items', 'enabled', 0, ['id' => $itemid]);

        $instance = $this->make_instance(['hud_round_cost_item' => $itemid, 'hud_round_cost_qty' => 1]);
        $state = round_service::load_state($instance->cmid, $this->user->id);
        [$state] = round_service::ensure_round_state($state, $instance, $instance->cmid, $this->user->id);

        [$state, $notification] = round_service::start_round($state, $instance, $this->user->id);

        $this->assertNotNull($notification);
        $this->assertFalse($state['roundstarted']);
    }

    /**
     * The guest account plays a free demo: start_round() must skip both the PlayerHUD
     * cost and the {playerwords_attempts} reservation, even when a real cost item is
     * configured and the guest's balance (it has none) would otherwise block it —
     * test_start_round_still_blocks_when_item_disabled_and_insufficient() above proves a
     * regular student is still blocked by the same kind of configuration, so this is a
     * guest-specific waiver, not a general bypass.
     *
     * @covers \mod_playerwords\local\round_service::start_round
     * @return void
     */
    public function test_start_round_guest_never_charges_or_reserves(): void {
        global $DB;
        $this->skip_if_no_playerhud();

        $biid = $this->make_block_instance($this->course);
        $itemid = $this->make_item($biid);
        $instance = $this->make_instance(['hud_round_cost_item' => $itemid, 'hud_round_cost_qty' => 1]);
        $this->setGuestUser();
        $guestid = (int)guest_user()->id;

        $state = round_service::load_state($instance->cmid, $guestid);
        [$state] = round_service::ensure_round_state($state, $instance, $instance->cmid, $guestid);
        [$state, $notification] = round_service::start_round($state, $instance, $guestid);

        $this->assertNull($notification);
        $this->assertTrue($state['roundstarted']);
        $this->assertSame(0, $state['attemptid']);
        $this->assertSame(0, $DB->count_records('playerwords_attempts', ['playerwordsid' => $instance->id]));
    }

    /**
     * The guest account plays a free demo: reveal_hint() must skip the PlayerHUD cost
     * even when a real cost item is configured and the guest's balance would otherwise
     * block it.
     *
     * @covers \mod_playerwords\local\round_service::reveal_hint
     * @return void
     */
    public function test_reveal_hint_guest_never_charges(): void {
        $this->skip_if_no_playerhud();

        $biid = $this->make_block_instance($this->course);
        $itemid = $this->make_item($biid);
        $instance = $this->make_instance(['hud_hint_cost_item' => $itemid, 'hud_hint_cost_qty' => 1]);
        $this->setGuestUser();
        $guestid = (int)guest_user()->id;

        $state = round_service::load_state($instance->cmid, $guestid);
        [$state] = round_service::ensure_round_state($state, $instance, $instance->cmid, $guestid);
        [$state] = round_service::start_round($state, $instance, $guestid);

        [$state, $notification] = round_service::reveal_hint($state, $instance, $guestid);

        $this->assertNull($notification);
        $this->assertTrue($state['hintrevealed']);
    }

    /**
     * The guest account plays a free demo: finishing a round (a win, in this case) must
     * leave no {playerwords_attempts} row, grant no PlayerHUD item, and never touch the
     * gradebook — every guest visitor to a course shares the same account, so none of
     * this could be safely attributed to one specific person.
     *
     * @covers \mod_playerwords\local\round_service::submit_guess
     * @return void
     */
    public function test_finish_round_guest_never_persists(): void {
        global $DB;
        $this->skip_if_no_playerhud();

        $biid = $this->make_block_instance($this->course);
        $itemid = $this->make_item($biid, 30);
        $instance = $this->make_instance(['hud_win_grant_item' => $itemid, 'hud_win_grant_qty' => 2]);
        $this->setGuestUser();
        $guestid = (int)guest_user()->id;

        $state = round_service::load_state($instance->cmid, $guestid);
        [$state, , $roundwordid] = round_service::ensure_round_state($state, $instance, $instance->cmid, $guestid);
        [$state] = round_service::start_round($state, $instance, $guestid);

        [$state] = round_service::submit_guess(
            $state,
            $instance,
            $instance->cmid,
            $guestid,
            $roundwordid,
            'boca',
            'boca'
        );

        $this->assertTrue($state['finished']);
        $this->assertTrue($state['won']);
        $this->assertSame(0, $state['attemptid']);
        $this->assertSame(0, $DB->count_records('playerwords_attempts', ['playerwordsid' => $instance->id]));
        $this->assertSame(0, $DB->count_records('block_playerhud_inventory', ['userid' => $guestid]));
    }
}
