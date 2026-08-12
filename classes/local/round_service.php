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

use completion_info;
use context_module;
use core_text;

/**
 * Owns every round-state mutation: starting, guessing, hinting, forfeiting, timing out
 * and resetting a round. This is the single source of truth for what happens on each
 * transition, shared by the classic page render and by the AJAX external functions.
 */
class round_service {
    /**
     * Grace window, in seconds, allowed between the configured deadline and when a
     * client-reported timeout is honoured server-side — covers normal network latency
     * between the client's countdown reaching zero and the request arriving.
     */
    private const TIMEOUT_TOLERANCE_SECONDS = 5;

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
                'attemptid'     => 0,
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

        // Only counts genuinely finished rounds: a still-pending reservation (timefinished =
        // 0, either mid-round right now or abandoned without ever finishing) must not start
        // the cooldown clock, the same way it must not count as a 0 in the grade average.
        $lastattempttime = $DB->get_field_sql(
            "SELECT MAX(timefinished) FROM {playerwords_attempts}"
            . " WHERE playerwordsid = :pid AND userid = :uid AND timefinished > 0",
            ['pid' => $instance->id, 'uid' => $userid]
        );
        if (empty($lastattempttime)) {
            return 0;
        }

        $until = (int)$lastattempttime + (int)$instance->cooldown_seconds;
        return $until > time() ? $until : 0;
    }

    /**
     * Counts how many rounds a user has completed for this instance.
     *
     * @param \stdClass $instance Activity instance.
     * @param int $userid User id.
     * @return int
     */
    public static function count_rounds_played(\stdClass $instance, int $userid): int {
        global $DB;

        return $DB->count_records('playerwords_attempts', [
            'playerwordsid' => $instance->id,
            'userid'        => $userid,
        ]);
    }

    /**
     * Returns a restriction message if the user cannot start a new round, null otherwise.
     *
     * @param \stdClass $instance Activity instance.
     * @param int $userid User id.
     * @return string|null
     */
    public static function get_round_restriction_notice(\stdClass $instance, int $userid): ?string {
        if ((int)$instance->max_rounds > 0) {
            $roundsplayed = self::count_rounds_played($instance, $userid);
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
     * Also never picks a new word while get_round_restriction_notice() reports max_rounds
     * or cooldown as active — this is the single point every caller (view render, and the
     * start_round/submit_guess/new_round externals) goes through to pick a word, so the
     * restriction is enforced once here instead of being repeated at each call site.
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

                // The reservation made for that word (see start_round()) is not the
                // student's fault — discard it rather than let it silently spend one of
                // their max_rounds without ever having been playable.
                if (!empty($state['attemptid'])) {
                    $DB->delete_records('playerwords_attempts', [
                        'id' => $state['attemptid'],
                        'timefinished' => 0,
                    ]);
                    $state['attemptid'] = 0;
                }
            }
        }

        if (!$wordremoved && $targetword === '') {
            if (self::get_round_restriction_notice($instance, $userid) !== null) {
                return [$state, '', 0];
            }

            $completedround = self::count_rounds_played($instance, $userid);
            $excludewordid = words_repository::get_last_played_word_id($instance, $userid);
            $pickedword = words_repository::pick_round_word($instance, $completedround, $excludewordid);
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
                $state['attemptid'] = 0;

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
     * Reserves the attempt row here, the moment the student genuinely commits to the
     * round (not when a word is merely picked for the lobby) — so abandoning it from
     * this point on still spends one of their max_rounds, instead of a closed tab
     * silently granting a free re-roll. finish_round() completes this same row rather
     * than inserting a second one.
     *
     * The guest account is exempt from both the reservation and the PlayerHUD cost: a
     * course's guest-access visitors all share the single guest user record, so nothing
     * here could be safely attributed to one specific person. Guests play a free demo
     * that leaves no {playerwords_attempts} row behind — see finish_round() for the
     * matching exemption on the completion side.
     *
     * @param array $state Current state.
     * @param \stdClass $instance Activity instance.
     * @param int $userid User id.
     * @return array [$state, $notification, $notificationtype]
     */
    public static function start_round(array $state, \stdClass $instance, int $userid): array {
        global $DB;

        $isguest = isguestuser();

        $roundcostitem = (int)($instance->hud_round_cost_item ?? 0);
        if (!$isguest && $roundcostitem > 0) {
            $blockinstanceid = hud_service::resolve_block_instance_id($instance);
            $consumed = hud_service::consume_items(
                $blockinstanceid,
                $userid,
                $roundcostitem,
                max(1, (int)($instance->hud_round_cost_qty ?? 1))
            );
            if (!$consumed) {
                $itemname = hud_service::get_item_name($blockinstanceid, $roundcostitem);
                return [$state, get_string('hud_insufficient_round', 'mod_playerwords', $itemname), 'warning'];
            }
        }

        $state['starttime'] = time();
        $state['roundstarted'] = true;

        if (!$isguest && empty($state['attemptid'])) {
            $state['attemptid'] = $DB->insert_record('playerwords_attempts', (object)[
                'playerwordsid' => $instance->id,
                'userid'        => $userid,
                'wordid'        => (int)$state['wordid'],
                'attempts_used' => 0,
                'time_used'     => 0,
                'completed'     => 0,
                'score'         => 0,
                'rankingpoints' => 0,
                'timecreated'   => time(),
                'timefinished'  => 0,
            ]);
        }

        return [$state, null, null];
    }

    /**
     * Reveals the hint, optionally consuming a PlayerHUD item cost.
     *
     * The guest account is exempt from the PlayerHUD cost — see start_round() for why.
     *
     * @param array $state Current state.
     * @param \stdClass $instance Activity instance.
     * @param int $userid User id.
     * @return array [$state, $notification, $notificationtype]
     */
    public static function reveal_hint(array $state, \stdClass $instance, int $userid): array {
        $hintcostitem = (int)($instance->hud_hint_cost_item ?? 0);
        if (!isguestuser() && $hintcostitem > 0) {
            $blockinstanceid = hud_service::resolve_block_instance_id($instance);
            $consumed = hud_service::consume_items(
                $blockinstanceid,
                $userid,
                $hintcostitem,
                max(1, (int)($instance->hud_hint_cost_qty ?? 1))
            );
            if (!$consumed) {
                $itemname = hud_service::get_item_name($blockinstanceid, $hintcostitem);
                return [$state, get_string('hud_insufficient_hint', 'mod_playerwords', $itemname), 'warning'];
            }
        }

        $state['hintrevealed'] = true;

        return [$state, null, null];
    }

    /**
     * Validates and applies one guess.
     *
     * Requires roundstarted: a client that calls this before start_round() — skipping
     * the "Iniciar rodada" button, which is the only place a configured PlayerHUD round
     * cost is actually charged — must not be able to play the round for free.
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

        if (empty($state['roundstarted'])) {
            return [$state, null, get_string('roundnotstarted', 'mod_playerwords'), 'warning'];
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

        // The client fires this the moment its own countdown reaches zero — never trust
        // that alone. Re-check the deadline server-side (with a small tolerance for
        // normal network latency) so neither clock drift nor a premature/forged call can
        // end a round before its configured time has actually run out. submit_guess()
        // already re-derives $outoftime the same way for the guess-triggered path; this
        // is the equivalent guard for the explicit "time's up" signal.
        $deadline = (int)$state['starttime'] + (int)$instance->timer_seconds;
        if ((int)$instance->timer_seconds <= 0 || time() < $deadline - self::TIMEOUT_TOLERANCE_SECONDS) {
            return [$state, get_string('roundnottimedout', 'mod_playerwords'), 'warning'];
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
     * The guest account never reaches the persistence block below: no attempt row, no
     * round_completed event, no grade update, no PlayerHUD grant, no completion update.
     * $state still gets 'finished'/'won'/etc. so the UI can show the round's outcome —
     * see start_round() for why guests are exempt in the first place.
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

        if (isguestuser()) {
            $state['attemptid'] = 0;
            return $state;
        }

        $timeused = max(0, time() - (int)$state['starttime']);
        $score = gameplay_service::calculate_round_score(
            $instance,
            (int)$state['attemptsused'],
            $timeused,
            $completed
        );
        $rankingpoints = gameplay_service::calculate_ranking_points(
            $instance,
            (int)$state['attemptsused'],
            $completed
        );

        // The round reservation made by start_round() is completed here instead of
        // inserting a second row. A missing reservation only happens for session state
        // left over from before this reserve-on-start split — insert fresh then, exactly
        // as before, so old in-flight sessions keep working.
        $attemptid = (int)($state['attemptid'] ?? 0);
        if ($attemptid > 0) {
            $DB->update_record('playerwords_attempts', (object)[
                'id'            => $attemptid,
                'wordid'        => $roundwordid,
                'attempts_used' => (int)$state['attemptsused'],
                'time_used'     => $timeused,
                'completed'     => $completed ? 1 : 0,
                'score'         => $score,
                'rankingpoints' => $rankingpoints,
                'timefinished'  => time(),
            ]);
        } else {
            $attemptid = $DB->insert_record('playerwords_attempts', (object)[
                'playerwordsid' => $instance->id,
                'userid'        => $userid,
                'wordid'        => $roundwordid,
                'attempts_used' => (int)$state['attemptsused'],
                'time_used'     => $timeused,
                'completed'     => $completed ? 1 : 0,
                'score'         => $score,
                'rankingpoints' => $rankingpoints,
                'timecreated'   => time(),
                'timefinished'  => time(),
            ]);
        }
        $state['attemptid'] = 0;

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

        // Grants only on a genuine win, never on forfeit/timeout/a wrong final guess.
        // Items are still granted on an unlimited-rounds activity, but their XP is
        // withheld — the same anti-farming rule block_playerhud itself applies to its
        // own infinite drops, replicated here since this grant never goes through a
        // real drop (see hud_service::grant_items() for why).
        if ($completed) {
            $grantitem = (int)($instance->hud_win_grant_item ?? 0);
            if ($grantitem > 0) {
                hud_service::grant_items(
                    hud_service::resolve_block_instance_id($instance),
                    $userid,
                    $grantitem,
                    max(1, (int)($instance->hud_win_grant_qty ?? 1)),
                    (int)$instance->max_rounds === 0
                );
            }
        }

        // Automatic completion (e.g. the "require attempts" custom rule) is only
        // recomputed and persisted when something explicitly asks for it — Moodle has
        // no cron sweep for this, unlike grading. Trigger it here so the activity page's
        // completion badge reflects a finished round immediately, the same way
        // mod_choice calls update_state() right after recording a response.
        $cm = get_coursemodule_from_id('playerwords', $cmid, 0, false, MUST_EXIST);
        $course = get_course($instance->course);
        $completioninfo = new completion_info($course);
        if ($completioninfo->is_enabled($cm)) {
            $completioninfo->update_state($cm, COMPLETION_COMPLETE, $userid);
        }

        return $state;
    }
}
