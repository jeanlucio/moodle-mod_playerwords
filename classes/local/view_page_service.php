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
 * Service to build PlayerWords view page state.
 *
 * @package mod_playerwords
 * @copyright  2026 Jean Lúcio
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_playerwords\local;

use context_module;
use core_text;
use moodle_url;

/**
 * Handles actions and template context of view.php.
 */
class view_page_service {
    /**
     * Builds full page data for view rendering.
     *
     * @param \stdClass $cm Course module.
     * @param \stdClass $instance Activity instance.
     * @param context_module $context Module context.
     * @param int $userid Current user id.
     * @return array
     */
    public static function build_page_data(
        \stdClass $cm,
        \stdClass $instance,
        context_module $context,
        int $userid
    ): array {
        $canmanagewords = has_capability('mod/playerwords:addinstance', $context);

        // Decided once per page load and marked immediately, not deferred to a client
        // callback: this is the same synchronous read-then-write pattern the preference
        // itself is meant to make trivial (see intro_service), and it means the flag can
        // never be shown twice even if the page fails to finish rendering client-side.
        $shouldautoshowintro = !intro_service::has_seen_intro($userid);
        if ($shouldautoshowintro) {
            intro_service::mark_intro_seen($userid);
        }

        $state = round_service::load_state((int)$cm->id, $userid);

        if ((int)$state['wordid'] === 0 || !empty($state['finished'])) {
            $restrictionnotice = round_service::get_round_restriction_notice($instance, $userid);
            if (!empty($state['finished'])) {
                round_service::save_state((int)$cm->id, $userid, $state);
                $displayword = (int)($state['wordid'] ?? 0) > 0 ? ($state['wordtext'] ?? '') : '';
                $templatectx = self::build_template_context($cm, $instance, $state, $displayword, $canmanagewords, $userid);
                return [
                    'templatecontext'      => $templatectx,
                    'cooldownuntil'        => round_service::compute_cooldown_until($instance, $userid),
                    'timeleft'             => 0,
                    'timertotal'           => 0,
                    'shouldautoshowintro'  => $shouldautoshowintro,
                ];
            }
            if ($restrictionnotice !== null) {
                round_service::save_state((int)$cm->id, $userid, $state);
                $templatectx = self::build_template_context($cm, $instance, $state, '', $canmanagewords, $userid);
                $templatectx['nogamewords'] = $restrictionnotice;
                return [
                    'templatecontext'      => $templatectx,
                    'cooldownuntil'        => 0,
                    'timeleft'             => 0,
                    'timertotal'           => 0,
                    'shouldautoshowintro'  => $shouldautoshowintro,
                ];
            }
        }

        [$state, $targetword] = round_service::ensure_round_state($state, $instance, (int)$cm->id, $userid);
        round_service::save_state((int)$cm->id, $userid, $state);

        $templatecontext = self::build_template_context(
            $cm,
            $instance,
            $state,
            $targetword,
            $canmanagewords,
            $userid
        );

        return [
            'templatecontext'      => $templatecontext,
            'cooldownuntil'        => round_service::compute_cooldown_until($instance, $userid),
            'timeleft'             => (int)($templatecontext['timeleft'] ?? 0),
            'timertotal'           => (int)$instance->timer_seconds,
            'shouldautoshowintro'  => $shouldautoshowintro,
        ];
    }

    /**
     * Builds template context array.
     *
     * @param \stdClass $cm Course module.
     * @param \stdClass $instance Activity instance.
     * @param array $state Session state.
     * @param string $targetword Current target.
     * @param bool $canmanagewords Whether user can manage words.
     * @param int $userid Current user ID.
     * @return array
     */
    private static function build_template_context(
        \stdClass $cm,
        \stdClass $instance,
        array $state,
        string $targetword,
        bool $canmanagewords,
        int $userid
    ): array {
        $showlobby = empty($state['finished']) && empty($state['roundstarted']) && ($targetword !== '');

        $inner = $showlobby
            ? round_presenter::build_lobby_context($instance, $state, $userid)
            : round_presenter::build_round_panel_context($instance, $cm, $state, $targetword, $userid);

        return [
            'hastargetword' => ($targetword !== ''),
            'nogamewords' => get_string('nogamewords', 'mod_playerwords'),
            'canmanagewords' => $canmanagewords,
            'managewordsbutton' => get_string('managewordsbutton', 'mod_playerwords'),
            'managewordsurl' => (new moodle_url('/mod/playerwords/managewords.php', ['id' => $cm->id]))->out(false),
            'toolbarhelp' => get_string('toolbarhelp', 'mod_playerwords'),
            'toolbarmyattempts' => get_string('toolbarmyattempts', 'mod_playerwords'),
            'myattemptsurl' => (new moodle_url('/mod/playerwords/myattempts.php', ['id' => $cm->id]))->out(false),
            'showforfeit' => !empty($state['roundstarted']) && empty($state['finished']),
            'forfeitlabel' => get_string('forfeitbutton', 'mod_playerwords'),
            'forfeitconfirm' => get_string('forfeitconfirm', 'mod_playerwords'),
            'showlobby' => $showlobby,
            'roundstarted' => !empty($state['roundstarted']),
        ]
            + self::build_help_context($instance)
            + self::build_inactive_words_context($instance, $canmanagewords)
            + round_presenter::build_ranking_context($instance, $cm, $userid, !empty($state['finished']))
            + $inner;
    }

    /**
     * Builds the context for the "inactive words" warning: approved pool words that
     * words_repository::get_candidate_words() would silently exclude from play, shown
     * only to whoever can manage the activity — a student never sees this.
     *
     * @param \stdClass $instance Activity instance.
     * @param bool $canmanagewords Whether the current user can manage the activity.
     * @return array
     */
    private static function build_inactive_words_context(\stdClass $instance, bool $canmanagewords): array {
        if (!$canmanagewords) {
            return ['hasinactivewords' => false];
        }

        $inactive = words_repository::get_inactive_words($instance);
        if (empty($inactive)) {
            return ['hasinactivewords' => false];
        }

        $lengthwords = [];
        $charsetwords = [];
        foreach ($inactive as $entry) {
            if ($entry['reason'] === 'invalidchars') {
                $charsetwords[] = $entry['word'];
            } else {
                $lengthwords[] = $entry['word'];
            }
        }

        return [
            'hasinactivewords' => true,
            'inactivewordstitle' => get_string('inactivewords_title', 'mod_playerwords'),
            'haslengthissues' => !empty($lengthwords),
            'lengthissuestext' => !empty($lengthwords) ? get_string('inactivewords_length', 'mod_playerwords', (object)[
                'count' => count($lengthwords),
                'words' => implode(', ', $lengthwords),
            ]) : '',
            'hascharsetissues' => !empty($charsetwords),
            'charsetissuestext' => !empty($charsetwords)
                ? get_string('inactivewords_invalidchars', 'mod_playerwords', (object)[
                    'count' => count($charsetwords),
                    'words' => implode(', ', $charsetwords),
                ])
                : '',
        ];
    }

    /**
     * Builds the context for the how-to-play help content, rendered as a hidden template
     * in the page and shown in a modal (see mod_playerwords/help_body).
     *
     * @param \stdClass $instance Activity instance.
     * @return array
     */
    private static function build_help_context(\stdClass $instance): array {
        $showgrading = (float)$instance->grade > 0;
        $showranking = !empty($instance->show_ranking);
        $showhud = ((int)($instance->hud_round_cost_item ?? 0) > 0)
            || ((int)($instance->hud_hint_cost_item ?? 0) > 0)
            || ((int)($instance->hud_win_grant_item ?? 0) > 0);

        return [
            'helptitle' => get_string('help_title', 'mod_playerwords'),
            'introtext' => get_string('help_intro', 'mod_playerwords'),
            'keyboardtext' => get_string('help_keyboard', 'mod_playerwords'),
            'legendcorrectlabel' => get_string('help_legend_correct', 'mod_playerwords'),
            'legendpresentlabel' => get_string('help_legend_present', 'mod_playerwords'),
            'legendabsentlabel' => get_string('help_legend_absent', 'mod_playerwords'),
            'attemptstext' => get_string('help_attempts', 'mod_playerwords'),
            'hinttext' => get_string('help_hint', 'mod_playerwords'),
            'timertext' => get_string('help_timer', 'mod_playerwords'),
            'showhud' => $showhud,
            'hudtext' => $showhud ? get_string('help_hud', 'mod_playerwords') : '',
            'showgrading' => $showgrading,
            'gradingtext' => $showgrading
                ? get_string('help_grading', 'mod_playerwords', round_presenter::grademethod_name($instance))
                : '',
            'showranking' => $showranking,
            'rankingtext' => $showranking ? get_string('help_ranking', 'mod_playerwords') : '',
            'reviewhint' => get_string('help_reviewhint', 'mod_playerwords'),
        ];
    }
}
