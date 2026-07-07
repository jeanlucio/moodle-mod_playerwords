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
     * @covers \mod_playerwords\local\round_presenter::build_grid_rows
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
     * @covers \mod_playerwords\local\round_presenter::build_grid_rows
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
     * @covers \mod_playerwords\local\round_presenter::build_cooldown_text
     * @return void
     */
    public function test_build_cooldown_text_inactive(): void {
        $this->assertSame('', round_presenter::build_cooldown_text(0));
    }

    /**
     * Tests that an active cooldown produces a non-empty formatted string.
     *
     * @covers \mod_playerwords\local\round_presenter::build_cooldown_text
     * @return void
     */
    public function test_build_cooldown_text_active(): void {
        $this->assertNotSame('', round_presenter::build_cooldown_text(time() + 3600));
    }

    /**
     * Tests that a not-yet-finished round has no feedback message.
     *
     * @covers \mod_playerwords\local\round_presenter::build_feedback_message
     * @return void
     */
    public function test_build_feedback_message_not_finished(): void {
        $this->assertSame('', round_presenter::build_feedback_message($this->make_state()));
    }

    /**
     * Tests that forfeited, timed-out and lost rounds each produce their own distinct message.
     *
     * @covers \mod_playerwords\local\round_presenter::build_feedback_message
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
     * @covers \mod_playerwords\local\round_presenter::build_feedback_message
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
     * @covers \mod_playerwords\local\round_presenter::build_ranking_context
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
     * @covers \mod_playerwords\local\round_presenter::build_ranking_context
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
     * @covers \mod_playerwords\local\round_presenter::build_row_letters
     * @return void
     */
    public function test_build_row_letters(): void {
        $letters = round_presenter::build_row_letters('casa', [0 => 'absent', 1 => 'correct', 2 => 'correct', 3 => 'absent']);
        $this->assertCount(4, $letters);
        $this->assertSame('C', $letters[0]['letter']);
        $this->assertSame('absent', $letters[0]['state']);
        $this->assertSame('A', $letters[1]['letter']);
        $this->assertSame('correct', $letters[1]['state']);
    }

    /**
     * Tests that the round-result context is structurally blank while the round is active,
     * never exposing the word/definition that are already sitting in session state.
     *
     * @covers \mod_playerwords\local\round_presenter::build_round_result_context
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
    }

    /**
     * Tests that the round-result context reveals the word/definition once finished, and
     * computes the cooldown from the current instance settings rather than session state.
     *
     * @covers \mod_playerwords\local\round_presenter::build_round_result_context
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
        ]);

        $context = round_presenter::build_round_result_context($instance, $cm, $state, $user->id, true);

        $this->assertTrue($context['showreveal']);
        $this->assertSame('boca', $context['revealword']);
        $this->assertTrue($context['showdefinition']);
        $this->assertSame('segredo', $context['revealdefinition']);
        $this->assertGreaterThan(time(), $context['cooldownuntil']);
        $this->assertTrue($context['cooldownactive']);
    }

    /**
     * Tests that changing cooldown_seconds after a round finished takes effect immediately —
     * the specific behaviour that motivated computing cooldown from the DB instead of caching
     * it in session state at the moment the round ended.
     *
     * @covers \mod_playerwords\local\round_presenter::build_round_result_context
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
}
