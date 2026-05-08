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
     * @param \stdClass $instance Activity instance.
     * @return \stdClass|null
     */
    public static function pick_round_word(\stdClass $instance): ?\stdClass {
        global $CFG;
        require_once($CFG->dirroot . '/mod/playerwords/lib.php');

        $candidates = self::get_candidate_words($instance);
        if ($candidates === []) {
            return null;
        }

        $wordmode = (int)($instance->wordmode ?? PLAYERWORDS_WORDMODE_RANDOM);

        if ($wordmode === PLAYERWORDS_WORDMODE_DAILY) {
            usort($candidates, fn($a, $b) => $a->id <=> $b->id);
            $daynumber = (int)date('Ymd');
            $index = ($daynumber + (int)$instance->id) % count($candidates);
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
