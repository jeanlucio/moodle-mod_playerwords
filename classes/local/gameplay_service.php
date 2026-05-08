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
 * Gameplay helper service.
 *
 * @package mod_playerwords
 * @copyright  2026 Jean Lúcio
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playerwords\local;

/**
 * Holds gameplay utility methods.
 */
class gameplay_service {
    /**
     * Builds one session key by module and user.
     *
     * @param int $cmid Course module id.
     * @param int $userid User id.
     * @return string
     */
    public static function build_session_key(int $cmid, int $userid): string {
        return $cmid . ':' . $userid;
    }

    /**
     * Builds per-letter feedback using Wordle-style logic.
     *
     * @param string $guess User guess.
     * @param string $target Target word.
     * @return array
     */
    public static function build_letter_feedback(string $guess, string $target): array {
        $guessletters = preg_split('//u', $guess, -1, PREG_SPLIT_NO_EMPTY);
        $targetletters = preg_split('//u', $target, -1, PREG_SPLIT_NO_EMPTY);

        $feedback = [];
        $remaining = [];

        foreach ($targetletters as $index => $letter) {
            if (($guessletters[$index] ?? null) === $letter) {
                $feedback[$index] = 'correct';
                continue;
            }
            if (!isset($remaining[$letter])) {
                $remaining[$letter] = 0;
            }
            $remaining[$letter]++;
        }

        foreach ($guessletters as $index => $letter) {
            if (isset($feedback[$index])) {
                continue;
            }
            if (!empty($remaining[$letter])) {
                $feedback[$index] = 'present';
                $remaining[$letter]--;
            } else {
                $feedback[$index] = 'absent';
            }
        }

        ksort($feedback);
        return $feedback;
    }

    /**
     * Calculates score for one finished round.
     *
     * @param \stdClass $instance Activity instance.
     * @param int $attemptsused Attempts used.
     * @param int $timeused Time used in seconds.
     * @param bool $completed Whether completed.
     * @return float
     */
    public static function calculate_round_score(
        \stdClass $instance,
        int $attemptsused,
        int $timeused,
        bool $completed
    ): float {
        if (!$completed) {
            return 0.0;
        }

        $maxattempts = max(1, (int)$instance->max_attempts);
        $attemptfactor = max(0.0, ($maxattempts - ($attemptsused - 1)) / $maxattempts);

        $timefactor = 1.0;
        if ((int)$instance->timer_seconds > 0) {
            $timefactor = max(0.0, ((int)$instance->timer_seconds - $timeused) / (int)$instance->timer_seconds);
        }

        $score = (($attemptfactor * 0.7) + ($timefactor * 0.3)) * (float)$instance->grade;
        return round(max(0.0, min((float)$instance->grade, $score)), 5);
    }
}
