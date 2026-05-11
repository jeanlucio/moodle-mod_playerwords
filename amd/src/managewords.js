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
 * Provides select-all, bulk approve, bulk delete and single-row delete via a
 * shared bulk form. Approve and delete buttons track separate counts: the approve
 * button counts only pending (unapproved) checked rows; the delete button counts
 * all checked rows. Individual "Delete" buttons pre-select their own checkbox then
 * trigger a confirmation modal before submitting.
 *
 * @module     mod_playerwords/managewords
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['core/modal_save_cancel', 'core/modal_events', 'core/str'], function(ModalSaveCancel, ModalEvents, Str) {
    'use strict';

    var bulkForm = null;
    var bulkActionField = null;
    var selectAllCheckbox = null;
    var bulkDeleteBtn = null;
    var bulkApproveBtn = null;

    /**
     * Refreshes both bulk-action button states and labels based on current selection.
     *
     * The approve button is enabled only when at least one pending word is checked.
     * The delete button is enabled when at least one word (any status) is checked.
     * Both labels show the relevant count in parentheses.
     */
    var updateBulkButtons = function() {
        var totalCount = document.querySelectorAll('.playerwords-bulk-check:checked').length;
        var pendingCount = document.querySelectorAll(
            '.playerwords-bulk-check:checked[data-pending="1"]'
        ).length;

        if (bulkDeleteBtn) {
            bulkDeleteBtn.disabled = totalCount === 0;
            bulkDeleteBtn.textContent = bulkDeleteBtn.dataset.labelbase + ' (' + totalCount + ')';
        }

        if (bulkApproveBtn) {
            bulkApproveBtn.disabled = pendingCount === 0;
            bulkApproveBtn.textContent = bulkApproveBtn.dataset.labelbase + ' (' + pendingCount + ')';
        }
    };

    /**
     * Opens the Moodle save/cancel modal then calls onConfirm when the user confirms.
     *
     * @param {string} title Modal title string.
     * @param {string} body  Modal body string.
     * @param {Function} onConfirm Called when the user clicks the save button.
     */
    var showModal = function(title, body, onConfirm) {
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
            updateBulkButtons();
        });

        document.querySelectorAll('.playerwords-bulk-check').forEach(function(cb) {
            cb.addEventListener('change', function() {
                var total = document.querySelectorAll('.playerwords-bulk-check').length;
                var checked = document.querySelectorAll('.playerwords-bulk-check:checked').length;
                selectAllCheckbox.checked = checked === total;
                selectAllCheckbox.indeterminate = checked > 0 && checked < total;
                updateBulkButtons();
            });
        });
    };

    /**
     * Wires up both bulk-action buttons (approve and delete).
     *
     * Each button sets the hidden bulkaction field to the appropriate action
     * value before submitting the shared form.
     */
    var initBulkActions = function() {
        bulkForm = document.getElementById('playerwords-bulk-form');
        bulkActionField = document.getElementById('playerwords-bulk-action');
        bulkDeleteBtn = document.getElementById('playerwords-bulk-delete-btn');
        bulkApproveBtn = document.getElementById('playerwords-bulk-approve-btn');

        if (!bulkForm) {
            return;
        }

        if (bulkDeleteBtn) {
            bulkDeleteBtn.addEventListener('click', function() {
                showModal(
                    bulkDeleteBtn.dataset.title,
                    bulkDeleteBtn.dataset.confirm,
                    function() {
                        if (bulkActionField) {
                            bulkActionField.value = 'delete';
                        }
                        bulkForm.submit();
                    }
                );
            });
        }

        if (bulkApproveBtn) {
            bulkApproveBtn.addEventListener('click', function() {
                showModal(
                    bulkApproveBtn.dataset.title,
                    bulkApproveBtn.dataset.confirm,
                    function() {
                        if (bulkActionField) {
                            bulkActionField.value = 'approve';
                        }
                        bulkForm.submit();
                    }
                );
            });
        }
    };

    /**
     * Attaches a click handler to each single-row delete button.
     *
     * Unchecks all checkboxes, checks only the clicked row, then shows a
     * confirmation modal and submits with bulkaction=delete on confirmation.
     */
    var initSingleDelete = function() {
        document.querySelectorAll('.playerwords-single-delete-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var wordid = btn.dataset.wordid;
                document.querySelectorAll('.playerwords-bulk-check').forEach(function(cb) {
                    cb.checked = cb.value === wordid;
                });
                if (selectAllCheckbox) {
                    selectAllCheckbox.checked = false;
                    selectAllCheckbox.indeterminate = false;
                }
                showModal(
                    btn.dataset.title,
                    btn.dataset.confirm,
                    function() {
                        if (bulkActionField) {
                            bulkActionField.value = 'delete';
                        }
                        bulkForm.submit();
                    }
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
            initBulkActions();
            initSingleDelete();
            updateBulkButtons();
        },
    };
});
