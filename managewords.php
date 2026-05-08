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
 * Manage manual words for one PlayerWords activity.
 *
 * @package mod_playerwords
 * @copyright 2026 Jean Lúcio
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');

use mod_playerwords\local\words_repository;

$id = required_param('id', PARAM_INT);
$cm = get_coursemodule_from_id('playerwords', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$instance = $DB->get_record('playerwords', ['id' => $cm->instance], '*', MUST_EXIST);
$context = context_module::instance($cm->id);

require_login($course, true, $cm);
require_capability('mod/playerwords:addinstance', $context);

$notification = null;
$notificationtype = null;

if (optional_param('addword', 0, PARAM_BOOL)) {
    require_sesskey();

    $manualword = trim(required_param('manualword', PARAM_TEXT));
    $manualhint = trim(optional_param('manualhint', '', PARAM_TEXT));
    $wordlength = core_text::strlen($manualword);

    if ($manualword === '') {
        $notification = get_string('error_manualwordrequired', 'mod_playerwords');
        $notificationtype = 'warning';
    } else if ($wordlength < (int)$instance->min_length || $wordlength > (int)$instance->max_length) {
        $notification = get_string(
            'error_manualwordlength',
            'mod_playerwords',
            ['min' => (int)$instance->min_length, 'max' => (int)$instance->max_length]
        );
        $notificationtype = 'warning';
    } else {
        words_repository::add_manual_word((int)$instance->id, (int)$USER->id, $manualword, $manualhint);
        $notification = get_string('manualwordadded', 'mod_playerwords');
        $notificationtype = 'success';
    }
}

$recentwords = words_repository::get_recent_words((int)$instance->id, 20);

$PAGE->set_url('/mod/playerwords/managewords.php', ['id' => $cm->id]);
$PAGE->set_title(get_string('managewordslabel', 'mod_playerwords'));
$PAGE->set_heading($course->fullname);
$PAGE->set_pagelayout('incourse');

$templaterows = [];
foreach ($recentwords as $recentword) {
    $sourcelabel = get_string('source_' . $recentword->source, 'mod_playerwords');
    $statuslabel = ((int)$recentword->approved === 1) ?
        get_string('approvedstatus', 'mod_playerwords') :
        get_string('pendingstatus', 'mod_playerwords');

    $templaterows[] = [
        'word' => $recentword->word,
        'source' => $sourcelabel,
        'approved' => $statuslabel,
    ];
}

$templatecontext = [
    'cmid' => $cm->id,
    'sesskey' => sesskey(),
    'backtogameurl' => (new moodle_url('/mod/playerwords/view.php', ['id' => $cm->id]))->out(false),
    'backtogamebutton' => get_string('backtogamebutton', 'mod_playerwords'),
    'managewordslabel' => get_string('managewordslabel', 'mod_playerwords'),
    'manualwordlabel' => get_string('manualwordlabel', 'mod_playerwords'),
    'manualhintlabel' => get_string('manualhintlabel', 'mod_playerwords'),
    'manualwordplaceholder' => get_string('manualwordplaceholder', 'mod_playerwords'),
    'manualhintplaceholder' => get_string('manualhintplaceholder', 'mod_playerwords'),
    'addwordbutton' => get_string('addwordbutton', 'mod_playerwords'),
    'recentwordslabel' => get_string('recentwordslabel', 'mod_playerwords'),
    'nowordsyet' => get_string('nowordsyet', 'mod_playerwords'),
    'wordcolumnlabel' => get_string('wordcolumnlabel', 'mod_playerwords'),
    'sourcecolumnlabel' => get_string('sourcecolumnlabel', 'mod_playerwords'),
    'statuscolumnlabel' => get_string('statuscolumnlabel', 'mod_playerwords'),
    'recentwords' => $templaterows,
    'hasrecentwords' => !empty($templaterows),
];

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($instance->name));

if (!empty($notification)) {
    echo $OUTPUT->notification($notification, $notificationtype);
}
echo $OUTPUT->render_from_template('mod_playerwords/managewords', $templatecontext);
echo $OUTPUT->footer();
