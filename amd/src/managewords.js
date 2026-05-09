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
 * Provides select-all, bulk delete and single-row delete via a shared bulk form.
 * Individual "Delete" buttons pre-select their own checkbox then trigger the same
 * confirmation modal before submitting, avoiding nested-form constraints.
 *
 * @module     mod_playerwords/managewords
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['core/modal_save_cancel', 'core/modal_events', 'core/str'], function(ModalSaveCancel, ModalEvents, Str) {
    'use strict';

    var bulkForm = null;
    var selectAllCheckbox = null;
    var bulkDeleteBtn = null;

    /**
     * Enables or disables the bulk-delete button based on checked row count.
     */
    var updateBulkButton = function() {
        if (!bulkDeleteBtn) {
            return;
        }
        var count = document.querySelectorAll('.playerwords-bulk-check:checked').length;
        bulkDeleteBtn.disabled = count === 0;
    };

    /**
     * Opens the Moodle save/cancel modal then calls onConfirm when the user confirms.
     *
     * @param {string} title Modal title string.
     * @param {string} body  Modal body string.
     * @param {Function} onConfirm Called when the user clicks the save button.
     */
    var showDeleteModal = function(title, body, onConfirm) {
        Promise.all([
            ModalSaveCancel.create({
                title: title,
                body: body,
                show: true,
                removeOnClose: true,
            }),
            Str.get_string('yes', 'core'),
        ]).then(function(results) {
            var modal = results[0];
            var yesStr = results[1];
            modal.setSaveButtonText(yesStr);
            modal.getRoot().on(ModalEvents.save, function() {
                onConfirm();
            });
            return;
        }).catch(window.console.error);
    };

    /**
     * Wires up the "select all" checkbox and keeps indeterminate state in sync.
     */
    var initSelectAll = function() {
        selectAllCheckbox = document.getElementById('playerwords-select-all');
        if (!selectAllCheckbox) {
            return;
        }

        selectAllCheckbox.addEventListener('change', function() {
            document.querySelectorAll('.playerwords-bulk-check').forEach(function(cb) {
                cb.checked = selectAllCheckbox.checked;
            });
            updateBulkButton();
        });

        document.querySelectorAll('.playerwords-bulk-check').forEach(function(cb) {
            cb.addEventListener('change', function() {
                var total   = document.querySelectorAll('.playerwords-bulk-check').length;
                var checked = document.querySelectorAll('.playerwords-bulk-check:checked').length;
                selectAllCheckbox.checked       = checked === total;
                selectAllCheckbox.indeterminate = checked > 0 && checked < total;
                updateBulkButton();
            });
        });
    };

    /**
     * Attaches the confirmation modal to the bulk-delete button.
     */
    var initBulkDelete = function() {
        bulkDeleteBtn = document.getElementById('playerwords-bulk-delete-btn');
        bulkForm      = document.getElementById('playerwords-bulk-form');
        if (!bulkDeleteBtn || !bulkForm) {
            return;
        }

        bulkDeleteBtn.addEventListener('click', function() {
            showDeleteModal(
                bulkDeleteBtn.dataset.title,
                bulkDeleteBtn.dataset.confirm,
                function() { bulkForm.submit(); }
            );
        });
    };

    /**
     * Attaches a click handler to each single-row delete button.
     *
     * Unchecks all checkboxes, checks only the clicked row, then shows the modal
     * and submits the shared bulk form on confirmation.
     */
    var initSingleDelete = function() {
        document.querySelectorAll('.playerwords-single-delete-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var wordid = btn.dataset.wordid;
                document.querySelectorAll('.playerwords-bulk-check').forEach(function(cb) {
                    cb.checked = cb.value === wordid;
                });
                if (selectAllCheckbox) {
                    selectAllCheckbox.checked       = false;
                    selectAllCheckbox.indeterminate = false;
                }
                showDeleteModal(
                    btn.dataset.title,
                    btn.dataset.confirm,
                    function() { bulkForm.submit(); }
                );
            });
        });
    };

    return {
        /**
         * Entry point called by managewords.php via $PAGE->requires->js_call_amd().
         */
        init: function() {
            initSelectAll();
            initBulkDelete();
            initSingleDelete();
        },
    };
});
