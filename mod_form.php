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
     * Source type bit flag for AI generated words.
     */
    private const SOURCE_AI = 4;

    /**
     * Defines forms elements.
     *
     * @return void
     */
    public function definition(): void {
        global $CFG;

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

        $mform->addElement(
            'advcheckbox',
            'source_ai',
            get_string('source_ai', 'mod_playerwords')
        );
        $mform->setType('source_ai', PARAM_INT);
        $mform->setDefault('source_ai', 0);

        $mform->addElement(
            'select',
            'aigranularity',
            get_string('aigranularity', 'mod_playerwords'),
            [
                'course' => get_string('aigranularity_course', 'mod_playerwords'),
                'section' => get_string('aigranularity_section', 'mod_playerwords'),
                'forum' => get_string('aigranularity_forum', 'mod_playerwords'),
            ]
        );
        $mform->setType('aigranularity', PARAM_ALPHA);
        $mform->setDefault('aigranularity', 'course');
        $mform->hideIf('aigranularity', 'source_ai', 'notchecked');

        $mform->addElement('header', 'gameplayheader', get_string('gameplayheader', 'mod_playerwords'));

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
        $mform->setDefault('max_length', 12);
        $mform->addRule('max_length', null, 'numeric', null, 'client');

        $mform->addElement('text', 'timer_seconds', get_string('timer_seconds', 'mod_playerwords'));
        $mform->setType('timer_seconds', PARAM_INT);
        $mform->setDefault('timer_seconds', 0);
        $mform->addRule('timer_seconds', null, 'numeric', null, 'client');

        $mform->addElement(
            'advcheckbox',
            'ignore_accents',
            get_string('ignore_accents', 'mod_playerwords')
        );
        $mform->setType('ignore_accents', PARAM_INT);
        $mform->setDefault('ignore_accents', 1);

        $mform->addElement(
            'advcheckbox',
            'show_ranking',
            get_string('show_ranking', 'mod_playerwords')
        );
        $mform->setType('show_ranking', PARAM_INT);
        $mform->setDefault('show_ranking', 1);

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
        if (!empty($data['source_ai'])) {
            $sources |= self::SOURCE_AI;
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

        if ((int)$data['timer_seconds'] < 0) {
            $errors['timer_seconds'] = get_string('error_timerseconds', 'mod_playerwords');
        }

        if (
            !empty($data['completionattemptsenabled']) &&
            ((int)$data['completionattempts'] < 1)
        ) {
            $errors['completionattemptsgroup'] = get_string('error_completionattempts', 'mod_playerwords');
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

        if (!empty($defaultvalues['sources'])) {
            $defaultvalues['source_manual'] = (int)(($defaultvalues['sources'] & self::SOURCE_MANUAL) !== 0);
            $defaultvalues['source_glossary'] = (int)(($defaultvalues['sources'] & self::SOURCE_GLOSSARY) !== 0);
            $defaultvalues['source_ai'] = (int)(($defaultvalues['sources'] & self::SOURCE_AI) !== 0);
        }

        if (!empty($defaultvalues['completionattempts'])) {
            $defaultvalues['completionattemptsenabled'] = 1;
        }
    }
}
