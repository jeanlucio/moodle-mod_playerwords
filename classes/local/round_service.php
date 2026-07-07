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
 * Round transition service.
 *
 * @package mod_playerwords
 * @copyright  2026 Jean Lúcio
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playerwords\local;

use context_module;
use core_text;

/**
 * Owns every round-state mutation: starting, guessing, hinting, forfeiting, timing out
 * and resetting a round. This is the single source of truth for what happens on each
 * transition, shared by the classic page render and by the AJAX external functions.
 */
class round_service {
    /**
     * Gets session state, creating defaults when missing.
     *
     * @param int $cmid Course module id.
     * @param int $userid User id.
     * @return array
     */
    public static function load_state(int $cmid, int $userid): array {
        global $SESSION;

        $sessionkey = gameplay_service::build_session_key($cmid, $userid);
        if (!isset($SESSION->mod_playerwords)) {
            $SESSION->mod_playerwords = [];
        }
        if (!isset($SESSION->mod_playerwords[$sessionkey])) {
            $SESSION->mod_playerwords[$sessionkey] = [
                'wordid'       => 0,
                'wordtext'     => '',
                'concept'      => '',
                'attemptsused' => 0,
                'starttime'    => 0,
                'hint'         => '',
                'hintrevealed' => false,
                'rows'         => [],
                'finished'      => false,
                'won'           => false,
                'forfeited'     => false,
                'timedout'      => false,
                'roundstarted'  => false,
            ];
        }

        return $SESSION->mod_playerwords[$sessionkey];
    }

    /**
     * Persists session state.
     *
     * @param int $cmid Course module id.
     * @param int $userid User id.
     * @param array $state Current state.
     * @return void
     */
    public static function save_state(int $cmid, int $userid, array $state): void {
        global $SESSION;

        $sessionkey = gameplay_service::build_session_key($cmid, $userid);
        $SESSION->mod_playerwords[$sessionkey] = $state;
    }

    /**
     * Marks the current round as finished so the next ensure_round_state() picks a fresh word.
     *
     * @param int $cmid Course module id.
     * @param int $userid User id.
     * @return void
     */
    public static function new_round(int $cmid, int $userid): void {
        global $SESSION;

        $sessionkey = gameplay_service::build_session_key($cmid, $userid);
        if (!isset($SESSION->mod_playerwords[$sessionkey])) {
            return;
        }
        $SESSION->mod_playerwords[$sessionkey]['wordid'] = 0;
        $SESSION->mod_playerwords[$sessionkey]['finished'] = false;
    }

    /**
     * Returns the epoch when the player's cooldown ends, or 0 if none is active.
     *
     * Computed fresh from the last attempt's timestamp and the activity's current
     * cooldown_seconds setting every time — never cached in session state — so a
     * change to the setting takes effect immediately for cooldowns already ticking,
     * the same way mod_quiz's inter-attempt delay always uses its current setting.
     *
     * @param \stdClass $instance Activity instance.
     * @param int $userid User id.
     * @return int
     */
    public static function compute_cooldown_until(\stdClass $instance, int $userid): int {
        global $DB;

        if ((int)$instance->cooldown_seconds <= 0) {
            return 0;
        }

        $lastattempttime = $DB->get_field_sql(
            "SELECT MAX(timecreated) FROM {playerwords_attempts}"
            . " WHERE playerwordsid = :pid AND userid = :uid",
            ['pid' => $instance->id, 'uid' => $userid]
        );
        if (empty($lastattempttime)) {
            return 0;
        }

        $until = (int)$lastattempttime + (int)$instance->cooldown_seconds;
        return $until > time() ? $until : 0;
    }

    /**
     * Returns a restriction message if the user cannot start a new round, null otherwise.
     *
     * @param \stdClass $instance Activity instance.
     * @param int $userid User id.
     * @return string|null
     */
    public static function get_round_restriction_notice(\stdClass $instance, int $userid): ?string {
        global $DB;

        if ((int)$instance->max_rounds > 0) {
            $roundsplayed = $DB->count_records('playerwords_attempts', [
                'playerwordsid' => $instance->id,
                'userid'        => $userid,
            ]);
            if ($roundsplayed >= (int)$instance->max_rounds) {
                return get_string('roundlimitreached', 'mod_playerwords', $instance->max_rounds);
            }
        }

        $cooldownuntil = self::compute_cooldown_until($instance, $userid);
        if ($cooldownuntil > 0) {
            return get_string('cooldownactive', 'mod_playerwords', format_time($cooldownuntil - time()));
        }

        return null;
    }

    /**
     * Ensures round target word is loaded in state, picking a new one if needed.
     *
     * Fires the round_started event when a fresh word is picked. Never picks a new word
     * while the current round is finished — that transition belongs exclusively to
     * new_round(); callers must reset the round first if they want to advance past it.
     *
     * @param array $state Current state.
     * @param \stdClass $instance Activity instance.
     * @param int $cmid Course module id.
     * @param int $userid User id.
     * @return array [$state, $targetword, $roundwordid]
     */
    public static function ensure_round_state(array $state, \stdClass $instance, int $cmid, int $userid): array {
        global $DB;

        if (!empty($state['finished'])) {
            return [$state, '', (int)$state['wordid']];
        }

        $targetword = '';
        $roundwordid = 0;
        $wordremoved = false;

        if ((int)$state['wordid'] > 0) {
            $roundwordid = (int)$state['wordid'];
            $wordrecord = words_repository::get_approved_word_by_id($roundwordid, (int)$instance->id);
            if ($wordrecord) {
                $targetword = word_normalizer::normalize($wordrecord->word);
                $state['hint'] = $wordrecord->hint ?? '';
                $state['concept'] = $wordrecord->concept ?? '';
                // Backward compat: sessions created before roundstarted flag was added.
                if (empty($state['roundstarted']) && !empty($state['starttime'])) {
                    $state['roundstarted'] = true;
                }
            } else {
                // Word was removed or unapproved mid-round; reset so the next load picks a fresh word.
                $state['wordid'] = 0;
                $state['attemptsused'] = 0;
                $state['rows'] = [];
                $wordremoved = true;
            }
        }

        if (!$wordremoved && $targetword === '') {
            $completedround = $DB->count_records('playerwords_attempts', [
                'playerwordsid' => $instance->id,
                'userid' => $userid,
            ]);
            $pickedword = words_repository::pick_round_word($instance, $completedround);
            if ($pickedword) {
                $targetword = word_normalizer::normalize($pickedword->word);
                $roundwordid = (int)$pickedword->id;
                $state['wordid'] = $roundwordid;
                $state['wordtext'] = $pickedword->word;
                $state['concept'] = $pickedword->concept ?? '';
                $state['attemptsused'] = 0;
                $state['starttime'] = 0;
                $state['hint'] = $pickedword->hint ?? '';
                $state['hintrevealed'] = false;
                $state['rows'] = [];
                $state['finished'] = false;
                $state['won'] = false;
                $state['forfeited'] = false;
                $state['timedout'] = false;
                $state['roundstarted'] = false;

                $event = \mod_playerwords\event\round_started::create([
                    'objectid' => $roundwordid,
                    'context'  => context_module::instance($cmid),
                    'other'    => ['wordlength' => core_text::strlen($targetword)],
                ]);
                $event->trigger();
            }
        }

        return [$state, $targetword, $roundwordid];
    }

    /**
     * Starts the round timer, optionally consuming a PlayerHUD item cost.
     *
     * @param array $state Current state.
     * @param \stdClass $instance Activity instance.
     * @param int $userid User id.
     * @return array [$state, $notification, $notificationtype]
     */
    public static function start_round(array $state, \stdClass $instance, int $userid): array {
        $roundcostitem = (int)($instance->hud_round_cost_item ?? 0);
        if ($roundcostitem > 0) {
            $consumed = hud_service::consume_items(
                $userid,
                $roundcostitem,
                max(1, (int)($instance->hud_round_cost_qty ?? 1))
            );
            if (!$consumed) {
                $itemname = hud_service::get_item_name($roundcostitem);
                return [$state, get_string('hud_insufficient_round', 'mod_playerwords', $itemname), 'warning'];
            }
        }

        $state['starttime'] = time();
        $state['roundstarted'] = true;

        return [$state, null, null];
    }

    /**
     * Reveals the hint, optionally consuming a PlayerHUD item cost.
     *
     * @param array $state Current state.
     * @param \stdClass $instance Activity instance.
     * @param int $userid User id.
     * @return array [$state, $notification, $notificationtype]
     */
    public static function reveal_hint(array $state, \stdClass $instance, int $userid): array {
        $hintcostitem = (int)($instance->hud_hint_cost_item ?? 0);
        if ($hintcostitem > 0) {
            $consumed = hud_service::consume_items(
                $userid,
                $hintcostitem,
                max(1, (int)($instance->hud_hint_cost_qty ?? 1))
            );
            if (!$consumed) {
                $itemname = hud_service::get_item_name($hintcostitem);
                return [$state, get_string('hud_insufficient_hint', 'mod_playerwords', $itemname), 'warning'];
            }
        }

        $state['hintrevealed'] = true;

        return [$state, null, null];
    }

    /**
     * Validates and applies one guess.
     *
     * @param array $state Current state.
     * @param \stdClass $instance Activity instance.
     * @param int $cmid Course module id.
     * @param int $userid User id.
     * @param int $roundwordid Current word id.
     * @param string $targetword Current normalized target word.
     * @param string $guess Raw guess text.
     * @return array [$state, $feedback|null, $notification, $notificationtype]
     */
    public static function submit_guess(
        array $state,
        \stdClass $instance,
        int $cmid,
        int $userid,
        int $roundwordid,
        string $targetword,
        string $guess
    ): array {
        if (!empty($state['finished']) || (int)$state['attemptsused'] >= (int)$instance->max_attempts) {
            return [$state, null, get_string('roundfinished', 'mod_playerwords'), 'warning'];
        }

        if ($targetword === '') {
            return [$state, null, get_string('nogamewords', 'mod_playerwords'), 'warning'];
        }

        $normalizedguess = word_normalizer::normalize($guess);
        $targetlength = core_text::strlen($targetword);
        $guesslength = core_text::strlen($normalizedguess);

        if (!preg_match('/^[\p{L}]+$/u', $normalizedguess)) {
            return [$state, null, get_string('error_invalidchars', 'mod_playerwords'), 'warning'];
        }

        if ($guesslength !== $targetlength) {
            $message = get_string('guesslengthmismatch', 'mod_playerwords', $targetlength);
            return [$state, null, $message, 'warning'];
        }

        $state['attemptsused']++;
        $feedback = gameplay_service::build_letter_feedback($normalizedguess, $targetword);
        $state['rows'][] = [
            'word' => $normalizedguess,
            'feedback' => $feedback,
        ];

        $iscompleted = ($normalizedguess === $targetword);
        $outofattempts = ((int)$state['attemptsused'] >= (int)$instance->max_attempts);
        $outoftime = false;
        if ((int)$instance->timer_seconds > 0) {
            $elapsed = time() - (int)$state['starttime'];
            $outoftime = $elapsed >= (int)$instance->timer_seconds;
        }

        if (!($iscompleted || $outofattempts || $outoftime)) {
            return [$state, $feedback, null, null];
        }

        $state = self::finish_round($state, $instance, $cmid, $userid, $roundwordid, $iscompleted, false, false);

        $notification = $iscompleted
            ? get_string('roundwon', 'mod_playerwords')
            : get_string('roundlost', 'mod_playerwords');

        return [$state, $feedback, $notification, $iscompleted ? 'success' : 'warning'];
    }

    /**
     * Handles a forfeit: marks the round as lost without an extra attempt.
     *
     * @param array $state Current state.
     * @param \stdClass $instance Activity instance.
     * @param int $cmid Course module id.
     * @param int $userid User id.
     * @return array [$state, $notification, $notificationtype]
     */
    public static function forfeit(array $state, \stdClass $instance, int $cmid, int $userid): array {
        if (empty($state['wordid']) || !empty($state['finished'])) {
            return [$state, get_string('roundfinished', 'mod_playerwords'), 'warning'];
        }

        $roundwordid = (int)$state['wordid'];
        $state = self::finish_round($state, $instance, $cmid, $userid, $roundwordid, false, true, false);

        return [$state, get_string('roundforfeited', 'mod_playerwords'), 'warning'];
    }

    /**
     * Handles a timer expiry: identical to forfeit but records a timedout flag.
     *
     * @param array $state Current state.
     * @param \stdClass $instance Activity instance.
     * @param int $cmid Course module id.
     * @param int $userid User id.
     * @return array [$state, $notification, $notificationtype]
     */
    public static function timeout(array $state, \stdClass $instance, int $cmid, int $userid): array {
        if (empty($state['wordid']) || !empty($state['finished'])) {
            return [$state, get_string('roundfinished', 'mod_playerwords'), 'warning'];
        }

        $roundwordid = (int)$state['wordid'];
        $state = self::finish_round($state, $instance, $cmid, $userid, $roundwordid, false, false, true);

        return [$state, get_string('roundtimeout', 'mod_playerwords'), 'warning'];
    }

    /**
     * Applies the shared "round just finished" bookkeeping.
     *
     * The single place all finish paths (guess completion, forfeit, timeout) go through:
     * flags, cooldown, score, attempt record, round_completed event and grade update.
     *
     * @param array $state Current state.
     * @param \stdClass $instance Activity instance.
     * @param int $cmid Course module id.
     * @param int $userid User id.
     * @param int $roundwordid Word id being played.
     * @param bool $completed Whether the player guessed correctly.
     * @param bool $forfeited Whether the player gave up.
     * @param bool $timedout Whether the timer expired.
     * @return array Updated state.
     */
    private static function finish_round(
        array $state,
        \stdClass $instance,
        int $cmid,
        int $userid,
        int $roundwordid,
        bool $completed,
        bool $forfeited,
        bool $timedout
    ): array {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/playerwords/lib.php');

        $state['finished'] = true;
        $state['endtime'] = time();
        $state['won'] = $completed;
        $state['forfeited'] = $forfeited;
        $state['timedout'] = $timedout;
        // Cooldown is never stored here: it is always computed fresh from the attempt
        // record this method is about to insert, via compute_cooldown_until(). That way
        // a later change to cooldown_seconds applies immediately, the same way mod_quiz's
        // inter-attempt delay always reflects its current setting.

        $timeused = max(0, time() - (int)$state['starttime']);
        $score = gameplay_service::calculate_round_score(
            $instance,
            (int)$state['attemptsused'],
            $timeused,
            $completed
        );

        $attemptid = $DB->insert_record('playerwords_attempts', (object)[
            'playerwordsid' => $instance->id,
            'userid'        => $userid,
            'wordid'        => $roundwordid,
            'attempts_used' => (int)$state['attemptsused'],
            'time_used'     => $timeused,
            'completed'     => $completed ? 1 : 0,
            'score'         => $score,
            'timecreated'   => time(),
        ]);

        $event = \mod_playerwords\event\round_completed::create([
            'objectid' => $attemptid,
            'context'  => context_module::instance($cmid),
            'other'    => [
                'completed'    => $completed,
                'score'        => $score,
                'attemptsused' => (int)$state['attemptsused'],
                'timeused'     => $timeused,
                'wordid'       => $roundwordid,
            ],
        ]);
        $event->trigger();

        playerwords_update_grades($instance, $userid);

        return $state;
    }
}
