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
    #[\Override]
    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        require_once($CFG->dirroot . '/mod/playerwords/lib.php');
    }

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

    /**
     * Tests that linear grade scoring gives full marks on both of the first two
     * attempts — a confident second guess is not treated as less deserving than a
     * first-try one.
     *
     * @covers \mod_playerwords\local\gameplay_service::calculate_round_score
     * @return void
     */
    public function test_calculate_score_linear_first_two_attempts(): void {
        $instance = (object)['grade' => 100, 'max_attempts' => 6, 'gradescoringmode' => PLAYERWORDS_SCORING_LINEAR];
        $this->assertSame(100.0, gameplay_service::calculate_round_score($instance, 1, 10, true));
        $this->assertSame(100.0, gameplay_service::calculate_round_score($instance, 2, 10, true));
    }

    /**
     * Tests that linear grade scoring still awards a positive share on the last
     * allowed attempt — never collapsing all the way to zero on a win. The scale is
     * spread over (max_attempts − 1) steps, since the first two attempts share the
     * same full-credit plateau, so the last attempt here earns 100 / 5, not 100 / 6.
     *
     * @covers \mod_playerwords\local\gameplay_service::calculate_round_score
     * @return void
     */
    public function test_calculate_score_linear_last_attempt(): void {
        $instance = (object)['grade' => 100, 'max_attempts' => 6, 'gradescoringmode' => PLAYERWORDS_SCORING_LINEAR];
        $score = gameplay_service::calculate_round_score($instance, 6, 10, true);
        $this->assertEqualsWithDelta(20.0, $score, 0.001);
    }

    /**
     * Tests that linear grade scoring still returns zero for a round not completed.
     *
     * @covers \mod_playerwords\local\gameplay_service::calculate_round_score
     * @return void
     */
    public function test_calculate_score_linear_not_completed(): void {
        $instance = (object)['grade' => 100, 'max_attempts' => 6, 'gradescoringmode' => PLAYERWORDS_SCORING_LINEAR];
        $score = gameplay_service::calculate_round_score($instance, 6, 10, false);
        $this->assertSame(0.0, $score);
    }

    /**
     * Tests that binary ranking points match the full grade on a win.
     *
     * @covers \mod_playerwords\local\gameplay_service::calculate_ranking_points
     * @return void
     */
    public function test_calculate_ranking_points_binary(): void {
        $instance = (object)['grade' => 100];
        $points = gameplay_service::calculate_ranking_points($instance, 3, true);
        $this->assertSame(100.0, $points);
    }

    /**
     * Tests that linear ranking points give full credit on the first two attempts,
     * then scale down proportionally over the remaining ones, independently from
     * whatever the grade scoring mode is set to.
     *
     * @covers \mod_playerwords\local\gameplay_service::calculate_ranking_points
     * @return void
     */
    public function test_calculate_ranking_points_linear(): void {
        $instance = (object)[
            'grade'              => 100,
            'max_attempts'       => 6,
            'gradescoringmode'   => PLAYERWORDS_SCORING_BINARY,
            'rankingscoringmode' => PLAYERWORDS_SCORING_LINEAR,
        ];
        $this->assertSame(100.0, gameplay_service::calculate_ranking_points($instance, 1, true));
        $this->assertSame(100.0, gameplay_service::calculate_ranking_points($instance, 2, true));
        $this->assertEqualsWithDelta(80.0, gameplay_service::calculate_ranking_points($instance, 3, true), 0.001);
        $this->assertEqualsWithDelta(20.0, gameplay_service::calculate_ranking_points($instance, 6, true), 0.001);
    }

    /**
     * Tests that linear scoring degenerates to full credit on every attempt when
     * max_attempts is 2 or fewer, since both allowed attempts fall within the
     * full-credit plateau.
     *
     * @covers \mod_playerwords\local\gameplay_service::calculate_ranking_points
     * @return void
     */
    public function test_calculate_ranking_points_linear_degenerates_at_low_max_attempts(): void {
        $instance = (object)[
            'grade'              => 100,
            'max_attempts'       => 2,
            'rankingscoringmode' => PLAYERWORDS_SCORING_LINEAR,
        ];
        $this->assertSame(100.0, gameplay_service::calculate_ranking_points($instance, 1, true));
        $this->assertSame(100.0, gameplay_service::calculate_ranking_points($instance, 2, true));
    }

    /**
     * Tests that a failed round always returns zero ranking points, regardless of mode.
     *
     * @covers \mod_playerwords\local\gameplay_service::calculate_ranking_points
     * @return void
     */
    public function test_calculate_ranking_points_not_completed(): void {
        $instance = (object)['grade' => 100, 'max_attempts' => 6, 'rankingscoringmode' => PLAYERWORDS_SCORING_LINEAR];
        $points = gameplay_service::calculate_ranking_points($instance, 6, false);
        $this->assertSame(0.0, $points);
    }
}
