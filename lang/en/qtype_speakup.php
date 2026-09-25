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
$string['allowpause'] = 'Allow pausing';
$string['allowpause_help'] = 'Whether the student can pause and resume mid-recording. Off by default: pausing/resuming mid-recording is a more fragile code path (it also restarts live browser transcription, if used), so leave this off unless a student genuinely needs to pause partway through.';
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
$string['expectedanswers'] = 'Target phrases';
$string['expectedanswers_help'] = 'Each phrase (or regular expression) contributes up to its own weight% of the pattern-matching grade if it matches the live transcript of the student\'s recording. Matched phrases add up, capped at 100%. A plain phrase matches as a substring anywhere in the transcript (case-insensitive, unless "Case sensitive" is checked); to use a regular expression instead, prefix a line with `/` and end it with `/` plus optional flags, e.g. `/\\bgood (morning|afternoon)\\b/i`.';
$string['answertext'] = 'Phrase';
$string['weight'] = 'Weight (%)';
$string['casesensitive'] = 'Case sensitive';
$string['addmorephrases'] = 'Add {no} more phrases';
$string['err_invalidweight'] = 'Weight must be between 0 and 100.';
$string['sttlanguage'] = 'Live transcription language';
$string['sttlanguage_help'] = 'The language the student is expected to speak, used by the browser\'s built-in live speech recognition (if available) to produce an instant transcript for pattern matching, e.g. `en-US`, `en-GB`, `ja-JP`. If the student\'s browser does not support speech recognition, or does not support this language, live transcription is silently skipped and the response is still recorded normally for manual grading.';
$string['aisettings'] = 'AI assessment settings';
$string['aisettings_help'] = 'Settings used when this question is assessed (in full or in part) by AI.';
$string['airubric'] = 'AI assessment rubric / instructions';
$string['airubric_help'] = 'Instructions sent to the AI assessment engine together with the transcript, e.g. what aspects of fluency, grammar, vocabulary or pronunciation to evaluate, and how to score them.';
$string['aiproviderid'] = 'AI provider';
$string['aiproviderid_help'] = 'Which admin-configured AI provider to use for assessing this question. Providers (and their credentials) are set up separately by a site administrator under Site administration > Plugins > Question types > Manage AI providers - you only choose which one to use here.';
$string['noaiproviders'] = 'No AI providers are configured yet. An administrator can add one under Site administration > Plugins > Question types > Manage AI providers.';
$string['err_aiproviderrequired'] = 'Please choose an AI provider, or configure one first under Site administration > Plugins > Question types > Manage AI providers.';

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
$string['aiassessmentpending'] = 'AI assessment pending - this response is waiting to be assessed. A grade will appear here once it is ready; a teacher can review or override it at any time.';
$string['aiassessmentfailed'] = 'AI assessment could not be completed for this response. It has been left for manual grading.';
$string['transcript'] = 'Transcript';
$string['browsertranscript'] = 'Live transcript (from browser speech recognition)';
$string['suggestedmatch_yes'] = 'Suggested match: model answer matched.';
$string['suggestedmatch_no'] = 'Suggested match: no model answer matched.';
$string['suggestedmatch_partial'] = 'Suggested match: {$a}% (some but not all target phrases matched).';
$string['suggestedmatch_none'] = 'No live transcript was captured for this response (unsupported browser/language, or pattern-matching is not used for this question).';
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
$string['speakup:manageaiproviders'] = 'Manage AI providers for SpeakUp questions';

// AI provider admin management page.
$string['aiprovidersheading'] = 'AI providers';
$string['aiprovidersheading_desc'] = 'Configure AI providers here (URLs, API keys, etc). Teachers setting up a question can then simply pick a provider by name - they never see or enter credentials directly.';
$string['manageaiproviders'] = 'Manage AI providers';
$string['manageaiproviders_intro'] = 'These providers can be selected when a teacher sets a SpeakUp question\'s grading method to include AI assessment. Credentials entered here are never shown on the question editing form.';
$string['addprovider'] = 'Add a new AI provider';
$string['editprovider'] = 'Edit AI provider';
$string['providersaved'] = 'AI provider saved.';
$string['providerdeleted'] = 'AI provider "{$a}" deleted.';
$string['confirmdeleteprovider'] = 'Delete the AI provider "{$a}"? Any question currently set to use it will fall back to manual grading for its AI portion.';
$string['name'] = 'Name';
$string['providertype'] = 'Provider type';
$string['providertype_help'] = 'Moodle\'s own core AI subsystem (configured under Site administration > AI), or an arbitrary external REST API.';
$string['aiprovidertype_core'] = 'Moodle core AI subsystem';
$string['aiprovidertype_external'] = 'External AI provider';
$string['capability'] = 'Capability';
$string['capability_help'] = 'Whether this provider can only assess a text transcript, or can accept the actual audio/video file directly (for assessing tone, pronunciation, gesture, etc). Moodle\'s core AI subsystem is always text-only, since its generate_text action cannot accept files.';
$string['aicapability_text'] = 'Text only (transcript)';
$string['aicapability_multimodal'] = 'Multimodal (audio/video file)';
$string['corename'] = 'Core provider name (optional)';
$string['corename_help'] = 'The specific core AI provider plugin to use, e.g. `aiprovider_openai`. Leave blank to use whichever core AI provider is available.';
$string['url'] = 'API URL';
$string['url_help'] = 'The full API endpoint URL, exactly as this provider expects it. The `{{MODEL}}` and `{{APIKEY}}` tokens (see the request body template help) can be used here too - useful for a provider that puts the model name in its URL path, e.g. `https://generativelanguage.googleapis.com/v1beta/models/{{MODEL}}:generateContent`.';
$string['username'] = 'Username (optional)';
$string['apikey'] = 'API key';
$string['headers'] = 'Extra HTTP headers';
$string['headers_help'] = 'JSON object of extra HTTP headers to send, e.g. `{"Authorization": "Bearer {{APIKEY}}"}` or `{"x-goog-api-key": "{{APIKEY}}"}`. The `{{MODEL}}` and `{{APIKEY}}` tokens (see the request body template help) also work here. Leave blank to fall back to `Authorization: Bearer <API key>` automatically, or leave entirely empty for a provider that needs no auth header.';
$string['model'] = 'Model';
$string['returnpath'] = 'Response key path';
$string['returnpath_help'] = 'Comma-separated path to the answer within the provider\'s JSON response, e.g. `choices,0,message,content` for an OpenAI-style chat completion response.';
$string['requesttemplate'] = 'Request body template';
$string['requesttemplate_help'] = 'The JSON request body to send, exactly as this provider expects it, with these tokens substituted at call time: `{{RUBRIC}}` (the assessment instructions), `{{TRANSCRIPT}}` (the transcript, if any), `{{MODEL}}` (the model name, from the Model field below), `{{APIKEY}}` (the API key, from the field above), `{{AUDIO_BASE64}}` (the recording, base64-encoded, for multimodal providers using inline upload), `{{AUDIO_MIMETYPE}}` (its MIME type, e.g. audio/webm). `{{MODEL}}` and `{{APIKEY}}` also work in the URL and extra-headers fields above - useful for a provider that puts the model in its URL path, or the key in a header rather than the request body. Example for an OpenAI-style chat completion: `{"model": "{{MODEL}}", "messages": [{"role": "system", "content": "{{RUBRIC}}"}, {"role": "user", "content": "{{TRANSCRIPT}}"}]}`.';
$string['err_requesttemplaterequired'] = 'A request body template is required for an external provider.';

// AI calling (classes/ai_caller.php).
$string['aipromptinstructions'] = 'Assess the response according to the instructions above, out of 100. If the recording or transcript does not actually attempt the task (e.g. it is silent, off-topic, or the student is asking about the task itself rather than doing it), still give a score reflecting that (typically 0, or low if a partial genuine attempt is present) and explain why in the feedback - never omit the score. End your reply with a line reading exactly "SCORE: N" where N is a whole number from 0 to 100, after any explanatory feedback. This line is mandatory in every response, with no exceptions.';
$string['aiprompttranscriptlabel'] = 'Transcript of the student\'s spoken response:';
$string['errorcoreaiunavailable'] = 'Moodle\'s core AI subsystem is not available on this site.';
$string['errornotranscript'] = 'No transcript is available to assess (the student\'s browser may not have supported live transcription).';
$string['erroraiprovider'] = 'The AI provider returned an error: {$a}';
$string['errorproviderconfig'] = 'No AI provider is configured for this question, or the configured provider could not be found.';
$string['erroruploadreferencenotsupported'] = 'The "upload first, then reference" file upload style is not yet supported.';
$string['errorrequestnotvalidjson'] = 'The provider\'s request template does not produce valid JSON once the rubric/transcript are inserted. Check the template for unescaped special characters.';
$string['erroraiproviderresponse'] = 'The AI provider\'s response could not be understood (invalid JSON, or the configured response key path did not match).';
$string['erroraiscoreunparseable'] = 'The AI did not return a score in the expected format.';
$string['uploadstyle'] = 'File upload style';
$string['uploadstyle_help'] = 'How the audio/video file is sent to a multimodal provider: inline (base64-encoded directly in the request body) or as a separate upload referenced by id/URL in the main request. Only relevant for multimodal providers.';
$string['uploadstyle_none'] = 'N/A (text only)';
$string['uploadstyle_inline_base64'] = 'Inline (base64-encoded in request body)';
$string['uploadstyle_upload_reference'] = 'Upload first, then reference by id/URL';
$string['enabled'] = 'Enabled';
$string['err_urlrequired'] = 'A URL is required for an external provider.';
$string['err_invalidurl'] = 'This does not look like a valid URL.';
$string['err_invalidjson'] = 'This does not look like valid JSON.';
$string['err_invalidcorename'] = 'This should look like a Moodle AI provider component name, e.g. aiprovider_openai.';
$string['erroraiconfigmissing'] = 'AI holistic assessment is selected but no AI assessment engine is configured. Contact your administrator.';
