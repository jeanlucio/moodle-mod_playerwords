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
 * Unit tests for round_presenter.
 *
 * @package    mod_playerwords
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playerwords\local;

/**
 * Tests for round_presenter.
 *
 * Requires database access: build_round_result_context() computes cooldown/restriction
 * fields via round_service, which reads playerwords_attempts directly instead of relying
 * on session state (so a cooldown_seconds change always applies immediately).
 *
 * @covers \mod_playerwords\local\round_presenter
 * @covers \mod_playerwords\local\ranking_service
 */
final class round_presenter_test extends \advanced_testcase {
    /** @var \stdClass Course used by the DB-dependent tests. */
    private \stdClass $course;

    #[\Override]
    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        $this->resetAfterTest();
        require_once($CFG->dirroot . '/mod/playerwords/lib.php');
        $this->course = $this->getDataGenerator()->create_course();
    }

    /**
     * Creates a playerwords instance for the DB-dependent tests.
     *
     * NOTE: playerwords_add_instance() recomputes cooldown_seconds from the transient
     * cooldown_amount/cooldown_unit form fields, ignoring a cooldown_seconds override.
     *
     * @param array $overrides Instance field overrides.
     * @return \stdClass
     */
    private function make_instance(array $overrides = []): \stdClass {
        global $DB;

        $record = array_merge([
            'course'          => $this->course->id,
            'show_ranking'    => 0,
            'cooldown_amount' => 0,
        ], $overrides);

        $instance = $this->getDataGenerator()->create_module('playerwords', $record);
        return $DB->get_record('playerwords', ['id' => $instance->id], '*', MUST_EXIST);
    }

    /**
     * Returns a minimal default state array, overridable per test.
     *
     * @param array $overrides State field overrides.
     * @return array
     */
    private function make_state(array $overrides = []): array {
        return array_merge([
            'wordid'       => 1,
            'wordtext'     => 'boca',
            'concept'      => 'boca',
            'attemptsused' => 0,
            'starttime'    => 0,
            'hint'         => '',
            'hintrevealed' => false,
            'rows'         => [],
            'finished'      => false,
            'won'           => false,
            'forfeited'     => false,
            'timedout'      => false,
            'roundstarted'  => false,
        ], $overrides);
    }

    /**
     * Tests that empty rows use the empty-cell state when no guesses were made yet.
     *
     * @return void
     */
    public function test_build_grid_rows_all_empty(): void {
        $rows = round_presenter::build_grid_rows($this->make_state(), 'boca', 6);
        $this->assertCount(6, $rows);
        $this->assertCount(4, $rows[0]['letters']);
        $this->assertSame('empty', $rows[0]['letters'][0]['state']);
        $this->assertSame('', $rows[0]['letters'][0]['letter']);
    }

    /**
     * Tests that a submitted row renders its letters and per-letter states.
     *
     * @return void
     */
    public function test_build_grid_rows_renders_submitted_row(): void {
        $state = $this->make_state([
            'rows' => [
                ['word' => 'casa', 'feedback' => [0 => 'absent', 1 => 'correct', 2 => 'correct', 3 => 'absent']],
            ],
        ]);

        $rows = round_presenter::build_grid_rows($state, 'boca', 6);

        $this->assertSame('C', $rows[0]['letters'][0]['letter']);
        $this->assertSame('absent', $rows[0]['letters'][0]['state']);
        $this->assertSame('correct', $rows[0]['letters'][1]['state']);
        // The second, not-yet-played row still renders as empty cells.
        $this->assertSame('empty', $rows[1]['letters'][0]['state']);
    }

    /**
     * Tests that an inactive cooldown produces an empty string.
     *
     * @return void
     */
    public function test_build_cooldown_text_inactive(): void {
        $this->assertSame('', round_presenter::build_cooldown_text(0));
    }

    /**
     * Tests that an active cooldown produces a non-empty formatted string.
     *
     * @return void
     */
    public function test_build_cooldown_text_active(): void {
        $this->assertNotSame('', round_presenter::build_cooldown_text(time() + 3600));
    }

    /**
     * Tests that a not-yet-finished round has no feedback message.
     *
     * @return void
     */
    public function test_build_feedback_message_not_finished(): void {
        $this->assertSame('', round_presenter::build_feedback_message($this->make_state()));
    }

    /**
     * Tests that forfeited, timed-out and lost rounds each produce their own distinct message.
     *
     * @return void
     */
    public function test_build_feedback_message_forfeited_timedout_and_lost_differ(): void {
        $forfeited = round_presenter::build_feedback_message($this->make_state(['finished' => true, 'forfeited' => true]));
        $timedout = round_presenter::build_feedback_message($this->make_state(['finished' => true, 'timedout' => true]));
        $lost = round_presenter::build_feedback_message($this->make_state(['finished' => true]));

        $this->assertNotSame($forfeited, $timedout);
        $this->assertNotSame($timedout, $lost);
        $this->assertNotSame($forfeited, $lost);
    }

    /**
     * Tests that a one-attempt win selects a different message than a last-attempt win.
     *
     * @return void
     */
    public function test_build_feedback_message_won_varies_by_attempts(): void {
        $first = round_presenter::build_feedback_message(
            $this->make_state(['finished' => true, 'won' => true, 'attemptsused' => 1])
        );
        $last = round_presenter::build_feedback_message(
            $this->make_state(['finished' => true, 'won' => true, 'attemptsused' => 6])
        );
        $this->assertNotSame($first, $last);
    }

    /**
     * Tests that ranking context keeps its full, stable key set when show_ranking is disabled.
     *
     * @return void
     */
    public function test_build_ranking_context_disabled(): void {
        $instance = (object)['show_ranking' => 0];
        $cm = (object)['id' => 5];
        $context = round_presenter::build_ranking_context($instance, $cm, 1, false);
        $this->assertFalse($context['showranking']);
        $this->assertSame([], $context['rankingrows']);
    }

    /**
     * Tests that ranking context defaults to an empty result set before a round finishes.
     *
     * @return void
     */
    public function test_build_ranking_context_not_finished_yet(): void {
        $instance = (object)['show_ranking' => 1];
        $cm = (object)['id' => 5];
        $context = round_presenter::build_ranking_context($instance, $cm, 1, false);
        $this->assertTrue($context['showranking']);
        $this->assertTrue($context['rankingempty']);
        $this->assertSame([], $context['rankingrows']);
    }

    /**
     * Tests that build_row_letters maps each letter to its uppercase form and cell state.
     *
     * @return void
     */
    public function test_build_row_letters(): void {
        $letters = round_presenter::build_row_letters(
            'casa',
            'casa',
            [0 => 'absent', 1 => 'correct', 2 => 'correct', 3 => 'absent']
        );
        $this->assertCount(4, $letters);
        $this->assertSame('C', $letters[0]['letter']);
        $this->assertSame('absent', $letters[0]['state']);
        $this->assertSame('A', $letters[1]['letter']);
        $this->assertSame('correct', $letters[1]['state']);
    }

    /**
     * A row shows the player's own accented spelling, not the accent-stripped form
     * feedback comparison uses internally — a player who typed "café" sees "CAFÉ"
     * echoed back, not "CAFE".
     *
     * @return void
     */
    public function test_build_row_letters_shows_original_accent(): void {
        $letters = round_presenter::build_row_letters(
            'cafe',
            'café',
            [0 => 'correct', 1 => 'correct', 2 => 'correct', 3 => 'correct']
        );

        $this->assertSame(['C', 'A', 'F', 'É'], array_column($letters, 'letter'));
    }

    /**
     * The winning row (every cell 'correct') shows the target word's own accented
     * spelling from state['wordtext'] — "AÇAÍ" — even though the player typed the
     * unaccented "acai", matching what the post-round reveal text already shows for
     * the same word. A player who does type the accent still sees it too, since it
     * matches wordtext either way; this specifically proves the row does not fall
     * back to echoing the player's own (possibly unaccented) spelling once it has won.
     *
     * @return void
     */
    public function test_build_grid_rows_winning_row_shows_target_spelling_not_players(): void {
        $state = $this->make_state([
            'wordtext' => 'açaí',
            'rows' => [
                ['word' => 'acai', 'originalword' => 'acai', 'feedback' => array_fill(0, 4, 'correct')],
            ],
        ]);

        $rows = round_presenter::build_grid_rows($state, 'acai', 6);

        $this->assertSame(['A', 'Ç', 'A', 'Í'], array_column($rows[0]['letters'], 'letter'));
    }

    /**
     * A wrong row (at least one cell not 'correct') still echoes the player's own
     * typed spelling, never the target word — only a fully correct row reveals the
     * target's own spelling (see test_build_grid_rows_winning_row_shows_target_spelling_not_players()).
     *
     * @return void
     */
    public function test_build_grid_rows_wrong_row_keeps_players_own_spelling(): void {
        $state = $this->make_state([
            'wordtext' => 'açaí',
            'rows' => [
                ['word' => 'cafe', 'originalword' => 'café', 'feedback' => ['absent', 'absent', 'absent', 'present']],
            ],
        ]);

        $rows = round_presenter::build_grid_rows($state, 'acai', 6);

        $this->assertSame(['C', 'A', 'F', 'É'], array_column($rows[0]['letters'], 'letter'));
    }

    /**
     * Tests that the round-result context is structurally blank while the round is active,
     * never exposing the word/definition that are already sitting in session state.
     *
     * @return void
     */
    public function test_build_round_result_context_blank_when_not_finished(): void {
        $instance = $this->make_instance();
        $cm = (object)['id' => 5];
        $state = $this->make_state(['wordtext' => 'boca', 'concept' => 'boca', 'hint' => 'segredo']);

        $context = round_presenter::build_round_result_context($instance, $cm, $state, 1, false);

        $this->assertFalse($context['showreveal']);
        $this->assertSame('', $context['revealword']);
        $this->assertFalse($context['showdefinition']);
        $this->assertSame('', $context['revealdefinition']);
        $this->assertSame(0, $context['cooldownuntil']);
        $this->assertSame('', $context['roundsplayedlabel']);
    }

    /**
     * Tests that the round-result context reveals the word/definition once finished, and
     * computes the cooldown from the current instance settings rather than session state.
     *
     * @return void
     */
    public function test_build_round_result_context_reveals_when_finished(): void {
        global $DB;

        $instance = $this->make_instance(['cooldown_amount' => 2, 'cooldown_unit' => 'minutes']);
        $user = $this->getDataGenerator()->create_user();
        $cm = (object)['id' => 5];
        $state = $this->make_state([
            'finished'      => true,
            'won'           => true,
            'attemptsused'  => 1,
            'wordtext'      => 'boca',
            'concept'       => 'boca',
            'hint'          => 'segredo',
        ]);

        // A round just finished for this user: an attempt row is what the cooldown is
        // computed from (never from session state — see round_service::compute_cooldown_until()).
        $DB->insert_record('playerwords_attempts', (object)[
            'playerwordsid' => $instance->id,
            'userid'        => $user->id,
            'wordid'        => 1,
            'attempts_used' => 1,
            'time_used'     => 5,
            'completed'     => 1,
            'score'         => 100,
            'timecreated'   => time(),
            'timefinished'  => time(),
        ]);

        $context = round_presenter::build_round_result_context($instance, $cm, $state, $user->id, true);

        $this->assertTrue($context['showreveal']);
        $this->assertSame('boca', $context['revealword']);
        $this->assertTrue($context['showdefinition']);
        $this->assertSame('segredo', $context['revealdefinition']);
        $this->assertGreaterThan(time(), $context['cooldownuntil']);
        $this->assertTrue($context['cooldownactive']);
        // Default max_rounds is 0 (unlimited), rendered as the infinity symbol.
        $this->assertSame("Rounds played: 1 / \u{221E}.", $context['roundsplayedlabel']);
    }

    /**
     * Tests that the round-result context reports the played/max rounds counter using
     * the configured limit once one is set, instead of the infinity symbol.
     *
     * @return void
     */
    public function test_build_round_result_context_rounds_played_label_with_limit(): void {
        global $DB;

        $instance = $this->make_instance(['max_rounds' => 10]);
        $user = $this->getDataGenerator()->create_user();
        $cm = (object)['id' => 5];
        $state = $this->make_state(['finished' => true, 'won' => true]);

        for ($i = 0; $i < 3; $i++) {
            $DB->insert_record('playerwords_attempts', (object)[
                'playerwordsid' => $instance->id,
                'userid'        => $user->id,
                'wordid'        => 1,
                'attempts_used' => 1,
                'time_used'     => 5,
                'completed'     => 1,
                'score'         => 100,
                'timecreated'   => time(),
                'timefinished'  => time(),
            ]);
        }

        $context = round_presenter::build_round_result_context($instance, $cm, $state, $user->id, true);

        $this->assertSame('Rounds played: 3 / 10.', $context['roundsplayedlabel']);
    }

    /**
     * Tests that the achieved-score line is absent before the round finishes.
     *
     * @return void
     */
    public function test_build_round_result_context_no_score_achieved_when_not_finished(): void {
        $instance = $this->make_instance();
        $cm = (object)['id' => 5];
        $state = $this->make_state();

        $context = round_presenter::build_round_result_context($instance, $cm, $state, 1, false);

        $this->assertFalse($context['showscoreachieved']);
        $this->assertSame('', $context['scoreachieved']);
    }

    /**
     * Tests that the achieved-score line stays hidden for an ungraded instance — the
     * score is always computed against instance->grade as its ceiling, so an ungraded
     * activity would otherwise always show a misleading 0.00 regardless of the outcome.
     *
     * @return void
     */
    public function test_build_round_result_context_hides_score_achieved_when_ungraded(): void {
        $instance = $this->make_instance(['grade' => 0]);
        $user = $this->getDataGenerator()->create_user();
        $cm = (object)['id' => 5];
        $state = $this->make_state(['finished' => true, 'won' => true, 'score' => 0.0]);

        $context = round_presenter::build_round_result_context($instance, $cm, $state, $user->id, true);

        $this->assertFalse($context['showscoreachieved']);
    }

    /**
     * Tests that the round result surfaces the score round_service::finish_round()
     * writes into session state for this round.
     *
     * @return void
     */
    public function test_build_round_result_context_shows_score_achieved(): void {
        $instance = $this->make_instance(['grade' => 100]);
        $user = $this->getDataGenerator()->create_user();
        $cm = (object)['id' => 5];
        $state = $this->make_state(['finished' => true, 'won' => true, 'score' => 80.0]);

        $context = round_presenter::build_round_result_context($instance, $cm, $state, $user->id, true);

        $this->assertTrue($context['showscoreachieved']);
        $this->assertSame(format_float(80.0, 2), $context['scoreachieved']);
    }

    /**
     * Unlike the grading-method line, the points/scoring-mode summary is meaningful
     * even when only a single round is possible, so it must stay visible there — the
     * only thing that hides it is the activity being ungraded.
     *
     * @return void
     */
    public function test_build_grade_summary_info_relevance(): void {
        $graded = $this->make_instance(['grade' => 100, 'max_rounds' => 0]);
        $ungraded = $this->make_instance(['grade' => 0]);
        $singleround = $this->make_instance(['grade' => 100, 'max_rounds' => 1]);

        $this->assertTrue(round_presenter::build_grade_summary_info($graded)['showgradesummary']);
        $this->assertFalse(round_presenter::build_grade_summary_info($ungraded)['showgradesummary']);
        $this->assertSame('', round_presenter::build_grade_summary_info($ungraded)['gradesummary']);
        $this->assertTrue(round_presenter::build_grade_summary_info($singleround)['showgradesummary']);
    }

    /**
     * The summary text names both the point value and the configured scoring mode.
     *
     * @return void
     */
    public function test_build_grade_summary_info_text(): void {
        $binary = $this->make_instance(['grade' => 100, 'gradescoringmode' => PLAYERWORDS_SCORING_BINARY]);
        $linear = $this->make_instance(['grade' => 50, 'gradescoringmode' => PLAYERWORDS_SCORING_LINEAR]);

        $this->assertSame(
            get_string('lobby_gradesummary', 'mod_playerwords', (object)[
                'points' => format_float(100.0, 2),
                'scoringmode' => get_string('scoringmode_binary', 'mod_playerwords'),
            ]),
            round_presenter::build_grade_summary_info($binary)['gradesummary']
        );
        $this->assertSame(
            get_string('lobby_gradesummary', 'mod_playerwords', (object)[
                'points' => format_float(50.0, 2),
                'scoringmode' => get_string('scoringmode_linear', 'mod_playerwords'),
            ]),
            round_presenter::build_grade_summary_info($linear)['gradesummary']
        );
    }

    /**
     * Tests that the grade and ranking scoring mode names resolve both configured
     * options — the two settings are independent, so each is tested with its own
     * instance rather than assuming they always match.
     *
     * @return void
     */
    public function test_scoring_mode_names(): void {
        $binary = $this->make_instance([
            'gradescoringmode' => PLAYERWORDS_SCORING_BINARY,
            'rankingscoringmode' => PLAYERWORDS_SCORING_BINARY,
        ]);
        $linear = $this->make_instance([
            'gradescoringmode' => PLAYERWORDS_SCORING_LINEAR,
            'rankingscoringmode' => PLAYERWORDS_SCORING_LINEAR,
        ]);

        $this->assertSame(
            get_string('scoringmode_binary', 'mod_playerwords'),
            round_presenter::grade_scoring_mode_name($binary)
        );
        $this->assertSame(
            get_string('scoringmode_binary', 'mod_playerwords'),
            round_presenter::ranking_scoring_mode_name($binary)
        );
        $this->assertSame(
            get_string('scoringmode_linear', 'mod_playerwords'),
            round_presenter::grade_scoring_mode_name($linear)
        );
        $this->assertSame(
            get_string('scoringmode_linear', 'mod_playerwords'),
            round_presenter::ranking_scoring_mode_name($linear)
        );
    }

    /**
     * Tests that the ranking scoring explanation embeds the matching Binary/Linear
     * detail string for each configured mode.
     *
     * @return void
     */
    public function test_ranking_scoring_explanation(): void {
        $binary = $this->make_instance(['rankingscoringmode' => PLAYERWORDS_SCORING_BINARY]);
        $linear = $this->make_instance(['rankingscoringmode' => PLAYERWORDS_SCORING_LINEAR]);

        $this->assertSame(
            get_string(
                'ranking_scoringexplanation',
                'mod_playerwords',
                get_string('ranking_scoringexplanation_binary', 'mod_playerwords')
            ),
            round_presenter::ranking_scoring_explanation($binary)
        );
        $this->assertSame(
            get_string(
                'ranking_scoringexplanation',
                'mod_playerwords',
                get_string('ranking_scoringexplanation_linear', 'mod_playerwords')
            ),
            round_presenter::ranking_scoring_explanation($linear)
        );
    }

    /**
     * Tests that the inline ranking summary is hidden when the activity has ranking
     * turned off, without ever touching ranking_service (no real cm needed).
     *
     * @return void
     */
    public function test_build_ranking_context_hidden_when_ranking_off(): void {
        $instance = $this->make_instance(['show_ranking' => 0]);
        $cm = (object)['id' => 5];
        $user = $this->getDataGenerator()->create_user();

        $context = round_presenter::build_ranking_context($instance, $cm, $user->id, true);

        $this->assertFalse($context['showranking']);
        $this->assertSame([], $context['rankingrows']);
    }

    /**
     * Tests that the inline ranking summary surfaces the same rows ranking.php's own
     * page would show, once a round has actually been completed — the real-data branch
     * of build_ranking_context() that only ever runs once show_ranking is on and the
     * round is finished.
     *
     * @return void
     */
    public function test_build_ranking_context_shows_rows_when_ranking_on(): void {
        $instance = $this->make_instance(['show_ranking' => 1]);
        $cm = get_coursemodule_from_instance('playerwords', $instance->id, $this->course->id, false, MUST_EXIST);
        $user = $this->getDataGenerator()->create_user();
        $modgenerator = $this->getDataGenerator()->get_plugin_generator('mod_playerwords');
        $word = $modgenerator->create_word($instance->id, 'boca');
        $modgenerator->create_attempt($instance->id, $user->id, $word->id, ['rankingpoints' => 80]);

        $context = round_presenter::build_ranking_context($instance, $cm, $user->id, true);

        $this->assertTrue($context['showranking']);
        $this->assertFalse($context['rankingempty']);
        $this->assertCount(1, $context['rankingrows']);
        $this->assertSame(format_float(80.0, 2), $context['rankingrows'][0]['totalscore']);
    }

    /**
     * Tests that the round result announces the PlayerHUD item granted for the win,
     * once a win-grant item is configured and the round was actually won.
     *
     * @return void
     */
    public function test_build_round_result_context_shows_hud_grant_label_on_win(): void {
        $itemid = $this->make_hud_item('Gold Key');
        $instance = $this->make_instance(['hud_win_grant_item' => $itemid, 'hud_win_grant_qty' => 2]);
        $cm = (object)['id' => 5];
        $state = $this->make_state(['finished' => true, 'won' => true]);

        $context = round_presenter::build_round_result_context($instance, $cm, $state, 1, true);

        $this->assertStringContainsString('Gold Key', $context['huditemgrantedlabel']);
    }

    /**
     * Tests that no grant label is shown when the round was lost, or when no win-grant
     * item is configured at all.
     *
     * @return void
     */
    public function test_build_round_result_context_no_hud_grant_label_on_loss_or_unconfigured(): void {
        $itemid = $this->make_hud_item('Gold Key');
        $cm = (object)['id' => 5];

        $instancewithitem = $this->make_instance(['hud_win_grant_item' => $itemid]);
        $lost = $this->make_state(['finished' => true, 'won' => false]);
        $lostcontext = round_presenter::build_round_result_context($instancewithitem, $cm, $lost, 1, true);
        $this->assertSame('', $lostcontext['huditemgrantedlabel']);

        $instancewithoutitem = $this->make_instance();
        $won = $this->make_state(['finished' => true, 'won' => true]);
        $unconfiguredcontext = round_presenter::build_round_result_context($instancewithoutitem, $cm, $won, 1, true);
        $this->assertSame('', $unconfiguredcontext['huditemgrantedlabel']);
    }

    /**
     * The guest account plays a free demo: round_service::finish_round() never actually
     * grants a PlayerHUD item to it, so the round result must not announce one even when
     * the state says the round was won and a win-grant item is configured — announcing
     * it would claim something that did not happen.
     *
     * @return void
     */
    public function test_build_round_result_context_no_hud_grant_label_for_guest(): void {
        $itemid = $this->make_hud_item('Gold Key');
        $instance = $this->make_instance(['hud_win_grant_item' => $itemid, 'hud_win_grant_qty' => 2]);
        $cm = (object)['id' => 5];
        $state = $this->make_state(['finished' => true, 'won' => true]);
        $this->setGuestUser();

        $context = round_presenter::build_round_result_context($instance, $cm, $state, (int)guest_user()->id, true);

        $this->assertSame('', $context['huditemgrantedlabel']);
    }

    /**
     * Tests that changing cooldown_seconds after a round finished takes effect immediately —
     * the specific behaviour that motivated computing cooldown from the DB instead of caching
     * it in session state at the moment the round ended.
     *
     * @return void
     */
    public function test_cooldown_reflects_a_later_settings_change(): void {
        global $DB;

        // Round finished under a long (1 day) cooldown.
        $instance = $this->make_instance(['cooldown_amount' => 1, 'cooldown_unit' => 'days']);
        $user = $this->getDataGenerator()->create_user();
        $cm = (object)['id' => 5];
        $state = $this->make_state(['finished' => true, 'won' => true]);

        $DB->insert_record('playerwords_attempts', (object)[
            'playerwordsid' => $instance->id,
            'userid'        => $user->id,
            'wordid'        => 1,
            'attempts_used' => 1,
            'time_used'     => 5,
            'completed'     => 1,
            'score'         => 100,
            'timecreated'   => time(),
            'timefinished'  => time(),
        ]);

        $before = round_presenter::build_round_result_context($instance, $cm, $state, $user->id, true);
        $this->assertTrue($before['cooldownactive']);
        $this->assertGreaterThan(time() + 3600, $before['cooldownuntil']);

        // The teacher disables the cooldown entirely.
        $DB->set_field('playerwords', 'cooldown_seconds', 0, ['id' => $instance->id]);
        $instance = $DB->get_record('playerwords', ['id' => $instance->id], '*', MUST_EXIST);

        $after = round_presenter::build_round_result_context($instance, $cm, $state, $user->id, true);
        $this->assertFalse($after['cooldownactive']);
        $this->assertSame(0, $after['cooldownuntil']);
    }

    /** @var int|null Memoized PlayerHUD block instance ID for $this->course. */
    private ?int $hudblockinstanceid = null;

    /**
     * Returns the PlayerHUD block instance ID for $this->course, creating it on first use.
     *
     * Items must belong to a real block instance in the activity's own course now that
     * hud_service delegates to external_items, which validates ownership before doing
     * anything — a placeholder blockinstanceid would make every cost/grant check fail.
     *
     * @return int
     */
    private function get_hud_block_instance(): int {
        global $DB;

        if ($this->hudblockinstanceid === null) {
            $ctx = \context_course::instance($this->course->id);
            $this->hudblockinstanceid = (int) $DB->insert_record('block_instances', (object) [
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
        }

        return $this->hudblockinstanceid;
    }

    /**
     * Inserts a block_playerhud_items record, skipping the test if the block is absent.
     *
     * @param string $name Item display name.
     * @return int Item id.
     */
    private function make_hud_item(string $name): int {
        global $DB;
        if (!$DB->get_manager()->table_exists('block_playerhud_items')) {
            $this->markTestSkipped('block_playerhud not installed.');
        }
        return $DB->insert_record('block_playerhud_items', (object)[
            'blockinstanceid' => $this->get_hud_block_instance(),
            'name'            => $name,
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
     * The lobby shows a PlayerHUD cost hint when a valid item is configured and the
     * round has not started yet, and disables starting when the user's balance is
     * short of the required quantity.
     *
     * @return void
     */
    public function test_build_lobby_context_shows_hud_cost_when_item_configured(): void {
        $itemid = $this->make_hud_item('Chave de Ouro');
        $instance = $this->make_instance(['hud_round_cost_item' => $itemid, 'hud_round_cost_qty' => 2]);
        $state = $this->make_state();
        $user = $this->getDataGenerator()->create_user();

        $context = round_presenter::build_lobby_context($instance, $state, $user->id);

        $this->assertTrue($context['hudstartcost']);
        $this->assertStringContainsString('Chave de Ouro', $context['hudstartcostlabel']);
        $this->assertFalse($context['canstart']);
    }

    /**
     * The guest account plays a free demo: round_service::start_round() never actually
     * charges it, so the lobby must not show a cost it won't apply, nor block starting
     * on a PlayerHUD balance the guest doesn't have (it has none at all).
     *
     * @return void
     */
    public function test_build_lobby_context_no_hud_cost_for_guest(): void {
        $itemid = $this->make_hud_item('Chave de Ouro');
        $instance = $this->make_instance(['hud_round_cost_item' => $itemid, 'hud_round_cost_qty' => 2]);
        $state = $this->make_state();
        $this->setGuestUser();

        $context = round_presenter::build_lobby_context($instance, $state, (int)guest_user()->id);

        $this->assertFalse($context['hudstartcost']);
        $this->assertSame('', $context['hudstartcostlabel']);
        $this->assertTrue($context['canstart']);
    }

    /**
     * The lobby allows starting once the user's balance meets the required quantity.
     *
     * @return void
     */
    public function test_build_lobby_context_canstart_true_with_enough_balance(): void {
        global $DB;

        $itemid = $this->make_hud_item('Chave de Ouro');
        $instance = $this->make_instance(['hud_round_cost_item' => $itemid, 'hud_round_cost_qty' => 1]);
        $state = $this->make_state();
        $user = $this->getDataGenerator()->create_user();
        $DB->insert_record('block_playerhud_inventory', (object)[
            'userid'      => $user->id,
            'itemid'      => $itemid,
            'dropid'      => 0,
            'source'      => 'manual',
            'timecreated' => time(),
        ]);

        $context = round_presenter::build_lobby_context($instance, $state, $user->id);

        $this->assertTrue($context['canstart']);
    }

    /**
     * The lobby hides the PlayerHUD cost hint once the round has already started —
     * the cost was already charged, so it should not keep being advertised.
     *
     * @return void
     */
    public function test_build_lobby_context_no_hud_cost_once_round_started(): void {
        $itemid = $this->make_hud_item('Chave de Ouro');
        $instance = $this->make_instance(['hud_round_cost_item' => $itemid]);
        $state = $this->make_state(['roundstarted' => true]);
        $user = $this->getDataGenerator()->create_user();

        $context = round_presenter::build_lobby_context($instance, $state, $user->id);

        $this->assertFalse($context['hudstartcost']);
        $this->assertSame('', $context['hudstartcostlabel']);
        $this->assertTrue($context['canstart']);
    }

    /**
     * The lobby's timer info text is populated only when the activity timer is enabled.
     *
     * @return void
     */
    public function test_build_lobby_context_timer_info_only_when_enabled(): void {
        $withtimer = $this->make_instance(['timer_minutes' => 3]);
        $withouttimer = $this->make_instance();
        $state = $this->make_state();
        $user = $this->getDataGenerator()->create_user();

        $enabledctx = round_presenter::build_lobby_context($withtimer, $state, $user->id);
        $disabledctx = round_presenter::build_lobby_context($withouttimer, $state, $user->id);

        $this->assertTrue($enabledctx['timerenabled']);
        $this->assertNotSame('', $enabledctx['lobbytimerinfo']);
        $this->assertFalse($disabledctx['timerenabled']);
        $this->assertSame('', $disabledctx['lobbytimerinfo']);
    }

    /**
     * The lobby always shows the played/max rounds counter, using the infinity symbol
     * when the activity allows unlimited rounds (max_rounds = 0, the default).
     *
     * @return void
     */
    public function test_build_lobby_context_rounds_played_label_unlimited(): void {
        $instance = $this->make_instance();
        $state = $this->make_state();
        $user = $this->getDataGenerator()->create_user();

        $context = round_presenter::build_lobby_context($instance, $state, $user->id);

        $this->assertSame("Rounds played: 0 / \u{221E}.", $context['roundsplayedlabel']);
    }

    /**
     * The lobby's rounds-played counter reflects both the configured limit and the
     * rounds the user has already completed for this instance.
     *
     * @return void
     */
    public function test_build_lobby_context_rounds_played_label_with_limit(): void {
        global $DB;

        $instance = $this->make_instance(['max_rounds' => 10]);
        $state = $this->make_state();
        $user = $this->getDataGenerator()->create_user();
        $DB->insert_record('playerwords_attempts', (object)[
            'playerwordsid' => $instance->id,
            'userid'        => $user->id,
            'wordid'        => 1,
            'attempts_used' => 1,
            'time_used'     => 5,
            'completed'     => 1,
            'score'         => 100,
            'timecreated'   => time(),
            'timefinished'  => time(),
        ]);

        $context = round_presenter::build_lobby_context($instance, $state, $user->id);

        $this->assertSame('Rounds played: 1 / 10.', $context['roundsplayedlabel']);
    }

    /**
     * The lobby shows the grading method info line when grading is enabled and more than
     * one round is possible, mirroring mod_quiz's pre-attempt "Grading method: X" message.
     *
     * @return void
     */
    public function test_build_lobby_context_shows_grading_method_info_when_relevant(): void {
        global $CFG;
        require_once($CFG->dirroot . '/mod/playerwords/lib.php');

        $instance = $this->make_instance([
            'grade'       => 100,
            'max_rounds'  => 0,
            'grademethod' => PLAYERWORDS_GRADE_AVERAGE,
        ]);
        $state = $this->make_state();
        $user = $this->getDataGenerator()->create_user();

        $context = round_presenter::build_lobby_context($instance, $state, $user->id);

        $this->assertTrue($context['showgradingmethodinfo']);
        $this->assertStringContainsString('Average grade', $context['gradingmethodinfo']);
    }

    /**
     * The lobby hides the grading method info line when only a single round is allowed —
     * every grading method would produce the same value, so naming it is just noise.
     *
     * @return void
     */
    public function test_build_lobby_context_hides_grading_method_info_for_single_round(): void {
        $instance = $this->make_instance(['grade' => 100, 'max_rounds' => 1]);
        $state = $this->make_state();
        $user = $this->getDataGenerator()->create_user();

        $context = round_presenter::build_lobby_context($instance, $state, $user->id);

        $this->assertFalse($context['showgradingmethodinfo']);
        $this->assertSame('', $context['gradingmethodinfo']);
    }

    /**
     * The lobby hides the grading method info line when the activity is not graded.
     *
     * @return void
     */
    public function test_build_lobby_context_hides_grading_method_info_when_ungraded(): void {
        $instance = $this->make_instance(['grade' => 0]);
        $state = $this->make_state();
        $user = $this->getDataGenerator()->create_user();

        $context = round_presenter::build_lobby_context($instance, $state, $user->id);

        $this->assertFalse($context['showgradingmethodinfo']);
    }

    /**
     * Tests that hints_enabled=0 hides the hint button entirely, even though the
     * picked word has a hint configured.
     *
     * @return void
     */
    public function test_build_round_panel_context_hints_disabled_hides_button(): void {
        $instance = $this->make_instance(['hints_enabled' => 0]);
        $cm = (object)['id' => 5];
        $user = $this->getDataGenerator()->create_user();
        $state = $this->make_state(['hint' => 'dica', 'hintrevealed' => false]);

        $context = round_presenter::build_round_panel_context($instance, $cm, $state, 'boca', $user->id);

        $this->assertFalse($context['canhint']);
    }

    /**
     * The round panel shows the PlayerHUD balance/cost line, and disables the hint
     * button, while the user's balance is short of the required quantity.
     *
     * @return void
     */
    public function test_build_round_panel_context_hint_button_shows_hud_cost(): void {
        $itemid = $this->make_hud_item('Lupa');
        $instance = $this->make_instance(['hud_hint_cost_item' => $itemid, 'hud_hint_cost_qty' => 1]);
        $cm = (object)['id' => 5];
        $user = $this->getDataGenerator()->create_user();
        $state = $this->make_state(['hint' => 'dica', 'hintrevealed' => false]);

        $context = round_presenter::build_round_panel_context($instance, $cm, $state, 'boca', $user->id);

        $this->assertTrue($context['hudhintcost']);
        $this->assertStringContainsString('Lupa', $context['hudhintcostlabel']);
        $this->assertFalse($context['canaffordhint']);
    }

    /**
     * The guest account plays a free demo: round_service::reveal_hint() never actually
     * charges it, so the round panel must not show a hint cost it won't apply, nor
     * block revealing the hint on a PlayerHUD balance the guest doesn't have.
     *
     * @return void
     */
    public function test_build_round_panel_context_no_hint_cost_for_guest(): void {
        $itemid = $this->make_hud_item('Lupa');
        $instance = $this->make_instance(['hud_hint_cost_item' => $itemid, 'hud_hint_cost_qty' => 1]);
        $cm = (object)['id' => 5];
        $state = $this->make_state(['hint' => 'dica', 'hintrevealed' => false]);
        $this->setGuestUser();

        $context = round_presenter::build_round_panel_context($instance, $cm, $state, 'boca', (int)guest_user()->id);

        $this->assertFalse($context['hudhintcost']);
        $this->assertSame('', $context['hudhintcostlabel']);
        $this->assertTrue($context['canaffordhint']);
    }

    /**
     * The confirmation modal's save button can be enabled once the user's balance
     * meets the required quantity.
     *
     * @return void
     */
    public function test_build_round_panel_context_canaffordhint_true_with_enough_balance(): void {
        global $DB;

        $itemid = $this->make_hud_item('Lupa');
        $instance = $this->make_instance(['hud_hint_cost_item' => $itemid, 'hud_hint_cost_qty' => 1]);
        $cm = (object)['id' => 5];
        $user = $this->getDataGenerator()->create_user();
        $DB->insert_record('block_playerhud_inventory', (object)[
            'userid'      => $user->id,
            'itemid'      => $itemid,
            'dropid'      => 0,
            'source'      => 'manual',
            'timecreated' => time(),
        ]);
        $state = $this->make_state(['hint' => 'dica', 'hintrevealed' => false]);

        $context = round_presenter::build_round_panel_context($instance, $cm, $state, 'boca', $user->id);

        $this->assertTrue($context['canaffordhint']);
    }

    /**
     * The hint cost label reflects the user's actual balance, distinct from the
     * configured required quantity.
     *
     * @return void
     */
    public function test_build_round_panel_context_hint_cost_label_reflects_balance(): void {
        global $DB;

        $itemid = $this->make_hud_item('Lupa');
        $instance = $this->make_instance(['hud_hint_cost_item' => $itemid, 'hud_hint_cost_qty' => 2]);
        $cm = (object)['id' => 5];
        $user = $this->getDataGenerator()->create_user();
        $DB->insert_record('block_playerhud_inventory', (object)[
            'userid'      => $user->id,
            'itemid'      => $itemid,
            'dropid'      => 0,
            'source'      => 'manual',
            'timecreated' => time(),
        ]);
        $state = $this->make_state(['hint' => 'dica', 'hintrevealed' => false]);

        $context = round_presenter::build_round_panel_context($instance, $cm, $state, 'boca', $user->id);

        $this->assertStringContainsString('2×', $context['hudhintcostlabel']);
        $this->assertStringContainsString('have 1', $context['hudhintcostlabel']);
    }

    /**
     * The round panel omits the PlayerHUD cost line once the hint has already been
     * revealed — the cost is never charged twice.
     *
     * @return void
     */
    public function test_build_round_panel_context_hint_button_omits_cost_once_revealed(): void {
        $itemid = $this->make_hud_item('Lupa');
        $instance = $this->make_instance(['hud_hint_cost_item' => $itemid]);
        $cm = (object)['id' => 5];
        $user = $this->getDataGenerator()->create_user();
        $state = $this->make_state(['hint' => 'dica', 'hintrevealed' => true]);

        $context = round_presenter::build_round_panel_context($instance, $cm, $state, 'boca', $user->id);

        $this->assertFalse($context['hudhintcost']);
        $this->assertSame('', $context['hudhintcostlabel']);
    }

    /**
     * timeleft stays 0 while the round has not started yet, even with a timer configured.
     *
     * @return void
     */
    public function test_build_round_panel_context_timeleft_zero_before_round_started(): void {
        $instance = $this->make_instance(['timer_minutes' => 2]);
        $cm = (object)['id' => 5];
        $user = $this->getDataGenerator()->create_user();
        $state = $this->make_state(['roundstarted' => false]);

        $context = round_presenter::build_round_panel_context($instance, $cm, $state, 'boca', $user->id);

        $this->assertSame(0, $context['timeleft']);
    }
}
