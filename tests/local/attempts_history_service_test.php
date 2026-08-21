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
 * Unit tests for attempts_history_service.
 *
 * @package    mod_playerwords
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playerwords\local;

use mod_playerwords\event\attempt_deleted;

/**
 * Tests for attempts_history_service — requires database.
 *
 * @covers \mod_playerwords\local\attempts_history_service
 * @covers \mod_playerwords\event\attempt_deleted
 */
final class attempts_history_service_test extends \advanced_testcase {
    /** @var \stdClass Course used by the tests. */
    private \stdClass $course;

    /** @var \stdClass Student used by the tests. */
    private \stdClass $user;

    #[\Override]
    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        $this->resetAfterTest();
        require_once($CFG->dirroot . '/mod/playerwords/lib.php');
        $this->course = $this->getDataGenerator()->create_course();
        $this->user = $this->getDataGenerator()->create_user();
    }

    /**
     * Creates a playerwords instance.
     *
     * @param array $overrides Instance field overrides.
     * @return \stdClass
     */
    private function make_instance(array $overrides = []): \stdClass {
        global $DB;

        $record = array_merge([
            'course'          => $this->course->id,
            'cooldown_amount' => 0,
        ], $overrides);

        $instance = $this->getDataGenerator()->create_module('playerwords', $record);
        return $DB->get_record('playerwords', ['id' => $instance->id], '*', MUST_EXIST);
    }

    /**
     * Inserts a finished or pending attempt record.
     *
     * @param \stdClass $instance Activity instance.
     * @param \stdClass $user User who played.
     * @param float $score Score for the attempt.
     * @param int $wordid Word id the attempt was played with, 0 for none.
     * @param bool $finished Whether the round is finished.
     * @param int $timeoffset Seconds to add to time(), used to control ordering.
     * @param ?float $rankingpoints Ranking points for the attempt; defaults to $score.
     * @return \stdClass Inserted playerwords_attempts record, including its new id.
     */
    private function add_attempt(
        \stdClass $instance,
        \stdClass $user,
        float $score,
        int $wordid = 0,
        bool $finished = true,
        int $timeoffset = 0,
        ?float $rankingpoints = null
    ): \stdClass {
        global $DB;

        $record = (object)[
            'playerwordsid' => $instance->id,
            'userid'        => $user->id,
            'wordid'        => $wordid,
            'attempts_used' => $finished ? 2 : 0,
            'time_used'     => $finished ? 65 : 0,
            'completed'     => $finished && $score > 0 ? 1 : 0,
            'score'         => $score,
            'rankingpoints' => $rankingpoints ?? $score,
            'timecreated'   => time() + $timeoffset,
            'timefinished'  => $finished ? (time() + $timeoffset) : 0,
        ];
        $record->id = $DB->insert_record('playerwords_attempts', $record);

        return $record;
    }

    /**
     * An activity with no finished attempts yields an empty history and no grade.
     *
     * @return void
     */
    public function test_get_history_is_empty_without_finished_attempts(): void {
        $instance = $this->make_instance(['grade' => 100]);

        $history = attempts_history_service::get_history($instance, $this->user->id);

        $this->assertTrue($history['isempty']);
        $this->assertSame([], $history['rows']);
        $this->assertFalse($history['showgrade']);
    }

    /**
     * A still-pending reservation (round in progress, or abandoned without ever
     * finishing) is excluded from the history, mirroring ranking_service.
     *
     * @return void
     */
    public function test_get_history_excludes_pending_attempts(): void {
        $instance = $this->make_instance(['grade' => 100]);
        $this->add_attempt($instance, $this->user, 100, 0, true);
        $this->add_attempt($instance, $this->user, 0, 0, false);

        $history = attempts_history_service::get_history($instance, $this->user->id);

        $this->assertCount(1, $history['rows']);
    }

    /**
     * Rows are shown most-recent-first, the reverse of the ASC order used for the
     * grade calculation.
     *
     * @return void
     */
    public function test_get_history_rows_are_most_recent_first(): void {
        $instance = $this->make_instance(['grade' => 100, 'grademethod' => PLAYERWORDS_GRADE_FIRST]);
        $this->add_attempt($instance, $this->user, 30, 0, true, -20);
        $this->add_attempt($instance, $this->user, 70, 0, true, -10);

        $history = attempts_history_service::get_history($instance, $this->user->id);

        $this->assertSame('70.00', $history['rows'][0]['score']);
        $this->assertSame('30.00', $history['rows'][1]['score']);
    }

    /**
     * The current grade matches playerwords_calculate_user_grade() for the instance's
     * configured grading method — the whole point of this service is to never
     * duplicate that aggregation logic.
     *
     * @return void
     */
    public function test_get_history_grade_matches_calculate_user_grade(): void {
        $instance = $this->make_instance(['grade' => 100, 'grademethod' => PLAYERWORDS_GRADE_HIGHEST]);
        $this->add_attempt($instance, $this->user, 40, 0, true, -20);
        $this->add_attempt($instance, $this->user, 90, 0, true, -10);

        $history = attempts_history_service::get_history($instance, $this->user->id);

        $this->assertTrue($history['showgrade']);
        $this->assertSame('90.00', $history['grade']);
        $this->assertSame('100.00', $history['maxgrade']);
    }

    /**
     * The grade summary is hidden entirely for an ungraded instance, even with
     * finished attempts on record — there is nothing meaningful to show.
     *
     * @return void
     */
    public function test_get_history_hides_grade_when_ungraded(): void {
        $instance = $this->make_instance(['grade' => 0]);
        $this->add_attempt($instance, $this->user, 0);

        $history = attempts_history_service::get_history($instance, $this->user->id);

        $this->assertFalse($history['showgrade']);
    }

    /**
     * The word column prefers the joined word's concept, falling back to its raw
     * word text when no concept was recorded (e.g. a manually added word).
     *
     * @return void
     */
    public function test_get_history_row_shows_word_text(): void {
        global $DB;

        $instance = $this->make_instance(['grade' => 100]);
        $wordid = $DB->insert_record('playerwords_words', (object)[
            'playerwordsid' => $instance->id,
            'word'          => 'boca',
            'concept'       => null,
            'hint'          => '',
            'source'        => 'manual',
            'glossaryid'    => 0,
            'approved'      => 1,
            'timecreated'   => time(),
            'addedby'       => $this->user->id,
        ]);
        $this->add_attempt($instance, $this->user, 100, $wordid);

        $history = attempts_history_service::get_history($instance, $this->user->id);

        $this->assertSame('boca', $history['rows'][0]['word']);
    }

    /**
     * Time used is formatted as m:ss for display.
     *
     * @return void
     */
    public function test_get_history_row_formats_time_used(): void {
        $instance = $this->make_instance(['grade' => 100]);
        $this->add_attempt($instance, $this->user, 100);

        $history = attempts_history_service::get_history($instance, $this->user->id);

        $this->assertSame('1:05', $history['rows'][0]['timeused']);
    }

    /**
     * The ranking-points column is included, formatted to 2 decimals, when the
     * activity has ranking enabled.
     *
     * @return void
     */
    public function test_get_history_shows_ranking_points_when_ranking_enabled(): void {
        $instance = $this->make_instance(['grade' => 100, 'show_ranking' => 1]);
        $this->add_attempt($instance, $this->user, 100, 0, true, 0, 83.33333);

        $history = attempts_history_service::get_history($instance, $this->user->id);

        $this->assertTrue($history['showranking']);
        $this->assertSame('83.33', $history['rows'][0]['rankingpoints']);
    }

    /**
     * The ranking-points column is omitted (empty string) when the activity has
     * ranking disabled — there is nothing meaningful to show a student about a
     * ranking they can never see.
     *
     * @return void
     */
    public function test_get_history_hides_ranking_points_when_ranking_disabled(): void {
        $instance = $this->make_instance(['grade' => 100, 'show_ranking' => 0]);
        $this->add_attempt($instance, $this->user, 100, 0, true, 0, 83.33333);

        $history = attempts_history_service::get_history($instance, $this->user->id);

        $this->assertFalse($history['showranking']);
        $this->assertSame('', $history['rows'][0]['rankingpoints']);
    }

    /**
     * get_all_history() returns every student's finished attempts, ordered most-recent-first
     * by default, with the student's full name attached to each row.
     *
     * @return void
     */
    public function test_get_all_history_includes_every_student_most_recent_first(): void {
        $instance = $this->make_instance(['grade' => 100]);
        $cm = get_coursemodule_from_instance('playerwords', $instance->id, 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        $other = $this->getDataGenerator()->create_user();

        $this->add_attempt($instance, $this->user, 30, 0, true, -20);
        $this->add_attempt($instance, $other, 70, 0, true, -10);

        $history = attempts_history_service::get_all_history(
            $cm,
            $instance,
            $context,
            $this->user->id,
            0,
            30,
            'date',
            'DESC',
            0
        );

        $this->assertSame(2, $history['total']);
        $this->assertSame(fullname($other), $history['rows'][0]['student']);
        $this->assertSame(fullname($this->user), $history['rows'][1]['student']);
    }

    /**
     * A user who can manage the activity (editingteacher) never appears in the report,
     * even with attempts of their own — same rule as ranking_service::get_ranking().
     *
     * @return void
     */
    public function test_get_all_history_excludes_users_who_can_manage_the_activity(): void {
        $instance = $this->make_instance(['grade' => 100]);
        $cm = get_coursemodule_from_instance('playerwords', $instance->id, 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $this->course->id, 'editingteacher');

        $this->add_attempt($instance, $this->user, 50);
        $this->add_attempt($instance, $teacher, 999);

        $history = attempts_history_service::get_all_history(
            $cm,
            $instance,
            $context,
            $this->user->id,
            0,
            30,
            'date',
            'DESC',
            0
        );
        $players = attempts_history_service::get_players_for_filter($cm, $instance, $context, $this->user->id);

        $this->assertSame(1, $history['total']);
        $this->assertSame(fullname($this->user), $history['rows'][0]['student']);
        $this->assertCount(1, $players);
        $this->assertSame(fullname($this->user), $players[0]->fullname);
    }

    /**
     * The studentid filter restricts the report to a single student's own rows.
     *
     * @return void
     */
    public function test_get_all_history_filters_by_student(): void {
        $instance = $this->make_instance(['grade' => 100]);
        $cm = get_coursemodule_from_instance('playerwords', $instance->id, 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        $other = $this->getDataGenerator()->create_user();

        $this->add_attempt($instance, $this->user, 30);
        $this->add_attempt($instance, $other, 70);

        $history = attempts_history_service::get_all_history(
            $cm,
            $instance,
            $context,
            $this->user->id,
            0,
            30,
            'date',
            'DESC',
            $this->user->id
        );

        $this->assertSame(1, $history['total']);
        $this->assertSame(fullname($this->user), $history['rows'][0]['student']);
    }

    /**
     * Sorting by score ascending puts the lowest-scoring row first, and an unknown sort
     * key falls back to the default (date) instead of causing a SQL error.
     *
     * @return void
     */
    public function test_get_all_history_sorts_by_allowed_column(): void {
        $instance = $this->make_instance(['grade' => 100]);
        $cm = get_coursemodule_from_instance('playerwords', $instance->id, 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);

        $this->add_attempt($instance, $this->user, 70, 0, true, -20);
        $this->add_attempt($instance, $this->user, 30, 0, true, -10);

        $ascending = attempts_history_service::get_all_history(
            $cm,
            $instance,
            $context,
            $this->user->id,
            0,
            30,
            'score',
            'ASC',
            0
        );
        $this->assertSame('30.00', $ascending['rows'][0]['score']);

        $unknownkey = attempts_history_service::get_all_history(
            $cm,
            $instance,
            $context,
            $this->user->id,
            0,
            30,
            'not_a_real_column; DROP TABLE',
            'DESC',
            0
        );
        $this->assertSame(2, $unknownkey['total']);
    }

    /**
     * Pagination returns distinct slices of the full result set.
     *
     * @return void
     */
    public function test_get_all_history_paginates(): void {
        $instance = $this->make_instance(['grade' => 100]);
        $cm = get_coursemodule_from_instance('playerwords', $instance->id, 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);

        for ($i = 0; $i < 5; $i++) {
            $this->add_attempt($instance, $this->user, (float)$i, 0, true, -$i);
        }

        $firstpage = attempts_history_service::get_all_history(
            $cm,
            $instance,
            $context,
            $this->user->id,
            0,
            2,
            'date',
            'DESC',
            0
        );
        $secondpage = attempts_history_service::get_all_history(
            $cm,
            $instance,
            $context,
            $this->user->id,
            1,
            2,
            'date',
            'DESC',
            0
        );

        $this->assertSame(5, $firstpage['total']);
        $this->assertCount(2, $firstpage['rows']);
        $this->assertCount(2, $secondpage['rows']);
        $this->assertNotSame($firstpage['rows'][0]['score'], $secondpage['rows'][0]['score']);
    }

    /**
     * Puts the instance's course module into SEPARATEGROUPS mode and returns the
     * reloaded $cm record, whose ->groupmode a stale in-memory copy would still
     * report as 0 (NOGROUPS).
     *
     * @param \stdClass $instance Activity instance.
     * @return \stdClass Reloaded course module record.
     */
    private function enable_separategroups(\stdClass $instance): \stdClass {
        global $DB;
        $cm = get_coursemodule_from_instance('playerwords', $instance->id, 0, false, MUST_EXIST);
        $DB->set_field('course_modules', 'groupmode', SEPARATEGROUPS, ['id' => $cm->id]);
        return get_coursemodule_from_instance('playerwords', $instance->id, 0, false, MUST_EXIST);
    }

    /**
     * Regression test: with SEPARATEGROUPS active, a viewer restricted to one group
     * (a non-editing teacher, the exact role mod/playerwords:viewreports is granted to
     * by default) must not see another group's students in the all-students report —
     * mirroring the restriction ranking_service::get_ranking() already applies.
     *
     * @return void
     */
    public function test_get_all_history_separategroups_restricts_to_viewers_own_group(): void {
        $instance = $this->make_instance(['grade' => 100]);
        $cm = $this->enable_separategroups($instance);
        $context = \context_module::instance($cm->id);

        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $this->course->id, 'teacher');
        $studenta = $this->getDataGenerator()->create_user();
        $studentb = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($studenta->id, $this->course->id, 'student');
        $this->getDataGenerator()->enrol_user($studentb->id, $this->course->id, 'student');

        $groupa = $this->getDataGenerator()->create_group(['courseid' => $this->course->id]);
        $groupb = $this->getDataGenerator()->create_group(['courseid' => $this->course->id]);
        $this->getDataGenerator()->create_group_member(['groupid' => $groupa->id, 'userid' => $teacher->id]);
        $this->getDataGenerator()->create_group_member(['groupid' => $groupa->id, 'userid' => $studenta->id]);
        $this->getDataGenerator()->create_group_member(['groupid' => $groupb->id, 'userid' => $studentb->id]);

        $this->add_attempt($instance, $studenta, 50);
        $this->add_attempt($instance, $studentb, 90);

        $history = attempts_history_service::get_all_history(
            $cm,
            $instance,
            $context,
            $teacher->id,
            0,
            30,
            'date',
            'DESC',
            0
        );

        $this->assertSame(1, $history['total']);
        $this->assertSame(fullname($studenta), $history['rows'][0]['student']);
    }

    /**
     * Regression test for the studentid bypass in the report's PoC: even if the
     * filter dropdown is correctly scoped, requesting another group's student id
     * directly via the studentid parameter must still return nothing — the group
     * scope is enforced as an additional SQL condition, not just a UI-level filter.
     *
     * @return void
     */
    public function test_get_all_history_separategroups_studentid_filter_cannot_bypass_group_scope(): void {
        $instance = $this->make_instance(['grade' => 100]);
        $cm = $this->enable_separategroups($instance);
        $context = \context_module::instance($cm->id);

        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $this->course->id, 'teacher');
        $studentb = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($studentb->id, $this->course->id, 'student');

        $groupa = $this->getDataGenerator()->create_group(['courseid' => $this->course->id]);
        $groupb = $this->getDataGenerator()->create_group(['courseid' => $this->course->id]);
        $this->getDataGenerator()->create_group_member(['groupid' => $groupa->id, 'userid' => $teacher->id]);
        $this->getDataGenerator()->create_group_member(['groupid' => $groupb->id, 'userid' => $studentb->id]);

        $this->add_attempt($instance, $studentb, 90);

        $history = attempts_history_service::get_all_history(
            $cm,
            $instance,
            $context,
            $teacher->id,
            0,
            30,
            'date',
            'DESC',
            $studentb->id
        );

        $this->assertSame(0, $history['total']);
        $this->assertTrue($history['isempty']);
    }

    /**
     * The filter dropdown itself must not offer a student from another group either —
     * otherwise the UI would advertise an id the report then (correctly) refuses.
     *
     * @return void
     */
    public function test_get_players_for_filter_separategroups_excludes_other_group(): void {
        $instance = $this->make_instance(['grade' => 100]);
        $cm = $this->enable_separategroups($instance);
        $context = \context_module::instance($cm->id);

        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $this->course->id, 'teacher');
        $studenta = $this->getDataGenerator()->create_user();
        $studentb = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($studenta->id, $this->course->id, 'student');
        $this->getDataGenerator()->enrol_user($studentb->id, $this->course->id, 'student');

        $groupa = $this->getDataGenerator()->create_group(['courseid' => $this->course->id]);
        $groupb = $this->getDataGenerator()->create_group(['courseid' => $this->course->id]);
        $this->getDataGenerator()->create_group_member(['groupid' => $groupa->id, 'userid' => $teacher->id]);
        $this->getDataGenerator()->create_group_member(['groupid' => $groupa->id, 'userid' => $studenta->id]);
        $this->getDataGenerator()->create_group_member(['groupid' => $groupb->id, 'userid' => $studentb->id]);

        $this->add_attempt($instance, $studenta, 50);
        $this->add_attempt($instance, $studentb, 90);

        $players = attempts_history_service::get_players_for_filter($cm, $instance, $context, $teacher->id);

        $this->assertCount(1, $players);
        $this->assertSame(fullname($studenta), $players[0]->fullname);
    }

    /**
     * A viewer holding moodle/site:accessallgroups sees every group's students
     * despite SEPARATEGROUPS — the standard Moodle override for a report-viewing
     * role, mirrored from the fix's own recommendation rather than left unhandled.
     *
     * @return void
     */
    public function test_get_all_history_accessallgroups_overrides_separategroups(): void {
        $instance = $this->make_instance(['grade' => 100]);
        $cm = $this->enable_separategroups($instance);
        $context = \context_module::instance($cm->id);

        $viewer = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($viewer->id, $this->course->id, 'teacher');
        $roleid = create_role(
            'Accessallgroups viewer',
            'accessallgroupsviewer',
            'Holds moodle/site:accessallgroups'
        );
        // Core declares moodle/site:accessallgroups at CONTEXT_MODULE — assigning it at
        // the module context mirrors exactly where a real site would override it (the
        // activity's own Permissions screen), not the course context above it.
        assign_capability('moodle/site:accessallgroups', CAP_ALLOW, $roleid, $context->id, true);
        role_assign($roleid, $viewer->id, $context->id);

        $studenta = $this->getDataGenerator()->create_user();
        $studentb = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($studenta->id, $this->course->id, 'student');
        $this->getDataGenerator()->enrol_user($studentb->id, $this->course->id, 'student');
        $groupa = $this->getDataGenerator()->create_group(['courseid' => $this->course->id]);
        $groupb = $this->getDataGenerator()->create_group(['courseid' => $this->course->id]);
        $this->getDataGenerator()->create_group_member(['groupid' => $groupa->id, 'userid' => $studenta->id]);
        $this->getDataGenerator()->create_group_member(['groupid' => $groupb->id, 'userid' => $studentb->id]);
        // Viewer deliberately joins neither group — accessallgroups alone must be enough.

        $this->add_attempt($instance, $studenta, 50);
        $this->add_attempt($instance, $studentb, 90);

        $history = attempts_history_service::get_all_history(
            $cm,
            $instance,
            $context,
            $viewer->id,
            0,
            30,
            'date',
            'DESC',
            0
        );

        $this->assertSame(2, $history['total']);
    }

    /**
     * Regression test for the security audit finding: both get_all_history()'s
     * 'student' column and get_players_for_filter()'s ->fullname used to build the
     * displayed name with $DB->sql_fullname() directly, ignoring both
     * $CFG->fullnamedisplay and the moodle/site:viewfullnames capability. The default
     * teacher archetype already holds that capability (so it would not have shown this
     * bug), so this uses a custom report-viewer role without it — a realistic
     * configuration for a site that restricts full-name visibility more tightly than
     * core's own teacher default.
     *
     * @return void
     */
    public function test_get_all_history_and_players_hide_surname_without_viewfullnames_capability(): void {
        global $CFG;

        $instance = $this->make_instance(['grade' => 100]);
        $cm = get_coursemodule_from_instance('playerwords', $instance->id, 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);

        $roleid = create_role('Report viewer without full names', 'reportviewernofullnames', '');
        assign_capability('mod/playerwords:viewreports', CAP_ALLOW, $roleid, $context->id, true);
        assign_capability('moodle/site:viewfullnames', CAP_PREVENT, $roleid, $context->id, true);
        $viewer = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($viewer->id, $this->course->id, 'student');
        role_assign($roleid, $viewer->id, $context->id);

        $student = $this->getDataGenerator()->create_user(['firstname' => 'Ana', 'lastname' => 'Secret']);
        $this->getDataGenerator()->enrol_user($student->id, $this->course->id, 'student');
        $this->add_attempt($instance, $student, 50);

        $CFG->fullnamedisplay = 'firstname';
        $this->setUser($viewer);

        $history = attempts_history_service::get_all_history(
            $cm,
            $instance,
            $context,
            $viewer->id,
            0,
            30,
            'date',
            'DESC',
            0
        );
        $players = attempts_history_service::get_players_for_filter($cm, $instance, $context, $viewer->id);

        $this->assertSame('Ana', $history['rows'][0]['student']);
        $this->assertCount(1, $players);
        $this->assertSame('Ana', $players[0]->fullname);
    }

    /**
     * The ordinary case: a valid attemptid belonging to this instance is deleted, the
     * owning userid is returned so the caller knows whose grade to recompute, and an
     * attempt_deleted event fires with the right data.
     *
     * @return void
     */
    public function test_delete_attempts_removes_the_row_and_fires_event(): void {
        global $DB;
        $instance = $this->make_instance(['grade' => 100]);
        $cm = get_coursemodule_from_instance('playerwords', $instance->id, 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        $student = $this->getDataGenerator()->create_user();
        $attempt = $this->add_attempt($instance, $student, 80);

        $sink = $this->redirectEvents();
        $affected = attempts_history_service::delete_attempts(
            [$attempt->id],
            $cm,
            $instance,
            $context,
            $this->user->id
        );

        $this->assertSame([(int)$student->id], $affected);
        $this->assertFalse($DB->record_exists('playerwords_attempts', ['id' => $attempt->id]));

        $events = array_values(array_filter($sink->get_events(), fn($e) => $e instanceof attempt_deleted));
        $this->assertCount(1, $events);
        $this->assertSame((int)$attempt->id, $events[0]->objectid);
        $this->assertSame((int)$student->id, $events[0]->relateduserid);
        $this->assertSame((int)$instance->id, $events[0]->other['playerwordsid']);
    }

    /**
     * An attemptid belonging to a different instance is never deleted, no matter how
     * it is passed in — the instance-isolation invariant every other query in this
     * class already upholds.
     *
     * @return void
     */
    public function test_delete_attempts_ignores_attempt_from_another_instance(): void {
        global $DB;
        $instancea = $this->make_instance(['grade' => 100]);
        $instanceb = $this->make_instance(['grade' => 100]);
        $cma = get_coursemodule_from_instance('playerwords', $instancea->id, 0, false, MUST_EXIST);
        $context = \context_module::instance($cma->id);
        $student = $this->getDataGenerator()->create_user();
        $attemptb = $this->add_attempt($instanceb, $student, 80);

        // Attacking instance A's report with instance B's attemptid.
        $affected = attempts_history_service::delete_attempts(
            [$attemptb->id],
            $cma,
            $instancea,
            $context,
            $this->user->id
        );

        $this->assertSame([], $affected);
        $this->assertTrue($DB->record_exists('playerwords_attempts', ['id' => $attemptb->id]));
    }

    /**
     * With SEPARATEGROUPS active, a viewer restricted to one group cannot delete an
     * attempt belonging to a student in a different group — the same restriction
     * get_all_history() already enforces, now also enforced on the write path.
     *
     * @return void
     */
    public function test_delete_attempts_separategroups_cannot_delete_outside_group(): void {
        global $DB;
        $instance = $this->make_instance(['grade' => 100]);
        $cm = $this->enable_separategroups($instance);
        $context = \context_module::instance($cm->id);

        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $this->course->id, 'teacher');
        $outsider = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($outsider->id, $this->course->id, 'student');

        $groupa = $this->getDataGenerator()->create_group(['courseid' => $this->course->id]);
        $this->getDataGenerator()->create_group_member(['groupid' => $groupa->id, 'userid' => $teacher->id]);
        // The outsider deliberately joins no group the teacher shares.

        $attempt = $this->add_attempt($instance, $outsider, 80);

        $affected = attempts_history_service::delete_attempts(
            [$attempt->id],
            $cm,
            $instance,
            $context,
            $teacher->id
        );

        $this->assertSame([], $affected);
        $this->assertTrue($DB->record_exists('playerwords_attempts', ['id' => $attempt->id]));
    }

    /**
     * Deleting several attempts across two students in one call returns both their
     * userids, deduplicated even when a student has more than one attempt deleted at
     * once.
     *
     * @return void
     */
    public function test_delete_attempts_bulk_returns_distinct_affected_userids(): void {
        global $DB;
        $instance = $this->make_instance(['grade' => 100]);
        $cm = get_coursemodule_from_instance('playerwords', $instance->id, 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        $studenta = $this->getDataGenerator()->create_user();
        $studentb = $this->getDataGenerator()->create_user();

        $attempta1 = $this->add_attempt($instance, $studenta, 40);
        $attempta2 = $this->add_attempt($instance, $studenta, 90);
        $attemptb = $this->add_attempt($instance, $studentb, 60);

        $affected = attempts_history_service::delete_attempts(
            [$attempta1->id, $attempta2->id, $attemptb->id],
            $cm,
            $instance,
            $context,
            $this->user->id
        );

        sort($affected);
        $expected = [(int)$studenta->id, (int)$studentb->id];
        sort($expected);
        $this->assertSame($expected, $affected);
        $this->assertSame(0, $DB->count_records('playerwords_attempts', ['playerwordsid' => $instance->id]));
    }
}
