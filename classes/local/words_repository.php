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
     * Splits a glossary concept into individual candidate words, ignoring configured stopwords.
     *
     * Single-word concepts are returned as-is. For multi-word concepts each
     * non-stopword token becomes a separate candidate. If all tokens are stopwords,
     * or if no stopwords are configured, every token is returned.
     *
     * @param string $concept Raw concept string from a glossary entry.
     * @return string[]
     */
    private static function extract_candidate_words(string $concept): array {
        $tokens = preg_split('/\s+/u', $concept, -1, PREG_SPLIT_NO_EMPTY);
        $tokens = array_values(array_filter($tokens, fn($t) => (bool)preg_match('/^[\p{L}]+$/u', $t)));
        if ($tokens === []) {
            return [];
        }
        if (count($tokens) === 1) {
            return $tokens;
        }
        $raw = (string)(get_config('mod_playerwords', 'glossarystopwords') ?? '');
        $stopwords = [];
        if ($raw !== '') {
            foreach (explode(',', $raw) as $w) {
                $w = core_text::strtolower(trim($w));
                if ($w !== '') {
                    $stopwords[] = $w;
                }
            }
        }
        if (empty($stopwords)) {
            return $tokens;
        }
        $filtered = array_values(array_filter(
            $tokens,
            fn($t) => !in_array(core_text::strtolower($t), $stopwords, true)
        ));
        return $filtered !== [] ? $filtered : $tokens;
    }

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
            'id, word, hint, concept'
        );

        $candidates = [];
        foreach ($records as $record) {
            $word = trim($record->word);
            $wordlength = core_text::strlen($word);
            if ($wordlength < (int)$instance->min_length || $wordlength > (int)$instance->max_length) {
                continue;
            }
            if (!preg_match('/^[\p{L}]+$/u', $word)) {
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
            'id, word, hint, concept',
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
            'concept' => trim($word),
            'hint' => trim($hint),
            'source' => 'manual',
            'approved' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
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
            "SELECT ge.id, ge.concept, ge.definition, ge.glossaryid"
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
            $hint = trim(html_entity_decode(strip_tags($entry->definition), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $words = self::extract_candidate_words($concept);

            foreach ($words as $word) {
                $key = core_text::strtolower($word);
                if (isset($existingmap[$key])) {
                    if ($existingmap[$key] !== true) {
                        $DB->update_record('playerwords_words', (object)[
                            'id'           => $existingmap[$key],
                            'hint'         => $hint,
                            'concept'      => $concept,
                            'glossaryid'   => (int)$entry->glossaryid,
                            'timemodified' => time(),
                        ]);
                        $existingmap[$key] = true;
                    }
                } else {
                    $DB->insert_record('playerwords_words', (object)[
                        'playerwordsid' => $instance->id,
                        'word'          => $word,
                        'concept'       => $concept,
                        'hint'          => $hint,
                        'source'        => 'glossary',
                        'glossaryid'    => (int)$entry->glossaryid,
                        'approved'      => 1,
                        'timecreated'   => time(),
                        'timemodified'  => time(),
                        'addedby'       => 0,
                    ]);
                    $existingmap[$key] = true;
                    $imported++;
                }
            }
        }

        $orphanids = [];
        foreach ($existingmap as $val) {
            if ($val !== true) {
                $orphanids[] = $val;
            }
        }
        if (!empty($orphanids)) {
            [$delsql, $delparams] = $DB->get_in_or_equal($orphanids, SQL_PARAMS_NAMED, 'del');
            $DB->delete_records_select('playerwords_words', "id $delsql", $delparams);
        }

        return $imported;
    }

    /**
     * Gets one word by id and activity, regardless of approval status.
     *
     * @param int $wordid Word id.
     * @param int $instanceid Activity instance id.
     * @return \stdClass|null
     */
    public static function get_word_by_id(int $wordid, int $instanceid): ?\stdClass {
        global $DB;
        $word = $DB->get_record_sql(
            "SELECT id, word, hint, source, approved
               FROM {playerwords_words}
              WHERE id = :id AND playerwordsid = :iid",
            ['id' => $wordid, 'iid' => $instanceid],
            IGNORE_MISSING
        );
        return $word ?: null;
    }

    /**
     * Updates word text and hint for an entry that belongs to the given activity.
     *
     * @param int $wordid Word id.
     * @param int $instanceid Activity instance id.
     * @param string $word New word text.
     * @param string $hint New hint text.
     * @return bool True if the record was found and updated.
     */
    public static function update_word(int $wordid, int $instanceid, string $word, string $hint): bool {
        global $DB;
        $existing = $DB->get_record_sql(
            "SELECT id FROM {playerwords_words} WHERE id = :id AND playerwordsid = :iid",
            ['id' => $wordid, 'iid' => $instanceid],
            IGNORE_MISSING
        );
        if (!$existing) {
            return false;
        }
        return $DB->update_record('playerwords_words', (object)[
            'id'           => $wordid,
            'word'         => trim($word),
            'concept'      => trim($word),
            'hint'         => trim($hint),
            'timemodified' => time(),
        ]);
    }

    /**
     * Bulk-deletes words that belong to the given activity instance.
     *
     * @param int[] $wordids Word ids to delete.
     * @param int $instanceid Activity instance id.
     * @return void
     */
    public static function delete_words_bulk(array $wordids, int $instanceid): void {
        global $DB;
        if (empty($wordids)) {
            return;
        }
        [$insql, $inparams] = $DB->get_in_or_equal($wordids, SQL_PARAMS_NAMED, 'wid');
        $inparams['instanceid'] = $instanceid;
        $DB->delete_records_select(
            'playerwords_words',
            "id $insql AND playerwordsid = :instanceid",
            $inparams
        );
    }

    /**
     * Deletes one word from a given activity instance.
     *
     * @param int $wordid Word id.
     * @param int $instanceid Activity instance id.
     * @return bool
     */
    public static function delete_word(int $wordid, int $instanceid): bool {
        global $DB;
        return $DB->delete_records('playerwords_words', ['id' => $wordid, 'playerwordsid' => $instanceid]);
    }

    /**
     * Inserts one AI-generated word as pending approval.
     *
     * @param int $instanceid Activity instance id.
     * @param int $userid User id.
     * @param string $word Word text.
     * @param string $hint Optional hint or definition.
     * @return void
     */
    public static function add_ai_word(int $instanceid, int $userid, string $word, string $hint): void {
        global $DB;

        $record = (object)[
            'playerwordsid' => $instanceid,
            'word' => trim($word),
            'concept' => trim($word),
            'hint' => trim($hint),
            'source' => 'ai',
            'approved' => 0,
            'timecreated' => time(),
            'timemodified' => time(),
            'addedby' => $userid,
        ];
        $DB->insert_record('playerwords_words', $record);
    }

    /**
     * Bulk-approves words that belong to the given activity instance.
     *
     * @param int[] $wordids Word ids to approve.
     * @param int $instanceid Activity instance id.
     * @return void
     */
    public static function approve_words_bulk(array $wordids, int $instanceid): void {
        global $DB;
        if (empty($wordids)) {
            return;
        }
        [$insql, $inparams] = $DB->get_in_or_equal($wordids, SQL_PARAMS_NAMED, 'wid');
        $inparams['instanceid'] = $instanceid;
        $condition = "id $insql AND playerwordsid = :instanceid";
        $DB->set_field_select('playerwords_words', 'approved', 1, $condition, $inparams);
        $DB->set_field_select('playerwords_words', 'timemodified', time(), $condition, $inparams);
    }

    /**
     * Returns words for the teacher word pool, ordered by the given column.
     *
     * Both $sort and $dir must be validated by the caller against an allow-list
     * before being passed here.
     *
     * @param int $instanceid Activity instance id.
     * @param int $limit Maximum number of records (0 = unlimited).
     * @param string $sort Column name to sort by.
     * @param string $dir Sort direction: 'ASC' or 'DESC'.
     * @return array
     */
    public static function get_recent_words(
        int $instanceid,
        int $limit = 0,
        string $sort = 'id',
        string $dir = 'DESC'
    ): array {
        global $DB;
        $sql = "SELECT w.id, w.word, w.source, w.approved, g.name AS glossaryname"
            . " FROM {playerwords_words} w"
            . " LEFT JOIN {glossary} g ON g.id = w.glossaryid"
            . " WHERE w.playerwordsid = :playerwordsid"
            . " ORDER BY w.$sort $dir";
        return $DB->get_records_sql($sql, ['playerwordsid' => $instanceid], 0, $limit);
    }
}
