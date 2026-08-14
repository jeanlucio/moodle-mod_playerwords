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
use mod_playerwords\local\intro_service;

/**
 * Tests for the Privacy API provider.
 */
final class provider_test extends \core_privacy\tests\provider_testcase {
    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
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
     * @param float $rankingpoints Ranking points awarded for the round. Deliberately
     *                             different from the fixed score value, so a test
     *                             asserting on it cannot be accidentally satisfied by
     *                             reading the score column instead.
     * @return void
     */
    private function make_attempt(int $userid, int $playerwordsid, int $wordid, float $rankingpoints = 80.0): void {
        global $DB;
        $DB->insert_record('playerwords_attempts', (object)[
            'playerwordsid' => $playerwordsid,
            'userid'        => $userid,
            'wordid'        => $wordid,
            'attempts_used' => 3,
            'time_used'     => 45,
            'completed'     => 1,
            'score'         => 100.0,
            'rankingpoints' => $rankingpoints,
            'timecreated'   => time(),
            'timefinished'  => time(),
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
     * Tests that get_metadata declares both playerwords tables and the
     * site-wide "seen intro" user preference.
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
        $this->assertContains(intro_service::get_preference_name(), $keys);
    }

    /**
     * Tests that the declared playerwords_attempts field keys match every real column
     * of the table (minus id). Asserted as a set-equality against $DB->get_columns()
     * rather than checking individual keys one by one, so a future column silently
     * added to install.xml without a matching metadata entry fails this test — the
     * exact drift that left rankingpoints undeclared until this test was added.
     *
     * @covers \mod_playerwords\privacy\provider::get_metadata
     * @return void
     */
    public function test_get_metadata_playerwords_attempts_fields_match_schema(): void {
        global $DB;

        $collection = new collection('mod_playerwords');
        $collection = provider::get_metadata($collection);

        $tableitem = null;
        foreach ($collection->get_collection() as $item) {
            if ($item->get_name() === 'playerwords_attempts') {
                $tableitem = $item;
                break;
            }
        }
        $this->assertNotNull($tableitem);

        $declaredfields = array_keys($tableitem->get_privacy_fields());
        $realcolumns = array_keys($DB->get_columns('playerwords_attempts'));
        $realcolumns = array_values(array_diff($realcolumns, ['id']));

        sort($declaredfields);
        sort($realcolumns);
        $this->assertSame($realcolumns, $declaredfields);
    }

    /**
     * Regression test for metadata drift: every field export_user_data() actually
     * puts in a playerwords_words row must be declared in get_metadata() — asserted
     * against what export_user_data() really returns, not by hardcoding the expected
     * key list, so a field added to one without the other fails this test rather than
     * silently drifting. Unlike playerwords_attempts above, playerwords_words is not
     * checked against the full table schema here: some of its columns (concept,
     * glossaryid, approved, timemodified) are catalogue/config data the teacher
     * authored or a manager changed, not personal data about the addedby user — see
     * test_get_metadata_playerwords_words_every_column_is_declared_or_documented()
     * below for the exhaustive column-by-column accounting. The addedby and
     * playerwordsid columns are deliberately not exported themselves, since the
     * export is already scoped by both — that is checked separately.
     *
     * @covers \mod_playerwords\privacy\provider::get_metadata
     * @covers \mod_playerwords\privacy\provider::export_user_data
     * @return void
     */
    public function test_playerwords_words_export_keys_are_all_declared_in_metadata(): void {
        $course = $this->getDataGenerator()->create_course();
        $cm = $this->make_cm($course);
        $user = $this->getDataGenerator()->create_user();
        $this->make_word($user->id, (int)$cm->id);

        $context = \context_module::instance($cm->cmid);
        $contextlist = new approved_contextlist($user, 'mod_playerwords', [$context->id]);
        provider::export_user_data($contextlist);

        $wordsdata = writer::with_context($context)->get_data([
            get_string('pluginname', 'mod_playerwords'),
            get_string('privacy:words', 'mod_playerwords'),
        ]);
        $this->assertNotEmpty($wordsdata->words);

        $declaredfields = [];
        foreach (provider::get_metadata(new collection('mod_playerwords'))->get_collection() as $item) {
            if ($item->get_name() === 'playerwords_words') {
                $declaredfields = array_keys($item->get_privacy_fields());
            }
        }
        $this->assertContains('addedby', $declaredfields);

        foreach (array_keys($wordsdata->words[0]) as $exportedkey) {
            $this->assertContains(
                $exportedkey,
                $declaredfields,
                "Field '$exportedkey' is exported but not declared in get_metadata()."
            );
        }
    }

    /**
     * Exhaustive accounting for every real column of playerwords_words, not just the
     * ones export_user_data() happens to touch: each column is either declared in
     * get_metadata() or explicitly listed here as a documented, justified exclusion.
     * Kept in sync by hand with the comment in provider.php::get_metadata(): concept
     * mirrors word verbatim for manual/AI rows and otherwise carries glossary content
     * on addedby=0 rows (never attributable to a real user); glossaryid only means
     * anything on those same addedby=0 rows; approved is a moderation flag a manager
     * sets, not necessarily addedby; timemodified can likewise be touched by a manager
     * acting on someone else's word.
     *
     * @covers \mod_playerwords\privacy\provider::get_metadata
     * @return void
     */
    public function test_get_metadata_playerwords_words_every_column_is_declared_or_documented(): void {
        global $DB;

        $documentedexclusions = ['concept', 'glossaryid', 'approved', 'timemodified'];

        $tableitem = null;
        foreach (provider::get_metadata(new collection('mod_playerwords'))->get_collection() as $item) {
            if ($item->get_name() === 'playerwords_words') {
                $tableitem = $item;
                break;
            }
        }
        $this->assertNotNull($tableitem);
        $declaredfields = array_keys($tableitem->get_privacy_fields());

        $realcolumns = array_keys($DB->get_columns('playerwords_words'));
        $realcolumns = array_values(array_diff($realcolumns, ['id']));

        $accountedfor = array_merge($declaredfields, $documentedexclusions);
        foreach ($realcolumns as $column) {
            $this->assertContains(
                $column,
                $accountedfor,
                "Column '$column' is neither declared in get_metadata() nor listed as a documented exclusion."
            );
        }

        // Also guards the other direction: an exclusion left in the list after the
        // column itself was renamed or dropped would otherwise go unnoticed.
        foreach ($documentedexclusions as $excluded) {
            $this->assertContains($excluded, $realcolumns);
            $this->assertNotContains($excluded, $declaredfields);
        }
    }

    /**
     * A user who never had the intro preference set exports no preference data.
     *
     * @covers \mod_playerwords\privacy\provider::export_user_preferences
     * @return void
     */
    public function test_export_user_preferences_no_pref(): void {
        $user = $this->getDataGenerator()->create_user();

        provider::export_user_preferences($user->id);

        $writer = writer::with_context(\context_system::instance());
        $this->assertFalse($writer->has_any_data());
    }

    /**
     * A user who has seen the intro exports exactly that one preference, under
     * the mod_playerwords component.
     *
     * @covers \mod_playerwords\privacy\provider::export_user_preferences
     * @return void
     */
    public function test_export_user_preferences_seen(): void {
        $user = $this->getDataGenerator()->create_user();
        intro_service::mark_intro_seen((int)$user->id);

        provider::export_user_preferences($user->id);

        $writer = writer::with_context(\context_system::instance());
        $this->assertTrue($writer->has_any_data());

        $prefs = (array)$writer->get_user_preferences('mod_playerwords');
        $this->assertCount(1, $prefs);
        $this->assertArrayHasKey(intro_service::get_preference_name(), $prefs);
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
        $wordid = $this->make_word(get_admin()->id, (int)$cm->id);
        $this->make_attempt($user->id, (int)$cm->id, $wordid);

        $contextlist = provider::get_contexts_for_userid($user->id);
        $contextids  = $contextlist->get_contextids();

        $expected = \context_module::instance($cm->cmid)->id;
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
        $this->make_word($user->id, (int)$cm->id);

        $contextlist = provider::get_contexts_for_userid($user->id);
        $contextids  = $contextlist->get_contextids();

        $expected = \context_module::instance($cm->cmid)->id;
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
        $wordid    = $this->make_word($teacher->id, (int)$cm->id);
        $this->make_attempt($student->id, (int)$cm->id, $wordid);

        $context  = \context_module::instance($cm->cmid);
        $userlist = new userlist($context, 'mod_playerwords');
        provider::get_users_in_context($userlist);
        $userids = $userlist->get_userids();

        $this->assertContains((int)$student->id, $userids);
        $this->assertContains((int)$teacher->id, $userids);
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
        $wordid  = $this->make_word($user->id, (int)$cm->id);
        $this->make_attempt($user->id, (int)$cm->id, $wordid);

        $context      = \context_module::instance($cm->cmid);
        $contextlist  = new approved_contextlist($user, 'mod_playerwords', [$context->id]);
        provider::delete_data_for_user($contextlist);

        $attempts = $DB->count_records('playerwords_attempts', [
            'userid'        => $user->id,
            'playerwordsid' => (int)$cm->id,
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
        $wordid   = $this->make_word($user1->id, (int)$cm->id);
        $this->make_attempt($user1->id, (int)$cm->id, $wordid);
        $this->make_attempt($user2->id, (int)$cm->id, $wordid);

        $context      = \context_module::instance($cm->cmid);
        $approvedlist = new approved_userlist($context, 'mod_playerwords', [$user1->id]);
        provider::delete_data_for_users($approvedlist);

        $this->assertSame(0, $DB->count_records('playerwords_attempts', ['userid' => $user1->id]));
        $this->assertSame(1, $DB->count_records('playerwords_attempts', ['userid' => $user2->id]));
    }

    /**
     * Tests that export_user_data writes both attempts and added-word data for the user.
     *
     * @covers \mod_playerwords\privacy\provider::export_user_data
     * @return void
     */
    public function test_export_user_data(): void {
        $course = $this->getDataGenerator()->create_course();
        $cm     = $this->make_cm($course);
        $user   = $this->getDataGenerator()->create_user();
        $wordid = $this->make_word($user->id, (int)$cm->id);
        $this->make_attempt($user->id, (int)$cm->id, $wordid);

        $context     = \context_module::instance($cm->cmid);
        $contextlist = new approved_contextlist($user, 'mod_playerwords', [$context->id]);
        provider::export_user_data($contextlist);

        $attemptsdata = writer::with_context($context)->get_data([
            get_string('pluginname', 'mod_playerwords'),
            get_string('privacy:attempts', 'mod_playerwords'),
        ]);
        $this->assertNotEmpty($attemptsdata->attempts);
        $this->assertSame($wordid, (int)$attemptsdata->attempts[0]['wordid']);
        $this->assertSame(80.0, (float)$attemptsdata->attempts[0]['rankingpoints']);

        $wordsdata = writer::with_context($context)->get_data([
            get_string('pluginname', 'mod_playerwords'),
            get_string('privacy:words', 'mod_playerwords'),
        ]);
        $this->assertNotEmpty($wordsdata->words);
        $this->assertSame('gato', $wordsdata->words[0]['word']);
    }

    /**
     * Tests that export_user_data is a no-op for an empty approved contextlist.
     *
     * @covers \mod_playerwords\privacy\provider::export_user_data
     * @return void
     */
    public function test_export_user_data_empty_contextlist_is_noop(): void {
        $user = $this->getDataGenerator()->create_user();
        $contextlist = new approved_contextlist($user, 'mod_playerwords', []);

        provider::export_user_data($contextlist);

        $this->expectNotToPerformAssertions();
    }

    /**
     * Tests that delete_data_for_user clears attempts and anonymises word authorship
     * across every context in the approved list, not just the first one.
     *
     * @covers \mod_playerwords\privacy\provider::delete_data_for_user
     * @return void
     */
    public function test_delete_data_for_user_across_multiple_contexts(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $cm1    = $this->make_cm($course);
        $cm2    = $this->make_cm($course);
        $user   = $this->getDataGenerator()->create_user();

        $wordid1 = $this->make_word($user->id, (int)$cm1->id);
        $this->make_attempt($user->id, (int)$cm1->id, $wordid1);
        $wordid2 = $this->make_word($user->id, (int)$cm2->id);
        $this->make_attempt($user->id, (int)$cm2->id, $wordid2);

        $context1    = \context_module::instance($cm1->cmid);
        $context2    = \context_module::instance($cm2->cmid);
        $contextlist = new approved_contextlist($user, 'mod_playerwords', [$context1->id, $context2->id]);

        provider::delete_data_for_user($contextlist);

        $this->assertSame(0, $DB->count_records('playerwords_attempts', ['userid' => $user->id]));
        $this->assertSame('0', (string)$DB->get_field('playerwords_words', 'addedby', ['id' => $wordid1]));
        $this->assertSame('0', (string)$DB->get_field('playerwords_words', 'addedby', ['id' => $wordid2]));
    }

    /**
     * Tests that delete_data_for_all_users_in_context clears every user's attempts and
     * anonymises every word author within that context only, leaving another activity's
     * data untouched.
     *
     * @covers \mod_playerwords\privacy\provider::delete_data_for_all_users_in_context
     * @return void
     */
    public function test_delete_data_for_all_users_in_context(): void {
        global $DB;

        $course   = $this->getDataGenerator()->create_course();
        $cmtarget = $this->make_cm($course);
        $cmother  = $this->make_cm($course);
        $user1    = $this->getDataGenerator()->create_user();
        $user2    = $this->getDataGenerator()->create_user();

        $wordid = $this->make_word($user1->id, (int)$cmtarget->id);
        $this->make_attempt($user1->id, (int)$cmtarget->id, $wordid);
        $this->make_attempt($user2->id, (int)$cmtarget->id, $wordid);

        $otherwordid = $this->make_word($user1->id, (int)$cmother->id);
        $this->make_attempt($user1->id, (int)$cmother->id, $otherwordid);

        provider::delete_data_for_all_users_in_context(\context_module::instance($cmtarget->cmid));

        $this->assertSame(0, $DB->count_records('playerwords_attempts', ['playerwordsid' => (int)$cmtarget->id]));
        $this->assertSame('0', (string)$DB->get_field('playerwords_words', 'addedby', ['id' => $wordid]));

        $this->assertSame(1, $DB->count_records('playerwords_attempts', ['playerwordsid' => (int)$cmother->id]));
        $this->assertEquals($user1->id, $DB->get_field('playerwords_words', 'addedby', ['id' => $otherwordid]));
    }

    /**
     * Tests that delete_data_for_all_users_in_context is a silent no-op for a
     * non-module context.
     *
     * @covers \mod_playerwords\privacy\provider::delete_data_for_all_users_in_context
     * @return void
     */
    public function test_delete_data_for_all_users_in_context_ignores_non_module_context(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $cm     = $this->make_cm($course);
        $user   = $this->getDataGenerator()->create_user();
        $wordid = $this->make_word($user->id, (int)$cm->id);
        $this->make_attempt($user->id, (int)$cm->id, $wordid);

        provider::delete_data_for_all_users_in_context(\context_system::instance());

        $this->assertSame(1, $DB->count_records('playerwords_attempts', ['playerwordsid' => (int)$cm->id]));
    }

    /**
     * Tests that get_users_in_context is a silent no-op for a non-module context.
     *
     * @covers \mod_playerwords\privacy\provider::get_users_in_context
     * @return void
     */
    public function test_get_users_in_context_ignores_non_module_context(): void {
        $userlist = new userlist(\context_system::instance(), 'mod_playerwords');

        provider::get_users_in_context($userlist);

        $this->assertSame([], $userlist->get_userids());
    }
}
