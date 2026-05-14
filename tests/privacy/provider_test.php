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
 * Privacy provider tests for mod_playerwords.
 *
 * @package    mod_playerwords
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playerwords\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Tests for the Privacy API provider.
 */
final class provider_test extends \advanced_testcase {
    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        writer::reset_service();
    }

    /**
     * Creates a playerwords course module and returns the cm record.
     *
     * @param \stdClass $course Course object.
     * @return \stdClass Course module record.
     */
    private function make_cm(\stdClass $course): \stdClass {
        return $this->getDataGenerator()->create_module('playerwords', ['course' => $course->id]);
    }

    /**
     * Inserts one attempt record for the given user and activity.
     *
     * @param int $userid        User ID.
     * @param int $playerwordsid Activity instance ID.
     * @param int $wordid        Word ID.
     * @return void
     */
    private function make_attempt(int $userid, int $playerwordsid, int $wordid): void {
        global $DB;
        $DB->insert_record('playerwords_attempts', (object)[
            'playerwordsid' => $playerwordsid,
            'userid'        => $userid,
            'wordid'        => $wordid,
            'attempts_used' => 3,
            'time_used'     => 45,
            'completed'     => 1,
            'score'         => 100.0,
            'timecreated'   => time(),
        ]);
    }

    /**
     * Inserts one word record attributed to the given user.
     *
     * @param int $userid        User ID.
     * @param int $playerwordsid Activity instance ID.
     * @return int Inserted word ID.
     */
    private function make_word(int $userid, int $playerwordsid): int {
        global $DB;
        return $DB->insert_record('playerwords_words', (object)[
            'playerwordsid' => $playerwordsid,
            'word'          => 'gato',
            'concept'       => 'gato',
            'hint'          => '',
            'source'        => 'manual',
            'glossaryid'    => 0,
            'approved'      => 1,
            'timecreated'   => time(),
            'addedby'       => $userid,
        ]);
    }

    /**
     * Tests that get_metadata declares both playerwords tables.
     *
     * @covers \mod_playerwords\privacy\provider::get_metadata
     * @return void
     */
    public function test_get_metadata(): void {
        $collection = new collection('mod_playerwords');
        $collection = provider::get_metadata($collection);
        $items = $collection->get_collection();
        $keys = array_map(fn($item) => $item->get_name(), $items);
        $this->assertContains('playerwords_attempts', $keys);
        $this->assertContains('playerwords_words', $keys);
    }

    /**
     * Tests that get_contexts_for_userid finds the context via attempts.
     *
     * @covers \mod_playerwords\privacy\provider::get_contexts_for_userid
     * @return void
     */
    public function test_get_contexts_for_userid_by_attempts(): void {
        $course = $this->getDataGenerator()->create_course();
        $cm     = $this->make_cm($course);
        $user   = $this->getDataGenerator()->create_user();
        $wordid = $this->make_word(get_admin()->id, (int)$cm->instance);
        $this->make_attempt($user->id, (int)$cm->instance, $wordid);

        $contextlist = provider::get_contexts_for_userid($user->id);
        $contextids  = $contextlist->get_contextids();

        $expected = \context_module::instance($cm->id)->id;
        $this->assertContains((string)$expected, $contextids);
    }

    /**
     * Tests that get_contexts_for_userid finds the context via added words.
     *
     * @covers \mod_playerwords\privacy\provider::get_contexts_for_userid
     * @return void
     */
    public function test_get_contexts_for_userid_by_words_added(): void {
        $course = $this->getDataGenerator()->create_course();
        $cm     = $this->make_cm($course);
        $user   = $this->getDataGenerator()->create_user();
        $this->make_word($user->id, (int)$cm->instance);

        $contextlist = provider::get_contexts_for_userid($user->id);
        $contextids  = $contextlist->get_contextids();

        $expected = \context_module::instance($cm->id)->id;
        $this->assertContains((string)$expected, $contextids);
    }

    /**
     * Tests that get_users_in_context returns both attempt users and word authors.
     *
     * @covers \mod_playerwords\privacy\provider::get_users_in_context
     * @return void
     */
    public function test_get_users_in_context(): void {
        $course    = $this->getDataGenerator()->create_course();
        $cm        = $this->make_cm($course);
        $student   = $this->getDataGenerator()->create_user();
        $teacher   = $this->getDataGenerator()->create_user();
        $wordid    = $this->make_word($teacher->id, (int)$cm->instance);
        $this->make_attempt($student->id, (int)$cm->instance, $wordid);

        $context  = \context_module::instance($cm->id);
        $userlist = new userlist($context, 'mod_playerwords');
        provider::get_users_in_context($userlist);
        $userids = $userlist->get_userids();

        $this->assertContains($student->id, $userids);
        $this->assertContains($teacher->id, $userids);
    }

    /**
     * Tests that delete_data_for_user removes only that user's attempts and anonymises their words.
     *
     * @covers \mod_playerwords\privacy\provider::delete_data_for_user
     * @return void
     */
    public function test_delete_data_for_user(): void {
        global $DB;

        $course  = $this->getDataGenerator()->create_course();
        $cm      = $this->make_cm($course);
        $user    = $this->getDataGenerator()->create_user();
        $wordid  = $this->make_word($user->id, (int)$cm->instance);
        $this->make_attempt($user->id, (int)$cm->instance, $wordid);

        $context      = \context_module::instance($cm->id);
        $contextlist  = new approved_contextlist($user, 'mod_playerwords', [$context->id]);
        provider::delete_data_for_user($contextlist);

        $attempts = $DB->count_records('playerwords_attempts', [
            'userid'        => $user->id,
            'playerwordsid' => (int)$cm->instance,
        ]);
        $this->assertSame(0, $attempts);

        $addedby = $DB->get_field('playerwords_words', 'addedby', ['id' => $wordid]);
        $this->assertSame('0', (string)$addedby);
    }

    /**
     * Tests that delete_data_for_users removes data for the listed users only.
     *
     * @covers \mod_playerwords\privacy\provider::delete_data_for_users
     * @return void
     */
    public function test_delete_data_for_users(): void {
        global $DB;

        $course   = $this->getDataGenerator()->create_course();
        $cm       = $this->make_cm($course);
        $user1    = $this->getDataGenerator()->create_user();
        $user2    = $this->getDataGenerator()->create_user();
        $wordid   = $this->make_word($user1->id, (int)$cm->instance);
        $this->make_attempt($user1->id, (int)$cm->instance, $wordid);
        $this->make_attempt($user2->id, (int)$cm->instance, $wordid);

        $context      = \context_module::instance($cm->id);
        $approvedlist = new approved_userlist($context, 'mod_playerwords', [$user1->id]);
        provider::delete_data_for_users($approvedlist);

        $this->assertSame(0, $DB->count_records('playerwords_attempts', ['userid' => $user1->id]));
        $this->assertSame(1, $DB->count_records('playerwords_attempts', ['userid' => $user2->id]));
    }
}
