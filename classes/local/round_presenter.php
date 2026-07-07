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
                for ($j = 0; $j < core_text::strlen($targetword); $j++) {
                    $rowletters[] = [
                        'letter' => '',
                        'state' => 'empty',
                        'arialabel' => get_string('cell_state_empty', 'mod_playerwords'),
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
     * @param array $state Session state.
     * @return string
     */
    public static function build_cooldown_text(array $state): string {
        $until = (int)($state['cooldownuntil'] ?? 0);
        if ($until <= 0) {
            return '';
        }
        $remaining = $until - time();
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
            'rankingoutsiderrow'   => ['position' => 0, 'fullname' => '', 'totalscore' => 0, 'iscurrentuser' => false],
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
        ] + self::build_ranking_context($instance, $cm, $userid, false);

        if (!$roundfinished) {
            return $blank;
        }

        $concept = $state['concept'] ?? '';
        $wordtext = $state['wordtext'] ?? '';
        $showrevealconcept = $concept !== '' && core_text::strtolower($concept) !== core_text::strtolower($wordtext);

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
            'cooldownuntil'         => (int)($state['cooldownuntil'] ?? 0),
            'cooldowntext'          => self::build_cooldown_text($state),
            'cooldowncountdownlabel' => $blank['cooldowncountdownlabel'],
            'cooldownactive'        => (int)($state['cooldownuntil'] ?? 0) > time(),
            'newroundlabel'         => $blank['newroundlabel'],
        ] + self::build_ranking_context($instance, $cm, $userid, true);
    }

    /**
     * Builds the pre-round lobby context.
     *
     * @param \stdClass $instance Activity instance record.
     * @param array $state Session state.
     * @return array
     */
    public static function build_lobby_context(\stdClass $instance, array $state): array {
        $hudstartcost = false;
        $hudstartcostlabel = '';
        $roundcostitem = (int)($instance->hud_round_cost_item ?? 0);
        if ($roundcostitem > 0 && empty($state['finished']) && empty($state['roundstarted'])) {
            $itemname = hud_service::get_item_name($roundcostitem);
            if ($itemname !== '') {
                $hudstartcost = true;
                $hudstartcostlabel = get_string('hud_costlabel', 'mod_playerwords', [
                    'qty'  => max(1, (int)($instance->hud_round_cost_qty ?? 1)),
                    'item' => $itemname,
                ]);
            }
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
            'startlabel' => get_string('startround', 'mod_playerwords'),
        ];
    }

    /**
     * Builds the active-round panel context: status line, hint control, grid, plus
     * whichever of round_play/round_result applies for the current state.
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

        $hintbuttonlabel = get_string('hintbuttonlabel', 'mod_playerwords');
        $hintcostitem = (int)($instance->hud_hint_cost_item ?? 0);
        if ($hintcostitem > 0 && !empty($state['hint']) && empty($state['hintrevealed'])) {
            $itemname = hud_service::get_item_name($hintcostitem);
            if ($itemname !== '') {
                $hintbuttonlabel .= ' (' . get_string('hud_costlabel', 'mod_playerwords', [
                    'qty'  => max(1, (int)($instance->hud_hint_cost_qty ?? 1)),
                    'item' => $itemname,
                ]) . ')';
            }
        }

        $timeleft = 0;
        if ((int)$instance->timer_seconds > 0 && !empty($state['roundstarted']) && !empty($state['starttime'])) {
            $reference = $roundfinished && !empty($state['endtime']) ? (int)$state['endtime'] : time();
            $timeleft = max(0, (int)$instance->timer_seconds - ($reference - (int)$state['starttime']));
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
            'hintbuttonlabel' => $hintbuttonlabel,
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
            'keyboardbackspacelabel' => get_string('keyboard_backspace', 'mod_playerwords'),
        ] + self::build_round_result_context($instance, $cm, $state, $userid, $roundfinished);
    }
}
