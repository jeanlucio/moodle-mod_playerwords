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
 * Word normalization helper.
 *
 * @package mod_playerwords
 * @copyright  2026 Jean Lúcio
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playerwords\local;

use core_text;

/**
 * Utility to normalize words consistently.
 */
class word_normalizer {
    /**
     * Normalizes one word.
     *
     * @param string $value Raw word.
     * @param bool $ignoreaccents Whether accents should be ignored.
     * @return string
     */
    public static function normalize(string $value, bool $ignoreaccents): string {
        $normalized = core_text::strtolower(trim($value));
        if ($ignoreaccents) {
            $normalized = core_text::strtolower(core_text::specialtoascii($normalized));
        }
        return $normalized;
    }
}
