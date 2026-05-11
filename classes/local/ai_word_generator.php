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
 * AI word generation wrapper for mod_playerwords.
 *
 * @package    mod_playerwords
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playerwords\local;

use core_text;

/**
 * Wraps local_playergames\cartridge\ai_generator for single-word term generation.
 *
 * All methods guard against the optional dependency being absent.
 */
class ai_word_generator {
    /**
     * Returns true if the AI generator class is installed.
     *
     * @return bool
     */
    public static function is_available(): bool {
        return class_exists('local_playergames\cartridge\ai_generator');
    }

    /**
     * Returns true if the AI generator is installed and an API key is configured.
     *
     * @return bool
     */
    public static function has_key(): bool {
        if (!self::is_available()) {
            return false;
        }
        $generator = new \local_playergames\cartridge\ai_generator();
        return $generator->has_key();
    }

    /**
     * Generates words using the AI provider and saves them as pending approval.
     *
     * Only single-word, purely alphabetic terms within the activity's configured
     * length bounds are saved. Multi-word phrases and numeric tokens are skipped.
     *
     * @param \stdClass $instance Activity instance record.
     * @param int $userid ID of the user triggering the generation.
     * @param string $topic Subject area or theme for the AI prompt.
     * @param int $count Number of concepts to request (1–20).
     * @return int Number of words saved as pending.
     * @throws \moodle_exception If no API key is available or the request fails.
     */
    public static function generate_and_save(
        \stdClass $instance,
        int $userid,
        string $topic,
        int $count
    ): int {
        $generator = new \local_playergames\cartridge\ai_generator();
        $language = get_string('thislanguage', 'langconfig');
        $items = $generator->generate($topic, $language, $count, 3);

        if (!is_array($items)) {
            return 0;
        }

        $saved = 0;
        foreach ($items as $item) {
            $term = trim($item['term'] ?? '');
            $hint = trim($item['definition'] ?? '');

            if ($term === '') {
                continue;
            }

            $tokens = preg_split('/\s+/u', $term, -1, PREG_SPLIT_NO_EMPTY);
            if (count($tokens) !== 1) {
                continue;
            }

            if (!preg_match('/^[\p{L}]+$/u', $term)) {
                continue;
            }

            $termlen = core_text::strlen($term);
            if ($termlen < (int)$instance->min_length || $termlen > (int)$instance->max_length) {
                continue;
            }

            words_repository::add_ai_word((int)$instance->id, $userid, $term, $hint);
            $saved++;
        }

        return $saved;
    }
}
