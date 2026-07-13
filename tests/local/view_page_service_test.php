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
 * Unit tests for view_page_service.
 *
 * @package    mod_playerwords
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playerwords\local;

use context_module;

/**
 * Tests for view_page_service::build_page_data() — requires database and session.
 *
 * This is the orchestration entry point used by view.php on every GET: it decides
 * between showing the lobby, a round in progress, a finished round, or a
 * restriction notice (cooldown/round limit). Earlier bugs in this project stemmed
 * exactly from this kind of orchestration logic being duplicated or not persisting
 * state correctly, so each branch is asserted explicitly here.
 */
final class view_page_service_test extends \advanced_testcase {
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
     * @return array{0: \stdClass, 1: \stdClass, 2: context_module} [instance, cm, context]
     */
    private function make_instance(array $overrides = []): array {
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
            'cooldown_amount' => 0,
            'cooldown_unit'   => 'minutes',
        ], $overrides);

        $moduleinfo = $this->getDataGenerator()->create_module('playerwords', $record);

        $DB->insert_record('playerwords_words', (object)[
            'playerwordsid' => $moduleinfo->id,
            'word'          => 'boca',
            'concept'       => 'boca',
            'hint'          => 'dica',
            'source'        => 'manual',
            'glossaryid'    => 0,
            'approved'      => 1,
            'timecreated'   => time(),
            'addedby'       => $this->user->id,
        ]);

        $instance = $DB->get_record('playerwords', ['id' => $moduleinfo->id], '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('playerwords', $instance->id, $this->course->id, false, MUST_EXIST);
        $context = context_module::instance($cm->id);

        return [$instance, $cm, $context];
    }

    /**
     * A fresh visit picks a word and shows the lobby, without starting the timer.
     *
     * @covers \mod_playerwords\local\view_page_service::build_page_data
     * @return void
     */
    public function test_build_page_data_shows_lobby_for_fresh_round(): void {
        [$instance, $cm, $context] = $this->make_instance();

        $pagedata = view_page_service::build_page_data($cm, $instance, $context, $this->user->id);
        $ctx = $pagedata['templatecontext'];

        $this->assertTrue($ctx['hastargetword']);
        $this->assertTrue($ctx['showlobby']);
        $this->assertFalse($ctx['roundstarted']);
        $this->assertSame(0, $pagedata['cooldownuntil']);
        $this->assertSame(0, $pagedata['timeleft']);
    }

    /**
     * The word picked on a fresh visit is persisted to session, so a second call
     * sees the same round instead of picking another word.
     *
     * @covers \mod_playerwords\local\view_page_service::build_page_data
     * @return void
     */
    public function test_build_page_data_persists_picked_word_across_calls(): void {
        [$instance, $cm, $context] = $this->make_instance();

        view_page_service::build_page_data($cm, $instance, $context, $this->user->id);
        $state = round_service::load_state($cm->id, $this->user->id);

        $this->assertGreaterThan(0, $state['wordid']);

        $pagedata = view_page_service::build_page_data($cm, $instance, $context, $this->user->id);
        $statesecond = round_service::load_state($cm->id, $this->user->id);

        $this->assertSame($state['wordid'], $statesecond['wordid']);
        $this->assertTrue($pagedata['templatecontext']['hastargetword']);
    }

    /**
     * Once the round is finished, build_page_data reports it as such and computes
     * a real cooldown from the attempt that was just recorded, instead of picking
     * a fresh word.
     *
     * @covers \mod_playerwords\local\view_page_service::build_page_data
     * @return void
     */
    public function test_build_page_data_reflects_finished_round_and_computes_cooldown(): void {
        [$instance, $cm, $context] = $this->make_instance(['cooldown_amount' => 2, 'cooldown_unit' => 'minutes']);

        $state = round_service::load_state($cm->id, $this->user->id);
        [$state, $targetword, $roundwordid] = round_service::ensure_round_state($state, $instance, $cm->id, $this->user->id);
        [$state] = round_service::start_round($state, $instance, $this->user->id);
        [$state] = round_service::submit_guess($state, $instance, $cm->id, $this->user->id, $roundwordid, $targetword, $targetword);
        round_service::save_state($cm->id, $this->user->id, $state);

        $pagedata = view_page_service::build_page_data($cm, $instance, $context, $this->user->id);

        $this->assertGreaterThan(0, $pagedata['cooldownuntil']);
        $this->assertSame(0, $pagedata['timeleft']);
        $this->assertSame(0, $pagedata['timertotal']);
    }

    /**
     * The forfeit button is only meaningful while a round is actively being played:
     * hidden in the lobby (nothing to forfeit yet), shown once the round starts, and
     * hidden again once it finishes (forfeiting an already-finished round makes no sense).
     *
     * @covers \mod_playerwords\local\view_page_service::build_page_data
     * @return void
     */
    public function test_build_page_data_shows_forfeit_only_during_active_round(): void {
        [$instance, $cm, $context] = $this->make_instance();

        $lobbypagedata = view_page_service::build_page_data($cm, $instance, $context, $this->user->id);
        $this->assertFalse($lobbypagedata['templatecontext']['showforfeit']);

        $state = round_service::load_state($cm->id, $this->user->id);
        [$state, , $roundwordid] = round_service::ensure_round_state($state, $instance, $cm->id, $this->user->id);
        [$state] = round_service::start_round($state, $instance, $this->user->id);
        round_service::save_state($cm->id, $this->user->id, $state);

        $activepagedata = view_page_service::build_page_data($cm, $instance, $context, $this->user->id);
        $this->assertTrue($activepagedata['templatecontext']['showforfeit']);

        [$state] = round_service::submit_guess($state, $instance, $cm->id, $this->user->id, $roundwordid, 'boca', 'boca');
        round_service::save_state($cm->id, $this->user->id, $state);

        $finishedpagedata = view_page_service::build_page_data($cm, $instance, $context, $this->user->id);
        $this->assertFalse($finishedpagedata['templatecontext']['showforfeit']);
    }

    /**
     * The template context always carries the attempt-history toolbar URL and the
     * how-to-play help content shown in the in-game modal, regardless of round state.
     *
     * @covers \mod_playerwords\local\view_page_service::build_page_data
     * @return void
     */
    public function test_build_page_data_includes_toolbar_urls(): void {
        [$instance, $cm, $context] = $this->make_instance();

        $pagedata = view_page_service::build_page_data($cm, $instance, $context, $this->user->id);
        $ctx = $pagedata['templatecontext'];

        $this->assertStringContainsString('myattempts.php', $ctx['myattemptsurl']);
        $this->assertNotEmpty($ctx['helptitle']);
        $this->assertNotEmpty($ctx['introtext']);
        $this->assertNotEmpty($ctx['keyboardtext']);
        $this->assertTrue($ctx['showranking']);
        $this->assertNotEmpty($ctx['rankingtext']);
        $this->assertFalse($ctx['showhud']);
        $this->assertSame('', $ctx['hudtext']);
    }

    /**
     * The help modal omits the ranking tie-break explanation when the teacher has
     * turned ranking off for the activity.
     *
     * @covers \mod_playerwords\local\view_page_service::build_page_data
     * @return void
     */
    public function test_build_page_data_hides_ranking_help_when_ranking_disabled(): void {
        [$instance, $cm, $context] = $this->make_instance(['show_ranking' => 0]);

        $pagedata = view_page_service::build_page_data($cm, $instance, $context, $this->user->id);
        $ctx = $pagedata['templatecontext'];

        $this->assertFalse($ctx['showranking']);
        $this->assertSame('', $ctx['rankingtext']);
    }

    /**
     * The help modal shows the PlayerHUD explanation as soon as any of the round cost,
     * hint cost, or win-grant settings is configured — one is enough to trigger it.
     *
     * @covers \mod_playerwords\local\view_page_service::build_page_data
     * @return void
     */
    public function test_build_page_data_shows_hud_help_when_win_grant_configured(): void {
        [$instance, $cm, $context] = $this->make_instance(['hud_win_grant_item' => 999999]);

        $pagedata = view_page_service::build_page_data($cm, $instance, $context, $this->user->id);
        $ctx = $pagedata['templatecontext'];

        $this->assertTrue($ctx['showhud']);
        $this->assertNotEmpty($ctx['hudtext']);
    }

    /**
     * When the round limit is reached before a word is even picked, the
     * restriction notice is surfaced instead of a target word.
     *
     * @covers \mod_playerwords\local\view_page_service::build_page_data
     * @return void
     */
    public function test_build_page_data_shows_restriction_notice_when_round_limit_reached(): void {
        global $DB;

        [$instance, $cm, $context] = $this->make_instance(['max_rounds' => 1]);

        $DB->insert_record('playerwords_attempts', (object)[
            'playerwordsid' => $instance->id,
            'userid'        => $this->user->id,
            'wordid'        => 1,
            'attempts_used' => 1,
            'time_used'     => 5,
            'completed'     => 1,
            'score'         => 100,
            'timecreated'   => time(),
            'timefinished'  => time(),
        ]);

        $pagedata = view_page_service::build_page_data($cm, $instance, $context, $this->user->id);
        $ctx = $pagedata['templatecontext'];

        $this->assertArrayHasKey('nogamewords', $ctx);
        $this->assertStringContainsString('1', $ctx['nogamewords']);
        $this->assertSame(0, $pagedata['cooldownuntil']);
    }

    /**
     * A user's very first page load of any PlayerWords activity is flagged to
     * auto-show the how-to-play intro, and that first load immediately marks the
     * site-wide preference so it is never repeated — including on the very same
     * lobby, matching intro_service's own contract.
     *
     * @covers \mod_playerwords\local\view_page_service::build_page_data
     * @return void
     */
    public function test_build_page_data_flags_autoshow_intro_once_on_lobby(): void {
        [$instance, $cm, $context] = $this->make_instance();

        $this->assertFalse(intro_service::has_seen_intro($this->user->id));

        $first = view_page_service::build_page_data($cm, $instance, $context, $this->user->id);
        $this->assertTrue($first['shouldautoshowintro']);
        $this->assertTrue(intro_service::has_seen_intro($this->user->id));

        $second = view_page_service::build_page_data($cm, $instance, $context, $this->user->id);
        $this->assertFalse($second['shouldautoshowintro']);
    }

    /**
     * The auto-show flag is site-wide, not per-activity: a user who already saw
     * the intro on one activity must not see it again on a second, different one.
     *
     * @covers \mod_playerwords\local\view_page_service::build_page_data
     * @return void
     */
    public function test_build_page_data_autoshow_intro_does_not_repeat_across_activities(): void {
        [$instancea, $cma, $contexta] = $this->make_instance();
        [$instanceb, $cmb, $contextb] = $this->make_instance();

        $firstactivity = view_page_service::build_page_data($cma, $instancea, $contexta, $this->user->id);
        $this->assertTrue($firstactivity['shouldautoshowintro']);

        $secondactivity = view_page_service::build_page_data($cmb, $instanceb, $contextb, $this->user->id);
        $this->assertFalse($secondactivity['shouldautoshowintro']);
    }

    /**
     * The auto-show flag is still surfaced on the finished-round branch of
     * build_page_data, not only on the fresh-lobby branch — otherwise a user whose
     * very first visit happens to already have a finished round recorded (e.g. an
     * imported attempt) would never see the intro automatically.
     *
     * @covers \mod_playerwords\local\view_page_service::build_page_data
     * @return void
     */
    public function test_build_page_data_flags_autoshow_intro_on_finished_round_branch(): void {
        [$instance, $cm, $context] = $this->make_instance();

        $state = round_service::load_state($cm->id, $this->user->id);
        [$state, $targetword, $roundwordid] = round_service::ensure_round_state($state, $instance, $cm->id, $this->user->id);
        [$state] = round_service::start_round($state, $instance, $this->user->id);
        [$state] = round_service::submit_guess($state, $instance, $cm->id, $this->user->id, $roundwordid, $targetword, $targetword);
        round_service::save_state($cm->id, $this->user->id, $state);

        $pagedata = view_page_service::build_page_data($cm, $instance, $context, $this->user->id);

        $this->assertTrue($pagedata['shouldautoshowintro']);
        $this->assertTrue(intro_service::has_seen_intro($this->user->id));
    }

    /**
     * The auto-show flag is still surfaced on the round-restriction branch of
     * build_page_data (round limit already reached before a word is even picked).
     *
     * @covers \mod_playerwords\local\view_page_service::build_page_data
     * @return void
     */
    public function test_build_page_data_flags_autoshow_intro_on_restriction_branch(): void {
        global $DB;

        [$instance, $cm, $context] = $this->make_instance(['max_rounds' => 1]);

        $DB->insert_record('playerwords_attempts', (object)[
            'playerwordsid' => $instance->id,
            'userid'        => $this->user->id,
            'wordid'        => 1,
            'attempts_used' => 1,
            'time_used'     => 5,
            'completed'     => 1,
            'score'         => 100,
            'timecreated'   => time(),
            'timefinished'  => time(),
        ]);

        $pagedata = view_page_service::build_page_data($cm, $instance, $context, $this->user->id);

        $this->assertTrue($pagedata['shouldautoshowintro']);
        $this->assertTrue(intro_service::has_seen_intro($this->user->id));
    }

    /**
     * The help modal content always carries the review hint pointing back to the
     * toolbar help icon, regardless of round state.
     *
     * @covers \mod_playerwords\local\view_page_service::build_page_data
     * @return void
     */
    public function test_build_page_data_includes_review_hint_in_help_context(): void {
        [$instance, $cm, $context] = $this->make_instance();

        $pagedata = view_page_service::build_page_data($cm, $instance, $context, $this->user->id);

        $this->assertNotEmpty($pagedata['templatecontext']['reviewhint']);
    }
}
