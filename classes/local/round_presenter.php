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
 * Round presenter service.
 *
 * @package mod_playerwords
 * @copyright  2026 Jean Lúcio
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playerwords\local;

use core_text;
use moodle_url;

/**
 * Builds round-related template context fragments, shared by the full-page render
 * and by the AJAX partial responses.
 */
class round_presenter {
    /**
     * Builds the per-letter cell data for one guessed row.
     *
     * @param string $word Normalized guess word for this row.
     * @param array $feedback Per-letter state map, indexed by position.
     * @return array
     */
    public static function build_row_letters(string $word, array $feedback): array {
        $letters = [];
        foreach (preg_split('//u', $word, -1, PREG_SPLIT_NO_EMPTY) as $index => $letter) {
            $cellstate = $feedback[$index] ?? 'absent';
            $upperletter = core_text::strtoupper($letter);
            $letters[] = [
                'letter' => s($upperletter),
                'state' => $cellstate,
                'arialabel' => get_string('cell_state_' . $cellstate, 'mod_playerwords', $upperletter),
            ];
        }
        return $letters;
    }

    /**
     * Builds grid rows for template.
     *
     * @param array $state Session state.
     * @param string $targetword Target word.
     * @param int $maxattempts Maximum attempts.
     * @return array
     */
    public static function build_grid_rows(array $state, string $targetword, int $maxattempts): array {
        $rows = [];
        for ($i = 0; $i < $maxattempts; $i++) {
            $rowstate = $state['rows'][$i] ?? null;
            if ($rowstate) {
                $rowletters = self::build_row_letters($rowstate['word'], $rowstate['feedback']);
            } else if ($targetword !== '') {
                $rowletters = [];
                $wordlength = core_text::strlen($targetword);
                for ($j = 0; $j < $wordlength; $j++) {
                    $rowletters[] = [
                        'letter' => '',
                        'state' => 'empty',
                        'arialabel' => get_string('cell_state_empty_position', 'mod_playerwords', (object) [
                            'position' => $j + 1,
                            'total' => $wordlength,
                        ]),
                    ];
                }
            } else {
                $rowletters = [];
            }
            $rows[] = ['letters' => $rowletters];
        }

        return $rows;
    }

    /**
     * Returns a formatted countdown string, or empty if no cooldown is active.
     *
     * @param int $cooldownuntil Epoch when the cooldown ends, 0 if inactive.
     * @return string
     */
    public static function build_cooldown_text(int $cooldownuntil): string {
        if ($cooldownuntil <= 0) {
            return '';
        }
        $remaining = $cooldownuntil - time();
        return $remaining > 0 ? format_time($remaining) : '';
    }

    /**
     * Returns the Wordle-style feedback message for the end screen.
     *
     * @param array $state Session state.
     * @return string
     */
    public static function build_feedback_message(array $state): string {
        if (empty($state['finished'])) {
            return '';
        }
        if (!empty($state['forfeited'])) {
            return get_string('feedback_forfeited', 'mod_playerwords');
        }
        if (!empty($state['timedout'])) {
            return get_string('feedback_timeout', 'mod_playerwords');
        }
        if (!empty($state['won'])) {
            $keys = [
                'feedback_genius', 'feedback_magnificent', 'feedback_impressive',
                'feedback_splendid', 'feedback_great', 'feedback_phew',
            ];
            $index = min(max(0, (int)$state['attemptsused'] - 1), count($keys) - 1);
            return get_string($keys[$index], 'mod_playerwords');
        }
        return get_string('feedback_lost', 'mod_playerwords');
    }

    /**
     * Whether the grading method is meaningful to surface to the student: grading must be
     * enabled with a numeric point scale (this summary does not attempt to format a Moodle
     * scale grade), and more than one round must be possible — with exactly one round, every
     * grading method produces the same value, so naming it would only be noise.
     *
     * @param \stdClass $instance Activity instance.
     * @return bool
     */
    private static function grading_info_relevant(\stdClass $instance): bool {
        return (float)$instance->grade > 0 && (int)$instance->max_rounds !== 1;
    }

    /**
     * Resolves the localized name of the instance's configured grading method.
     *
     * Public so attempts_history_service can reuse it for the "my attempts" summary,
     * without duplicating the lookup against playerwords_get_grademethod_options().
     *
     * @param \stdClass $instance Activity instance.
     * @return string
     */
    public static function grademethod_name(\stdClass $instance): string {
        global $CFG;
        require_once($CFG->dirroot . '/mod/playerwords/lib.php');

        $options = playerwords_get_grademethod_options();
        return $options[(int)$instance->grademethod] ?? $options[PLAYERWORDS_GRADE_HIGHEST];
    }

    /**
     * Builds the grading-method explanation shown in the lobby before a round starts,
     * mirroring how mod_quiz tells students its grading method whenever more than one
     * attempt is possible (its "gradingmethod" info message on the quiz view page).
     *
     * @param \stdClass $instance Activity instance.
     * @return array
     */
    public static function build_grading_method_info(\stdClass $instance): array {
        if (!self::grading_info_relevant($instance)) {
            return ['showgradingmethodinfo' => false, 'gradingmethodinfo' => ''];
        }

        return [
            'showgradingmethodinfo' => true,
            'gradingmethodinfo' => get_string(
                'gradingmethodinfo',
                'mod_playerwords',
                self::grademethod_name($instance)
            ),
        ];
    }

    /**
     * Builds the "grade so far" summary shown after a round finishes, mirroring mod_quiz's
     * gradesofar message: the grading method name alongside the student's current computed
     * grade, read straight from the gradebook item so it always matches what the teacher sees.
     *
     * @param \stdClass $instance Activity instance.
     * @param int $userid Current user id.
     * @return array
     */
    public static function build_grade_so_far(\stdClass $instance, int $userid): array {
        $blank = ['showgradesofar' => false, 'gradesofarmessage' => ''];

        if (!self::grading_info_relevant($instance)) {
            return $blank;
        }

        global $CFG;
        require_once($CFG->libdir . '/gradelib.php');

        $gradeitem = \grade_item::fetch([
            'itemtype'     => 'mod',
            'itemmodule'   => 'playerwords',
            'iteminstance' => $instance->id,
            'itemnumber'   => 0,
            'courseid'     => $instance->course,
        ]);
        if (!$gradeitem) {
            return $blank;
        }

        $grade = $gradeitem->get_grade($userid, false);
        if ($grade === null || $grade->finalgrade === null) {
            return $blank;
        }

        $a = (object)[
            'method'   => self::grademethod_name($instance),
            'mygrade'  => format_float((float)$grade->finalgrade, 2),
            'maxgrade' => format_float((float)$instance->grade, 2),
        ];

        return [
            'showgradesofar' => true,
            'gradesofarmessage' => get_string('gradesofar', 'mod_playerwords', $a),
        ];
    }

    /**
     * Builds the ranking template context fields.
     *
     * Always returns the full key set (even when show_ranking is disabled or the round is
     * not finished) so the shape stays stable for both Mustache rendering and the strongly
     * typed AJAX external functions. The inline ranking rows are only ever populated once
     * show_ranking is enabled and the round has finished; the rankingurl link is always present.
     *
     * @param \stdClass $instance Activity instance record.
     * @param \stdClass $cm Course module record.
     * @param int $userid Current user id.
     * @param bool $roundfinished Whether the current round is finished.
     * @return array
     */
    public static function build_ranking_context(
        \stdClass $instance,
        \stdClass $cm,
        int $userid,
        bool $roundfinished
    ): array {
        $base = [
            'showranking'          => !empty($instance->show_ranking),
            'rankingurl'           => (new moodle_url('/mod/playerwords/ranking.php', ['id' => $cm->id]))->out(false),
            'rankingviewfulllabel' => get_string('ranking_viewfull', 'mod_playerwords'),
            'rankingtitle'         => get_string('ranking_title', 'mod_playerwords'),
            'rankingpositionlabel' => get_string('ranking_position', 'mod_playerwords'),
            'rankingplayerlabel'   => get_string('ranking_player', 'mod_playerwords'),
            'rankingpointslabel'   => get_string('ranking_points', 'mod_playerwords'),
            'rankingemptylabel'    => get_string('ranking_empty', 'mod_playerwords'),
            'rankingrows'          => [],
            'rankinghasoutsider'   => false,
            'rankingoutsiderrow'   => [
                'position' => 0,
                'fullname' => '',
                'totalscore' => format_float(0, 2),
                'iscurrentuser' => false,
            ],
            'rankingempty'         => true,
        ];

        if (empty($instance->show_ranking) || !$roundfinished) {
            return $base;
        }

        $ranking = ranking_service::get_ranking($instance, $cm, $userid);
        $base['rankingrows']        = $ranking['rows'];
        $base['rankinghasoutsider'] = $ranking['hasoutsider'];
        $base['rankingoutsiderrow'] = $ranking['outsiderrow'] ?? $base['rankingoutsiderrow'];
        $base['rankingempty']       = $ranking['isempty'];

        return $base;
    }

    /**
     * Builds the post-round result context: reveal, cooldown and ranking.
     *
     * When $roundfinished is false, every reveal-related field is structurally blank —
     * this method never reads the target word/definition into the response unless the
     * round has actually finished, since that is the security boundary for AJAX callers.
     *
     * @param \stdClass $instance Activity instance record.
     * @param \stdClass $cm Course module record.
     * @param array $state Session state.
     * @param int $userid Current user id.
     * @param bool $roundfinished Whether the current round is finished.
     * @return array
     */
    public static function build_round_result_context(
        \stdClass $instance,
        \stdClass $cm,
        array $state,
        int $userid,
        bool $roundfinished
    ): array {
        $blank = [
            'feedbackmessage'       => '',
            'showreveal'            => false,
            'revealword'            => '',
            'revealwordlabel'       => get_string('revealwordlabel', 'mod_playerwords'),
            'showrevealconcept'     => false,
            'revealconcept'         => '',
            'revealconceptlabel'    => get_string('revealconceptlabel', 'mod_playerwords'),
            'showdefinition'        => false,
            'revealdefinition'      => '',
            'revealdefinitionlabel' => get_string('revealdefinitionlabel', 'mod_playerwords'),
            'cooldownuntil'         => 0,
            'cooldowntext'          => '',
            'cooldowncountdownlabel' => get_string('cooldowncountdownlabel', 'mod_playerwords'),
            'cooldownactive'        => false,
            'newroundlabel'         => get_string('newroundlabel', 'mod_playerwords'),
            'showgradesofar'        => false,
            'gradesofarmessage'     => '',
            'roundsplayedlabel'     => '',
            'huditemgrantedlabel'   => '',
        ] + self::build_ranking_context($instance, $cm, $userid, false);

        if (!$roundfinished) {
            return $blank;
        }

        $concept = $state['concept'] ?? '';
        $wordtext = $state['wordtext'] ?? '';
        $showrevealconcept = $concept !== '' && core_text::strtolower($concept) !== core_text::strtolower($wordtext);

        // Computed fresh from the DB every time (never from session state), so a change
        // to cooldown_seconds or max_rounds takes effect immediately — see
        // round_service::compute_cooldown_until() for why.
        $cooldownuntil = round_service::compute_cooldown_until($instance, $userid);
        $restricted = round_service::get_round_restriction_notice($instance, $userid) !== null;

        return [
            'feedbackmessage'       => self::build_feedback_message($state),
            'showreveal'            => ($wordtext !== ''),
            'revealword'            => $wordtext,
            'revealwordlabel'       => $blank['revealwordlabel'],
            'showrevealconcept'     => $showrevealconcept,
            'revealconcept'         => $concept,
            'revealconceptlabel'    => $blank['revealconceptlabel'],
            'showdefinition'        => !empty($state['hint']),
            'revealdefinition'      => $state['hint'] ?? '',
            'revealdefinitionlabel' => $blank['revealdefinitionlabel'],
            'cooldownuntil'         => $cooldownuntil,
            'cooldowntext'          => self::build_cooldown_text($cooldownuntil),
            'cooldowncountdownlabel' => $blank['cooldowncountdownlabel'],
            // Hides the new-round button for either reason: an active time-based
            // cooldown, or the round limit having been reached.
            'cooldownactive'        => $restricted,
            'newroundlabel'         => $blank['newroundlabel'],
            'roundsplayedlabel'     => self::build_rounds_played_label($instance, $userid),
            'huditemgrantedlabel'   => self::build_hud_grant_label($instance, $state),
        ] + self::build_grade_so_far($instance, $userid)
          + self::build_ranking_context($instance, $cm, $userid, true);
    }

    /**
     * Builds the "you received X× item" text shown after a won round, when the activity
     * has a win-grant item configured. Blank when there is nothing to announce (no grant
     * configured, or the round was not actually won) — and always blank for the guest
     * account, which round_service::finish_round() never actually grants anything to.
     *
     * @param \stdClass $instance Activity instance record.
     * @param array $state Session state.
     * @return string
     */
    private static function build_hud_grant_label(\stdClass $instance, array $state): string {
        $grantitem = (int)($instance->hud_win_grant_item ?? 0);
        if ($grantitem <= 0 || empty($state['won']) || isguestuser()) {
            return '';
        }

        $itemname = hud_service::get_item_name(hud_service::resolve_block_instance_id($instance), $grantitem);
        if ($itemname === '') {
            return '';
        }

        return get_string('hud_grantedlabel', 'mod_playerwords', (object)[
            'qty'  => max(1, (int)($instance->hud_win_grant_qty ?? 1)),
            'item' => $itemname,
        ]);
    }

    /**
     * Builds the "X of Y× item" balance/cost text for a PlayerHUD-gated action, and
     * whether the user currently has enough to afford it. Shared by the lobby's
     * start-round cost and the round panel's hint cost — same shape, same decision
     * (own balance vs configured requirement), different action.
     *
     * @param int $blockinstanceid Block instance ID the item must belong to.
     * @param int $itemid PlayerHUD item id, 0 disables the check.
     * @param int $requiredqty Quantity required by the activity's configuration.
     * @param int $userid Current user id.
     * @return array {applies: bool, label: string, canafford: bool}
     */
    private static function build_hud_cost_info(int $blockinstanceid, int $itemid, int $requiredqty, int $userid): array {
        $blank = ['applies' => false, 'label' => '', 'canafford' => true];

        if ($itemid <= 0) {
            return $blank;
        }

        $itemname = hud_service::get_item_name($blockinstanceid, $itemid);
        if ($itemname === '') {
            return $blank;
        }

        $requiredqty = max(1, $requiredqty);
        $availableqty = hud_service::get_available_quantity($blockinstanceid, $userid, $itemid);

        $label = get_string('hud_balancecost', 'mod_playerwords', (object)[
            'available' => $availableqty,
            'required'  => $requiredqty,
            'item'      => $itemname,
        ]);

        return [
            'applies' => true,
            'label' => $label,
            'canafford' => ($availableqty >= $requiredqty),
        ];
    }

    /**
     * Builds the "rounds played" counter text (e.g. "3 / 10" or "3 / ∞"), shown in the
     * lobby before starting and again in the post-round report, so students always know
     * how many rounds they have used against the activity's configured round limit.
     *
     * @param \stdClass $instance Activity instance record.
     * @param int $userid Current user id.
     * @return string
     */
    private static function build_rounds_played_label(\stdClass $instance, int $userid): string {
        $maxrounds = (int)$instance->max_rounds;
        $maxlabel = $maxrounds > 0 ? (string)$maxrounds : "\u{221E}";

        return get_string('roundsplayed', 'mod_playerwords', (object)[
            'played' => round_service::count_rounds_played($instance, $userid),
            'max'    => $maxlabel,
        ]);
    }

    /**
     * Builds the pre-round lobby context.
     *
     * The guest account never sees a PlayerHUD start cost — round_service::start_round()
     * waives it entirely for guests, so showing one here (and possibly blocking canstart
     * on a balance the guest doesn't have) would misrepresent what actually happens.
     *
     * @param \stdClass $instance Activity instance record.
     * @param array $state Session state.
     * @param int $userid Current user id.
     * @return array
     */
    public static function build_lobby_context(\stdClass $instance, array $state, int $userid): array {
        $hudstartcost = false;
        $hudstartcostlabel = '';
        $canstart = true;
        $roundcostitem = (int)($instance->hud_round_cost_item ?? 0);
        if ($roundcostitem > 0 && empty($state['finished']) && empty($state['roundstarted']) && !isguestuser()) {
            $info = self::build_hud_cost_info(
                hud_service::resolve_block_instance_id($instance),
                $roundcostitem,
                (int)($instance->hud_round_cost_qty ?? 1),
                $userid
            );
            $hudstartcost = $info['applies'];
            $hudstartcostlabel = $info['label'];
            $canstart = $info['canafford'];
        }

        return [
            'timerenabled' => ((int)$instance->timer_seconds > 0),
            'lobbytimerinfo' => (
                (int)$instance->timer_seconds > 0
                    ? get_string('lobby_timerinfo', 'mod_playerwords', format_time((int)$instance->timer_seconds))
                    : ''
            ),
            'hudstartcost' => $hudstartcost,
            'hudstartcostlabel' => $hudstartcostlabel,
            'canstart' => $canstart,
            'startlabel' => get_string('startround', 'mod_playerwords'),
            'roundsplayedlabel' => self::build_rounds_played_label($instance, $userid),
        ] + self::build_grading_method_info($instance);
    }

    /**
     * Builds the active-round panel context: status line, hint control, grid, plus
     * whichever of round_play/round_result applies for the current state.
     *
     * The guest account never sees a PlayerHUD hint cost, for the same reason
     * build_lobby_context() hides the round-start cost — round_service::reveal_hint()
     * waives it entirely for guests.
     *
     * @param \stdClass $instance Activity instance record.
     * @param \stdClass $cm Course module record.
     * @param array $state Session state.
     * @param string $targetword Current normalized target word.
     * @param int $userid Current user id.
     * @return array
     */
    public static function build_round_panel_context(
        \stdClass $instance,
        \stdClass $cm,
        array $state,
        string $targetword,
        int $userid
    ): array {
        $roundfinished = !empty($state['finished']);

        $hudhintcost = false;
        $hudhintcostlabel = '';
        $canaffordhint = true;
        $hintcostitem = (int)($instance->hud_hint_cost_item ?? 0);
        if ($hintcostitem > 0 && !empty($state['hint']) && empty($state['hintrevealed']) && !isguestuser()) {
            $info = self::build_hud_cost_info(
                hud_service::resolve_block_instance_id($instance),
                $hintcostitem,
                (int)($instance->hud_hint_cost_qty ?? 1),
                $userid
            );
            $hudhintcost = $info['applies'];
            $hudhintcostlabel = $info['label'];
            $canaffordhint = $info['canafford'];
        }

        $timeleft = 0;
        if (
            !$roundfinished
            && (int)$instance->timer_seconds > 0
            && !empty($state['roundstarted'])
            && !empty($state['starttime'])
        ) {
            $timeleft = max(0, (int)$instance->timer_seconds - (time() - (int)$state['starttime']));
        }

        return [
            'attemptslabel' => get_string('attemptslabel', 'mod_playerwords'),
            'attemptsused' => (int)$state['attemptsused'],
            'maxattempts' => (int)$instance->max_attempts,
            'timerenabled' => ((int)$instance->timer_seconds > 0),
            'timerlabel' => get_string('timerlabel', 'mod_playerwords'),
            'timeleft' => $timeleft,
            'hintlabel' => get_string('hintlabel', 'mod_playerwords'),
            'hintvalue' => !empty($state['hintrevealed']) ? ($state['hint'] ?? '') : '',
            'showhint' => !empty($state['hintrevealed']) && !empty($state['hint']),
            'canhint' => !empty($state['hint']) && empty($state['hintrevealed']) && empty($state['finished']),
            'hintbuttonlabel' => get_string('hintbuttonlabel', 'mod_playerwords'),
            'hudhintcost' => $hudhintcost,
            'hudhintcostlabel' => $hudhintcostlabel,
            'canaffordhint' => $canaffordhint,
            'rows' => self::build_grid_rows($state, $targetword, (int)$instance->max_attempts),
            'roundfinished' => $roundfinished,
            'guesslabel' => get_string('guesslabel', 'mod_playerwords'),
            'guessplaceholder' => get_string('guessplaceholder', 'mod_playerwords'),
            'guessmaxlength' => ($targetword !== '') ? core_text::strlen($targetword) : (int)$instance->max_length,
            'submitguess' => get_string('submitguess', 'mod_playerwords'),
            'forfeitlabel' => get_string('forfeitbutton', 'mod_playerwords'),
            'forfeitconfirm' => get_string('forfeitconfirm', 'mod_playerwords'),
            'keyboardlabel' => get_string('keyboard_label', 'mod_playerwords'),
            'keyboardenterlabel' => get_string('keyboard_enter', 'mod_playerwords'),
            'keyboardentertext' => get_string('keyboard_enter_text', 'mod_playerwords'),
            'keyboardbackspacelabel' => get_string('keyboard_backspace', 'mod_playerwords'),
            'showcedilla' => words_repository::has_cedilla_word((int)$instance->id),
        ] + self::build_round_result_context($instance, $cm, $state, $userid, $roundfinished);
    }
}
