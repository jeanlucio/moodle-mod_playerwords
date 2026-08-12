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
 * Attempt history for a PlayerWords instance: a student's own history, or — for
 * whoever can manage the activity — every student's history, paginated and sortable.
 *
 * @package    mod_playerwords
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use mod_playerwords\local\attempts_history_service;

$id = required_param('id', PARAM_INT);
$cm = get_coursemodule_from_id('playerwords', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$instance = $DB->get_record('playerwords', ['id' => $cm->instance], '*', MUST_EXIST);
$context = context_module::instance($cm->id);

require_login($course, true, $cm);
require_capability('mod/playerwords:view', $context);

$PAGE->set_url('/mod/playerwords/myattempts.php', ['id' => $cm->id]);
$PAGE->set_heading($course->fullname);
$PAGE->set_pagelayout('incourse');
$PAGE->requires->css('/mod/playerwords/styles.css');

$activityurl = (new moodle_url('/mod/playerwords/view.php', ['id' => $cm->id]))->out(false);
$backlabel = get_string('backtogamebutton', 'mod_playerwords');

// Whoever can review reports (teacher, editingteacher, manager) sees every student instead
// of only their own history.
if (has_capability('mod/playerwords:viewreports', $context)) {
    $page = optional_param('page', 0, PARAM_INT);
    $sort = optional_param('sort', 'date', PARAM_ALPHA);
    $dir = (strtoupper(optional_param('dir', 'DESC', PARAM_ALPHA)) === 'ASC') ? 'ASC' : 'DESC';
    $filteruserid = optional_param('studentid', 0, PARAM_INT);
    $perpage = attempts_history_service::REPORT_PERPAGE;

    $history = attempts_history_service::get_all_history(
        $instance,
        $context,
        $page,
        $perpage,
        $sort,
        $dir,
        $filteruserid
    );
    $players = attempts_history_service::get_players_for_filter($instance, $context);

    $columns = [
        ['key' => 'student', 'label' => get_string('myattempts_student', 'mod_playerwords'), 'alignend' => false],
        ['key' => 'word', 'label' => get_string('myattempts_word', 'mod_playerwords'), 'alignend' => false],
        ['key' => 'attempts', 'label' => get_string('myattempts_attempts', 'mod_playerwords'), 'alignend' => true],
        ['key' => 'time', 'label' => get_string('myattempts_time', 'mod_playerwords'), 'alignend' => true],
        ['key' => 'score', 'label' => get_string('myattempts_score', 'mod_playerwords'), 'alignend' => true],
    ];
    if ($history['showranking']) {
        $columns[] = [
            'key' => 'rankingpoints',
            'label' => get_string('myattempts_rankingpoints', 'mod_playerwords'),
            'alignend' => true,
        ];
    }
    $columns[] = ['key' => 'date', 'label' => get_string('myattempts_date', 'mod_playerwords'), 'alignend' => false];

    foreach ($columns as &$column) {
        $active = ($column['key'] === $sort);
        $nextdir = ($active && $dir === 'ASC') ? 'DESC' : 'ASC';
        $columnurl = new moodle_url('/mod/playerwords/myattempts.php', [
            'id'        => $cm->id,
            'sort'      => $column['key'],
            'dir'       => $nextdir,
            'studentid' => $filteruserid,
        ]);
        $column['url'] = $columnurl->out(false);
        $column['active'] = $active;
        $column['arrow'] = $active ? ($dir === 'ASC' ? ' ▲' : ' ▼') : '';
    }
    unset($column);

    $studentoptions = [
        ['id' => 0, 'name' => get_string('myattempts_allstudents', 'mod_playerwords'), 'selected' => ($filteruserid === 0)],
    ];
    foreach ($players as $player) {
        $studentoptions[] = [
            'id'       => $player->id,
            'name'     => $player->fullname,
            'selected' => ((int)$player->id === $filteruserid),
        ];
    }

    $baseurl = new moodle_url('/mod/playerwords/myattempts.php', [
        'id' => $cm->id, 'sort' => $sort, 'dir' => $dir, 'studentid' => $filteruserid,
    ]);
    $pagingbar = $OUTPUT->paging_bar($history['total'], $page, $perpage, $baseurl);

    $templatecontext = [
        'activityname'      => format_string($instance->name, true, ['context' => $context]),
        'activityurl'       => $activityurl,
        'backlabel'         => $backlabel,
        'cmid'              => $cm->id,
        'reporttitle'       => get_string('myattempts_report_title', 'mod_playerwords'),
        'emptylabel'        => get_string('myattempts_empty_report', 'mod_playerwords'),
        'rows'              => $history['rows'],
        'isempty'           => $history['isempty'],
        'showranking'       => $history['showranking'],
        'columns'           => $columns,
        'filterlabel'       => get_string('myattempts_student', 'mod_playerwords'),
        'filterbuttonlabel' => get_string('myattempts_filterbutton', 'mod_playerwords'),
        'studentoptions'    => $studentoptions,
        'filterurl'         => (new moodle_url('/mod/playerwords/myattempts.php'))->out(false),
        'currentsort'       => $sort,
        'currentdir'        => $dir,
        'pagingbar'         => $pagingbar,
    ];

    $PAGE->set_title(get_string('myattempts_report_title', 'mod_playerwords') . ' — ' . format_string($instance->name));

    echo $OUTPUT->header();
    echo $OUTPUT->render_from_template('mod_playerwords/attempts_report', $templatecontext);
    echo $OUTPUT->footer();
} else {
    // Always the logged-in user's own data — never a userid read from the request.
    $history = attempts_history_service::get_history($instance, (int)$USER->id);

    $templatecontext = [
        'activityname'     => format_string($instance->name, true, ['context' => $context]),
        'activityurl'      => $activityurl,
        'backlabel'        => $backlabel,
        'myattemptstitle'  => get_string('myattempts_title', 'mod_playerwords'),
        'wordlabel'        => get_string('myattempts_word', 'mod_playerwords'),
        'attemptslabel'    => get_string('myattempts_attempts', 'mod_playerwords'),
        'timelabel'        => get_string('myattempts_time', 'mod_playerwords'),
        'scorelabel'       => get_string('myattempts_score', 'mod_playerwords'),
        'rankingpointslabel' => get_string('myattempts_rankingpoints', 'mod_playerwords'),
        'datelabel'        => get_string('myattempts_date', 'mod_playerwords'),
        'emptylabel'       => get_string('myattempts_empty', 'mod_playerwords'),
        'rows'             => $history['rows'],
        'isempty'          => $history['isempty'],
        'showranking'      => $history['showranking'],
        'showgrade'        => $history['showgrade'],
        'gradesummary'     => get_string('gradesofar', 'mod_playerwords', (object)[
            'method'   => $history['grademethodname'],
            'mygrade'  => $history['grade'],
            'maxgrade' => $history['maxgrade'],
        ]),
    ];

    $PAGE->set_title(get_string('myattempts_title', 'mod_playerwords') . ' — ' . format_string($instance->name));

    echo $OUTPUT->header();
    echo $OUTPUT->render_from_template('mod_playerwords/myattempts', $templatecontext);
    echo $OUTPUT->footer();
}
