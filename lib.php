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
 * @copyright 2026 Jean Lúcio
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Source type bit flag for manual words.
 */
const PLAYERWORDS_SOURCE_MANUAL = 1;

/**
 * Source type bit flag for glossary words.
 */
const PLAYERWORDS_SOURCE_GLOSSARY = 2;

/**
 * Source type bit flag for AI generated words.
 */
const PLAYERWORDS_SOURCE_AI = 4;

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
        case FEATURE_BACKUP_MOODLE2:
            return true;
        default:
            return null;
    }
}
