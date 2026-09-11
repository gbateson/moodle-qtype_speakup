<?php
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
 * English language strings for qtype_speakup.
 *
 * @package   qtype_speakup
 * @copyright 2026 Gordon Bateson and contributors
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'SpeakUp - spoken response';
$string['pluginname_help'] = 'Students record a spoken response, within a time limit, to a written or audio prompt. The response can be graded automatically against model answers/patterns, by an AI assessment engine, or reviewed manually by a teacher.';
$string['pluginname_link'] = 'question/type/speakup';
$string['pluginnameadding'] = 'Adding a SpeakUp spoken response question';
$string['pluginnameediting'] = 'Editing a SpeakUp spoken response question';
$string['pluginnamesummary'] = 'Students give a timed spoken response, assessed by exact/pattern matching, AI, or a teacher.';

// Editing form.
$string['timelimitinseconds'] = 'Response time limit';
$string['timelimitinseconds_help'] = 'The maximum time, in seconds, the student has to record their spoken response after they start recording.';
$string['mediatype'] = 'Response type';
$string['mediatype_help'] = 'Audio only, or audio plus webcam video. Video lets AI assessment also consider non-verbal communication such as facial expressions and gestures, in addition to the spoken language.';
$string['mediatype_audio'] = 'Audio only';
$string['mediatype_video'] = 'Audio + video (webcam)';
$string['audioprompt'] = 'Audio prompt (optional)';
$string['audioprompt_help'] = 'An optional audio file the student hears instead of, or as well as, the written question text.';
$string['gradingmethod'] = 'Grading method';
$string['gradingmethod_help'] = 'How the recorded response is assessed:

* Pattern matching - the transcript is compared against one or more expected answers or regular expressions.
* AI holistic assessment - the transcript and/or audio is sent to an AI assessment engine along with a rubric.
* Manual only - a teacher listens and grades by hand; no automatic grading is attempted.';
$string['gradingmethod_exact'] = 'Pattern matching (exact / regular expression)';
$string['gradingmethod_ai'] = 'AI holistic assessment';
$string['gradingmethod_manual'] = 'Manual grading only';
$string['feedbackmode'] = 'Feedback mode';
$string['feedbackmode_help'] = 'Formative feedback is shown to the student to help them improve, without necessarily contributing to a final grade. Summative feedback is shown together with a final grade for the attempt.';
$string['feedbackmode_formative'] = 'Formative (practice)';
$string['feedbackmode_summative'] = 'Summative (assessed)';
$string['expectedanswers'] = 'Model answers / patterns';
$string['expectedanswers_help'] = 'One expected answer or regular expression per line. Prefix a line with `/` and end with `/` (with optional flags) to treat it as a regular expression, e.g. `/\\bgood (morning|afternoon)\\b/i`. Matching is performed against the automatic transcript of the student\'s recording.';
$string['airubric'] = 'AI assessment rubric / instructions';
$string['airubric_help'] = 'Instructions sent to the AI assessment engine together with the transcript, e.g. what aspects of fluency, grammar, vocabulary or pronunciation to evaluate, and how to score them.';

// Attempt / player UI.
$string['norecording'] = 'No recording submitted.';
$string['recordyouranswer'] = 'Record your answer';
$string['startrecording'] = 'Start recording';
$string['startrecordingx'] = 'Start recording {$a}';
$string['startcamerax'] = 'Start camera {$a}';
$string['stoprecording'] = 'Stop recording';
$string['timeremaining'] = 'Time remaining: {$a}';
$string['recordingsubmitted'] = 'Recording submitted';
$string['awaitingtranscription'] = 'Your recording has been received and is being processed. This can take a minute or two.';
$string['awaitingaiassessment'] = 'Your recording has been transcribed and sent for assessment.';
$string['transcript'] = 'Transcript';
$string['norecordingpermission'] = 'This browser or device could not access the microphone. Please check permissions and try again.';

// Recorder widget strings (used directly by amd/src/avrecording.js via
// M.util.get_string calls - keys and structure follow qtype_recordrtc,
// The Open University, GPL v3+).
$string['timedisplay'] = '{$a->mins}:{$a->secs}';
$string['startsharescreen'] = 'Start screen recording';
$string['recordagainx'] = 'Re-record {$a}';
$string['uploadpreparing'] = 'Preparing upload ...';
$string['uploadpreparingpercent'] = 'Preparing upload ({$a}%)';
$string['uploadprogress'] = 'Uploading ({$a})';
$string['uploadcomplete'] = 'Recording uploaded';
$string['uploadfailed'] = 'Upload failed';
$string['uploadfailed404'] = 'Upload failed (file too big?)';
$string['uploadaborted'] = 'Saving aborted';
$string['recordingfailed'] = 'Recording failed';
$string['pause'] = 'Pause';
$string['resume'] = 'Resume';
$string['recordagain'] = 'Re-record';
$string['nearingmaxsize'] = 'You have attained the maximum size limit for file uploads';
$string['nearingmaxsize_title'] = 'Recording stopped';
$string['gumabort'] = 'Something strange happened which prevented the webcam/microphone from being used';
$string['gumabort_title'] = 'Something happened';
$string['gumnotallowed'] = 'You must allow the browser access to the webcam/microphone';
$string['gumnotallowed_title'] = 'Wrong permissions';
$string['gumnotfound'] = 'There is no input device connected or enabled';
$string['gumnotfound_title'] = 'Device missing';
$string['gumnotreadable'] = 'Something is preventing the browser from accessing the webcam/microphone';
$string['gumnotreadable_title'] = 'Hardware error';
$string['gumnotsupported'] = 'Your browser does not support recording over an insecure connection and must close the plugin';
$string['gumnotsupported_title'] = 'No support for insecure connection';
$string['gumoverconstrained'] = 'The current webcam/microphone can not produce a stream with the required constraints';
$string['gumoverconstrained_title'] = 'Problem with constraints';
$string['gumsecurity'] = 'Your browser does not support recording over an insecure connection and must close the plugin';
$string['gumsecurity_title'] = 'No support for insecure connection';
$string['gumtype'] = 'Tried to get stream from the webcam/microphone, but no constraints were specified';
$string['gumtype_title'] = 'No constraints specified';
$string['nowebrtc'] = 'Your browser offers limited or no support for WebRTC technologies yet, and cannot be used with this type of question. Please switch or upgrade your browser.';
$string['nowebrtctitle'] = 'WebRTC not supported';
$string['insecurewarning'] = 'Your browser will not allow this plugin to work unless it is used over HTTPS.';
$string['insecurewarningtitle'] = 'Insecure connection';
$string['err_compatibility'] = 'While iOS/iPadOS devices can record video files in open formats, they cannot currently play them back. Until Apple fix this, you will have to use another device to view this video.';
$string['downloadrecording'] = 'Download {$a}';
$string['speakup:downloadrecordings'] = 'Download recordings';

// Privacy.
$string['privacy:metadata:speakup_response'] = 'Information about a student\'s spoken response to a SpeakUp question.';
$string['privacy:metadata:speakup_response:questionattemptid'] = 'The question attempt this response belongs to.';
$string['privacy:metadata:speakup_response:transcript'] = 'The automatic transcript generated from the student\'s recording.';
$string['privacy:metadata:speakup_response:aifeedback'] = 'Feedback generated by the AI assessment engine.';
$string['privacy:metadata:audiofile'] = 'The audio recording itself, stored as a Moodle file, containing the student\'s voice.';
$string['privacy:metadata:externalai'] = 'If AI holistic assessment is used, the transcript (and possibly audio) of the student\'s response is sent to an external AI/transcription service for assessment.';
$string['privacy:metadata:externalai:transcript'] = 'The transcript of the spoken response.';
$string['privacy:metadata:externalai:audio'] = 'The audio recording, if the configured service performs its own transcription.';

// Errors.
$string['errornotimelimit'] = 'You must set a time limit greater than zero.';
$string['err_invalidregex'] = 'This does not look like a valid regular expression: {$a}';
$string['erroraiconfigmissing'] = 'AI holistic assessment is selected but no AI assessment engine is configured. Contact your administrator.';
