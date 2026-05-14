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
 * Form definition for mod_playerwords.
 *
 * @package mod_playerwords
 * @copyright  2026 Jean Lúcio
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
require_once(__DIR__ . '/../../course/moodleform_mod.php');
require_once(__DIR__ . '/lib.php');

/**
 * Activity settings form for PlayerWords.
 */
class mod_playerwords_mod_form extends moodleform_mod {
    /**
     * Source type bit flag for manual words.
     */
    private const SOURCE_MANUAL = 1;

    /**
     * Source type bit flag for glossary words.
     */
    private const SOURCE_GLOSSARY = 2;

    /**
     * Defines forms elements.
     *
     * @return void
     */
    public function definition(): void {
        global $CFG, $COURSE, $DB;

        $mform = $this->_form;

        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('name'), ['size' => '64']);
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEANHTML);
        }
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $this->standard_intro_elements();

        $mform->addElement('header', 'playersourcesheader', get_string('playersourcesheader', 'mod_playerwords'));

        $mform->addElement(
            'advcheckbox',
            'source_manual',
            get_string('source_manual', 'mod_playerwords')
        );
        $mform->setType('source_manual', PARAM_INT);
        $mform->setDefault('source_manual', 1);

        $mform->addElement(
            'advcheckbox',
            'source_glossary',
            get_string('source_glossary', 'mod_playerwords')
        );
        $mform->setType('source_glossary', PARAM_INT);
        $mform->setDefault('source_glossary', 0);

        $glossaryoptions = [0 => get_string('glossaryid_all', 'mod_playerwords')];
        $courseglossaries = $DB->get_records('glossary', ['course' => $COURSE->id], 'name ASC', 'id, name');
        foreach ($courseglossaries as $glossary) {
            $glossaryoptions[$glossary->id] = format_string($glossary->name);
        }
        $mform->addElement(
            'select',
            'glossaryid',
            get_string('glossaryid', 'mod_playerwords'),
            $glossaryoptions
        );
        $mform->setType('glossaryid', PARAM_INT);
        $mform->setDefault('glossaryid', 0);
        $mform->hideIf('glossaryid', 'source_glossary', 'notchecked');

        $mform->addElement('header', 'gameplayheader', get_string('gameplayheader', 'mod_playerwords'));

        $mform->addElement(
            'select',
            'wordmode',
            get_string('wordmode', 'mod_playerwords'),
            [
                PLAYERWORDS_WORDMODE_RANDOM  => get_string('wordmode_random', 'mod_playerwords'),
                PLAYERWORDS_WORDMODE_SHARED => get_string('wordmode_shared', 'mod_playerwords'),
            ]
        );
        $mform->setType('wordmode', PARAM_INT);
        $mform->setDefault('wordmode', PLAYERWORDS_WORDMODE_RANDOM);

        $mform->addElement('text', 'max_attempts', get_string('max_attempts', 'mod_playerwords'));
        $mform->setType('max_attempts', PARAM_INT);
        $mform->setDefault('max_attempts', 6);
        $mform->addRule('max_attempts', null, 'numeric', null, 'client');

        $mform->addElement('text', 'min_length', get_string('min_length', 'mod_playerwords'));
        $mform->setType('min_length', PARAM_INT);
        $mform->setDefault('min_length', 4);
        $mform->addRule('min_length', null, 'numeric', null, 'client');

        $mform->addElement('text', 'max_length', get_string('max_length', 'mod_playerwords'));
        $mform->setType('max_length', PARAM_INT);
        $mform->setDefault('max_length', 6);
        $mform->addRule('max_length', null, 'numeric', null, 'client');

        $mform->addElement('text', 'timer_minutes', get_string('timer_minutes', 'mod_playerwords'));
        $mform->setType('timer_minutes', PARAM_INT);
        $mform->setDefault('timer_minutes', 0);
        $mform->addRule('timer_minutes', null, 'numeric', null, 'client');

        $mform->addElement(
            'select',
            'show_ranking',
            get_string('show_ranking', 'mod_playerwords'),
            [0 => get_string('no'), 1 => get_string('yes')]
        );
        $mform->setType('show_ranking', PARAM_INT);
        $mform->setDefault('show_ranking', 1);

        $maxroundsoptions = [0 => get_string('max_rounds_unlimited', 'mod_playerwords')];
        for ($i = 1; $i <= 10; $i++) {
            $maxroundsoptions[$i] = $i;
        }
        $mform->addElement('select', 'max_rounds', get_string('max_rounds', 'mod_playerwords'), $maxroundsoptions);
        $mform->setType('max_rounds', PARAM_INT);
        $mform->setDefault('max_rounds', 0);

        $cooldowngroup = [];
        $cooldowngroup[] = $mform->createElement('text', 'cooldown_amount', '', ['size' => 5]);
        $cooldowngroup[] = $mform->createElement(
            'select',
            'cooldown_unit',
            '',
            [
                'minutes' => get_string('cooldown_unit_minutes', 'mod_playerwords'),
                'hours'   => get_string('cooldown_unit_hours', 'mod_playerwords'),
                'days'    => get_string('cooldown_unit_days', 'mod_playerwords'),
            ]
        );
        $mform->addGroup(
            $cooldowngroup,
            'cooldowngroup',
            get_string('cooldown_label', 'mod_playerwords'),
            [' '],
            false
        );
        $mform->setType('cooldown_amount', PARAM_INT);
        $mform->setType('cooldown_unit', PARAM_ALPHA);
        $mform->setDefault('cooldown_amount', 1);
        $mform->setDefault('cooldown_unit', 'days');

        $this->standard_grading_coursemodule_elements();
        $mform->setDefault('grade[modgrade_type]', 'none');

        $mform->addElement(
            'select',
            'grademethod',
            get_string('grademethod', 'mod_playerwords'),
            [
                PLAYERWORDS_GRADE_HIGHEST     => get_string('grademethod_highest', 'mod_playerwords'),
                PLAYERWORDS_GRADE_AVERAGE     => get_string('grademethod_average', 'mod_playerwords'),
                PLAYERWORDS_GRADE_FIRST       => get_string('grademethod_first', 'mod_playerwords'),
                PLAYERWORDS_GRADE_LAST        => get_string('grademethod_last', 'mod_playerwords'),
                PLAYERWORDS_GRADE_AVERAGE_ALL => get_string('grademethod_average_all', 'mod_playerwords'),
            ]
        );
        $mform->setType('grademethod', PARAM_INT);
        $mform->setDefault('grademethod', PLAYERWORDS_GRADE_HIGHEST);
        $mform->hideIf('grademethod', 'grade[modgrade_type]', 'eq', 'none');

        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    /**
     * Custom validation for PlayerWords settings.
     *
     * @param array $data Form data.
     * @param array $files Submitted files.
     * @return array
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);

        $sources = 0;
        if (!empty($data['source_manual'])) {
            $sources |= self::SOURCE_MANUAL;
        }
        if (!empty($data['source_glossary'])) {
            $sources |= self::SOURCE_GLOSSARY;
        }
        if ($sources === 0) {
            $errors['source_manual'] = get_string('error_atleastonesource', 'mod_playerwords');
        }

        if ((int)$data['max_attempts'] < 1) {
            $errors['max_attempts'] = get_string('error_maxattempts', 'mod_playerwords');
        }

        if ((int)$data['min_length'] < 1) {
            $errors['min_length'] = get_string('error_minlength', 'mod_playerwords');
        }

        if ((int)$data['max_length'] < (int)$data['min_length']) {
            $errors['max_length'] = get_string('error_maxlength', 'mod_playerwords');
        }

        if ((int)$data['timer_minutes'] < 0) {
            $errors['timer_minutes'] = get_string('error_timerseconds', 'mod_playerwords');
        }

        if ((int)$data['cooldown_amount'] < 0) {
            $errors['cooldowngroup'] = get_string('error_cooldown', 'mod_playerwords');
        }

        if (
            !empty($data['completionattemptsenabled']) &&
            ((int)$data['completionattempts'] < 1)
        ) {
            $errors['completionattemptsgroup'] = get_string('error_completionattempts', 'mod_playerwords');
        }

        if (
            (int)$data['grademethod'] === PLAYERWORDS_GRADE_AVERAGE_ALL &&
            (int)$data['max_rounds'] === 0
        ) {
            $errors['grademethod'] = get_string('error_grademethod_average_all', 'mod_playerwords');
        }

        return $errors;
    }

    /**
     * Adds custom completion rules to Moodle completion section.
     *
     * @return array
     */
    public function add_completion_rules(): array {
        $mform = $this->_form;

        $group = [];
        $group[] = $mform->createElement('checkbox', 'completionattemptsenabled', '', '');
        $group[] = $mform->createElement('text', 'completionattempts', '', ['size' => 3]);
        $mform->addGroup(
            $group,
            'completionattemptsgroup',
            get_string('completionattemptsgroup', 'mod_playerwords'),
            [' '],
            false
        );

        $mform->setType('completionattempts', PARAM_INT);
        $mform->setDefault('completionattempts', 1);
        $mform->disabledIf('completionattempts', 'completionattemptsenabled', 'notchecked');

        return ['completionattemptsgroup'];
    }

    /**
     * Returns whether at least one completion rule is enabled.
     *
     * @param array $data Form data.
     * @return bool
     */
    public function completion_rule_enabled($data): bool {
        return !empty($data['completionattemptsenabled']) && (int)$data['completionattempts'] > 0;
    }

    /**
     * Normalises form data before saving.
     *
     * @param array $defaultvalues Default form values.
     * @return void
     */
    public function data_preprocessing(&$defaultvalues): void {
        parent::data_preprocessing($defaultvalues);

        if (isset($defaultvalues['grade']) && (float)$defaultvalues['grade'] > 0) {
            $defaultvalues['grade'] = (int)round((float)$defaultvalues['grade']);
        }

        if (!empty($defaultvalues['sources'])) {
            $defaultvalues['source_manual'] = (int)(($defaultvalues['sources'] & self::SOURCE_MANUAL) !== 0);
            $defaultvalues['source_glossary'] = (int)(($defaultvalues['sources'] & self::SOURCE_GLOSSARY) !== 0);
        }

        if (!empty($defaultvalues['completionattempts'])) {
            $defaultvalues['completionattemptsenabled'] = 1;
        }

        if (isset($defaultvalues['cooldown_seconds'])) {
            $seconds = (int)$defaultvalues['cooldown_seconds'];
            if ($seconds === 0) {
                $defaultvalues['cooldown_amount'] = 0;
                $defaultvalues['cooldown_unit']   = 'minutes';
            } else if ($seconds % 86400 === 0) {
                $defaultvalues['cooldown_amount'] = $seconds / 86400;
                $defaultvalues['cooldown_unit']   = 'days';
            } else if ($seconds % 3600 === 0) {
                $defaultvalues['cooldown_amount'] = $seconds / 3600;
                $defaultvalues['cooldown_unit']   = 'hours';
            } else {
                $defaultvalues['cooldown_amount'] = max(1, (int) round($seconds / 60));
                $defaultvalues['cooldown_unit']   = 'minutes';
            }
        }

        if (isset($defaultvalues['timer_seconds'])) {
            $defaultvalues['timer_minutes'] = (int)round((int)$defaultvalues['timer_seconds'] / 60);
        }
    }
}
