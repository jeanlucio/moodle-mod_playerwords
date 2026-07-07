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

        $state = round_service::load_state((int)$cm->id, $userid);

        if ((int)$state['wordid'] === 0 || !empty($state['finished'])) {
            $restrictionnotice = round_service::get_round_restriction_notice($instance, $userid);
            if (!empty($state['finished'])) {
                round_service::save_state((int)$cm->id, $userid, $state);
                $displayword = (int)($state['wordid'] ?? 0) > 0 ? ($state['wordtext'] ?? '') : '';
                $templatectx = self::build_template_context($cm, $instance, $state, $displayword, $canmanagewords, $userid);
                if ($restrictionnotice !== null && (int)($state['cooldownuntil'] ?? 0) <= time()) {
                    $templatectx['cooldownactive'] = true;
                }
                return [
                    'templatecontext' => $templatectx,
                    'cooldownuntil'   => (int)($state['cooldownuntil'] ?? 0),
                    'timeleft'        => 0,
                    'timertotal'      => 0,
                ];
            }
            if ($restrictionnotice !== null) {
                round_service::save_state((int)$cm->id, $userid, $state);
                $templatectx = self::build_template_context($cm, $instance, $state, '', $canmanagewords, $userid);
                $templatectx['nogamewords'] = $restrictionnotice;
                return [
                    'templatecontext' => $templatectx,
                    'cooldownuntil'   => 0,
                    'timeleft'        => 0,
                    'timertotal'      => 0,
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
            'templatecontext' => $templatecontext,
            'cooldownuntil'   => (int)($state['cooldownuntil'] ?? 0),
            'timeleft'        => (int)($templatecontext['timeleft'] ?? 0),
            'timertotal'      => (int)$instance->timer_seconds,
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
            ? round_presenter::build_lobby_context($instance, $state)
            : round_presenter::build_round_panel_context($instance, $cm, $state, $targetword, $userid);

        return [
            'hastargetword' => ($targetword !== ''),
            'nogamewords' => get_string('nogamewords', 'mod_playerwords'),
            'canmanagewords' => $canmanagewords,
            'managewordsbutton' => get_string('managewordsbutton', 'mod_playerwords'),
            'managewordsurl' => (new moodle_url('/mod/playerwords/managewords.php', ['id' => $cm->id]))->out(false),
            'showlobby' => $showlobby,
            'roundstarted' => !empty($state['roundstarted']),
        ]
            + round_presenter::build_ranking_context($instance, $cm, $userid, !empty($state['finished']))
            + $inner;
    }
}
