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
 * @package    mod_playerwords
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** Source type bit flag for manual words. */
define('PLAYERWORDS_SOURCE_MANUAL', 1);

/** Source type bit flag for glossary words. */
define('PLAYERWORDS_SOURCE_GLOSSARY', 2);

/** Grade aggregation: highest score across all rounds. */
define('PLAYERWORDS_GRADE_HIGHEST', 1);

/** Grade aggregation: average score across all rounds. */
define('PLAYERWORDS_GRADE_AVERAGE', 2);

/** Grade aggregation: score from the first round. */
define('PLAYERWORDS_GRADE_FIRST', 3);

/** Grade aggregation: score from the last round. */
define('PLAYERWORDS_GRADE_LAST', 4);

/** Grade aggregation: average over all required rounds (uses max_rounds as denominator). */
define('PLAYERWORDS_GRADE_AVERAGE_ALL', 5);

/** Per-round scoring: full grade if the round was won, zero otherwise. */
define('PLAYERWORDS_SCORING_BINARY', 1);

/** Per-round scoring: proportional to attempts spared out of max_attempts. */
define('PLAYERWORDS_SCORING_LINEAR', 2);

/**
 * Fixed points base the ranking total is scored against — deliberately independent of
 * the activity's own configured grade (which may be 0, "No grade"), so ranking stays
 * meaningful even for an ungraded activity. See gameplay_service::compute_points().
 */
define('PLAYERWORDS_RANKING_BASE_POINTS', 100);

/** Word selection mode: a random word is picked each round. */
define('PLAYERWORDS_WORDMODE_RANDOM', 1);

/** Word selection mode: all students receive the same words in the same order per round number. */
define('PLAYERWORDS_WORDMODE_SHARED', 2);

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
 * Returns the available grading method options, keyed by their PLAYERWORDS_GRADE_* constant.
 *
 * Single source of truth shared by the settings form dropdown and the student-facing
 * grading method label, so both always describe the same five methods identically.
 *
 * @return array<int, string>
 */
function playerwords_get_grademethod_options(): array {
    return [
        PLAYERWORDS_GRADE_HIGHEST     => get_string('grademethod_highest', 'mod_playerwords'),
        PLAYERWORDS_GRADE_AVERAGE     => get_string('grademethod_average', 'mod_playerwords'),
        PLAYERWORDS_GRADE_FIRST       => get_string('grademethod_first', 'mod_playerwords'),
        PLAYERWORDS_GRADE_LAST        => get_string('grademethod_last', 'mod_playerwords'),
        PLAYERWORDS_GRADE_AVERAGE_ALL => get_string('grademethod_average_all', 'mod_playerwords'),
    ];
}

/**
 * Returns the available per-round scoring mode options, keyed by their
 * PLAYERWORDS_SCORING_* constant.
 *
 * Shared by the grade-scoring and ranking-scoring settings form dropdowns — they offer
 * the same two choices, computed by the same formula, just feeding different columns.
 *
 * @return array<int, string>
 */
function playerwords_get_scoring_mode_options(): array {
    return [
        PLAYERWORDS_SCORING_BINARY => get_string('scoringmode_binary', 'mod_playerwords'),
        PLAYERWORDS_SCORING_LINEAR => get_string('scoringmode_linear', 'mod_playerwords'),
    ];
}

/**
 * Calculates a single user's final grade from their round attempts.
 *
 * @param stdClass $instance Activity instance.
 * @param array $attempts Finished attempt records for this user, ordered by timefinished ASC.
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
        case PLAYERWORDS_GRADE_AVERAGE_ALL:
            $totalrounds = (int)($instance->max_rounds ?? 0);
            if ($totalrounds <= 0) {
                return array_sum($scores) / count($scores);
            }
            return array_sum($scores) / $totalrounds;
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

    // The timefinished > 0 filter excludes rounds that are still an open reservation
    // (either genuinely in progress right now, or abandoned without ever finishing) —
    // neither has a real outcome yet, so counting either would incorrectly drag a
    // "highest" or "average" grade toward a 0 the student never actually earned.
    $sql = "SELECT a.id, a.userid, a.score, a.timefinished
              FROM {playerwords_attempts} a
             WHERE a.playerwordsid = :instanceid AND a.timefinished > 0";
    $params = ['instanceid' => $instance->id];

    if ($userid > 0) {
        $sql .= ' AND a.userid = :userid';
        $params['userid'] = $userid;
    }

    $sql .= ' ORDER BY a.timefinished ASC';
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
    unset($data->source_manual, $data->source_glossary);
    $multipliers = ['minutes' => 60, 'hours' => 3600, 'days' => 86400];
    $unit        = $data->cooldown_unit ?? 'days';
    $amount      = (int)($data->cooldown_amount ?? 0);
    $data->cooldown_seconds = $amount * ($multipliers[$unit] ?? 86400);
    unset($data->cooldown_amount, $data->cooldown_unit);
    $data->timer_seconds = max(0, (int)($data->timer_minutes ?? 0)) * 60;
    unset($data->timer_minutes);
    $data->timecreated  = time();
    $data->timemodified = time();
    $data->id = $DB->insert_record('playerwords', $data);
    playerwords_grade_item_update($data);
    \mod_playerwords\local\words_repository::sync_glossary_words($data);
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
    unset($data->source_manual, $data->source_glossary);
    $multipliers = ['minutes' => 60, 'hours' => 3600, 'days' => 86400];
    $unit        = $data->cooldown_unit ?? 'days';
    $amount      = (int)($data->cooldown_amount ?? 0);
    $data->cooldown_seconds = $amount * ($multipliers[$unit] ?? 86400);
    unset($data->cooldown_amount, $data->cooldown_unit);
    $data->timer_seconds = max(0, (int)($data->timer_minutes ?? 0)) * 60;
    unset($data->timer_minutes);
    $data->id           = $data->instance;
    $data->timemodified = time();
    $result = $DB->update_record('playerwords', $data);
    playerwords_grade_item_update($data);
    \mod_playerwords\local\words_repository::sync_glossary_words($data);
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
 * @return mixed True if module supports feature, a purpose string for
 *     FEATURE_MOD_PURPOSE/FEATURE_MOD_OTHERPURPOSE, null if doesn't know.
 */
function playerwords_supports(string $feature): mixed {
    // FEATURE_MOD_OTHERPURPOSE only exists from Moodle 5.1 onwards (MDL-85598); this
    // plugin also targets Moodle 4.5, where referencing the undefined constant as a
    // switch case label would still be a fatal error, guard or not — checked ahead of
    // the switch instead. Lets the activity chooser list this activity under both its
    // primary purpose (interactive content) and this secondary one (assessment).
    if (defined('FEATURE_MOD_OTHERPURPOSE') && $feature === FEATURE_MOD_OTHERPURPOSE) {
        return MOD_PURPOSE_ASSESSMENT;
    }

    switch ($feature) {
        case FEATURE_GROUPS:
            return true;
        case FEATURE_GROUPINGS:
            return true;
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
 * Populates the course module info object with custom completion rule data.
 *
 * Called by Moodle when building cm_info. Stores the required attempt count in
 * customdata so activity_custom_completion::get_available_custom_rules() can
 * determine whether the rule is enabled for this instance, and so
 * mod_playerwords\completion\custom_completion::get_state() can evaluate it.
 *
 * @param stdClass $coursemodule The raw course_modules row (id, instance, …).
 * @return cached_cm_info|false A populated info object, or false on failure.
 */
function playerwords_get_coursemodule_info(stdClass $coursemodule): cached_cm_info|false {
    global $DB;

    $fields = 'id, name, completionattempts';
    $playerwords = $DB->get_record('playerwords', ['id' => $coursemodule->instance], $fields);
    if (!$playerwords) {
        return false;
    }

    $info = new cached_cm_info();
    $info->name = $playerwords->name;

    if ($coursemodule->completion == COMPLETION_TRACKING_AUTOMATIC) {
        $info->customdata['customcompletionrules']['completionattempts'] = (int)$playerwords->completionattempts;
    }

    return $info;
}

/**
 * Describes the active custom completion rules.
 *
 * @param stdClass|cm_info $cm The course module info.
 * @return array An array of active completion rule descriptions.
 */
function playerwords_get_completion_active_rule_descriptions(stdClass|cm_info $cm): array {
    $descriptions = [];

    $rules = $cm->customdata['customcompletionrules'] ?? [];
    if (!empty($rules['completionattempts'])) {
        $descriptions[] = get_string('completionattempts_desc', 'mod_playerwords', $rules['completionattempts']);
    }

    return $descriptions;
}

/**
 * Adds the PlayerWords section to the course reset form.
 *
 * @param MoodleQuickForm $mform The course reset form.
 * @return void
 */
function playerwords_reset_course_form_definition(MoodleQuickForm $mform): void {
    $mform->addElement('header', 'playerwordsheader', get_string('modulenameplural', 'mod_playerwords'));
    $mform->addElement('advcheckbox', 'reset_playerwords_attempts', get_string('resetplayerwordsattempts', 'mod_playerwords'));
}

/**
 * Returns the default values for the PlayerWords course reset form.
 *
 * @param stdClass $course The course being reset.
 * @return array
 */
function playerwords_reset_course_form_defaults(stdClass $course): array {
    return ['reset_playerwords_attempts' => 1];
}

/**
 * Removes student round attempts and recalculates grades when a course is reset.
 *
 * @param stdClass $data Reset form data, must contain courseid.
 * @return array Status messages for the course reset report.
 */
function playerwords_reset_userdata(stdClass $data): array {
    global $DB;

    $status = [];
    if (empty($data->reset_playerwords_attempts)) {
        return $status;
    }

    $instances = $DB->get_records('playerwords', ['course' => $data->courseid]);
    if (empty($instances)) {
        return $status;
    }

    [$insql, $inparams] = $DB->get_in_or_equal(array_keys($instances), SQL_PARAMS_NAMED, 'pid');
    $DB->delete_records_select('playerwords_attempts', "playerwordsid $insql", $inparams);

    foreach ($instances as $instance) {
        playerwords_grade_item_update($instance, 'reset');
    }

    $status[] = [
        'component' => get_string('modulenameplural', 'mod_playerwords'),
        'item'      => get_string('resetplayerwordsattempts', 'mod_playerwords'),
        'error'     => false,
    ];

    return $status;
}

/**
 * Reports the total XP potentially earnable through this course's win-grant configuration,
 * for block_playerhud's "Total XP no jogo" ceiling estimate.
 *
 * Discovered automatically by block_playerhud via get_plugins_with_function() — see
 * \block_playerhud\local\analytics::game_xp_totals(). Only called when block_playerhud is
 * active, so \block_playerhud\local\external_items is always available here. An unlimited
 * activity (max_rounds = 0) contributes nothing, mirroring the same anti-farming rule applied
 * to the real grant in round_service::submit_guess().
 *
 * @param int $blockinstanceid PlayerHUD block instance ID to report potential XP for.
 * @return array Rows shaped like block_playerhud's own item/quest breakdown entries.
 */
function playerwords_playerhud_grant_potential(int $blockinstanceid): array {
    global $DB;

    $courseid = $DB->get_field_sql(
        "SELECT ctx.instanceid
           FROM {block_instances} bi
           JOIN {context} ctx ON bi.parentcontextid = ctx.id
          WHERE bi.id = :biid AND ctx.contextlevel = :clevel",
        ['biid' => $blockinstanceid, 'clevel' => CONTEXT_COURSE]
    );

    if (!$courseid) {
        return [];
    }

    $instances = $DB->get_records_select(
        'playerwords',
        'course = :courseid AND hud_win_grant_item > 0 AND max_rounds > 0',
        ['courseid' => $courseid],
        '',
        'id, name, hud_win_grant_item, hud_win_grant_qty, max_rounds'
    );

    $itemids = array_map(fn(\stdClass $instance): int => (int)$instance->hud_win_grant_item, $instances);
    $xpbyitem = \mod_playerwords\local\hud_service::get_xp_for_items($blockinstanceid, $itemids);

    $rows = [];
    foreach ($instances as $instance) {
        $itemid = (int)$instance->hud_win_grant_item;
        $itemxp = $xpbyitem[$itemid] ?? 0;
        if ($itemxp <= 0) {
            // Zero-XP item, or the item does not belong to this block instance (e.g. stale
            // config copied from another course) — either way, nothing to add here.
            continue;
        }

        $qty = max(1, (int)$instance->hud_win_grant_qty);

        $rows[] = [
            'name'       => format_string($instance->name),
            'xp_each'    => $itemxp * $qty,
            'drop_count' => 0,
            'total_uses' => (int)$instance->max_rounds,
            'xp_total'   => $itemxp * $qty * (int)$instance->max_rounds,
            'is_quest'   => false,
            'infinite'   => false,
        ];
    }

    return $rows;
}
