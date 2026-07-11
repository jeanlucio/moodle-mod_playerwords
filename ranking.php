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
 * Top-5 ranking page for a PlayerWords activity — deliberately capped, not paginated, to
 * avoid publicly ranking every student (see ranking_service::TOP_N).
 *
 * @package    mod_playerwords
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use mod_playerwords\local\ranking_service;

$id = required_param('id', PARAM_INT);
$cm = get_coursemodule_from_id('playerwords', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$instance = $DB->get_record('playerwords', ['id' => $cm->instance], '*', MUST_EXIST);
$context = context_module::instance($cm->id);

require_login($course, true, $cm);
require_capability('mod/playerwords:view', $context);

if (empty($instance->show_ranking)) {
    redirect(new moodle_url('/mod/playerwords/view.php', ['id' => $cm->id]));
}

$PAGE->set_url('/mod/playerwords/ranking.php', ['id' => $cm->id]);
$PAGE->set_title(get_string('ranking_title', 'mod_playerwords') . ' — ' . format_string($instance->name));
$PAGE->set_heading($course->fullname);
$PAGE->set_pagelayout('incourse');
$PAGE->requires->css('/mod/playerwords/styles.css');

$ranking = ranking_service::get_ranking($instance, $cm, (int)$USER->id);

$templatecontext = [
    'activityname'          => format_string($instance->name, true, ['context' => $context]),
    'activityurl'           => (new moodle_url('/mod/playerwords/view.php', ['id' => $cm->id]))->out(false),
    'backlabel'             => get_string('ranking_back', 'mod_playerwords'),
    'rankingtitle'          => get_string('ranking_title', 'mod_playerwords'),
    'rankingpositionlabel'  => get_string('ranking_position', 'mod_playerwords'),
    'rankingplayerlabel'    => get_string('ranking_player', 'mod_playerwords'),
    'rankingpointslabel'    => get_string('ranking_points', 'mod_playerwords'),
    'rankingrows'           => $ranking['rows'],
    'rankinghasoutsider'    => $ranking['hasoutsider'],
    'rankingoutsiderrow'    => $ranking['outsiderrow'],
    'rankingempty'          => $ranking['isempty'],
    'rankingemptylabel'     => get_string('ranking_empty', 'mod_playerwords'),
    'rankingtiebreaktext'   => get_string('ranking_tiebreak', 'mod_playerwords'),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('mod_playerwords/ranking', $templatecontext);
echo $OUTPUT->footer();
