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

/**
 * Builds one student's own finished-round history, plus their currently computed
 * grade, for the "my attempts" page.
 */
class attempts_history_service {
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

        return [
            'word'          => $attempt->concept ?: ($attempt->word ?: ''),
            'attemptsused'  => (int)$attempt->attempts_used,
            'timeused'      => sprintf('%d:%02d', $minutes, $seconds),
            'won'           => !empty($attempt->completed),
            'score'         => format_float((float)$attempt->score, 2),
            'rankingpoints' => $showranking ? format_float((float)$attempt->rankingpoints, 2) : '',
            'datefinished'  => userdate((int)$attempt->timefinished, get_string('strftimedatetime', 'langconfig')),
        ];
    }
}
