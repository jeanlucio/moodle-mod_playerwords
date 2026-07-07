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
 * Backup and restore tests for mod_playerwords.
 *
 * @package    mod_playerwords
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playerwords;

/**
 * Tests that duplicating a playerwords activity completes without error.
 */
final class backup_restore_test extends \advanced_testcase {
    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Tests that duplicating an activity copies its words, renames the copy, and is
     * immediately visible — a regression test for a missing prepare_activity_structure()
     * call in the restore step, which left the restore's old-to-new context mapping
     * unset. That mapping is what the generic post-restore duplicate flow (renaming to
     * "(copy)", moving the module, rebuilding the course cache) and the generic
     * calendar-events restore step both depend on; without it, duplicating threw
     * unknown_context_mapping and left the copy invisible until caches were purged.
     *
     * @covers \restore_playerwords_activity_structure_step::define_structure
     * @return void
     */
    public function test_duplicate_activity(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->dirroot . '/mod/playerwords/lib.php');

        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $instance = $this->getDataGenerator()->create_module('playerwords', ['course' => $course->id]);
        $DB->insert_record('playerwords_words', (object)[
            'playerwordsid' => $instance->id,
            'word'          => 'teste',
            'concept'       => 'teste',
            'hint'          => 'dica',
            'source'        => 'manual',
            'glossaryid'    => 0,
            'approved'      => 1,
            'timecreated'   => time(),
            'addedby'       => $user->id,
        ]);

        $cm = get_coursemodule_from_instance('playerwords', $instance->id, $course->id, false, MUST_EXIST);

        $newcm = duplicate_module($course, $cm);

        $this->assertNotNull($newcm);
        $this->assertNotSame($cm->id, $newcm->id);
        $this->assertStringContainsString('(copy)', $newcm->name);

        $newinstance = $DB->get_record('playerwords', ['id' => $newcm->instance], '*', MUST_EXIST);
        $this->assertSame(1, $DB->count_records('playerwords_words', ['playerwordsid' => $newinstance->id]));

        // No explicit cache purge here: this proves the context mapping (and therefore
        // the whole post-restore cleanup) actually ran, since a stale course cache is
        // exactly the symptom the missing mapping used to cause.
        $modinfo = get_fast_modinfo($course->id);
        $this->assertNotNull($modinfo->get_cm($newcm->id));

        // Regression guard: the restore step used to also call playerwords_grade_item_update()
        // in after_execute(), racing against the generic grades-restore step and leaving two
        // grade_items for the same instance.
        $this->assertSame(1, $DB->count_records('grade_items', [
            'courseid'    => $course->id,
            'itemtype'    => 'mod',
            'itemmodule'  => 'playerwords',
            'iteminstance' => $newinstance->id,
        ]));
    }
}
