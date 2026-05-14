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
 * PlayerHUD integration helpers for mod_playerwords.
 *
 * @package    mod_playerwords
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playerwords\local;

/**
 * Provides item-cost integration between mod_playerwords and block_playerhud.
 *
 * All methods gracefully return neutral values when PlayerHUD is not installed.
 */
class hud_service {
    /**
     * Returns the PlayerHUD block instance ID for the given course, or null if none.
     *
     * @param int $courseid Course ID.
     * @return int|null
     */
    public static function get_block_instance_id(int $courseid): ?int {
        global $DB;

        $sql = "SELECT bi.id
                  FROM {block_instances} bi
                  JOIN {context} ctx ON bi.parentcontextid = ctx.id
                 WHERE bi.blockname = 'playerhud'
                   AND ctx.contextlevel = :clevel
                   AND ctx.instanceid  = :courseid";

        $id = $DB->get_field_sql($sql, [
            'clevel'   => CONTEXT_COURSE,
            'courseid' => $courseid,
        ]);

        return ($id !== false) ? (int)$id : null;
    }

    /**
     * Returns enabled items for a block instance, sorted by name.
     *
     * @param int $blockinstanceid Block instance ID.
     * @return \stdClass[] Array of objects with id and name fields.
     */
    public static function get_items_for_block(int $blockinstanceid): array {
        global $DB;
        return array_values($DB->get_records(
            'block_playerhud_items',
            ['blockinstanceid' => $blockinstanceid, 'enabled' => 1],
            'name ASC',
            'id, name'
        ));
    }

    /**
     * Returns the formatted display name of an item, or empty string if not found.
     *
     * @param int $itemid Item ID.
     * @return string
     */
    public static function get_item_name(int $itemid): string {
        global $DB;

        if ($itemid <= 0) {
            return '';
        }
        $name = $DB->get_field('block_playerhud_items', 'name', ['id' => $itemid]);
        return ($name !== false) ? format_string($name) : '';
    }

    /**
     * Atomically consumes $qty items of $itemid from $userid's inventory.
     *
     * Uses FIFO order (oldest first). Returns false when the user does not have
     * enough available items; in that case no items are consumed.
     *
     * @param int $userid User ID.
     * @param int $itemid Item ID from block_playerhud_items.
     * @param int $qty    Number of items to consume.
     * @return bool True on success, false if insufficient.
     */
    public static function consume_items(int $userid, int $itemid, int $qty): bool {
        global $DB;

        if ($qty <= 0) {
            return true;
        }

        $lockfactory = \core\lock\lock_config::get_lock_factory('mod_playerwords');
        $lockkey = 'hud_uid' . $userid . '_iid' . $itemid;
        $lock = $lockfactory->get_lock($lockkey, 5);

        if (!$lock) {
            return false;
        }

        try {
            $sql = "SELECT id
                      FROM {block_playerhud_inventory}
                     WHERE userid = :uid AND itemid = :iid
                           AND source NOT IN ('revoked', 'consumed')
                  ORDER BY timecreated ASC";

            $records = $DB->get_records_sql($sql, ['uid' => $userid, 'iid' => $itemid], 0, $qty);

            if (count($records) < $qty) {
                return false;
            }

            $ids = array_keys($records);
            [$insql, $inparams] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'ci');
            $DB->set_field_select('block_playerhud_inventory', 'source', 'consumed', "id $insql", $inparams);

            return true;
        } finally {
            $lock->release();
        }
    }
}
