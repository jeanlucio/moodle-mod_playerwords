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
    if (!empty($data->completionmingradeenabled) && !empty($data->completionmingrade)) {
        $data->gradepass = (float)$data->completionmingrade;
    } else {
        $data->gradepass = 0;
    }
    unset($data->completionattemptsenabled, $data->completionmingradeenabled, $data->completionmingrade);

    $data->sources = playerwords_build_sources($data);
    unset($data->source_manual, $data->source_glossary, $data->source_ai);
    $data->timecreated  = time();
    $data->timemodified = time();
    return $DB->insert_record('playerwords', $data);
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
    if (!empty($data->completionmingradeenabled) && !empty($data->completionmingrade)) {
        $data->gradepass = (float)$data->completionmingrade;
    } else {
        $data->gradepass = 0;
    }
    unset($data->completionattemptsenabled, $data->completionmingradeenabled, $data->completionmingrade);

    $data->sources = playerwords_build_sources($data);
    unset($data->source_manual, $data->source_glossary, $data->source_ai);
    $data->id           = $data->instance;
    $data->timemodified = time();
    return $DB->update_record('playerwords', $data);
}

/**
 * Delete a playerwords instance.
 *
 * @param int $id Instance id.
 * @return bool True on success.
 */
function playerwords_delete_instance(int $id): bool {
    global $DB;
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
            return MOD_PURPOSE_ASSESSMENT;
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
        'id, completionattempts, gradepass',
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

    $gradeok = null;
    if ((float)$playerwords->gradepass > 0) {
        $maxscore = $DB->get_field_sql(
            "SELECT MAX(a.score)
               FROM {playerwords_attempts} a
              WHERE a.playerwordsid = :playerwordsid
                AND a.userid = :userid",
            [
                'playerwordsid' => $playerwords->id,
                'userid' => $userid,
            ]
        );
        $gradeok = ((float)$maxscore) >= (float)$playerwords->gradepass;
    }

    $activeconditions = [];
    if ($attemptsok !== null) {
        $activeconditions[] = $attemptsok;
    }
    if ($gradeok !== null) {
        $activeconditions[] = $gradeok;
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
