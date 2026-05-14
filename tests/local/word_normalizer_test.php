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
 * Unit tests for word_normalizer.
 *
 * @package    mod_playerwords
 * @category   test
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playerwords\local;

/**
 * Tests for word_normalizer — pure logic, no database required.
 */
final class word_normalizer_test extends \basic_testcase {
    /**
     * Provides raw input strings and their expected normalized form.
     *
     * @return array[]
     */
    public static function normalize_provider(): array {
        return [
            'uppercase lowercased'         => ['GATO', 'gato'],
            'accent stripped acao'         => ['ação', 'acao'],
            'leading and trailing spaces'  => ['  gato  ', 'gato'],
            'combined uppercase accent'    => ['  AÇÃO  ', 'acao'],
            'cedilla and tilde'            => ['maçã', 'maca'],
            'initial accented letter'      => ['Ótimo', 'otimo'],
            'already normalized'           => ['bola', 'bola'],
            'accented e'                   => ['café', 'cafe'],
        ];
    }

    /**
     * Tests that normalize produces the expected lowercase accent-free string.
     *
     * @covers \mod_playerwords\local\word_normalizer::normalize
     * @dataProvider normalize_provider
     * @param string $input    Raw input string.
     * @param string $expected Expected normalized string.
     * @return void
     */
    public function test_normalize(string $input, string $expected): void {
        $this->assertSame($expected, word_normalizer::normalize($input));
    }
}
