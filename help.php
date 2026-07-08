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
 * How-to-play help page for a PlayerWords instance.
 *
 * @package    mod_playerwords
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use mod_playerwords\local\round_presenter;

$id = required_param('id', PARAM_INT);
$cm = get_coursemodule_from_id('playerwords', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$instance = $DB->get_record('playerwords', ['id' => $cm->instance], '*', MUST_EXIST);
$context = context_module::instance($cm->id);

require_login($course, true, $cm);
require_capability('mod/playerwords:view', $context);

$PAGE->set_url('/mod/playerwords/help.php', ['id' => $cm->id]);
$PAGE->set_title(get_string('help_title', 'mod_playerwords') . ' — ' . format_string($instance->name));
$PAGE->set_heading($course->fullname);
$PAGE->set_pagelayout('incourse');
$PAGE->requires->css('/mod/playerwords/styles.css');

$showgrading = (float)$instance->grade > 0;

$templatecontext = [
    'activityurl' => (new moodle_url('/mod/playerwords/view.php', ['id' => $cm->id]))->out(false),
    'backlabel' => get_string('backtogamebutton', 'mod_playerwords'),
    'helptitle' => get_string('help_title', 'mod_playerwords'),
    'introtext' => get_string('help_intro', 'mod_playerwords'),
    'legendcorrectlabel' => get_string('help_legend_correct', 'mod_playerwords'),
    'legendpresentlabel' => get_string('help_legend_present', 'mod_playerwords'),
    'legendabsentlabel' => get_string('help_legend_absent', 'mod_playerwords'),
    'attemptstext' => get_string('help_attempts', 'mod_playerwords'),
    'hinttext' => get_string('help_hint', 'mod_playerwords'),
    'timertext' => get_string('help_timer', 'mod_playerwords'),
    'showgrading' => $showgrading,
    'gradingtext' => $showgrading
        ? get_string('help_grading', 'mod_playerwords', round_presenter::grademethod_name($instance))
        : '',
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('mod_playerwords/help', $templatecontext);
echo $OUTPUT->footer();
