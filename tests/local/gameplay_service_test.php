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
 * Unit tests for gameplay_service.
 *
 * @package    mod_playerwords
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playerwords\local;

/**
 * Tests for gameplay_service — pure logic, no database required.
 */
final class gameplay_service_test extends \basic_testcase {
    /**
     * Provides guess/target pairs with expected per-letter feedback arrays.
     *
     * @return array[]
     */
    public static function build_letter_feedback_provider(): array {
        return [
            'all correct' => [
                'guess'    => 'gato',
                'target'   => 'gato',
                'expected' => [0 => 'correct', 1 => 'correct', 2 => 'correct', 3 => 'correct'],
            ],
            'all absent' => [
                'guess'    => 'bbbb',
                'target'   => 'aaaa',
                'expected' => [0 => 'absent', 1 => 'absent', 2 => 'absent', 3 => 'absent'],
            ],
            'last letter absent' => [
                'guess'    => 'gato',
                'target'   => 'gata',
                'expected' => [0 => 'correct', 1 => 'correct', 2 => 'correct', 3 => 'absent'],
            ],
            'all present wrong position' => [
                'guess'    => 'abcd',
                'target'   => 'dcba',
                'expected' => [0 => 'present', 1 => 'present', 2 => 'present', 3 => 'present'],
            ],
            'single letter correct' => [
                'guess'    => 'a',
                'target'   => 'a',
                'expected' => [0 => 'correct'],
            ],
            'single letter absent' => [
                'guess'    => 'a',
                'target'   => 'b',
                'expected' => [0 => 'absent'],
            ],
            'duplicate guess capped to one present' => [
                // Target has only one 'a'; the second 'a' in the guess must be absent.
                'guess'    => 'aab',
                'target'   => 'xxa',
                'expected' => [0 => 'present', 1 => 'absent', 2 => 'absent'],
            ],
            'correct position consumes its letter from the pool' => [
                // Position 0 is correct; pool has one 'b' and one 'a', so positions 1 and 2 are present.
                'guess'    => 'aab',
                'target'   => 'aba',
                'expected' => [0 => 'correct', 1 => 'present', 2 => 'present'],
            ],
            'correct letter exhausts pool so later duplicate is absent' => [
                // Position 0 is correct and consumes the only 'a'; position 2 guess 'a' must be absent.
                'guess'    => 'aba',
                'target'   => 'axx',
                'expected' => [0 => 'correct', 1 => 'absent', 2 => 'absent'],
            ],
        ];
    }

    /**
     * Tests build_letter_feedback across multiple guess/target combinations.
     *
     * @covers \mod_playerwords\local\gameplay_service::build_letter_feedback
     * @dataProvider build_letter_feedback_provider
     * @param string $guess    User guess.
     * @param string $target   Target word.
     * @param array  $expected Expected feedback map indexed by position.
     * @return void
     */
    public function test_build_letter_feedback(string $guess, string $target, array $expected): void {
        $result = gameplay_service::build_letter_feedback($guess, $target);
        $this->assertSame($expected, $result);
    }

    /**
     * Tests that a completed round returns the full configured grade as a float.
     *
     * @covers \mod_playerwords\local\gameplay_service::calculate_round_score
     * @return void
     */
    public function test_calculate_score_completed(): void {
        $instance = (object)['grade' => 100];
        $score = gameplay_service::calculate_round_score($instance, 3, 60, true);
        $this->assertSame(100.0, $score);
    }

    /**
     * Tests that a failed round always returns zero regardless of grade config.
     *
     * @covers \mod_playerwords\local\gameplay_service::calculate_round_score
     * @return void
     */
    public function test_calculate_score_not_completed(): void {
        $instance = (object)['grade' => 100];
        $score = gameplay_service::calculate_round_score($instance, 6, 120, false);
        $this->assertSame(0.0, $score);
    }

    /**
     * Tests that a decimal grade stored as a string is correctly cast to float.
     *
     * @covers \mod_playerwords\local\gameplay_service::calculate_round_score
     * @return void
     */
    public function test_calculate_score_decimal_grade(): void {
        $instance = (object)['grade' => '75.5'];
        $score = gameplay_service::calculate_round_score($instance, 1, 10, true);
        $this->assertSame(75.5, $score);
    }
}
