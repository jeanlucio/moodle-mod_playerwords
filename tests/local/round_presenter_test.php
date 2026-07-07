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
 * Tests for round_presenter — pure logic, no database required.
 */
final class round_presenter_test extends \basic_testcase {
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
            'cooldownuntil' => 0,
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
        $this->assertSame('', round_presenter::build_cooldown_text($this->make_state()));
    }

    /**
     * Tests that an active cooldown produces a non-empty formatted string.
     *
     * @covers \mod_playerwords\local\round_presenter::build_cooldown_text
     * @return void
     */
    public function test_build_cooldown_text_active(): void {
        $state = $this->make_state(['cooldownuntil' => time() + 3600]);
        $this->assertNotSame('', round_presenter::build_cooldown_text($state));
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
        $instance = (object)['show_ranking' => 1];
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
     * Tests that the round-result context reveals the word/definition once finished.
     *
     * @covers \mod_playerwords\local\round_presenter::build_round_result_context
     * @return void
     */
    public function test_build_round_result_context_reveals_when_finished(): void {
        $instance = (object)['show_ranking' => 0];
        $cm = (object)['id' => 5];
        $state = $this->make_state([
            'finished'      => true,
            'won'           => true,
            'attemptsused'  => 1,
            'wordtext'      => 'boca',
            'concept'       => 'boca',
            'hint'          => 'segredo',
            'cooldownuntil' => time() + 3600,
        ]);

        $context = round_presenter::build_round_result_context($instance, $cm, $state, 1, true);

        $this->assertTrue($context['showreveal']);
        $this->assertSame('boca', $context['revealword']);
        $this->assertTrue($context['showdefinition']);
        $this->assertSame('segredo', $context['revealdefinition']);
        $this->assertGreaterThan(time(), $context['cooldownuntil']);
        $this->assertTrue($context['cooldownactive']);
    }
}
