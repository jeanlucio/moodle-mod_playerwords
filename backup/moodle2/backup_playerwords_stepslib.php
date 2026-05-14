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
 * Backup structure step for mod_playerwords.
 *
 * @package    mod_playerwords
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Defines the XML tree structure for a PlayerWords backup.
 */
class backup_playerwords_activity_structure_step extends backup_activity_structure_step {
    /**
     * Returns the root backup element with all nested children.
     *
     * @return backup_nested_element
     */
    protected function define_structure(): backup_nested_element {
        $userinfo = $this->get_setting_value('userinfo');

        // Root element — mirrors all columns in {playerwords}.
        $playerwords = new backup_nested_element('playerwords', ['id'], [
            'name',
            'intro',
            'introformat',
            'sources',
            'glossaryid',
            'min_length',
            'max_length',
            'max_attempts',
            'timer_seconds',
            'show_ranking',
            'wordmode',
            'max_rounds',
            'cooldown_seconds',
            'completionattempts',
            'grade',
            'gradepass',
            'grademethod',
            'hud_round_cost_item',
            'hud_round_cost_qty',
            'hud_hint_cost_item',
            'hud_hint_cost_qty',
            'timecreated',
            'timemodified',
        ]);

        // Words belong to the activity and are always backed up.
        $words = new backup_nested_element('words');
        $word = new backup_nested_element('word', ['id'], [
            'word',
            'concept',
            'hint',
            'source',
            'glossaryid',
            'approved',
            'timecreated',
            'addedby',
        ]);

        // Attempts are user data — only backed up when userinfo is enabled.
        $attempts = new backup_nested_element('attempts');
        $attempt = new backup_nested_element('attempt', ['id'], [
            'userid',
            'wordid',
            'attempts_used',
            'time_used',
            'completed',
            'score',
            'timecreated',
        ]);

        // Build the tree.
        $playerwords->add_child($words);
        $words->add_child($word);

        if ($userinfo) {
            $playerwords->add_child($attempts);
            $attempts->add_child($attempt);
        }

        // Connect elements to database tables.
        $playerwords->set_source_table('playerwords', ['id' => backup::VAR_ACTIVITYID]);
        $word->set_source_table('playerwords_words', ['playerwordsid' => backup::VAR_PARENTID]);

        if ($userinfo) {
            $attempt->set_source_table(
                'playerwords_attempts',
                ['playerwordsid' => backup::VAR_ACTIVITYID]
            );
        }

        // Annotate IDs that reference other tables so they are remapped on restore.
        $playerwords->annotate_ids('glossary', 'glossaryid');

        $word->annotate_ids('user', 'addedby');
        $word->annotate_ids('glossary', 'glossaryid');

        if ($userinfo) {
            $attempt->annotate_ids('user', 'userid');
            // Wordid is an intra-plugin reference; resolved via the words mapping table.
            $attempt->annotate_ids('playerwords_words', 'wordid');
        }

        // Wrap the root in the standard activity envelope.
        return $this->prepare_activity_structure($playerwords);
    }
}
