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
 * External function tests for count_glossary_candidates.
 *
 * @package    mod_playerwords
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playerwords\external;

use core_external\external_api;

/**
 * Tests for the mod_playerwords_count_glossary_candidates web service.
 */
final class count_glossary_candidates_test extends \advanced_testcase {
    /** @var \stdClass Course used by the tests. */
    private \stdClass $course;

    /** @var \stdClass Teacher with mod/playerwords:addinstance. */
    private \stdClass $teacher;

    /** @var \stdClass Student without mod/playerwords:addinstance. */
    private \stdClass $student;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->course = $this->getDataGenerator()->create_course();
        $this->teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($this->teacher->id, $this->course->id, 'editingteacher');
        $this->student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($this->student->id, $this->course->id, 'student');
    }

    /**
     * Creates a glossary in the test course with one approved entry.
     *
     * @param string $concept Entry concept text.
     * @return \stdClass Glossary module record.
     */
    private function make_glossary(string $concept): \stdClass {
        $glossary = $this->getDataGenerator()->create_module('glossary', ['course' => $this->course->id]);
        $this->getDataGenerator()->get_plugin_generator('mod_glossary')->create_content($glossary, [
            'concept'    => $concept,
            'definition' => 'definicao',
            'approved'   => 1,
        ]);
        return $glossary;
    }

    /**
     * Calls the mod_playerwords_count_glossary_candidates web service through the
     * real dispatch path.
     *
     * @param int $glossaryid Glossary id, or 0 for every course glossary.
     * @param int $minlength Candidate minimum word length.
     * @param int $maxlength Candidate maximum word length.
     * @return array Response shaped as ['error' => bool, 'data' => array|null, ...].
     */
    private function call_count(int $glossaryid, int $minlength, int $maxlength): array {
        $_POST['sesskey'] = sesskey();
        return external_api::call_external_function('mod_playerwords_count_glossary_candidates', [
            'courseid'   => $this->course->id,
            'glossaryid' => $glossaryid,
            'minlength'  => $minlength,
            'maxlength'  => $maxlength,
        ]);
    }

    /**
     * Counts candidate words for a specific glossary within the requested range.
     *
     * @covers \mod_playerwords\external\count_glossary_candidates::execute
     * @return void
     */
    public function test_counts_candidates_for_a_specific_glossary(): void {
        set_config('glossarystopwords', '', 'mod_playerwords');
        $glossary = $this->make_glossary('planeta');

        $this->setUser($this->teacher);
        $response = $this->call_count($glossary->id, 4, 8);

        $this->assertFalse($response['error']);
        $this->assertSame(1, $response['data']['count']);
    }

    /**
     * A word outside the requested length range is excluded from the count.
     *
     * @covers \mod_playerwords\external\count_glossary_candidates::execute
     * @return void
     */
    public function test_excludes_words_outside_range(): void {
        $glossary = $this->make_glossary('planeta');

        $this->setUser($this->teacher);
        $response = $this->call_count($glossary->id, 20, 30);

        $this->assertSame(0, $response['data']['count']);
    }

    /**
     * A user without mod/playerwords:addinstance (e.g. a student) is rejected.
     *
     * @covers \mod_playerwords\external\count_glossary_candidates::execute
     * @return void
     */
    public function test_requires_addinstance_capability(): void {
        $glossary = $this->make_glossary('planeta');

        $this->setUser($this->student);

        $this->expectException(\required_capability_exception::class);
        \mod_playerwords\external\count_glossary_candidates::execute($this->course->id, $glossary->id, 1, 30);
    }
}
