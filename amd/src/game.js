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
 * Handles the forfeit confirmation dialog, the post-round cooldown countdown,
 * and the virtual keyboard.
 *
 * @module     mod_playerwords/game
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['core/modal_save_cancel', 'core/modal_events', 'core/str'], function(ModalSaveCancel, ModalEvents, Str) {
    'use strict';

    var SHAKE_KEY = 'pw_shake_pending';

    /**
     * Strips non-letter characters from the guess input as the user types.
     *
     * Handles physical keyboard input; virtual keyboard only sends letters by design.
     */
    var initInputFilter = function() {
        var input = document.getElementById('playerwords-guess');
        if (!input) {
            return;
        }
        input.addEventListener('input', function() {
            var filtered = input.value.replace(/[^\p{L}]/gu, '');
            if (filtered !== input.value) {
                input.value = filtered;
            }
        });
    };

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
     * @param {HTMLElement} el    The span element to update.
     * @param {number}      until Unix timestamp (seconds) when the cooldown ends.
     * @param {number}      cmid  Course-module id used to build the reload URL.
     */
    var tick = function(el, until, cmid) {
        var remaining = until - Math.floor(Date.now() / 1000);
        if (remaining <= 0) {
            window.location.href = M.cfg.wwwroot + '/mod/playerwords/view.php?id=' + cmid;
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
            tick(el, until, cmid);
        }, 1000);
    };

    /**
     * Formats a seconds count as "Xmin YYs".
     *
     * @param {number} seconds Total seconds remaining.
     * @returns {string}
     */
    var formatGameTime = function(seconds) {
        var m = Math.floor(seconds / 60);
        var s = seconds % 60;
        return m + 'min ' + String(s).padStart(2, '0') + 's';
    };

    /**
     * Ticks the round timer down one second and re-schedules itself.
     *
     * Adds the urgency class once remaining time falls below the threshold,
     * and submits the forfeit form when time runs out.
     *
     * @param {HTMLElement} el        The span showing the countdown.
     * @param {number}      remaining Seconds remaining.
     * @param {number}      threshold Seconds at which to add the urgency class.
     */
    var tickTimer = function(el, remaining, threshold) {
        el.textContent = formatGameTime(remaining);
        if (remaining <= threshold) {
            el.classList.add('pw-timer-urgent');
        }
        if (remaining <= 0) {
            var timeoutForm = document.getElementById('playerwords-timeout-form');
            if (timeoutForm) {
                timeoutForm.submit();
            }
            return;
        }
        window.setTimeout(function() {
            tickTimer(el, remaining - 1, threshold);
        }, 1000);
    };

    /**
     * Starts the round timer countdown if the timer element is present and
     * there is an active round (forfeit form exists).
     *
     * @param {number} timeleft   Seconds remaining at page load.
     * @param {number} timertotal Total seconds configured for the round.
     */
    var initTimer = function(timeleft, timertotal) {
        var el = document.getElementById('playerwords-timer-countdown');
        if (!el || timeleft <= 0) {
            return;
        }
        el.textContent = formatGameTime(timeleft);
        if (!document.getElementById('playerwords-forfeit-form')) {
            return;
        }
        var threshold = timertotal > 0 ? Math.max(10, Math.floor(timertotal * 0.2)) : 30;
        tickTimer(el, timeleft, threshold);
    };

    /**
     * Starts the cooldown countdown if the element is present.
     *
     * @param {number} until Unix timestamp when the cooldown ends.
     * @param {number} cmid  Course-module id used to build the reload URL.
     */
    var initCountdown = function(until, cmid) {
        var el = document.getElementById('playerwords-cooldown-countdown');
        if (!el) {
            return;
        }
        tick(el, until, cmid);
    };

    /**
     * Applies state classes to keyboard keys from the rendered grid, then wires
     * key-click events to the guess input and form.
     */
    var initKeyboard = function() {
        var keyboard = document.getElementById('playerwords-keyboard');
        if (!keyboard) {
            return;
        }
        var input = document.getElementById('playerwords-guess');
        var form = document.querySelector('.mod-playerwords-form');
        if (!input || !form) {
            return;
        }

        // State precedence: correct (3) > present (2) > absent (1).
        var stateRank = {absent: 1, present: 2, correct: 3};
        var letterStates = {};

        document.querySelectorAll('.mod-playerwords-cell').forEach(function(cell) {
            var letter = cell.textContent.trim().toUpperCase();
            if (!letter) {
                return;
            }
            var bestState = null;
            var bestRank = 0;
            Object.keys(stateRank).forEach(function(s) {
                if (cell.classList.contains('is-' + s) && stateRank[s] > bestRank) {
                    bestRank = stateRank[s];
                    bestState = s;
                }
            });
            if (bestState && (!letterStates[letter] || stateRank[bestState] > stateRank[letterStates[letter]])) {
                letterStates[letter] = bestState;
            }
        });

        keyboard.querySelectorAll('[data-key]').forEach(function(btn) {
            var key = btn.dataset.key;
            if (letterStates[key]) {
                btn.classList.add('is-' + letterStates[key]);
            }
        });

        keyboard.addEventListener('click', function(e) {
            var btn = e.target.closest('[data-key]');
            if (!btn) {
                return;
            }
            var key = btn.dataset.key;
            if (key === 'BACKSPACE') {
                input.value = input.value.slice(0, -1);
            } else if (key === 'ENTER') {
                if (form.requestSubmit) {
                    form.requestSubmit();
                } else {
                    form.submit();
                }
            } else {
                var max = parseInt(input.getAttribute('maxlength'), 10);
                if (input.value.length < max) {
                    input.value += key;
                }
            }
            input.dispatchEvent(new Event('input'));
        });
    };

    /**
     * Finds the first fully-empty grid row, marks it as active, and syncs the
     * guess input value into it as the user types.
     */
    var initGridPreview = function() {
        var input = document.getElementById('playerwords-guess');
        if (!input) {
            return;
        }

        var activeRow = null;
        var activeCells = null;

        document.querySelectorAll('.mod-playerwords-row').forEach(function(row) {
            if (activeRow) {
                return;
            }
            var cells = Array.from(row.querySelectorAll('.mod-playerwords-cell'));
            var allEmpty = cells.every(function(c) {
                return c.classList.contains('is-empty');
            });
            if (allEmpty) {
                activeRow = row;
                activeCells = cells;
            }
        });

        if (!activeRow) {
            return;
        }

        activeRow.classList.add('pw-row-active');

        var updatePreview = function() {
            var val = input.value.toUpperCase();
            activeCells.forEach(function(cell, i) {
                cell.textContent = i < val.length ? val[i] : '';
            });
        };

        input.addEventListener('input', updatePreview);
    };

    /**
     * Sets a sessionStorage flag before the guess form submits so the next
     * page load knows to shake the last filled row.
     */
    var initGuessSubmitFlag = function() {
        var form = document.querySelector('.mod-playerwords-form');
        if (!form) {
            return;
        }
        form.addEventListener('submit', function() {
            try {
                window.sessionStorage.setItem(SHAKE_KEY, '1');
            } catch (e) {
                // Storage may be unavailable; the shake animation is non-critical.
            }
        });
    };

    /**
     * On page load, if a guess was just submitted and the round is still active,
     * plays a shake animation on the last filled row.
     */
    var initShakeLastRow = function() {
        var pending;
        try {
            pending = window.sessionStorage.getItem(SHAKE_KEY);
            window.sessionStorage.removeItem(SHAKE_KEY);
        } catch (e) {
            return;
        }
        if (!pending) {
            return;
        }
        if (!document.getElementById('playerwords-forfeit-form')) {
            return;
        }

        var lastFilledRow = null;
        document.querySelectorAll('.mod-playerwords-row').forEach(function(row) {
            var cells = row.querySelectorAll('.mod-playerwords-cell');
            var hasFilled = Array.from(cells).some(function(c) {
                return !c.classList.contains('is-empty') && c.textContent.trim();
            });
            if (hasFilled) {
                lastFilledRow = row;
            }
        });

        if (lastFilledRow) {
            lastFilledRow.classList.add('pw-shake');
            window.setTimeout(function() {
                lastFilledRow.classList.remove('pw-shake');
            }, 500);
        }
    };

    return {
        /**
         * Entry point called by view.php via $PAGE->requires->js_call_amd().
         *
         * @param {number} cooldownUntil Unix timestamp when the cooldown ends (0 = disabled).
         * @param {number} timeleft      Seconds remaining in the current round (0 = no timer).
         * @param {number} timertotal    Total seconds configured for the round (0 = no timer).
         * @param {number} cmid          Course-module id used to build the reload URL.
         */
        init: function(cooldownUntil, timeleft, timertotal, cmid) {
            initInputFilter();
            initForfeit();
            initKeyboard();
            initGridPreview();
            initGuessSubmitFlag();
            initShakeLastRow();
            if (timeleft > 0) {
                initTimer(timeleft, timertotal || 0);
            }
            if (cooldownUntil > 0) {
                initCountdown(cooldownUntil, cmid);
            }
            var guessInput = document.getElementById('playerwords-guess');
            if (guessInput && document.getElementById('playerwords-forfeit-form')) {
                guessInput.focus({preventScroll: true});
            }
        },
    };
});
