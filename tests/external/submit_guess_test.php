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
 *
 * @covers \mod_playerwords\external\submit_guess
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
     * Inserts a genuine block_playerhud_items record (with its own block instance).
     *
     * @return int Item id.
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
            'name'            => 'Chave de Ouro',
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
     * Puts a round in progress for the current guest user. Must run after
     * $this->setGuestUser() — setUser()/setGuestUser() empties the session, so any
     * session state written before it is silently lost.
     *
     * @param \stdClass $instance Activity instance.
     * @return void
     */
    private function start_round_for_guest(\stdClass $instance): void {
        $guestid = (int)guest_user()->id;
        $state = round_service::load_state($instance->cmid, $guestid);
        [$state] = round_service::ensure_round_state($state, $instance, $instance->cmid, $guestid);
        [$state] = round_service::start_round($state, $instance, $guestid);
        round_service::save_state($instance->cmid, $guestid, $state);
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
        // Regression guard: build_round_result_context() must keep returning exactly
        // the keys roundresult_structure() declares, or the external API silently
        // strips them on this dispatch path.
        $this->assertArrayHasKey('showscoreachieved', $data['roundresult']);
        $this->assertArrayHasKey('showranking', $data['roundresult']);
    }

    /**
     * A winning guess's immediate per-row feedback shows the target word's own
     * accented spelling, not the player's own (possibly unaccented) typed guess —
     * matching what round_presenter::build_grid_rows() already does for the full
     * grid, since this response is built through the same round_presenter::
     * resolve_row_display_word() helper rather than duplicating the logic.
     *
     * @return void
     */
    public function test_correct_guess_feedback_shows_target_accent(): void {
        global $DB;
        $instance = $this->make_instance();
        // Only one candidate word for the round, so pick_round_word() cannot pick
        // anything but the accented one this test actually means to exercise.
        $DB->delete_records('playerwords_words', ['playerwordsid' => $instance->id, 'word' => 'boca']);
        $DB->insert_record('playerwords_words', (object)[
            'playerwordsid' => $instance->id,
            'word'          => 'açaí',
            'concept'       => 'açaí',
            'hint'          => 'fruto roxo da amazonia',
            'source'        => 'manual',
            'glossaryid'    => 0,
            'approved'      => 1,
            'timecreated'   => time(),
            'addedby'       => $this->student->id,
        ]);

        $this->setUser($this->student);
        $this->start_round_for_student($instance);

        $result = $this->call_submit_guess($instance->cmid, 'acai');

        $this->assertFalse($result['error']);
        $data = $result['data'];
        $this->assertTrue($data['won']);
        $this->assertSame(['A', 'Ç', 'A', 'Í'], array_column($data['feedback'], 'letter'));
    }

    /**
     * Tests that an out-of-attempts loss also reveals the word, and never before that.
     *
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
     * Regression test for the round-cost bypass: a student who skips the "Iniciar
     * rodada" button — the only place a configured PlayerHUD round cost is actually
     * charged — and calls mod_playerwords_submit_guess directly through the real web
     * service dispatch path must be rejected, even with a correct guess for the word
     * already sitting in session (view.php's GET-time ensure_round_state() call puts it
     * there before the student ever clicks anything). The round must not finish, must
     * not be scored, and must leave no {playerwords_attempts} row behind.
     *
     * @return void
     */
    public function test_rejects_guess_when_round_not_started_bypassing_hud_cost(): void {
        global $DB;
        $this->skip_if_no_playerhud();

        $itemid = $this->make_hud_item();
        $instance = $this->make_instance(['hud_round_cost_item' => $itemid, 'hud_round_cost_qty' => 1]);
        $this->setUser($this->student);

        // Load the target word into session (what view.php's GET does), but never call
        // start_round — the exact shape of the exploit in the security report's PoC.
        $state = round_service::load_state($instance->cmid, $this->student->id);
        [$state] = round_service::ensure_round_state($state, $instance, $instance->cmid, $this->student->id);
        round_service::save_state($instance->cmid, $this->student->id, $state);

        $result = $this->call_submit_guess($instance->cmid, 'boca');

        $this->assertFalse($result['error']);
        $data = $result['data'];
        $this->assertFalse($data['finished']);
        $this->assertNotEmpty($data['notification']);
        $this->assertSame(0, $DB->count_records('playerwords_attempts', ['playerwordsid' => $instance->id]));
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

        $result = $this->call_submit_guess($instance->cmid, 'boca');

        $this->assertTrue($result['error']);
    }

    /**
     * The guest account is allowed to play a free demo through the real web service
     * dispatch path, all the way to winning a round: the call succeeds and the response
     * reveals the word as usual, but round_service::finish_round() must leave no
     * {playerwords_attempts} row behind and grant no PlayerHUD item — every guest
     * visitor to a course shares the same account, so nothing here could be safely
     * attributed to one specific person.
     *
     * @return void
     */
    public function test_guest_can_win_demo_without_persisting(): void {
        global $DB;

        $instance = $this->make_instance();
        $this->enable_guest_access();
        $this->setGuestUser();
        $this->start_round_for_guest($instance);

        $result = $this->call_submit_guess($instance->cmid, 'boca');

        $this->assertFalse($result['error']);
        $data = $result['data'];
        $this->assertTrue($data['finished']);
        $this->assertTrue($data['won']);
        $this->assertSame('boca', $data['roundresult']['revealword']);
        $this->assertSame(0, $DB->count_records('playerwords_attempts', ['playerwordsid' => $instance->id]));
    }

    /**
     * Tests that timeleft reflects seconds actually remaining while the round is
     * still in progress (timer enabled).
     *
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
     * @return void
     */
    public function test_fractional_ranking_points_survive_the_webservice_call(): void {
        // Grade=0 (ungraded) deliberately: ranking points are scored against the fixed
        // PLAYERWORDS_RANKING_BASE_POINTS, never the activity's own grade, so 83.33
        // below can only have come from that fixed base — proof at the webservice
        // layer, not just the gameplay_service unit tests, that grade=0 no longer
        // means "ranking is always zero".
        $instance = $this->make_instance([
            'grade'              => 0,
            'show_ranking'       => 1,
            'max_attempts'       => 7,
            'rankingscoringmode' => PLAYERWORDS_SCORING_LINEAR,
        ]);
        $this->setUser($this->student);
        $this->start_round_for_student($instance);

        // Two wrong guesses first, so the winning guess lands on the 3rd of 7 attempts —
        // the first two attempts share the same full-credit plateau in Linear mode, so
        // rankingpoints = 100 * (7 - 3 + 1) / (7 - 1) = 83.33, a genuinely fractional value.
        $this->call_submit_guess($instance->cmid, 'casa');
        $this->call_submit_guess($instance->cmid, 'casa');
        $result = $this->call_submit_guess($instance->cmid, 'boca');

        $this->assertFalse($result['error']);
        $rows = $result['data']['roundresult']['rankingrows'];
        $this->assertNotEmpty($rows);
        $this->assertSame('83.33', $rows[0]['totalscore']);
    }
}
