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
 * Unit tests for the custom completion rules.
 *
 * @package    mod_playerwords
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playerwords\completion;

use advanced_testcase;

/**
 * Tests for \mod_playerwords\completion\custom_completion.
 *
 * @covers \mod_playerwords\completion\custom_completion
 */
final class custom_completion_test extends advanced_testcase {
    /**
     * Creates a course and a playerwords activity requiring the given number of attempts.
     *
     * @param int $required Number of attempts required for completion.
     * @return array{0: \stdClass, 1: \stdClass} [course, cm]
     */
    private function create_fixture(int $required): array {
        global $CFG;
        $CFG->enablecompletion = true;

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $cm = $this->getDataGenerator()->create_module('playerwords', [
            'course'                      => $course->id,
            'completion'                  => COMPLETION_TRACKING_AUTOMATIC,
            'completionattemptsenabled'   => $required > 0,
            'completionattempts'          => $required,
        ]);

        return [$course, $cm];
    }

    /**
     * The rule is incomplete while the student has fewer attempts than required.
     */
    public function test_get_state_incomplete_below_threshold(): void {
        global $DB;
        $this->resetAfterTest();

        [$course, $cm] = $this->create_fixture(2);
        $user = $this->getDataGenerator()->create_user();

        $DB->insert_record('playerwords_attempts', (object)[
            'playerwordsid' => $cm->id,
            'userid'        => $user->id,
            'wordid'        => 1,
            'attempts_used' => 1,
            'time_used'     => 5,
            'completed'     => 1,
            'score'         => 100,
            'timecreated'   => time(),
        ]);

        $cminfo = get_fast_modinfo($course)->get_cm($cm->cmid);
        $completion = new custom_completion($cminfo, (int)$user->id);

        $this->assertEquals(COMPLETION_INCOMPLETE, $completion->get_state('completionattempts'));
    }

    /**
     * The rule is complete once the student reaches the required attempt count.
     */
    public function test_get_state_complete_at_threshold(): void {
        global $DB;
        $this->resetAfterTest();

        [$course, $cm] = $this->create_fixture(2);
        $user = $this->getDataGenerator()->create_user();

        for ($i = 0; $i < 2; $i++) {
            $DB->insert_record('playerwords_attempts', (object)[
                'playerwordsid' => $cm->id,
                'userid'        => $user->id,
                'wordid'        => 1,
                'attempts_used' => 1,
                'time_used'     => 5,
                'completed'     => 1,
                'score'         => 100,
                'timecreated'   => time(),
            ]);
        }

        $cminfo = get_fast_modinfo($course)->get_cm($cm->cmid);
        $completion = new custom_completion($cminfo, (int)$user->id);

        $this->assertEquals(COMPLETION_COMPLETE, $completion->get_state('completionattempts'));
    }

    /**
     * The rule is only reported as available when the teacher has actually enabled it.
     */
    public function test_rule_not_available_when_disabled(): void {
        $this->resetAfterTest();

        [$course, $cm] = $this->create_fixture(0);

        $cminfo = get_fast_modinfo($course)->get_cm($cm->cmid);
        $completion = new custom_completion($cminfo, 0);

        $this->assertSame([], $completion->get_available_custom_rules());
    }

    /**
     * The module declares exactly one custom completion rule: completionattempts.
     *
     * @covers \mod_playerwords\completion\custom_completion::get_defined_custom_rules
     */
    public function test_get_defined_custom_rules_returns_completionattempts(): void {
        $this->assertSame(['completionattempts'], custom_completion::get_defined_custom_rules());
    }

    /**
     * The custom rule's human-readable description includes the configured attempt count.
     */
    public function test_get_custom_rule_descriptions_includes_required_count(): void {
        $this->resetAfterTest();

        [$course, $cm] = $this->create_fixture(3);
        $cminfo = get_fast_modinfo($course)->get_cm($cm->cmid);
        $completion = new custom_completion($cminfo, 0);

        $descriptions = $completion->get_custom_rule_descriptions();

        $this->assertArrayHasKey('completionattempts', $descriptions);
        $this->assertStringContainsString('3', $descriptions['completionattempts']);
    }

    /**
     * The display order places the custom rule after the two core rules.
     */
    public function test_get_sort_order_places_custom_rule_last(): void {
        $this->resetAfterTest();

        [$course, $cm] = $this->create_fixture(2);
        $cminfo = get_fast_modinfo($course)->get_cm($cm->cmid);
        $completion = new custom_completion($cminfo, 0);

        $this->assertSame(
            ['completionview', 'completionusegrade', 'completionattempts'],
            $completion->get_sort_order()
        );
    }
}
