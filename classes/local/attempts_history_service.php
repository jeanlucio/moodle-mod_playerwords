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
 * Attempt history query service for mod_playerwords.
 *
 * @package    mod_playerwords
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playerwords\local;

use context_course;

/**
 * Builds one student's own finished-round history, plus their currently computed
 * grade, for the "my attempts" page.
 */
class attempts_history_service {
    /** @var int Default rows per page for the all-students attempt report. */
    const REPORT_PERPAGE = 30;

    /** @var array<string,string> Allow-listed sortable columns for the all-students report. */
    private const SORTABLE_COLUMNS = [
        'student'       => 'studentname',
        'word'          => 'pw.word',
        'attempts'      => 'pa.attempts_used',
        'time'          => 'pa.time_used',
        'score'         => 'pa.score',
        'rankingpoints' => 'pa.rankingpoints',
        'date'          => 'pa.timefinished',
    ];

    /**
     * Returns the finished-round history and current grade for one student.
     *
     * Reads only rows matching both playerwordsid and userid — this is the sole
     * security boundary for the "my attempts" page, so the caller must always pass
     * the logged-in user's own id, never one read from the request.
     *
     * @param \stdClass $instance Activity instance record.
     * @param int $userid Student id, always the logged-in user.
     * @return array {rows, isempty, showgrade, grade, maxgrade, grademethodname, showranking}
     */
    public static function get_history(\stdClass $instance, int $userid): array {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/playerwords/lib.php');

        $sql = "SELECT pa.*, pw.word, pw.concept
                  FROM {playerwords_attempts} pa
             LEFT JOIN {playerwords_words} pw ON pw.id = pa.wordid
                 WHERE pa.playerwordsid = :instanceid
                       AND pa.userid = :userid
                       AND pa.timefinished > 0
              ORDER BY pa.timefinished ASC";
        $attemptsasc = array_values($DB->get_records_sql($sql, [
            'instanceid' => (int)$instance->id,
            'userid'     => $userid,
        ]));

        $isempty = empty($attemptsasc);
        $showranking = !empty($instance->show_ranking);

        $rows = array_map(
            fn(\stdClass $attempt): array => self::build_row($attempt, $showranking),
            array_reverse($attemptsasc)
        );

        $showgrade = !$isempty && (float)$instance->grade > 0;
        $grade = 0.0;
        if ($showgrade) {
            $grade = playerwords_calculate_user_grade($instance, $attemptsasc);
        }

        return [
            'rows'             => $rows,
            'isempty'          => $isempty,
            'showgrade'        => $showgrade,
            'grade'            => format_float($grade, 2),
            'maxgrade'         => format_float((float)$instance->grade, 2),
            'grademethodname'  => round_presenter::grademethod_name($instance),
            'showranking'      => $showranking,
        ];
    }

    /**
     * Formats one attempt record into a display row.
     *
     * @param \stdClass $attempt Attempt record, joined with word/concept.
     * @param bool $showranking Whether to include the ranking-points column.
     * @return array
     */
    private static function build_row(\stdClass $attempt, bool $showranking): array {
        $minutes = intdiv((int)$attempt->time_used, 60);
        $seconds = (int)$attempt->time_used % 60;

        $row = [
            'word'          => $attempt->concept ?: ($attempt->word ?: ''),
            'attemptsused'  => (int)$attempt->attempts_used,
            'timeused'      => sprintf('%d:%02d', $minutes, $seconds),
            'won'           => !empty($attempt->completed),
            'score'         => format_float((float)$attempt->score, 2),
            'rankingpoints' => $showranking ? format_float((float)$attempt->rankingpoints, 2) : '',
            'datefinished'  => userdate((int)$attempt->timefinished, get_string('strftimedatetime', 'langconfig')),
        ];

        if (isset($attempt->studentname)) {
            $row['student'] = $attempt->studentname;
        }

        return $row;
    }

    /**
     * Returns the manager exclusion SQL fragment and params, or empty values when nobody
     * with the manage capability holds it in this context.
     *
     * Excludes anyone who can manage the activity (editingteacher, manager) from the report,
     * the same rule ranking_service::get_ranking() applies — a teacher previewing the activity
     * should not be tracked as a player in a student-facing report either.
     *
     * @param \context $context Module context.
     * @return array{0: string, 1: array} SQL fragment (empty string if none) and its params.
     */
    private static function manager_exclusion(\context $context): array {
        $managers = get_users_by_capability($context, 'mod/playerwords:addinstance', 'u.id');
        if (empty($managers)) {
            return ['', []];
        }

        global $DB;
        [$notinsql, $notinparams] = $DB->get_in_or_equal(array_keys($managers), SQL_PARAMS_NAMED, 'mgr', false);
        return ["AND pa.userid $notinsql", $notinparams];
    }

    /**
     * Resolves the group-membership filter for the current viewer of the
     * teacher/manager-facing report — the SEPARATEGROUPS counterpart of
     * ranking_service::resolve_user_filter(), with the moodle/site:accessallgroups
     * override that a manage/report-viewing role can legitimately hold.
     *
     * Returns null when no filter is needed (every student's rows visible). Returns
     * an array of user ids — members of every group the viewer themselves belongs
     * to — when SEPARATEGROUPS is active and the viewer lacks accessallgroups.
     *
     * @param \stdClass $cm Course module record.
     * @param \context $context Module context.
     * @param int $userid Current viewer's user id.
     * @return int[]|null
     */
    private static function resolve_group_filter(\stdClass $cm, \context $context, int $userid): ?array {
        global $DB;

        $groupmode = groups_get_activity_groupmode($cm);
        if ($groupmode != SEPARATEGROUPS || has_capability('moodle/site:accessallgroups', $context, $userid)) {
            return null;
        }

        $groups = groups_get_all_groups($cm->course, $userid, $cm->groupingid);
        if (empty($groups)) {
            return [$userid];
        }

        [$sql, $params] = groups_get_members_ids_sql(
            array_keys($groups),
            context_course::instance($cm->course)
        );
        return array_map('intval', $DB->get_fieldset_sql($sql, $params));
    }

    /**
     * Returns one page of finished-round attempts across every student, for the
     * teacher/manager-facing report.
     *
     * @param \stdClass $cm Course module record.
     * @param \stdClass $instance Activity instance record.
     * @param \context $context Module context.
     * @param int $viewerid Current viewer's user id, for SEPARATEGROUPS scoping.
     * @param int $page Zero-based page number.
     * @param int $perpage Rows per page.
     * @param string $sort Sort key, must be one of the SORTABLE_COLUMNS keys.
     * @param string $dir Sort direction, 'ASC' or 'DESC'.
     * @param int $filteruserid Restrict to one student id, 0 for every student.
     * @return array {rows, isempty, total, showranking}
     */
    public static function get_all_history(
        \stdClass $cm,
        \stdClass $instance,
        \context $context,
        int $viewerid,
        int $page,
        int $perpage,
        string $sort,
        string $dir,
        int $filteruserid
    ): array {
        global $DB;

        $sortcolumn = self::SORTABLE_COLUMNS[$sort] ?? self::SORTABLE_COLUMNS['date'];
        $dir = (strtoupper($dir) === 'ASC') ? 'ASC' : 'DESC';

        $params = ['instanceid' => (int)$instance->id];
        $userwhere = '';
        if ($filteruserid > 0) {
            $userwhere = 'AND pa.userid = :filteruserid';
            $params['filteruserid'] = $filteruserid;
        }

        [$managerwhere, $managerparams] = self::manager_exclusion($context);
        $params = array_merge($params, $managerparams);

        $groupwhere = '';
        $groupfilter = self::resolve_group_filter($cm, $context, $viewerid);
        if ($groupfilter !== null) {
            [$groupinsql, $groupinparams] = $DB->get_in_or_equal($groupfilter, SQL_PARAMS_NAMED, 'grp');
            $groupwhere = "AND pa.userid $groupinsql";
            $params = array_merge($params, $groupinparams);
        }

        $fullname = $DB->sql_fullname('u.firstname', 'u.lastname');
        $wheresql = "pa.playerwordsid = :instanceid
                       AND pa.timefinished > 0
                       $userwhere
                       $managerwhere
                       $groupwhere";

        $total = $DB->count_records_sql(
            "SELECT COUNT(*)
               FROM {playerwords_attempts} pa
               JOIN {user} u ON u.id = pa.userid
              WHERE $wheresql",
            $params
        );

        $sql = "SELECT pa.*, pw.word, pw.concept, $fullname AS studentname
                  FROM {playerwords_attempts} pa
                  JOIN {user} u ON u.id = pa.userid
             LEFT JOIN {playerwords_words} pw ON pw.id = pa.wordid
                 WHERE $wheresql
              ORDER BY $sortcolumn $dir, pa.id $dir";

        $records = $DB->get_records_sql($sql, $params, $page * $perpage, $perpage);

        $showranking = !empty($instance->show_ranking);
        $rows = array_map(
            fn(\stdClass $attempt): array => self::build_row($attempt, $showranking),
            array_values($records)
        );

        return [
            'rows'        => $rows,
            'isempty'     => ($total === 0),
            'total'       => (int)$total,
            'showranking' => $showranking,
        ];
    }

    /**
     * Returns students with at least one finished attempt, for the report's filter dropdown.
     * Excludes the same manager set get_all_history() excludes from the report itself, and
     * applies the same SEPARATEGROUPS scoping so the dropdown never offers a student the
     * report itself would refuse to show.
     *
     * @param \stdClass $cm Course module record.
     * @param \stdClass $instance Activity instance record.
     * @param \context $context Module context.
     * @param int $viewerid Current viewer's user id, for SEPARATEGROUPS scoping.
     * @return \stdClass[] Objects with id and fullname, ordered by fullname.
     */
    public static function get_players_for_filter(
        \stdClass $cm,
        \stdClass $instance,
        \context $context,
        int $viewerid
    ): array {
        global $DB;

        [$managerwhere, $managerparams] = self::manager_exclusion($context);
        $params = array_merge(['instanceid' => (int)$instance->id], $managerparams);

        $groupwhere = '';
        $groupfilter = self::resolve_group_filter($cm, $context, $viewerid);
        if ($groupfilter !== null) {
            [$groupinsql, $groupinparams] = $DB->get_in_or_equal($groupfilter, SQL_PARAMS_NAMED, 'grp');
            $groupwhere = "AND pa.userid $groupinsql";
            $params = array_merge($params, $groupinparams);
        }

        $fullname = $DB->sql_fullname('u.firstname', 'u.lastname');
        $sql = "SELECT DISTINCT u.id, $fullname AS fullname
                  FROM {playerwords_attempts} pa
                  JOIN {user} u ON u.id = pa.userid
                 WHERE pa.playerwordsid = :instanceid
                       AND pa.timefinished > 0
                       $managerwhere
                       $groupwhere
              ORDER BY fullname ASC";

        return array_values($DB->get_records_sql($sql, $params));
    }
}
