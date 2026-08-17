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
 * @package    mod_playerwords
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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
     * Calculates the grade contribution of one finished round.
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
        global $CFG;
        require_once($CFG->dirroot . '/mod/playerwords/lib.php');

        $mode = (int)($instance->gradescoringmode ?? PLAYERWORDS_SCORING_BINARY);
        return self::compute_points($instance, $mode, $attemptsused, $completed, (float)$instance->grade);
    }

    /**
     * Calculates the ranking-points contribution of one finished round.
     *
     * Independent from calculate_round_score() in two ways: the grade and the ranking
     * each have their own scoring mode setting, and the ranking is always scored
     * against the fixed PLAYERWORDS_RANKING_BASE_POINTS, never the activity's own
     * grade — an ungraded activity ($instance->grade == 0, "No grade", the mod_form
     * default) must still produce a meaningful ranking when show_ranking is enabled
     * (also the default).
     *
     * @param \stdClass $instance Activity instance.
     * @param int $attemptsused Attempts used.
     * @param bool $completed Whether completed.
     * @return float
     */
    public static function calculate_ranking_points(
        \stdClass $instance,
        int $attemptsused,
        bool $completed
    ): float {
        global $CFG;
        require_once($CFG->dirroot . '/mod/playerwords/lib.php');

        $mode = (int)($instance->rankingscoringmode ?? PLAYERWORDS_SCORING_BINARY);
        return self::compute_points($instance, $mode, $attemptsused, $completed, (float)PLAYERWORDS_RANKING_BASE_POINTS);
    }

    /**
     * Shared points formula for both the grade and the ranking.
     *
     * Binary mode awards full credit on a win, zero otherwise. Linear mode awards full
     * credit on the first two attempts — a confident second guess is not meaningfully
     * less deserving than a first-try one — then scales the remaining attempts down
     * proportionally, so winning on the last allowed attempt still earns a positive
     * (non-zero) share. Not completing the round always earns zero, regardless of
     * mode. Degenerates to Binary when max_attempts is 1 or 2, since every allowed
     * attempt then falls within the full-credit plateau.
     *
     * @param \stdClass $instance Activity instance.
     * @param int $mode One of the PLAYERWORDS_SCORING_* constants.
     * @param int $attemptsused Attempts used.
     * @param bool $completed Whether completed.
     * @param float $maxpoints The scoring base — the caller's own grade or ranking base.
     * @return float
     */
    private static function compute_points(
        \stdClass $instance,
        int $mode,
        int $attemptsused,
        bool $completed,
        float $maxpoints
    ): float {
        if (!$completed) {
            return 0.0;
        }

        if ($mode !== PLAYERWORDS_SCORING_LINEAR) {
            return $maxpoints;
        }

        $maxattempts = (int)$instance->max_attempts;
        if ($maxattempts <= 0) {
            return $maxpoints;
        }

        $attemptsused = min(max($attemptsused, 1), $maxattempts);
        if ($attemptsused <= 2) {
            return $maxpoints;
        }

        return $maxpoints * ($maxattempts - $attemptsused + 1) / ($maxattempts - 1);
    }
}
