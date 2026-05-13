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

    if ($oldversion < 2026050803) {
        $table = new xmldb_table('playerwords');

        $maxrounds = new xmldb_field(
            'max_rounds',
            XMLDB_TYPE_INTEGER,
            '10',
            null,
            XMLDB_NOTNULL,
            null,
            '0',
            'show_ranking'
        );
        if (!$dbman->field_exists($table, $maxrounds)) {
            $dbman->add_field($table, $maxrounds);
        }

        $cooldownseconds = new xmldb_field(
            'cooldown_seconds',
            XMLDB_TYPE_INTEGER,
            '10',
            null,
            XMLDB_NOTNULL,
            null,
            '86400',
            'max_rounds'
        );
        if (!$dbman->field_exists($table, $cooldownseconds)) {
            $dbman->add_field($table, $cooldownseconds);
        }

        upgrade_mod_savepoint(true, 2026050803, 'playerwords');
    }

    if ($oldversion < 2026050804) {
        $table = new xmldb_table('playerwords');

        $grademethod = new xmldb_field(
            'grademethod',
            XMLDB_TYPE_INTEGER,
            '10',
            null,
            XMLDB_NOTNULL,
            null,
            '1',
            'gradepass'
        );
        if (!$dbman->field_exists($table, $grademethod)) {
            $dbman->add_field($table, $grademethod);
        }

        upgrade_mod_savepoint(true, 2026050804, 'playerwords');
    }

    if ($oldversion < 2026050805) {
        $table = new xmldb_table('playerwords');

        $wordmode = new xmldb_field(
            'wordmode',
            XMLDB_TYPE_INTEGER,
            '10',
            null,
            XMLDB_NOTNULL,
            null,
            '1',
            'show_ranking'
        );
        if (!$dbman->field_exists($table, $wordmode)) {
            $dbman->add_field($table, $wordmode);
        }

        upgrade_mod_savepoint(true, 2026050805, 'playerwords');
    }

    if ($oldversion < 2026050811) {
        $table = new xmldb_table('playerwords');

        $glossaryid = new xmldb_field(
            'glossaryid',
            XMLDB_TYPE_INTEGER,
            '10',
            null,
            XMLDB_NOTNULL,
            null,
            '0',
            'aigranularity'
        );
        if (!$dbman->field_exists($table, $glossaryid)) {
            $dbman->add_field($table, $glossaryid);
        }

        upgrade_mod_savepoint(true, 2026050811, 'playerwords');
    }

    if ($oldversion < 2026050900) {
        $table = new xmldb_table('playerwords_words');

        $concept = new xmldb_field(
            'concept',
            XMLDB_TYPE_CHAR,
            '255',
            null,
            null,
            null,
            null,
            'word'
        );
        if (!$dbman->field_exists($table, $concept)) {
            $dbman->add_field($table, $concept);
        }

        upgrade_mod_savepoint(true, 2026050900, 'playerwords');
    }

    if ($oldversion < 2026051000) {
        $table = new xmldb_table('playerwords_words');

        $glossaryid = new xmldb_field(
            'glossaryid',
            XMLDB_TYPE_INTEGER,
            '10',
            null,
            XMLDB_NOTNULL,
            null,
            '0',
            'source'
        );
        if (!$dbman->field_exists($table, $glossaryid)) {
            $dbman->add_field($table, $glossaryid);
        }

        upgrade_mod_savepoint(true, 2026051000, 'playerwords');
    }

    if ($oldversion < 2026051002) {
        $table = new xmldb_table('playerwords');
        $field = new xmldb_field('aigranularity');
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }
        upgrade_mod_savepoint(true, 2026051002, 'playerwords');
    }

    return true;
}
