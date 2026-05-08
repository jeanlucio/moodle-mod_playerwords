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
 * @copyright 2026 Jean Lúcio
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Execute mod_playerwords upgrade steps.
 *
 * @param int $oldversion Previous plugin version.
 * @return bool
 */
function xmldb_playerwords_upgrade(int $oldversion): bool {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026050800) {
        $table = new xmldb_table('playerwords');

        $completiontype = new xmldb_field('completiontype');
        if ($dbman->field_exists($table, $completiontype)) {
            $dbman->drop_field($table, $completiontype);
        }

        upgrade_mod_savepoint(true, 2026050800, 'playerwords');
    }

    if ($oldversion < 2026050801) {
        $table = new xmldb_table('playerwords');

        $completionattempts = new xmldb_field(
            'completionattempts',
            XMLDB_TYPE_INTEGER,
            '10',
            null,
            XMLDB_NOTNULL,
            null,
            '0',
            'show_ranking'
        );
        if (!$dbman->field_exists($table, $completionattempts)) {
            $dbman->add_field($table, $completionattempts);
        }

        upgrade_mod_savepoint(true, 2026050801, 'playerwords');
    }

    return true;
}
