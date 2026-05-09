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
 * Data access layer for playerwords words.
 *
 * @package mod_playerwords
 * @copyright  2026 Jean Lúcio
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playerwords\local;

use core_text;

/**
 * Repository for words used by the activity.
 */
class words_repository {
    /**
     * Returns approved words that match configured length.
     *
     * @param \stdClass $instance Activity instance.
     * @return array
     */
    public static function get_candidate_words(\stdClass $instance): array {
        global $DB;

        $records = $DB->get_records_select(
            'playerwords_words',
            'playerwordsid = :playerwordsid AND approved = :approved',
            [
                'playerwordsid' => $instance->id,
                'approved' => 1,
            ],
            '',
            'id, word, hint'
        );

        $candidates = [];
        foreach ($records as $record) {
            $wordlength = core_text::strlen(trim($record->word));
            if ($wordlength < (int)$instance->min_length || $wordlength > (int)$instance->max_length) {
                continue;
            }
            $candidates[] = $record;
        }

        return $candidates;
    }

    /**
     * Picks one word for a new round according to the configured word mode.
     *
     * In WORDMODE_SHARED mode all students receive the same word for each round
     * number: round N uses index (completedround + instanceid) % total, cycling
     * silently when the word list is exhausted.
     *
     * @param \stdClass $instance Activity instance.
     * @param int $completedround Number of rounds the student has already completed.
     * @return \stdClass|null
     */
    public static function pick_round_word(\stdClass $instance, int $completedround = 0): ?\stdClass {
        global $CFG;
        require_once($CFG->dirroot . '/mod/playerwords/lib.php');

        $candidates = self::get_candidate_words($instance);
        if ($candidates === []) {
            return null;
        }

        $wordmode = (int)($instance->wordmode ?? PLAYERWORDS_WORDMODE_RANDOM);

        if ($wordmode === PLAYERWORDS_WORDMODE_SHARED) {
            $instanceseed = (int)$instance->id;
            usort($candidates, function ($a, $b) use ($instanceseed) {
                return crc32($instanceseed . '_' . $a->id) <=> crc32($instanceseed . '_' . $b->id);
            });
            $index = $completedround % count($candidates);
            return $candidates[$index];
        }

        $index = random_int(0, count($candidates) - 1);
        return $candidates[$index];
    }

    /**
     * Gets one approved word by id and activity.
     *
     * @param int $wordid Word id.
     * @param int $instanceid Activity instance id.
     * @return \stdClass|null
     */
    public static function get_approved_word_by_id(int $wordid, int $instanceid): ?\stdClass {
        global $DB;

        $word = $DB->get_record(
            'playerwords_words',
            [
                'id' => $wordid,
                'playerwordsid' => $instanceid,
                'approved' => 1,
            ],
            'id, word, hint',
            IGNORE_MISSING
        );

        return $word ?: null;
    }

    /**
     * Inserts one manual word as approved.
     *
     * @param int $instanceid Activity instance id.
     * @param int $userid User id.
     * @param string $word Word text.
     * @param string $hint Optional hint.
     * @return void
     */
    public static function add_manual_word(int $instanceid, int $userid, string $word, string $hint): void {
        global $DB;

        $record = (object)[
            'playerwordsid' => $instanceid,
            'word' => trim($word),
            'hint' => trim($hint),
            'source' => 'manual',
            'approved' => 1,
            'timecreated' => time(),
            'addedby' => $userid,
        ];
        $DB->insert_record('playerwords_words', $record);
    }

    /**
     * Imports approved glossary entries into the word pool for a given activity instance.
     *
     * Existing words (matched case-insensitively by concept) have their hint updated.
     * New words are inserted as approved. Words outside the configured length bounds are skipped.
     *
     * @param \stdClass $instance Activity instance.
     * @return int Number of new words imported.
     */
    public static function sync_glossary_words(\stdClass $instance): int {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/playerwords/lib.php');

        if (!((int)$instance->sources & PLAYERWORDS_SOURCE_GLOSSARY)) {
            return 0;
        }

        $glossaryid = (int)($instance->glossaryid ?? 0);
        if ($glossaryid > 0) {
            $glossaryids = [$glossaryid];
        } else {
            $glossaryids = $DB->get_fieldset_select(
                'glossary',
                'id',
                'course = :course',
                ['course' => $instance->course]
            );
            if (empty($glossaryids)) {
                return 0;
            }
        }

        [$insql, $inparams] = $DB->get_in_or_equal($glossaryids, SQL_PARAMS_NAMED, 'gid');
        $entries = $DB->get_records_sql(
            "SELECT ge.id, ge.concept, ge.definition"
            . " FROM {glossary_entries} ge"
            . " WHERE ge.glossaryid $insql AND ge.approved = 1",
            $inparams
        );

        $existing = $DB->get_records_select(
            'playerwords_words',
            'playerwordsid = :pid AND source = :source',
            ['pid' => $instance->id, 'source' => 'glossary'],
            '',
            'id, word'
        );
        $existingmap = [];
        foreach ($existing as $rec) {
            $existingmap[core_text::strtolower($rec->word)] = $rec->id;
        }

        $imported = 0;
        foreach ($entries as $entry) {
            $concept = trim($entry->concept);
            if ($concept === '') {
                continue;
            }
            $wordlength = core_text::strlen($concept);
            if ($wordlength < (int)$instance->min_length || $wordlength > (int)$instance->max_length) {
                continue;
            }
            $hint = trim(html_entity_decode(strip_tags($entry->definition), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $key = core_text::strtolower($concept);
            if (isset($existingmap[$key])) {
                $DB->set_field('playerwords_words', 'hint', $hint, ['id' => $existingmap[$key]]);
            } else {
                $DB->insert_record('playerwords_words', (object)[
                    'playerwordsid' => $instance->id,
                    'word'          => $concept,
                    'hint'          => $hint,
                    'source'        => 'glossary',
                    'approved'      => 1,
                    'timecreated'   => time(),
                    'addedby'       => 0,
                ]);
                $existingmap[$key] = true;
                $imported++;
            }
        }

        return $imported;
    }

    /**
     * Returns latest words for teacher preview.
     *
     * @param int $instanceid Activity instance id.
     * @param int $limit Number of records.
     * @return array
     */
    public static function get_recent_words(int $instanceid, int $limit = 10): array {
        global $DB;

        return $DB->get_records(
            'playerwords_words',
            ['playerwordsid' => $instanceid],
            'id DESC',
            'id, word, source, approved',
            0,
            $limit
        );
    }
}
