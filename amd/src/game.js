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
 * AMD module for mod_playerwords game interactions.
 *
 * Handles the forfeit confirmation dialog and the post-round cooldown countdown.
 *
 * @module     mod_playerwords/game
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['core/modal_save_cancel', 'core/modal_events', 'core/str'], function(ModalSaveCancel, ModalEvents, Str) {
    'use strict';

    /**
     * Attaches a Moodle confirmation modal to the forfeit form submit event.
     */
    var initForfeit = function() {
        var form = document.getElementById('playerwords-forfeit-form');
        if (!form) {
            return;
        }
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
    };

    /**
     * Updates the countdown span every second until the timestamp is reached.
     *
     * @param {HTMLElement} el  The span element to update.
     * @param {number}      until Unix timestamp (seconds) when the cooldown ends.
     */
    var tick = function(el, until) {
        var remaining = until - Math.floor(Date.now() / 1000);
        if (remaining <= 0) {
            el.textContent = el.dataset.ready;
            return;
        }
        var h = Math.floor(remaining / 3600);
        var m = Math.floor((remaining % 3600) / 60);
        var s = remaining % 60;
        var parts = [];
        if (h > 0) {
            parts.push(h + 'h');
        }
        parts.push(String(m).padStart(2, '0') + 'm');
        parts.push(String(s).padStart(2, '0') + 's');
        el.textContent = parts.join(' ');
        window.setTimeout(function() {
            tick(el, until);
        }, 1000);
    };

    /**
     * Starts the cooldown countdown if the element is present.
     *
     * @param {number} until Unix timestamp when the cooldown ends.
     */
    var initCountdown = function(until) {
        var el = document.getElementById('playerwords-cooldown-countdown');
        if (!el) {
            return;
        }
        tick(el, until);
    };

    return {
        /**
         * Entry point called by view.php via $PAGE->requires->js_call_amd().
         *
         * @param {number} cooldownUntil Unix timestamp when the cooldown ends (0 = disabled).
         */
        init: function(cooldownUntil) {
            initForfeit();
            if (cooldownUntil > 0) {
                initCountdown(cooldownUntil);
            }
        },
    };
});
