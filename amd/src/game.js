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
 * Submits guesses via AJAX so the page never reloads — the target word stays
 * server-side the whole time, only per-letter feedback comes back on each
 * response. Also handles the forfeit confirmation dialog, the round/cooldown
 * countdowns, and the on-screen keyboard.
 *
 * @module     mod_playerwords/game
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Config from 'core/config';
import ModalEvents from 'core/modal_events';
import ModalSaveCancel from 'core/modal_save_cancel';
import Notification from 'core/notification';
import {getString} from 'core/str';
import Templates from 'core/templates';

/** @type {?number} Handle of the pending round-timer tick, if any. */
let timerHandle = null;

/** @type {?number} Handle of the pending cooldown-countdown tick, if any. */
let cooldownHandle = null;

/** @type {HTMLElement[]} Cells of the grid row currently mirroring the guess input. */
let activeRowCells = [];

/**
 * Writes a message into the live region so screen readers announce it.
 *
 * @param {string} message Message to announce.
 */
const announce = (message) => {
    const region = document.getElementById('playerwords-live-region');
    if (region) {
        region.textContent = message;
    }
};

/**
 * Shows a visible Moodle notification for a server-side rejection message (e.g. an
 * insufficient PlayerHUD item balance, or a stale action on an already-finished
 * round). Without this, the message only ever reached the aria-live region — silent
 * for sighted users, who would just see the button do nothing.
 *
 * @param {string} message Notification text.
 * @param {string} type Notification type: success, info, warning or error.
 */
const notify = (message, type) => {
    Notification.addNotification({message, type: type || 'info'});
};

/**
 * Strips non-letter characters from the guess input as the user types.
 *
 * Handles physical keyboard input; the on-screen keyboard only sends letters by design.
 */
const initInputFilter = () => {
    const input = document.getElementById('playerwords-guess');
    if (!input) {
        return;
    }
    input.addEventListener('input', () => {
        const filtered = input.value.replace(/[^\p{L}]/gu, '');
        if (filtered !== input.value) {
            input.value = filtered;
        }
    });
};

/**
 * Attaches a Moodle confirmation modal to the forfeit button, ending the round via
 * mod_playerwords_end_round on confirm — no page reload.
 *
 * @param {number} cmid Course-module id.
 * @param {number} timertotal Total seconds configured for the round (0 = no timer).
 */
const initForfeit = (cmid, timertotal) => {
    const button = document.getElementById('playerwords-forfeit-button');
    if (!button) {
        return;
    }
    button.addEventListener('click', () => {
        Promise.all([
            ModalSaveCancel.create({
                title: button.dataset.title,
                body: button.dataset.confirm,
                show: true,
                removeOnClose: true,
            }),
            getString('yes', 'core'),
        ]).then(([modal, yesStr]) => {
            modal.setSaveButtonText(yesStr);
            modal.getRoot().on(ModalEvents.save, () => {
                endRound(cmid, 'forfeit', timertotal);
            });
            return;
        }).catch(Notification.exception);
    });
};

/**
 * Wires the hint-reveal button via mod_playerwords_reveal_hint. Idempotent: the button is
 * always freshly rendered whenever the round panel is (re)rendered, so this is safe to call
 * again after every such swap.
 *
 * When the activity charges a PlayerHUD item for the hint, the button carries a
 * data-hud-confirm-* pair (set only in that case) and a confirmation modal — reusing the
 * exact pattern already used for the forfeit button — shows the cost/balance right before
 * the item is spent, instead of it sitting as permanent text above the button. A free hint
 * (no cost configured) reveals immediately on click, same as before.
 *
 * @param {number} cmid Course-module id.
 */
const initHintButton = (cmid) => {
    const button = document.getElementById('playerwords-hint-button');
    if (!button) {
        return;
    }

    const revealHint = async() => {
        let payload;
        try {
            payload = await Ajax.call([{methodname: 'mod_playerwords_reveal_hint', args: {cmid}}])[0];
        } catch (error) {
            Notification.exception(error);
            return;
        }
        if (payload.notification) {
            notify(payload.notification, payload.notificationtype);
        }
        if (!payload.success) {
            return;
        }
        // Reveal_hint returns no notification on success (that field is reserved for
        // errors) — announce the hint itself so screen-reader users learn it too.
        announce(`${button.dataset.hintLabel} ${payload.hintvalue}`);
        const alertEl = document.getElementById('playerwords-hint-alert');
        const valueEl = document.getElementById('playerwords-hint-value');
        if (valueEl) {
            valueEl.textContent = payload.hintvalue;
        }
        if (alertEl) {
            alertEl.hidden = false;
        }
        button.hidden = true;
    };

    button.addEventListener('click', () => {
        if (!button.dataset.hudConfirmBody) {
            revealHint();
            return;
        }
        Promise.all([
            ModalSaveCancel.create({
                title: button.dataset.hudConfirmTitle,
                body: button.dataset.hudConfirmBody,
                show: true,
                removeOnClose: true,
            }),
            getString('yes', 'core'),
        ]).then(([modal, yesStr]) => {
            modal.setSaveButtonText(yesStr);
            if (button.dataset.hudConfirmInsufficient) {
                modal.setButtonDisabled('save', true);
            }
            modal.getRoot().on(ModalEvents.save, revealHint);
            return;
        }).catch(Notification.exception);
    });
};

/**
 * Formats a seconds count as "Xmin YYs".
 *
 * @param {number} seconds Total seconds remaining.
 * @returns {string}
 */
const formatGameTime = (seconds) => {
    const m = Math.floor(seconds / 60);
    const s = seconds % 60;
    return `${m}min ${String(s).padStart(2, '0')}s`;
};

/**
 * Cancels any pending round-timer tick.
 */
const stopTimer = () => {
    if (timerHandle) {
        window.clearTimeout(timerHandle);
        timerHandle = null;
    }
};

/**
 * Ticks the round timer down one second and re-schedules itself.
 *
 * Recomputes remaining time from an absolute deadline on every tick (like the cooldown
 * countdown already does) instead of decrementing a local counter, so a throttled or
 * jank-delayed setTimeout never lets the displayed countdown drift from the server's
 * real deadline. Adds the urgency class once remaining time falls below the threshold,
 * and ends the round via mod_playerwords_end_round (reason: timeout) once time runs out
 * — the server independently re-validates that the deadline actually passed.
 *
 * @param {HTMLElement} el        The span showing the countdown.
 * @param {number}      deadline  Unix timestamp (seconds) when the round times out.
 * @param {number}      threshold Seconds at which to add the urgency class.
 * @param {number}      cmid      Course-module id.
 * @param {number}      timertotal Total seconds configured for the round.
 */
const tickTimer = (el, deadline, threshold, cmid, timertotal) => {
    const remaining = deadline - Math.floor(Date.now() / 1000);
    el.textContent = formatGameTime(Math.max(0, remaining));
    if (remaining <= threshold) {
        el.classList.add('pw-timer-urgent');
    }
    if (remaining <= 0) {
        stopTimer();
        endRound(cmid, 'timeout', timertotal);
        return;
    }
    timerHandle = window.setTimeout(() => tickTimer(el, deadline, threshold, cmid, timertotal), 1000);
};

/**
 * (Re)starts the round-timer countdown if the timer element is present and there is an
 * active round. Cancels any timer already running first, so this is safe to call again
 * after every guess to reseed the countdown from the server's fresh remaining value.
 *
 * @param {number} timeleft   Seconds remaining.
 * @param {number} timertotal Total seconds configured for the round.
 * @param {number} cmid       Course-module id.
 */
const startTimer = (timeleft, timertotal, cmid) => {
    stopTimer();
    const el = document.getElementById('playerwords-timer-countdown');
    if (!el || timeleft <= 0 || !document.getElementById('playerwords-round-play')) {
        return;
    }
    el.textContent = formatGameTime(timeleft);
    const threshold = timertotal > 0 ? Math.max(10, Math.floor(timertotal * 0.2)) : 30;
    const deadline = Math.floor(Date.now() / 1000) + timeleft;
    tickTimer(el, deadline, threshold, cmid, timertotal);
};

/**
 * Cancels any pending cooldown-countdown tick.
 */
const stopCountdown = () => {
    if (cooldownHandle) {
        window.clearTimeout(cooldownHandle);
        cooldownHandle = null;
    }
};

/**
 * Updates the cooldown countdown span every second until the timestamp is reached.
 *
 * @param {HTMLElement} el    The span element to update.
 * @param {number}      until Unix timestamp (seconds) when the cooldown ends.
 * @param {number}      cmid  Course-module id used to build the reload URL.
 */
const tickCountdown = (el, until, cmid) => {
    const remaining = until - Math.floor(Date.now() / 1000);
    if (remaining <= 0) {
        stopCountdown();
        window.location.href = `${Config.wwwroot}/mod/playerwords/view.php?id=${cmid}`;
        return;
    }
    const h = Math.floor(remaining / 3600);
    const m = Math.floor((remaining % 3600) / 60);
    const s = remaining % 60;
    const parts = [];
    if (h > 0) {
        parts.push(`${h}h`);
    }
    parts.push(`${String(m).padStart(2, '0')}m`);
    parts.push(`${String(s).padStart(2, '0')}s`);
    el.textContent = parts.join(' ');
    cooldownHandle = window.setTimeout(() => tickCountdown(el, until, cmid), 1000);
};

/**
 * (Re)starts the cooldown countdown if the element is present. Cancels any cooldown
 * tick already running first, so this is safe to call again after a round finishes.
 *
 * @param {number} until Unix timestamp when the cooldown ends.
 * @param {number} cmid  Course-module id used to build the reload URL.
 */
const startCountdown = (until, cmid) => {
    stopCountdown();
    const el = document.getElementById('playerwords-cooldown-countdown');
    if (!el || until <= 0) {
        return;
    }
    tickCountdown(el, until, cmid);
};

/**
 * Rescans every rendered grid cell and recolors the on-screen keyboard keys, using the
 * highest-ranked state seen for each letter (correct > present > absent). Safe to call
 * repeatedly — e.g. once at page load and again after every guess.
 */
const recolorKeyboard = () => {
    const keyboard = document.getElementById('playerwords-keyboard');
    if (!keyboard) {
        return;
    }

    // State precedence: correct (3) > present (2) > absent (1).
    const stateRank = {absent: 1, present: 2, correct: 3};
    const letterStates = {};

    document.querySelectorAll('.mod-playerwords-cell').forEach((cell) => {
        const letter = cell.textContent.trim().toUpperCase();
        if (!letter) {
            return;
        }
        let bestState = null;
        let bestRank = 0;
        Object.keys(stateRank).forEach((s) => {
            if (cell.classList.contains(`is-${s}`) && stateRank[s] > bestRank) {
                bestRank = stateRank[s];
                bestState = s;
            }
        });
        if (bestState && (!letterStates[letter] || stateRank[bestState] > stateRank[letterStates[letter]])) {
            letterStates[letter] = bestState;
        }
    });

    keyboard.querySelectorAll('[data-key]').forEach((btn) => {
        const state = letterStates[btn.dataset.key];
        if (state) {
            btn.classList.add(`is-${state}`);
        }
    });
};

/**
 * Wires on-screen keyboard clicks to the guess input and form. Wired once per keyboard
 * element — the keyboard DOM node persists across guesses within the same round.
 *
 * @param {HTMLElement} input Guess text input.
 * @param {HTMLElement} form  Guess form.
 */
const wireKeyboardClicks = (input, form) => {
    const keyboard = document.getElementById('playerwords-keyboard');
    if (!keyboard) {
        return;
    }
    keyboard.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-key]');
        if (!btn) {
            return;
        }
        const key = btn.dataset.key;
        if (key === 'BACKSPACE') {
            input.value = input.value.slice(0, -1);
        } else if (key === 'ENTER') {
            if (form.requestSubmit) {
                form.requestSubmit();
            } else {
                form.submit();
            }
        } else {
            const max = parseInt(input.getAttribute('maxlength'), 10);
            if (input.value.length < max) {
                input.value += key;
            }
        }
        input.dispatchEvent(new Event('input'));
    });
};

/**
 * Finds the first fully-empty grid row and marks it as the live preview row, replacing
 * any previous marker. Call again after every guess/round transition, since the row that
 * was active is no longer empty once a guess lands in it.
 */
const refreshActiveRow = () => {
    document.querySelectorAll('.mod-playerwords-row.pw-row-active').forEach((row) => {
        row.classList.remove('pw-row-active');
    });
    activeRowCells = [];
    for (const row of document.querySelectorAll('.mod-playerwords-row')) {
        const cells = Array.from(row.querySelectorAll('.mod-playerwords-cell'));
        if (cells.length && cells.every((c) => c.classList.contains('is-empty'))) {
            row.classList.add('pw-row-active');
            activeRowCells = cells;
            break;
        }
    }
};

/**
 * Mirrors the guess input value (uppercased) into the active row's cells.
 */
const updateRowPreview = () => {
    const input = document.getElementById('playerwords-guess');
    if (!input || !activeRowCells.length) {
        return;
    }
    const val = input.value.toUpperCase();
    activeRowCells.forEach((cell, i) => {
        cell.textContent = i < val.length ? val[i] : '';
    });
};

/**
 * Sets up the live guess preview: finds the active row and mirrors typed letters into it.
 */
const initGridPreview = () => {
    const input = document.getElementById('playerwords-guess');
    if (!input) {
        return;
    }
    refreshActiveRow();
    input.addEventListener('input', updateRowPreview);
};

/**
 * Briefly shakes a grid row to signal a wrong guess.
 *
 * @param {HTMLElement} row Row element to shake.
 */
const shakeRow = (row) => {
    row.classList.add('pw-shake');
    window.setTimeout(() => row.classList.remove('pw-shake'), 450);
};

/**
 * Paints one guess's per-letter feedback onto its grid row.
 *
 * @param {number} attemptsused Attempts used so far, 1-based; identifies the row.
 * @param {Array} feedback Per-letter {letter, state, arialabel} objects.
 * @returns {?HTMLElement} The row element that was patched, or null if not found.
 */
const paintGuessRow = (attemptsused, feedback) => {
    const rows = document.querySelectorAll('.mod-playerwords-row');
    const row = rows[attemptsused - 1];
    if (!row) {
        return null;
    }
    const cells = row.querySelectorAll('.mod-playerwords-cell');
    feedback.forEach((letterinfo, i) => {
        const cell = cells[i];
        if (!cell) {
            return;
        }
        cell.textContent = letterinfo.letter;
        cell.classList.remove('is-empty');
        cell.classList.add(`is-${letterinfo.state}`);
        cell.setAttribute('aria-label', letterinfo.arialabel);
    });
    return row;
};

/**
 * Shows or hides the header timer badge, which lives outside #playerwords-stage so it
 * survives the lobby/round-panel swap instead of being re-rendered on every transition.
 *
 * @param {boolean} visible Whether the timer badge should be shown.
 */
const setTimerBadgeVisible = (visible) => {
    const wrapper = document.getElementById('playerwords-timer-wrapper');
    if (wrapper) {
        wrapper.hidden = !visible;
    }
};

/**
 * Shows or hides the toolbar's forfeit button. It also lives outside #playerwords-stage
 * (its click handler is wired once, in init(), not on every round-panel re-render), so
 * visibility is the only thing that needs to track the active-round/lobby/finished state.
 *
 * @param {boolean} visible Whether the forfeit button should be shown.
 */
const setForfeitButtonVisible = (visible) => {
    const button = document.getElementById('playerwords-forfeit-button');
    if (button) {
        button.hidden = !visible;
    }
};

/**
 * Wires every control inside a freshly-rendered round panel: keyboard, grid preview,
 * guess form and hint button. Safe to call again after each panel re-render, since each
 * wired element only ever exists once at a time in the DOM.
 *
 * @param {number} cmid Course-module id.
 * @param {number} timertotal Total seconds configured for the round (0 = no timer).
 */
const wireRoundPanel = (cmid, timertotal) => {
    recolorKeyboard();
    initGridPreview();
    initGuessForm(cmid, timertotal);
    initHintButton(cmid);
};

/**
 * Swaps the active-round controls for the post-round result panel, re-rendering
 * mod_playerwords/round_result from the context the server just returned so the
 * ranking table and reveal panel stay a single source of truth with the PHP side.
 *
 * @param {Object} roundresult Context matching mod_playerwords/round_result.
 * @param {number} cmid Course-module id, used to (re)start the cooldown countdown.
 * @param {number} timertotal Total seconds configured for the round (0 = no timer).
 */
const showRoundResult = async(roundresult, cmid, timertotal) => {
    stopTimer();
    setTimerBadgeVisible(false);
    setForfeitButtonVisible(false);
    const playNode = document.getElementById('playerwords-round-play');
    if (!playNode) {
        return;
    }
    const {html, js} = await Templates.renderForPromise('mod_playerwords/round_result', roundresult);
    await Templates.replaceNode(playNode, html, js);
    initNewRound(cmid, timertotal);
    if (roundresult.cooldownuntil > 0) {
        startCountdown(roundresult.cooldownuntil, cmid);
    }
    const focusTarget = document.querySelector('#playerwords-round-result button, #playerwords-round-result a')
        ?? document.getElementById('playerwords-round-result');
    if (focusTarget) {
        focusTarget.focus();
    }
};

/**
 * Renders the active-round panel into the stage and wires all of its controls.
 *
 * @param {Object} panelcontext Context matching mod_playerwords/round_panel.
 * @param {number} cmid Course-module id.
 * @param {number} timertotal Total seconds configured for the round (0 = no timer).
 */
const showRoundPanel = async(panelcontext, cmid, timertotal) => {
    const stage = document.getElementById('playerwords-stage');
    if (!stage) {
        return;
    }
    const {html, js} = await Templates.renderForPromise('mod_playerwords/round_panel', panelcontext);
    await Templates.replaceNodeContents(stage, html, js);
    wireRoundPanel(cmid, timertotal);
    setTimerBadgeVisible(true);
    setForfeitButtonVisible(true);
    if (panelcontext.timerenabled && panelcontext.timeleft > 0) {
        startTimer(panelcontext.timeleft, timertotal, cmid);
    }
    const guessInput = document.getElementById('playerwords-guess');
    if (guessInput) {
        guessInput.focus();
    }
};

/**
 * Renders the pre-round lobby into the stage and wires its start-round button.
 *
 * @param {Object} lobbycontext Context matching mod_playerwords/lobby.
 * @param {number} cmid Course-module id.
 * @param {number} timertotal Total seconds configured for the round (0 = no timer).
 */
const showLobby = async(lobbycontext, cmid, timertotal) => {
    const stage = document.getElementById('playerwords-stage');
    if (!stage) {
        return;
    }
    const {html, js} = await Templates.renderForPromise('mod_playerwords/lobby', lobbycontext);
    await Templates.replaceNodeContents(stage, html, js);
    setTimerBadgeVisible(false);
    setForfeitButtonVisible(false);
    initStartRound(cmid, timertotal);
    const startButton = document.getElementById('playerwords-start-round-button');
    if (startButton) {
        startButton.focus();
    }
};

/**
 * Wires the lobby's start-round button via mod_playerwords_start_round.
 *
 * @param {number} cmid Course-module id.
 * @param {number} timertotal Total seconds configured for the round (0 = no timer).
 */
const initStartRound = (cmid, timertotal) => {
    const button = document.getElementById('playerwords-start-round-button');
    if (!button) {
        return;
    }
    button.addEventListener('click', async() => {
        let payload;
        try {
            payload = await Ajax.call([{methodname: 'mod_playerwords_start_round', args: {cmid}}])[0];
        } catch (error) {
            Notification.exception(error);
            return;
        }
        if (payload.notification) {
            notify(payload.notification, payload.notificationtype);
        }
        if (!payload.success) {
            return;
        }
        await showRoundPanel(payload.roundpanel, cmid, timertotal);
    });
};

/**
 * Wires a round-result's new-round button via mod_playerwords_new_round.
 *
 * @param {number} cmid Course-module id.
 * @param {number} timertotal Total seconds configured for the round (0 = no timer).
 */
const initNewRound = (cmid, timertotal) => {
    const button = document.getElementById('playerwords-new-round-button');
    if (!button) {
        return;
    }
    button.addEventListener('click', async() => {
        let payload;
        try {
            payload = await Ajax.call([{methodname: 'mod_playerwords_new_round', args: {cmid}}])[0];
        } catch (error) {
            Notification.exception(error);
            return;
        }
        if (payload.notification) {
            notify(payload.notification, payload.notificationtype);
        }
        if (!payload.hastargetword) {
            // Round-limit or lingering cooldown restriction: mirror the classic
            // page-load warning in place of the stage, since there is no fresh round.
            const stage = document.getElementById('playerwords-stage');
            if (stage && payload.notification) {
                stage.textContent = '';
                const alertEl = document.createElement('div');
                alertEl.className = 'alert alert-warning';
                alertEl.textContent = payload.notification;
                stage.appendChild(alertEl);
            }
            return;
        }
        await showLobby(payload.lobby, cmid, timertotal);
    });
};

/**
 * Ends the round (forfeit or timeout) via mod_playerwords_end_round and applies the
 * response, without ever reloading the page.
 *
 * @param {number} cmid Course-module id.
 * @param {string} reason Either "forfeit" or "timeout".
 * @param {number} timertotal Total seconds configured for the round (0 = no timer).
 */
const endRound = async(cmid, reason, timertotal) => {
    let payload;
    try {
        payload = await Ajax.call([{
            methodname: 'mod_playerwords_end_round',
            args: {cmid, reason},
        }])[0];
    } catch (error) {
        Notification.exception(error);
        return;
    }
    if (payload.notification) {
        notify(payload.notification, payload.notificationtype);
    }
    if (payload.finished) {
        await showRoundResult(payload.roundresult, cmid, timertotal);
    }
};

/**
 * Submits guesses via mod_playerwords_submit_guess and applies the response in place,
 * without ever reloading the page. The server is the sole authority on the target word;
 * this only ever receives per-letter feedback, never the word itself before it finishes.
 *
 * @param {number} cmid Course-module id.
 * @param {number} timertotal Total seconds configured for the round (0 = no timer).
 */
const initGuessForm = (cmid, timertotal) => {
    const form = document.getElementById('playerwords-guess-form');
    const input = document.getElementById('playerwords-guess');
    if (!form || !input) {
        return;
    }

    wireKeyboardClicks(input, form);

    form.addEventListener('submit', async(e) => {
        e.preventDefault();
        const guess = input.value;

        let payload;
        try {
            payload = await Ajax.call([{
                methodname: 'mod_playerwords_submit_guess',
                args: {cmid, guess},
            }])[0];
        } catch (error) {
            Notification.exception(error);
            return;
        }

        if (payload.notification) {
            notify(payload.notification, payload.notificationtype);
        }

        if (!payload.feedback.length) {
            // Guess was rejected server-side (wrong length, invalid characters, round over).
            return;
        }

        // A guess that continues the round carries no notification of its own (that field
        // is reserved for round-finish/error messages) — announce the per-letter result
        // directly so screen-reader users get feedback on every guess, not just the last one.
        if (!payload.notification) {
            announce(payload.feedback.map((letterinfo) => letterinfo.arialabel).join('. '));
        }

        const row = paintGuessRow(payload.attemptsused, payload.feedback);
        recolorKeyboard();
        // Move the live-preview marker to the next empty row BEFORE clearing the input,
        // otherwise the input-clear below would mirror an empty value into the row we
        // just painted (the old active row) and wipe out the letters we just drew.
        refreshActiveRow();
        input.value = '';
        input.dispatchEvent(new Event('input'));

        if (payload.finished) {
            await showRoundResult(payload.roundresult, cmid, timertotal);
            return;
        }

        startTimer(payload.timeleft, timertotal, cmid);
        if (row) {
            shakeRow(row);
        }
        input.focus();
    });
};

/**
 * Entry point called by view.php via $PAGE->requires->js_call_amd().
 *
 * @param {number} cooldownUntil Unix timestamp when the cooldown ends (0 = disabled).
 * @param {number} timeleft      Seconds remaining in the current round (0 = no timer).
 * @param {number} timertotal    Total seconds configured for the round (0 = no timer).
 * @param {number} cmid          Course-module id.
 */
const init = (cooldownUntil, timeleft, timertotal, cmid) => {
    initInputFilter();
    initForfeit(cmid, timertotal || 0);
    wireRoundPanel(cmid, timertotal || 0);
    initStartRound(cmid, timertotal || 0);
    initNewRound(cmid, timertotal || 0);
    if (timeleft > 0) {
        startTimer(timeleft, timertotal || 0, cmid);
    }
    if (cooldownUntil > 0) {
        startCountdown(cooldownUntil, cmid);
    }
    const guessInput = document.getElementById('playerwords-guess');
    if (guessInput && document.getElementById('playerwords-round-play')) {
        guessInput.focus({preventScroll: true});
    }
};

export {init};
