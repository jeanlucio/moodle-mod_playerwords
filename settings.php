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
 * Plugin administration settings.
 *
 * @package    mod_playerwords
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings->add(new admin_setting_configtextarea(
        'mod_playerwords/glossarystopwords',
        get_string('glossarystopwords', 'mod_playerwords'),
        get_string('glossarystopwords_desc', 'mod_playerwords'),
        ''
    ));

    if (!\mod_playerwords\local\hud_service::is_installed()) {
        $settings->add(new admin_setting_heading(
            'mod_playerwords/hudnotinstalled',
            get_string('hud_notinstalled_heading', 'mod_playerwords'),
            get_string('hud_notinstalled_desc', 'mod_playerwords')
        ));
    }
}
