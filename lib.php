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
 * Library functions for mod_playerwords.
 *
 * @package mod_playerwords
 * @copyright  2026 Jean Lúcio
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** Source type bit flag for manual words. */
define('PLAYERWORDS_SOURCE_MANUAL', 1);

/** Source type bit flag for glossary words. */
define('PLAYERWORDS_SOURCE_GLOSSARY', 2);

/** Source type bit flag for AI generated words. */
define('PLAYERWORDS_SOURCE_AI', 4);

/** Grade aggregation: highest score across all rounds. */
define('PLAYERWORDS_GRADE_HIGHEST', 1);

/** Grade aggregation: average score across all rounds. */
define('PLAYERWORDS_GRADE_AVERAGE', 2);

/** Grade aggregation: score from the first round. */
define('PLAYERWORDS_GRADE_FIRST', 3);

/** Grade aggregation: score from the last round. */
define('PLAYERWORDS_GRADE_LAST', 4);

/** Word selection mode: a random word is picked each round. */
define('PLAYERWORDS_WORDMODE_RANDOM', 1);

/** Word selection mode: the same word is used for all students on a given day. */
define('PLAYERWORDS_WORDMODE_DAILY', 2);

/**
 * Builds the source bitmask from form data.
 *
 * @param stdClass $data Form data.
 * @return int
 */
function playerwords_build_sources(stdClass $data): int {
    $sources = 0;

    if (!empty($data->source_manual)) {
        $sources |= PLAYERWORDS_SOURCE_MANUAL;
    }
    if (!empty($data->source_glossary)) {
        $sources |= PLAYERWORDS_SOURCE_GLOSSARY;
    }
    if (!empty($data->source_ai)) {
        $sources |= PLAYERWORDS_SOURCE_AI;
    }

    return $sources;
}

/**
 * Tells Moodle this plugin uses a branded icon (disables purpose recolour filter).
 *
 * @return bool
 */
function mod_playerwords_is_branded(): bool {
    return true;
}

/**
 * Creates or updates the grade item for a playerwords instance.
 *
 * @param stdClass $instance Activity instance (must have id, course, name, grade, gradepass).
 * @param mixed $grades Grade object(s), null to update item only, or 'reset' to reset grades.
 * @return int GRADE_UPDATE_OK or error constant.
 */
function playerwords_grade_item_update(stdClass $instance, mixed $grades = null): int {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    $params = [
        'itemname' => $instance->name,
        'idnumber' => $instance->cmidnumber ?? '',
    ];

    if ((int)$instance->grade > 0) {
        $params['gradetype'] = GRADE_TYPE_VALUE;
        $params['grademax']  = (float)$instance->grade;
        $params['grademin']  = 0.0;
    } else if ((int)$instance->grade < 0) {
        $params['gradetype'] = GRADE_TYPE_SCALE;
        $params['scaleid']   = -(int)$instance->grade;
    } else {
        $params['gradetype'] = GRADE_TYPE_NONE;
    }

    if (!empty($instance->gradepass)) {
        $params['gradepass'] = (float)$instance->gradepass;
    }

    if ($grades === 'reset') {
        $params['reset'] = true;
        $grades = null;
    }

    return grade_update(
        'mod/playerwords',
        $instance->course,
        'mod',
        'playerwords',
        $instance->id,
        0,
        $grades,
        $params
    );
}

/**
 * Calculates a single user's final grade from their round attempts.
 *
 * @param stdClass $instance Activity instance.
 * @param array $attempts Attempt records for this user, ordered by timecreated ASC.
 * @return float
 */
function playerwords_calculate_user_grade(stdClass $instance, array $attempts): float {
    if (empty($attempts)) {
        return 0.0;
    }

    $scores = array_map(fn($a) => (float)$a->score, $attempts);
    $grademethod = (int)($instance->grademethod ?? PLAYERWORDS_GRADE_HIGHEST);

    switch ($grademethod) {
        case PLAYERWORDS_GRADE_AVERAGE:
            return array_sum($scores) / count($scores);
        case PLAYERWORDS_GRADE_FIRST:
            return $scores[array_key_first($scores)];
        case PLAYERWORDS_GRADE_LAST:
            return $scores[array_key_last($scores)];
        case PLAYERWORDS_GRADE_HIGHEST:
        default:
            return max($scores);
    }
}

/**
 * Updates gradebook grades for one or all users of a playerwords instance.
 *
 * @param stdClass $instance Activity instance.
 * @param int $userid User id, 0 to update all users.
 * @return void
 */
function playerwords_update_grades(stdClass $instance, int $userid = 0): void {
    global $CFG, $DB;
    require_once($CFG->libdir . '/gradelib.php');

    $sql = "SELECT a.id, a.userid, a.score, a.timecreated
              FROM {playerwords_attempts} a
             WHERE a.playerwordsid = :instanceid";
    $params = ['instanceid' => $instance->id];

    if ($userid > 0) {
        $sql .= ' AND a.userid = :userid';
        $params['userid'] = $userid;
    }

    $sql .= ' ORDER BY a.timecreated ASC';
    $attempts = $DB->get_records_sql($sql, $params);

    if (empty($attempts)) {
        playerwords_grade_item_update($instance);
        return;
    }

    $userattempts = [];
    foreach ($attempts as $attempt) {
        $userattempts[$attempt->userid][] = $attempt;
    }

    $grades = [];
    foreach ($userattempts as $uid => $userattemptlist) {
        $grade = new stdClass();
        $grade->userid = $uid;
        $grade->rawgrade = playerwords_calculate_user_grade($instance, $userattemptlist);
        $grades[$uid] = $grade;
    }

    playerwords_grade_item_update($instance, $grades);
}

/**
 * Add a new playerwords instance.
 *
 * @param stdClass $data Form data.
 * @return int New instance id.
 */
function playerwords_add_instance(stdClass $data): int {
    global $DB;

    if (empty($data->completionattemptsenabled)) {
        $data->completionattempts = 0;
    }
    unset($data->completionattemptsenabled);

    $data->gradepass = isset($data->gradepass) ? (float)$data->gradepass : 0.0;

    $data->sources = playerwords_build_sources($data);
    unset($data->source_manual, $data->source_glossary, $data->source_ai);
    $multipliers = ['minutes' => 60, 'hours' => 3600, 'days' => 86400];
    $unit        = $data->cooldown_unit ?? 'days';
    $amount      = (int)($data->cooldown_amount ?? 0);
    $data->cooldown_seconds = $amount * ($multipliers[$unit] ?? 86400);
    unset($data->cooldown_amount, $data->cooldown_unit);
    $data->timecreated  = time();
    $data->timemodified = time();
    $data->id = $DB->insert_record('playerwords', $data);
    playerwords_grade_item_update($data);
    return $data->id;
}

/**
 * Update an existing playerwords instance.
 *
 * @param stdClass $data Form data.
 * @return bool True on success.
 */
function playerwords_update_instance(stdClass $data): bool {
    global $DB;

    if (empty($data->completionattemptsenabled)) {
        $data->completionattempts = 0;
    }
    unset($data->completionattemptsenabled);

    $data->gradepass = isset($data->gradepass) ? (float)$data->gradepass : 0.0;

    $data->sources = playerwords_build_sources($data);
    unset($data->source_manual, $data->source_glossary, $data->source_ai);
    $multipliers = ['minutes' => 60, 'hours' => 3600, 'days' => 86400];
    $unit        = $data->cooldown_unit ?? 'days';
    $amount      = (int)($data->cooldown_amount ?? 0);
    $data->cooldown_seconds = $amount * ($multipliers[$unit] ?? 86400);
    unset($data->cooldown_amount, $data->cooldown_unit);
    $data->id           = $data->instance;
    $data->timemodified = time();
    $result = $DB->update_record('playerwords', $data);
    playerwords_grade_item_update($data);
    return $result;
}

/**
 * Delete a playerwords instance.
 *
 * @param int $id Instance id.
 * @return bool True on success.
 */
function playerwords_delete_instance(int $id): bool {
    global $CFG, $DB;
    require_once($CFG->libdir . '/gradelib.php');

    $instance = $DB->get_record('playerwords', ['id' => $id], 'id, course', MUST_EXIST);
    grade_update(
        'mod/playerwords',
        $instance->course,
        'mod',
        'playerwords',
        $id,
        0,
        null,
        ['deleted' => 1]
    );
    $DB->delete_records('playerwords_attempts', ['playerwordsid' => $id]);
    $DB->delete_records('playerwords_words', ['playerwordsid' => $id]);
    $DB->delete_records('playerwords', ['id' => $id]);
    return true;
}

/**
 * Return the features this module supports.
 *
 * @param string $feature FEATURE_xx constant for requested feature.
 * @return mixed True if module supports feature, null if doesn't know.
 */
function playerwords_supports(string $feature): mixed {
    switch ($feature) {
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return true;
        case FEATURE_MOD_PURPOSE:
            return MOD_PURPOSE_INTERACTIVECONTENT;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_COMPLETION_HAS_RULES:
            return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;
        default:
            return null;
    }
}

/**
 * Checks if user completes the activity according to custom rules.
 *
 * @param stdClass $course Course data.
 * @param stdClass $cm Course-module data.
 * @param int $userid User id.
 * @param bool $type Type of aggregation for completion requirements.
 * @return bool
 */
function playerwords_get_completion_state(
    stdClass $course,
    stdClass $cm,
    int $userid,
    bool $type
): bool {
    global $DB;

    $playerwords = $DB->get_record(
        'playerwords',
        ['id' => $cm->instance],
        'id, completionattempts',
        MUST_EXIST
    );

    if ((int)$playerwords->completionattempts === 0) {
        $attemptsok = null;
    } else {
        $attemptscount = $DB->count_records(
            'playerwords_attempts',
            [
                'playerwordsid' => $playerwords->id,
                'userid' => $userid,
            ]
        );

        $attemptsok = $attemptscount >= (int)$playerwords->completionattempts;
    }

    $activeconditions = [];
    if ($attemptsok !== null) {
        $activeconditions[] = $attemptsok;
    }

    if ($activeconditions === []) {
        return $type;
    }

    if ($type) {
        return !in_array(false, $activeconditions, true);
    }

    return in_array(
        true,
        $activeconditions,
        true
    );
}
