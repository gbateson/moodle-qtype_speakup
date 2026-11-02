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
 * SpeakUp questions in the Moodle app (see classes/output/mobile.php).
 *
 * The app gets the question's HTML from the site. This shows its question
 * text. When the question is being attempted, the response (the recorder or
 * uploader, and the audio prompt) is shown in a frame, from respond.php on
 * the site, so that it works just as it does in a browser. The frame uses
 * the same draft file area as the response, so the files are where the quiz
 * expects them, and it sends the response's other values (e.g. how many
 * times the prompt was played) back here, to the hidden fields that the app
 * sends with the quiz.
 *
 * When the question is reviewed, the site's HTML is shown as it is.
 *
 * This file is plain JavaScript, run by the app, not an AMD module.
 *
 * @package   qtype_speakup
 * @copyright 2026 Gordon Bateson and contributors
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

var that = this;

var result = {
    componentInit: function() {
        var question = this.question;
        if (!question) {
            return that.CoreQuestionHelperProvider.showComponentError(that.onAbort);
        }

        var div = document.createElement('div');
        div.innerHTML = question.html;
        that.CoreQuestionHelperProvider.replaceCorrectnessClasses(div);
        that.CoreQuestionHelperProvider.replaceFeedbackClasses(div);
        that.CoreQuestionHelperProvider.treatCorrectnessIcons(div);

        var qtext = div.querySelector('.qtext');
        question.text = qtext ? qtext.innerHTML : '';

        // The response's draft file area. If there is none, the question is
        // being reviewed: show the site's HTML, without the question text.
        var recording = div.querySelector('input[type=hidden][name$="_recording"]');
        if (!recording) {
            var formulation = div.querySelector('.formulation') || div;
            if (qtext) {
                qtext.remove();
            }
            formulation.querySelectorAll('h4.accesshide').forEach(function(heading) {
                heading.remove();
            });
            question.speakupReview = formulation.innerHTML;
            return true;
        }

        // The field names start with e.g. "q123:4_" for usage 123, slot 4.
        var prefix = recording.name.substring(0, recording.name.length - 'recording'.length);
        var match = prefix.match(/^q(\d+):(\d+)_$/);
        if (!match) {
            return that.CoreQuestionHelperProvider.showComponentError(that.onAbort);
        }
        var usage = match[1];
        var slot = match[2];

        // The hidden fields of the response, sent with the quiz.
        question.speakupFields = [];
        div.querySelectorAll('input[type=hidden]').forEach(function(input) {
            if (input.name.indexOf(prefix) === 0 && input.name !== prefix + ':sequencecheck') {
                question.speakupFields.push({name: input.name, value: input.value});
            }
        });

        var site = that.CoreSitesProvider.getCurrentSite();
        var siteurl = site.getURL().replace(/\/+$/, '');
        var origin = new URL(siteurl).origin;
        var frameid = 'qtype-speakup-frame-' + usage + '-' + slot;
        question.speakupFrameId = frameid;
        question.speakupLoading = true;

        // Receive the response's values, and the frame's height, from the frame.
        window.qtypeSpeakupListeners = window.qtypeSpeakupListeners || {};
        if (window.qtypeSpeakupListeners[prefix]) {
            window.removeEventListener('message', window.qtypeSpeakupListeners[prefix]);
        }
        var listener = function(e) {
            var data = e.data;
            if (e.origin !== origin || !data || data.type !== 'qtype_speakup' || data.prefix !== prefix) {
                return;
            }
            if (data.fields) {
                Object.keys(data.fields).forEach(function(name) {
                    var value = String(data.fields[name]);
                    question.speakupFields.forEach(function(field) {
                        if (field.name === name) {
                            field.value = value;
                        }
                    });
                    var input = document.querySelector('input[type=hidden][name="' + CSS.escape(name) + '"]');
                    if (input) {
                        input.value = value;
                    }
                });
            }
            if (data.height) {
                var iframe = document.getElementById(frameid + '-iframe');
                if (iframe) {
                    iframe.style.height = (Math.ceil(data.height) + 16) + 'px';
                }
            }
        };
        window.qtypeSpeakupListeners[prefix] = listener;
        window.addEventListener('message', listener);

        // Show the frame, logged in to the site, once the template is shown.
        var url = siteurl + '/question/type/speakup/respond.php?usage=' + usage + '&slot=' + slot +
            '&draft=' + encodeURIComponent(recording.value);
        var addFrame = function(src, tries) {
            var container = document.getElementById(frameid);
            if (!container) {
                if (tries < 100) {
                    setTimeout(function() {
                        addFrame(src, tries + 1);
                    }, 100);
                }
                return;
            }
            if (container.querySelector('iframe')) {
                return;
            }
            var iframe = document.createElement('iframe');
            iframe.id = frameid + '-iframe';
            iframe.src = src;
            // Let the frame record, play the prompt, and show pictures full screen.
            iframe.setAttribute('allow', 'microphone; camera; autoplay; fullscreen');
            iframe.setAttribute('allowfullscreen', 'allowfullscreen');
            iframe.addEventListener('load', function() {
                question.speakupLoading = false;
            });
            container.appendChild(iframe);
        };
        site.getAutoLoginUrl(url, false).then(function(src) {
            addFrame(src, 0);
            return src;
        }).catch(function() {
            addFrame(url, 0);
        });

        return true;
    }
};

result;
