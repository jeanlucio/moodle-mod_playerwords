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
 * Upgrade steps for mod_playerwords.
 *
 * @package mod_playerwords
 * @copyright  2026 Jean Lúcio
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Runs the mod_playerwords upgrade steps.
 *
 * @param int $oldversion The version being upgraded from.
 * @return bool True on success.
 */
function xmldb_playerwords_upgrade(int $oldversion): bool {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026070701) {
        $table = new xmldb_table('playerwords_words');
        $field = new xmldb_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'timecreated');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2026070701, 'playerwords');
    }

    if ($oldversion < 2026070801) {
        $table = new xmldb_table('playerwords_attempts');
        $field = new xmldb_field('timefinished', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'timecreated');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Every pre-existing row was inserted only once the round had already finished
        // (the "reserve on start, finish on completion" split starts with this upgrade),
        // so timecreated already holds its finish time.
        $DB->execute('UPDATE {playerwords_attempts} SET timefinished = timecreated WHERE timefinished = 0');

        upgrade_mod_savepoint(true, 2026070801, 'playerwords');
    }

    return true;
}
