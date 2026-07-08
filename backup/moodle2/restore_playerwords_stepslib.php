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
 * Restore structure step for mod_playerwords.
 *
 * @package    mod_playerwords
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Processes the XML tree produced by backup and rebuilds the database records.
 */
class restore_playerwords_activity_structure_step extends restore_activity_structure_step {
    /**
     * Returns the path elements the restore engine should process.
     *
     * @return restore_path_element[]
     */
    protected function define_structure(): array {
        $paths = [];
        $userinfo = $this->get_setting_value('userinfo');

        $paths[] = new restore_path_element('playerwords', '/activity/playerwords');
        $paths[] = new restore_path_element(
            'playerwords_word',
            '/activity/playerwords/words/word'
        );

        if ($userinfo) {
            $paths[] = new restore_path_element(
                'playerwords_attempt',
                '/activity/playerwords/attempts/attempt'
            );
        }

        // Wrap with the generic '/activity' path so the base class's process_activity()
        // runs: it registers the old-to-new context mapping and the old activity id.
        // Without this, restore_calendarevents_structure_step::after_execute() (a generic
        // step that runs for every activity) fails with unknown_context_mapping, and
        // course_format\local\cmactions::duplicate() never reaches its post-restore
        // cleanup (renaming to "(copy)", moving to the target section, rebuilding the
        // course cache) since the exception aborts the restore plan first.
        return $this->prepare_activity_structure($paths);
    }

    /**
     * Restores the root playerwords instance record.
     *
     * @param array|object $data XML data for this element.
     * @return void
     */
    public function process_playerwords(array|object $data): void {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->course = $this->get_courseid();
        $data->timemodified = $this->apply_date_offset($data->timemodified);
        $data->timecreated = $this->apply_date_offset($data->timecreated);

        // Remap glossaryid if the glossary was included in the backup.
        if (!empty($data->glossaryid)) {
            $data->glossaryid = $this->get_mappingid('glossary', $data->glossaryid, 0);
        }

        $newitemid = $DB->insert_record('playerwords', $data);
        $this->apply_activity_instance($newitemid);
        $this->set_mapping('playerwords', $oldid, $newitemid);
    }

    /**
     * Restores a word record belonging to the activity.
     *
     * @param array|object $data XML data for this element.
     * @return void
     */
    public function process_playerwords_word(array|object $data): void {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->playerwordsid = $this->get_new_parentid('playerwords');
        $data->timecreated = $this->apply_date_offset($data->timecreated);

        // Remap addedby to the restored user; fall back to 0 (anonymous) when unmapped.
        $data->addedby = (int)$this->get_mappingid('user', $data->addedby, 0);

        // Remap the source glossary when it was included in the backup.
        if (!empty($data->glossaryid)) {
            $data->glossaryid = $this->get_mappingid('glossary', $data->glossaryid, 0);
        }

        $newitemid = $DB->insert_record('playerwords_words', $data);
        // Register mapping so attempts can resolve their wordid cross-reference.
        $this->set_mapping('playerwords_words', $oldid, $newitemid);
    }

    /**
     * Restores a student attempt record (only when userinfo is enabled).
     *
     * @param array|object $data XML data for this element.
     * @return void
     */
    public function process_playerwords_attempt(array|object $data): void {
        global $DB;

        $data = (object)$data;

        $data->playerwordsid = $this->get_new_parentid('playerwords');
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timefinished = $this->apply_date_offset($data->timefinished);

        $data->userid = (int)$this->get_mappingid('user', $data->userid);
        $data->wordid = (int)$this->get_mappingid('playerwords_words', $data->wordid);

        // Skip orphaned attempts (word or user not mapped).
        if (empty($data->userid) || empty($data->wordid)) {
            return;
        }

        $DB->insert_record('playerwords_attempts', $data);
    }

    /**
     * Restores files embedded in the activity's intro editor field.
     *
     * The grade item itself is not touched here: restore_activity_grades_structure_step
     * (added generically by restore_activity_task for every gradable module) already
     * restores it. Calling playerwords_grade_item_update() again here raced against that
     * generic step and left two grade_items for the same instance.
     *
     * @return void
     */
    protected function after_execute(): void {
        $this->add_related_files('mod_playerwords', 'intro', null);
    }
}
