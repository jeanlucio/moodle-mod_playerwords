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
 * Unit tests for hud_service.
 *
 * Tests for get_block_instance_id always run.
 * Tests for item/inventory operations are skipped when block_playerhud is not installed.
 *
 * @package    mod_playerwords
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playerwords\local;

/**
 * Tests for hud_service.
 */
final class hud_service_test extends \advanced_testcase {
    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
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
            'blockname'        => 'playerhud',
            'parentcontextid'  => $ctx->id,
            'showinsubcontexts' => 0,
            'pagetypepattern'  => 'course-view-*',
            'subpagepattern'   => null,
            'defaultregion'    => 'side-pre',
            'defaultweight'    => 0,
            'configdata'       => base64_encode(serialize(new \stdClass())),
            'timecreated'      => time(),
            'timemodified'     => time(),
        ]);
    }

    /**
     * Inserts a block_playerhud_items record for the given block instance.
     *
     * @param int $blockinstanceid Block instance ID.
     * @param string $name         Item display name.
     * @return int Item ID.
     */
    private function make_item(int $blockinstanceid, string $name = 'Gold Key'): int {
        global $DB;
        return $DB->insert_record('block_playerhud_items', (object)[
            'blockinstanceid' => $blockinstanceid,
            'name'            => $name,
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
     * Inserts one available inventory entry for the given user and item.
     *
     * @param int $userid  User ID.
     * @param int $itemid  Item ID.
     * @param int $offset  Seconds to subtract from time() to simulate ordering.
     * @return int Inventory record ID.
     */
    private function make_inventory(int $userid, int $itemid, int $offset = 0): int {
        global $DB;
        return $DB->insert_record('block_playerhud_inventory', (object)[
            'userid'      => $userid,
            'itemid'      => $itemid,
            'dropid'      => 0,
            'source'      => 'manual',
            'timecreated' => time() - $offset,
        ]);
    }

    // Tests for get_block_instance_id (no PlayerHUD tables required).

    /**
     * Tests that null is returned when no playerhud block exists in the course.
     *
     * @covers \mod_playerwords\local\hud_service::get_block_instance_id
     * @return void
     */
    public function test_get_block_instance_id_returns_null_when_absent(): void {
        $course = $this->getDataGenerator()->create_course();
        $this->assertNull(hud_service::get_block_instance_id($course->id));
    }

    /**
     * Tests that the correct block instance ID is returned when one exists.
     *
     * @covers \mod_playerwords\local\hud_service::get_block_instance_id
     * @return void
     */
    public function test_get_block_instance_id_finds_block(): void {
        $course = $this->getDataGenerator()->create_course();
        $biid   = $this->make_block_instance($course);
        $this->assertSame($biid, hud_service::get_block_instance_id($course->id));
    }

    /**
     * Tests that a block in a different course is not returned.
     *
     * @covers \mod_playerwords\local\hud_service::get_block_instance_id
     * @return void
     */
    public function test_get_block_instance_id_ignores_other_course(): void {
        $course1 = $this->getDataGenerator()->create_course();
        $course2 = $this->getDataGenerator()->create_course();
        $this->make_block_instance($course1);
        $this->assertNull(hud_service::get_block_instance_id($course2->id));
    }

    // Tests that require block_playerhud tables.

    /**
     * Tests that is_available_for_course is true once a block instance exists.
     *
     * @covers \mod_playerwords\local\hud_service::is_available_for_course
     * @return void
     */
    public function test_is_available_for_course_true_with_block_instance(): void {
        $this->skip_if_no_playerhud();
        $course = $this->getDataGenerator()->create_course();
        $this->make_block_instance($course);
        $this->assertTrue(hud_service::is_available_for_course($course->id));
    }

    /**
     * Tests that is_available_for_course is false when the course has no block instance,
     * even though the block plugin itself is installed.
     *
     * @covers \mod_playerwords\local\hud_service::is_available_for_course
     * @return void
     */
    public function test_is_available_for_course_false_without_block_instance(): void {
        $this->skip_if_no_playerhud();
        $course = $this->getDataGenerator()->create_course();
        $this->assertFalse(hud_service::is_available_for_course($course->id));
    }

    /**
     * Tests that is_available_for_course ignores a block instance living in another course.
     *
     * @covers \mod_playerwords\local\hud_service::is_available_for_course
     * @return void
     */
    public function test_is_available_for_course_ignores_other_course(): void {
        $this->skip_if_no_playerhud();
        $course1 = $this->getDataGenerator()->create_course();
        $course2 = $this->getDataGenerator()->create_course();
        $this->make_block_instance($course1);
        $this->assertFalse(hud_service::is_available_for_course($course2->id));
    }

    /**
     * Tests that get_item_name returns the item name formatted for display.
     *
     * @covers \mod_playerwords\local\hud_service::get_item_name
     * @return void
     */
    public function test_get_item_name(): void {
        $this->skip_if_no_playerhud();
        $course = $this->getDataGenerator()->create_course();
        $biid   = $this->make_block_instance($course);
        $itemid = $this->make_item($biid, 'Gold Key');
        $this->assertSame('Gold Key', hud_service::get_item_name($itemid));
    }

    /**
     * Tests that get_item_name returns an empty string for item id zero.
     *
     * @covers \mod_playerwords\local\hud_service::get_item_name
     * @return void
     */
    public function test_get_item_name_zero_returns_empty(): void {
        $this->skip_if_no_playerhud();
        $this->assertSame('', hud_service::get_item_name(0));
    }

    /**
     * Tests that get_items_for_block returns only enabled items sorted by name.
     *
     * @covers \mod_playerwords\local\hud_service::get_items_for_block
     * @return void
     */
    public function test_get_items_for_block(): void {
        global $DB;
        $this->skip_if_no_playerhud();
        $course = $this->getDataGenerator()->create_course();
        $biid   = $this->make_block_instance($course);
        $this->make_item($biid, 'Zinc Key');
        $this->make_item($biid, 'Alpha Key');
        // Disabled item — must not appear.
        $DB->insert_record('block_playerhud_items', (object)[
            'blockinstanceid' => $biid,
            'name'            => 'Hidden',
            'xp'              => 0,
            'image'           => '',
            'description'     => '',
            'enabled'         => 0,
            'secret'          => 0,
            'timecreated'     => time(),
            'timemodified'    => time(),
        ]);

        $items = hud_service::get_items_for_block($biid);
        $this->assertCount(2, $items);
        $this->assertSame('Alpha Key', $items[0]->name);
        $this->assertSame('Zinc Key', $items[1]->name);
    }

    /**
     * Tests that consume_items returns false when the user has fewer items than requested.
     *
     * @covers \mod_playerwords\local\hud_service::consume_items
     * @return void
     */
    public function test_consume_items_insufficient(): void {
        $this->skip_if_no_playerhud();
        $user   = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $biid   = $this->make_block_instance($course);
        $itemid = $this->make_item($biid);
        $this->make_inventory($user->id, $itemid);

        $result = hud_service::consume_items($user->id, $itemid, 2);
        $this->assertFalse($result);
    }

    /**
     * Tests that consume_items marks the exact quantity as consumed and returns true.
     *
     * @covers \mod_playerwords\local\hud_service::consume_items
     * @return void
     */
    public function test_consume_items_success(): void {
        global $DB;
        $this->skip_if_no_playerhud();
        $user   = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $biid   = $this->make_block_instance($course);
        $itemid = $this->make_item($biid);
        $this->make_inventory($user->id, $itemid);
        $this->make_inventory($user->id, $itemid);

        $result = hud_service::consume_items($user->id, $itemid, 2);
        $this->assertTrue($result);

        $remaining = $DB->count_records_select(
            'block_playerhud_inventory',
            "userid = :uid AND itemid = :iid AND source NOT IN ('revoked','consumed')",
            ['uid' => $user->id, 'iid' => $itemid]
        );
        $this->assertSame(0, $remaining);
    }

    /**
     * Tests that consume_items follows FIFO order (oldest entries consumed first).
     *
     * @covers \mod_playerwords\local\hud_service::consume_items
     * @return void
     */
    public function test_consume_items_fifo(): void {
        global $DB;
        $this->skip_if_no_playerhud();
        $user   = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $biid   = $this->make_block_instance($course);
        $itemid = $this->make_item($biid);
        $oldest = $this->make_inventory($user->id, $itemid, 100);
        $middle = $this->make_inventory($user->id, $itemid, 50);
        $newest = $this->make_inventory($user->id, $itemid, 0);

        hud_service::consume_items($user->id, $itemid, 2);

        $this->assertSame('consumed', $DB->get_field('block_playerhud_inventory', 'source', ['id' => $oldest]));
        $this->assertSame('consumed', $DB->get_field('block_playerhud_inventory', 'source', ['id' => $middle]));
        $this->assertNotSame('consumed', $DB->get_field('block_playerhud_inventory', 'source', ['id' => $newest]));
    }

    /**
     * Tests that consume_items with qty zero always returns true without touching the inventory.
     *
     * @covers \mod_playerwords\local\hud_service::consume_items
     * @return void
     */
    public function test_consume_items_zero_qty_short_circuits(): void {
        $this->skip_if_no_playerhud();
        $user   = $this->getDataGenerator()->create_user();
        $this->assertTrue(hud_service::consume_items($user->id, 999, 0));
    }
}
