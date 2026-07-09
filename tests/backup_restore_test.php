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
     * Inserts a block_instances record for block_playerhud in the given course context.
     *
     * @param \stdClass $course Course object.
     * @return int Block instance ID.
     */
    private function make_block_instance(\stdClass $course): int {
        global $DB;
        $ctx = \context_course::instance($course->id);
        return $DB->insert_record('block_instances', (object)[
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

    /**
     * Inserts a block_playerhud_items record for the given block instance.
     *
     * @param int $blockinstanceid Block instance ID.
     * @return int Item ID.
     */
    private function make_item(int $blockinstanceid): int {
        global $DB;
        return $DB->insert_record('block_playerhud_items', (object)[
            'blockinstanceid' => $blockinstanceid,
            'name'            => 'Troféu',
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
     * Backs up the given course and restores it into a brand new course, returning that
     * course. Mirrors block_playerhud's own full-course backup/restore test pattern.
     *
     * @param \stdClass $course Source course.
     * @return \stdClass The new course the backup was restored into.
     */
    private function backup_and_restore_into_new_course(\stdClass $course): \stdClass {
        global $CFG;
        require_once($CFG->dirroot . '/backup/util/includes/backup_includes.php');
        require_once($CFG->dirroot . '/backup/util/includes/restore_includes.php');

        $admin = get_admin();

        $bc = new \backup_controller(
            \backup::TYPE_1COURSE,
            $course->id,
            \backup::FORMAT_MOODLE,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $admin->id
        );
        $bc->execute_plan();
        $backupfile = $bc->get_results()['backup_destination'];
        $bc->destroy();

        $newcourse = $this->getDataGenerator()->create_course();
        $tempdir = \restore_controller::get_tempdir_name($newcourse->id, $admin->id);
        $fp = get_file_packer('application/vnd.moodle.backup');
        $backupfile->extract_to_pathname($fp, make_backup_temp_directory($tempdir));

        $rc = new \restore_controller(
            $tempdir,
            $newcourse->id,
            \backup::INTERACTIVE_NO,
            \backup::MODE_GENERAL,
            $admin->id,
            \backup::TARGET_EXISTING_ADDING
        );
        $rc->execute_precheck();
        $rc->execute_plan();
        $rc->destroy();

        return $newcourse;
    }

    /**
     * Duplicating an activity within the same course never touches the block, so its
     * hud_win_grant_item — still a genuinely valid item in that same course — must survive
     * unchanged, even though no playerhud_item restore mapping was ever registered.
     *
     * @covers \restore_playerwords_activity_structure_step::resolve_hud_item
     * @return void
     */
    public function test_duplicate_activity_preserves_hud_item_from_same_course(): void {
        global $CFG, $DB;
        $this->skip_if_no_playerhud();
        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->dirroot . '/mod/playerwords/lib.php');
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $biid = $this->make_block_instance($course);
        $itemid = $this->make_item($biid);
        $instance = $this->getDataGenerator()->create_module('playerwords', [
            'course' => $course->id, 'hud_win_grant_item' => $itemid,
        ]);

        $cm = get_coursemodule_from_instance('playerwords', $instance->id, $course->id, false, MUST_EXIST);
        $newcm = duplicate_module($course, $cm);

        $newinstance = $DB->get_record('playerwords', ['id' => $newcm->instance], '*', MUST_EXIST);
        $this->assertSame($itemid, (int)$newinstance->hud_win_grant_item);
    }

    /**
     * A full course backup/restore into a new course carries the PlayerHUD block along, so
     * the restored activity's hud_win_grant_item must point at the item's NEW id, via the
     * playerhud_item mapping block_playerhud's own restore step registers.
     *
     * @covers \restore_playerwords_activity_structure_step::resolve_hud_item
     * @return void
     */
    public function test_backup_restore_full_course_remaps_hud_item(): void {
        global $CFG, $DB;
        $this->skip_if_no_playerhud();
        require_once($CFG->dirroot . '/mod/playerwords/lib.php');
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $biid = $this->make_block_instance($course);
        $itemid = $this->make_item($biid);
        $this->getDataGenerator()->create_module('playerwords', [
            'course' => $course->id, 'hud_win_grant_item' => $itemid,
        ]);

        $newcourse = $this->backup_and_restore_into_new_course($course);

        $newinstance = $DB->get_record('playerwords', ['course' => $newcourse->id], '*', MUST_EXIST);
        $newblock = $DB->get_record('block_instances', [
            'blockname' => 'playerhud',
            'parentcontextid' => \context_course::instance($newcourse->id)->id,
        ], '*', MUST_EXIST);
        $newitemid = $DB->get_field('block_playerhud_items', 'id', [
            'blockinstanceid' => $newblock->id, 'name' => 'Troféu',
        ], MUST_EXIST);

        $this->assertNotSame($itemid, (int)$newitemid);
        $this->assertSame((int)$newitemid, (int)$newinstance->hud_win_grant_item);
    }

    /**
     * An activity whose hud_win_grant_item points at another course's PlayerHUD item — a
     * stale or misconfigured reference that predates this activity's own backup — must have
     * that field dropped on restore, never silently kept pointing at a foreign course's item.
     * The foreign course's own PlayerHUD block is deliberately not part of this backup, so no
     * playerhud_item mapping exists for it either.
     *
     * @covers \restore_playerwords_activity_structure_step::resolve_hud_item
     * @return void
     */
    public function test_backup_restore_drops_hud_item_from_foreign_course(): void {
        global $CFG, $DB;
        $this->skip_if_no_playerhud();
        require_once($CFG->dirroot . '/mod/playerwords/lib.php');
        $this->setAdminUser();

        $foreigncourse = $this->getDataGenerator()->create_course();
        $foreignbiid = $this->make_block_instance($foreigncourse);
        $foreignitemid = $this->make_item($foreignbiid);

        $course = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->create_module('playerwords', [
            'course' => $course->id, 'hud_win_grant_item' => $foreignitemid,
        ]);

        $newcourse = $this->backup_and_restore_into_new_course($course);

        $newinstance = $DB->get_record('playerwords', ['course' => $newcourse->id], '*', MUST_EXIST);
        $this->assertSame(0, (int)$newinstance->hud_win_grant_item);
    }
}
