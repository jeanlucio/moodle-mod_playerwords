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
import Modal from 'core/modal';
import ModalEvents from 'core/modal_events';
import ModalSaveCancel from 'core/modal_save_cancel';
import Notification from 'core/notification';
import {getString} from 'core/str';
import Templates from 'core/templates';
import {add as addToast} from 'core/toast';

/** @type {?number} Handle of the pending round-timer tick, if any. */
let timerHandle = null;

/** @type {?number} Handle of the pending cooldown-countdown tick, if any. */
let cooldownHandle = null;

/** @type {HTMLElement[]} Cells of the grid row currently mirroring the guess input. */
let activeRowCells = [];

/**
 * Maps a notification type to its core rendering template — mirrors core/notification's
 * own internal (unexported) mapping, needed here because that module offers no
 * "replace instead of stack" mode of its own to reuse directly (see notify() below).
 *
 * @type {Object<string, string>}
 */
const NOTIFICATION_TEMPLATES = {
    success: 'core/notification_success',
    info: 'core/notification_info',
    warning: 'core/notification_warning',
    error: 'core/notification_error',
};

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
 * Shows visible player feedback, either as an auto-dismissing toast or as a persistent
 * Moodle notification the player must close themselves. round_service flags every
 * round-flow message (wrong guess, hint revealed, round won, forfeited, timed out...)
 * as toast-worthy: a persistent notification never clears on its own, so a wrong-guess
 * warning could still be on screen next to a later "round won" message, reading as
 * contradictory feedback. Toasts fade out on their own and never accumulate that way.
 * Mirrors the same notify()/toast split already established in mod_playercross.
 *
 * The persistent path renders the same core notification templates
 * core/notification.addNotification() itself uses, but replaces the notification
 * region's contents instead of calling that helper directly. addNotification() only
 * ever prepends and never clears on its own — only a manual close-button dismisses a
 * banner — so a message that recurs on routine play would otherwise pile up into a
 * permanent wall of identical banners instead of reflecting just the current state,
 * exactly as a usability test with real students showed. Tracking "the node
 * addNotification() just added" from the outside to remove it on the next call is not a
 * reliable fix either: addNotification()'s own returned promise resolves before its
 * internal render chain has actually updated the DOM (a timing gap in
 * core/notification.js itself, confirmed by inspection), so a caller has no dependable
 * moment at which to read back which node was just inserted.
 *
 * @param {string} message Notification text.
 * @param {string} type Notification type: success, info, warning or error.
 * @param {boolean} [toast] Whether the server flagged this message as toast-worthy.
 */
const notify = async(message, type, toast) => {
    if (!message) {
        return;
    }
    if (toast) {
        addToast(message, {type: type || 'success'});
        return;
    }
    const region = document.getElementById('user-notifications');
    if (!region) {
        return;
    }
    const template = NOTIFICATION_TEMPLATES[type] || NOTIFICATION_TEMPLATES.info;
    const {html, js} = await Templates.renderForPromise(template, {message, closebutton: true, announce: true});
    Templates.replaceNodeContents(region, html, js);
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
 * Opens the how-to-play content (already server-rendered into #playerwords-help-content)
 * in a modal, keeping the current round visible instead of navigating away to a
 * separate page.
 *
 * @param {HTMLElement} button Help toolbar button, source of the modal title.
 * @param {HTMLElement} content Hidden container holding the pre-rendered help body.
 */
const openHelpModal = (button, content) => {
    Modal.create({
        title: button.dataset.title,
        body: content.innerHTML,
        show: true,
        removeOnClose: true,
    }).catch(Notification.exception);
};

/**
 * Wires the toolbar's help button to open the how-to-play modal on click, and — when
 * requested by the server for this page load — opens it once automatically too. The
 * server decides `autoshow` from a site-wide user preference (see intro_service::
 * has_seen_intro()) that is marked seen the moment it is decided, so this can only ever
 * fire once per user across every PlayerWords activity on the whole site, not once per
 * activity or per course.
 *
 * @param {boolean} autoshow Whether to open the modal immediately, once, on this load.
 */
const initHelpModal = (autoshow) => {
    const button = document.getElementById('playerwords-help-button');
    const content = document.getElementById('playerwords-help-content');
    if (!button || !content) {
        return;
    }
    button.addEventListener('click', () => {
        openHelpModal(button, content);
    });
    if (autoshow) {
        openHelpModal(button, content);
    }
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
            notify(payload.notification, payload.notificationtype, payload.toast);
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
 * Accent variants offered by the long-press popup, keyed by the base letter's own
 * keyboard key. Matching stays accent-insensitive (see word_normalizer::normalize()) —
 * this is purely a typing convenience so students can practise proper Portuguese
 * spelling, never a requirement to guess correctly.
 *
 * @type {Object<string, string[]>}
 */
const ACCENT_VARIANTS = {
    A: ['Á', 'À', 'Â', 'Ã'],
    E: ['É', 'Ê'],
    I: ['Í'],
    O: ['Ó', 'Ô', 'Õ'],
    U: ['Ú'],
};

/** @type {number} Touch hold duration, in ms, before the accent popup appears. */
const ACCENT_LONG_PRESS_MS = 450;

/** @type {?HTMLElement} The accent popup currently on screen, if any. */
let accentPopup = null;

/**
 * Writes one letter into the guess input, respecting maxlength — shared by a normal
 * keyboard tap and an accent-popup selection so both go through the same length check
 * and shake feedback.
 *
 * @param {HTMLElement} input Guess text input.
 * @param {string} letter Single letter to append.
 */
const writeLetter = (input, letter) => {
    const max = parseInt(input.getAttribute('maxlength'), 10);
    if (input.value.length < max) {
        input.value += letter;
    } else {
        shakeElement(input);
    }
    input.dispatchEvent(new Event('input'));
};

/**
 * Removes the last letter from the guess input — shared by a Backspace tap on the
 * on-screen keyboard and a physical Backspace keypress, so both go through the same
 * input-event contract as writeLetter.
 *
 * @param {HTMLElement} input Guess text input.
 */
const removeLastLetter = (input) => {
    input.value = input.value.slice(0, -1);
    input.dispatchEvent(new Event('input'));
};

/**
 * Submits the guess form, preferring requestSubmit() (fires the form's own submit
 * event and any validation) over a bare submit() — shared by the on-screen keyboard's
 * ENTER key and a physical Enter keypress.
 *
 * @param {HTMLElement} form Guess form.
 */
const submitGuessForm = (form) => {
    if (form.requestSubmit) {
        form.requestSubmit();
    } else {
        form.submit();
    }
};

/**
 * Removes the accent popup, if one is currently shown. Safe to call unconditionally.
 */
const removeAccentPopup = () => {
    if (accentPopup) {
        accentPopup.remove();
        accentPopup = null;
    }
};

/**
 * Marks one accent-popup option as the one that will be committed on release, moving
 * the highlight away from whichever option had it before — mirrors a phone's own
 * native long-press-for-diacritics keyboard, where sliding a finger across the popup
 * before lifting it picks whichever option is currently underneath.
 *
 * @param {HTMLElement} popup The accent popup element.
 * @param {HTMLElement} target The option to highlight.
 */
const highlightAccentOption = (popup, target) => {
    popup.querySelectorAll('.pw-accent-option').forEach((opt) => {
        opt.classList.toggle('is-active', opt === target);
    });
};

/**
 * Builds and positions the accent popup above the long-pressed key, options being the
 * plain base letter (pre-selected, so a long press released without sliding still
 * types the same letter a normal tap would) followed by each accented variant.
 *
 * @param {HTMLElement} keyboard The keyboard container — the popup's own positioning parent.
 * @param {HTMLElement} btn The long-pressed key button.
 * @param {string} baseLetter The key's own base letter, e.g. "E".
 */
const showAccentPopup = (keyboard, btn, baseLetter) => {
    removeAccentPopup();
    const popup = document.createElement('div');
    popup.className = 'pw-accent-popup';
    [baseLetter, ...ACCENT_VARIANTS[baseLetter]].forEach((letter, i) => {
        const opt = document.createElement('button');
        opt.type = 'button';
        opt.tabIndex = -1;
        opt.className = 'pw-accent-option' + (i === 0 ? ' is-active' : '');
        opt.textContent = letter;
        opt.dataset.letter = letter;
        popup.appendChild(opt);
    });

    const btnRect = btn.getBoundingClientRect();
    const kbRect = keyboard.getBoundingClientRect();
    popup.style.left = `${btnRect.left - kbRect.left + (btnRect.width / 2)}px`;
    popup.style.top = `${btnRect.top - kbRect.top}px`;
    keyboard.appendChild(popup);

    // Keys near either edge of the keyboard (A is the leftmost key with variants)
    // would otherwise centre the popup partly off-screen — nudge it back in.
    const popupRect = popup.getBoundingClientRect();
    if (popupRect.left < kbRect.left) {
        popup.style.left = `${parseFloat(popup.style.left) + (kbRect.left - popupRect.left) + 4}px`;
    } else if (popupRect.right > kbRect.right) {
        popup.style.left = `${parseFloat(popup.style.left) - (popupRect.right - kbRect.right) - 4}px`;
    }

    accentPopup = popup;
};

/**
 * Wires the accent-popup long-press gesture on every keyboard key that has variants
 * (see ACCENT_VARIANTS). Touch-only by nature — a long press has no equivalent on a
 * physical keyboard, which can already type accents through the operating system, so
 * desktop typing is entirely unaffected. A normal (short) tap still falls through to
 * wireKeyboardClicks' own click handler exactly as before.
 *
 * @param {HTMLElement} keyboard The keyboard container.
 * @param {HTMLElement} input Guess text input.
 */
const initAccentLongPress = (keyboard, input) => {
    let pressTimer = null;
    let longPressActive = false;

    const clearPressTimer = () => {
        if (pressTimer) {
            window.clearTimeout(pressTimer);
            pressTimer = null;
        }
    };

    const endLongPress = (commit) => {
        if (commit && accentPopup) {
            const active = accentPopup.querySelector('.pw-accent-option.is-active');
            if (active) {
                writeLetter(input, active.dataset.letter);
            }
        }
        removeAccentPopup();
        longPressActive = false;
    };

    keyboard.addEventListener('touchstart', (e) => {
        const btn = e.target.closest('[data-key]');
        const baseLetter = btn?.dataset.key;
        if (!baseLetter || !ACCENT_VARIANTS[baseLetter]) {
            return;
        }
        clearPressTimer();
        pressTimer = window.setTimeout(() => {
            longPressActive = true;
            showAccentPopup(keyboard, btn, baseLetter);
        }, ACCENT_LONG_PRESS_MS);
    }, {passive: true});

    keyboard.addEventListener('touchmove', (e) => {
        if (!longPressActive || !accentPopup) {
            return;
        }
        // Backs up the long-press keys' own touch-action: none (see styles.css) —
        // without this the page can still scroll under the player's finger while
        // they are sliding across the accent options, on a browser that resolves
        // touch-action more loosely.
        e.preventDefault();
        const touch = e.touches[0];
        const option = document.elementFromPoint(touch.clientX, touch.clientY)?.closest('.pw-accent-option');
        if (option) {
            highlightAccentOption(accentPopup, option);
        }
    });

    keyboard.addEventListener('touchend', (e) => {
        clearPressTimer();
        if (longPressActive) {
            // Suppresses the synthetic click touchend would otherwise fire next,
            // which would type the plain base letter a second time.
            e.preventDefault();
            endLongPress(true);
        }
    });

    keyboard.addEventListener('touchcancel', () => {
        clearPressTimer();
        endLongPress(false);
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
            removeLastLetter(input);
        } else if (key === 'ENTER') {
            submitGuessForm(form);
        } else {
            writeLetter(input, key);
        }
    });
    initAccentLongPress(keyboard, input);
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
 * Briefly shakes an element — used both for a wrong guess (grid row) and for a
 * rejected keystroke past the guess input's maxlength.
 *
 * @param {HTMLElement} el Element to shake.
 */
const shakeElement = (el) => {
    el.classList.add('pw-shake');
    window.setTimeout(() => el.classList.remove('pw-shake'), 450);
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
    initInputFilter();
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
    // The keyboard is a sibling of playNode (see round_panel.mustache), not a
    // descendant, so replacing playNode alone would leave it lingering on screen.
    document.getElementById('playerwords-keyboard')?.remove();
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
            notify(payload.notification, payload.notificationtype, payload.toast);
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
            notify(payload.notification, payload.notificationtype, payload.toast);
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
        notify(payload.notification, payload.notificationtype, payload.toast);
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
            notify(payload.notification, payload.notificationtype, payload.toast);
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
            shakeElement(row);
        }
        input.focus();
    });
};

/**
 * Whether the given element is a genuinely different text-editing surface (so letters
 * typed into it are unrelated to the guess and must be left alone).
 *
 * @param {?Element} el Element to check, typically document.activeElement.
 * @param {HTMLElement} guessInput The guess input, exempted since it is never "other".
 * @returns {boolean}
 */
const isOtherTextField = (el, guessInput) => {
    if (!el || el === guessInput) {
        return false;
    }
    return el.tagName === 'INPUT' || el.tagName === 'TEXTAREA' || el.isContentEditable;
};

/**
 * Whether the given element natively activates on Enter/Space (so Enter must still
 * activate it instead of submitting the guess).
 *
 * @param {?Element} el Element to check, typically document.activeElement.
 * @returns {boolean}
 */
const isActivatableControl = (el) => {
    return Boolean(el) && (el.tagName === 'BUTTON' || el.tagName === 'A' || el.tagName === 'SELECT');
};

/**
 * Captures physical-keyboard input at the document level so typing works regardless of
 * where focus happens to be on the page. Without this, typing only reached the guess
 * because game.js keeps refocusing a small text input sitting between the letter grid
 * and the on-screen keyboard — a usability test with real students showed they would
 * click elsewhere first (a grid row, the hint button) and then type, expecting it to
 * just work, the same way a physical keyboard works in Wordle. Wired once in init(),
 * never inside wireRoundPanel(): it targets document, which persists across every
 * round-panel re-render, so wiring it again per round would stack duplicate listeners
 * and type every letter multiple times.
 *
 * Routes letters/Backspace/Enter through the exact same writeLetter/removeLastLetter/
 * submitGuessForm helpers the on-screen keyboard already uses, so both input sources
 * stay one code path. Letters and Backspace are safe to redirect unconditionally (plain
 * keys have no native meaning on a focused button), but Enter is only redirected when
 * focus is on the guess input itself or on nothing interactive (body, grid cells,
 * status text) — a focused button/link still activates normally on Enter, e.g. the
 * hint or forfeit button, instead of submitting an unrelated guess.
 */
const initPhysicalKeyboardCapture = () => {
    document.addEventListener('keydown', (e) => {
        if (e.isComposing || e.ctrlKey || e.metaKey || e.altKey) {
            return;
        }
        const input = document.getElementById('playerwords-guess');
        const form = document.getElementById('playerwords-guess-form');
        if (!input || !form) {
            return;
        }
        const active = document.activeElement;
        if (e.key === 'Backspace') {
            if (isOtherTextField(active, input)) {
                return;
            }
            e.preventDefault();
            removeLastLetter(input);
        } else if (e.key === 'Enter') {
            if (isOtherTextField(active, input) || isActivatableControl(active)) {
                return;
            }
            e.preventDefault();
            submitGuessForm(form);
        } else if (e.key.length === 1 && /\p{L}/u.test(e.key)) {
            if (isOtherTextField(active, input)) {
                return;
            }
            e.preventDefault();
            writeLetter(input, e.key.toUpperCase());
        }
    });
};

/**
 * Entry point called by view.php via $PAGE->requires->js_call_amd().
 *
 * @param {number} cooldownUntil Unix timestamp when the cooldown ends (0 = disabled).
 * @param {number} timeleft      Seconds remaining in the current round (0 = no timer).
 * @param {number} timertotal    Total seconds configured for the round (0 = no timer).
 * @param {number} cmid          Course-module id.
 * @param {boolean} shouldAutoShowIntro Whether to open the how-to-play modal once, automatically.
 */
const init = (cooldownUntil, timeleft, timertotal, cmid, shouldAutoShowIntro) => {
    initHelpModal(Boolean(shouldAutoShowIntro));
    initForfeit(cmid, timertotal || 0);
    initPhysicalKeyboardCapture();
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
