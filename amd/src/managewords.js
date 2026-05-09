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
 * AMD module for mod_playerwords manage-words page interactions.
 *
 * Attaches a Moodle confirmation modal to each word delete form.
 *
 * @module     mod_playerwords/managewords
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['core/modal_save_cancel', 'core/modal_events', 'core/str'], function(ModalSaveCancel, ModalEvents, Str) {
    'use strict';

    /**
     * Attaches a confirmation modal to every delete form on the page.
     */
    var initDeleteForms = function() {
        var forms = document.querySelectorAll('.mod-playerwords-delete-form');
        forms.forEach(function(form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                Promise.all([
                    ModalSaveCancel.create({
                        title: form.dataset.title,
                        body: form.dataset.confirm,
                        show: true,
                        removeOnClose: true,
                    }),
                    Str.get_string('yes', 'core'),
                ]).then(function(results) {
                    var modal = results[0];
                    var yesStr = results[1];
                    modal.setSaveButtonText(yesStr);
                    modal.getRoot().on(ModalEvents.save, function() {
                        form.submit();
                    });
                    return;
                }).catch(window.console.error);
            });
        });
    };

    return {
        /**
         * Entry point called by managewords.php via $PAGE->requires->js_call_amd().
         */
        init: function() {
            initDeleteForms();
        },
    };
});
