// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Live, client-side speech-to-text for qtype_speakup, using the browser's
 * own SpeechRecognition API (feature-detected across the standard and
 * legacy-prefixed names). Produces an instant transcript with no server
 * round-trip, used only to feed SPEAKUP_GRADING_EXACT's pattern-matching
 * suggestion (see question.php::grade_by_pattern()).
 *
 * Design notes:
 * - This module is intentionally decoupled from amd/src/avrecording.js -
 *   rather than hooking into its button click handlers directly (which
 *   would mean editing that large, mostly-ported file), it watches the
 *   recorder widget's own `data-state` attribute via a MutationObserver,
 *   and starts/stops recognition in step with whatever avrecording.js is
 *   already doing to that attribute ('new' -> 'starting' -> 'recording'
 *   [ <-> 'paused' ] -> 'recorded').
 * - If the browser has no SpeechRecognition support at all, or the
 *   requested language is unsupported, this silently does nothing: the
 *   hidden 'browsertranscript' field (and the visible
 *   '.qtype_speakup-live-transcript' element, if the renderer added one)
 *   are simply left empty. Live transcription is always a bonus, never a
 *   requirement - the actual audio/video recording and upload are
 *   completely unaffected either way.
 * - The SpeechRecognition API has no native pause/resume: each session's
 *   evt.results starts fresh. Pausing/resuming (mirroring avrecording.js's
 *   own pause button) is handled by folding each session's finalized text
 *   into a running `committedText` accumulator on stop(), which a
 *   following resume then builds on top of, rather than discarding it -
 *   this is NOT reset on resume, only on a genuinely fresh recording.
 * - Each recognition session's event handlers check that they are still
 *   the *current* session before touching shared state. Chrome's
 *   implementation is cloud-based (real network round-trip), so a session
 *   we have already stop()'d can still deliver a late, straggling 'result'
 *   event afterwards; without this guard such a stale event can silently
 *   corrupt/overwrite what the next session has already captured.
 * - Loosely based on the cross-browser SpeechRecognition wrapper pattern
 *   used in Gordon Bateson's own "spoken communication practice bot"
 *   script (result accumulation via evt.resultIndex, rather than the
 *   zero-width-space string-surgery approach seen in some older Moodle
 *   speaking-assessment plugins).
 *
 * @module    qtype_speakup/speechrecognition
 * @copyright 2026 Gordon Bateson and contributors
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Log from 'core/log';

// Cross-browser access to the SpeechRecognition constructor. Prefer the
// standard unprefixed name (newer Firefox/Safari); fall back to Chrome's
// vendor-prefixed name.
const SpeechRecognitionApi = window.SpeechRecognition || window.webkitSpeechRecognition;

/** Recorder-widget data-state values we care about (see avrecording.js). */
const STATE_RECORDING = 'recording';
const STATE_PAUSED = 'paused';

/**
 * Set up live transcription for one question's recorder widget.
 *
 * @param {String} questionId the unique DOM id of this question's outer div.
 * @param {String} lang BCP-47 language tag, e.g. 'en-US', 'ja-JP'.
 */
export const init = (questionId, lang) => {
    if (!SpeechRecognitionApi) {
        Log.debug('qtype_speakup: SpeechRecognition not supported in this browser - live transcription disabled.');
        return;
    }

    const questionDiv = document.getElementById(questionId);
    if (!questionDiv) {
        return;
    }

    const widget = questionDiv.querySelector('.qtype_speakup-audio-widget, .qtype_speakup-video-widget');
    const field = questionDiv.querySelector('.qtype_speakup-browsertranscript-field');
    const liveDisplay = questionDiv.querySelector('.qtype_speakup-live-transcript');
    if (!widget || !field) {
        return;
    }

    let recognition = null;
    let languageUnsupported = false;

    // Text finalized in *previous* recognition sessions during this same
    // overall recording attempt (i.e. before the most recent pause). The
    // SpeechRecognition API has no native "resume" - each session starts
    // its own fresh evt.results list - so without this, resuming after a
    // pause would silently discard everything captured before the pause.
    // This is cleared only on a genuinely fresh recording (see start()),
    // never on resume.
    let committedText = '';

    // Text finalized so far within the *current* recognition session only.
    let sessionFinal = '';

    const createRecognition = () => {
        const r = new SpeechRecognitionApi();
        r.continuous = true;
        r.interimResults = true;
        r.lang = lang;

        r.addEventListener('result', (evt) => {
            if (recognition !== r) {
                // Stale event from a session we've already moved past
                // (e.g. a late network response after pausing) - ignore
                // it rather than let it corrupt the current session's text.
                return;
            }
            let final = '';
            let interim = '';
            for (let i = 0; i < evt.results.length; i++) {
                const txt = evt.results[i][0].transcript;
                if (evt.results[i].isFinal) {
                    final += txt;
                } else if (i >= evt.resultIndex) {
                    interim += txt;
                }
            }
            sessionFinal = final;
            const text = [committedText, sessionFinal, interim]
                    .map((s) => s.trim())
                    .filter((s) => s !== '')
                    .join(' ');
            field.value = text;
            if (liveDisplay) {
                liveDisplay.textContent = text;
            }
        });

        r.addEventListener('error', (evt) => {
            if (recognition !== r) {
                return;
            }
            // Any error here just means "no live transcript this time" -
            // it must never block the actual recording/upload, which is
            // handled entirely separately by avrecording.js.
            Log.debug('qtype_speakup: speech recognition error (' + evt.error + ').');
            if (evt.error === 'language-not-supported') {
                languageUnsupported = true;
            }
        });

        return r;
    };

    /**
     * @param {Boolean} isResume true if resuming after a pause (keep
     *      committedText); false for a genuinely fresh recording (clear it).
     */
    const start = (isResume) => {
        if (languageUnsupported) {
            return;
        }
        if (!isResume) {
            committedText = '';
        }
        sessionFinal = '';
        recognition = createRecognition();
        try {
            recognition.start();
        } catch (e) {
            // Can throw if called at an unexpected moment (e.g. already
            // running) on some browsers - safe to just skip this attempt.
            Log.debug('qtype_speakup: could not start speech recognition: ' + e.message);
        }
    };

    const stop = () => {
        if (recognition) {
            // Fold whatever this session finalized into committedText, so
            // a following resume (or the final submitted value) keeps it.
            committedText = [committedText, sessionFinal]
                    .map((s) => s.trim())
                    .filter((s) => s !== '')
                    .join(' ');
            sessionFinal = '';
            try {
                recognition.stop();
            } catch (e) {
                // Already stopped - ignore.
                Log.debug('qtype_speakup: speech recognition stop() ignored: ' + e.message);
            }
            recognition = null;
        }
    };

    let previousState = widget.dataset.state;
    const observer = new MutationObserver(() => {
        const state = widget.dataset.state;
        if (state === previousState) {
            return;
        }
        if (state === STATE_RECORDING) {
            // 'starting' -> 'recording' is a fresh start; 'paused' ->
            // 'recording' is a resume, which must keep committedText.
            start(previousState === STATE_PAUSED);
        } else if (state === STATE_PAUSED || state === 'recorded' || state === 'new') {
            stop();
        }
        previousState = state;
    });
    observer.observe(widget, {attributes: true, attributeFilter: ['data-state']});
};
