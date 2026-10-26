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
$string['pluginnamesummary'] = 'Students give a timed spoken response, graded automatically (target phrases and AI) or by a teacher.';

// Editing form.
$string['timelimitinseconds'] = 'Response time limit';
$string['timelimitinseconds_help'] = 'The maximum time, in seconds, the student has to record their spoken response after they start recording.';
$string['allowpause'] = 'Allow pausing';
$string['allowpause_help'] = 'Whether the student can pause and resume mid-recording. Off by default: pausing/resuming mid-recording is a more fragile code path (it also restarts live browser transcription, if used), so leave this off unless a student genuinely needs to pause partway through.';
$string['mediatype'] = 'Response type';
$string['mediatype_help'] = 'Audio only, or audio plus webcam video. Video lets AI assessment also consider non-verbal communication such as facial expressions and gestures, in addition to the spoken language.';
$string['mediatype_audio'] = 'Audio only';
$string['mediatype_video'] = 'Audio + video (webcam)';
$string['responsemethod'] = 'Response method';
$string['responsemethod_help'] = 'How students give their response:

* **Record** - students record their response live, in the browser.
* **Upload** - students upload an audio or video file. On a smartphone, choosing a file usually also offers to record one there and then.

Uploaded files have no live transcript. So, unless they are graded manually, they are first transcribed by an AI provider that receives the audio or video file itself, and then graded by target phrases and/or AI. This happens in the background, after the response is submitted. The time limit still applies to uploads: files that are longer than the time limit are flagged when the response is reviewed.';
$string['responsemethod_record'] = 'Record';
$string['responsemethod_upload'] = 'Upload';
$string['transcriptionproviderid'] = 'Transcription provider';
$string['transcriptionproviderid_help'] = 'Uploaded files have no live transcript, so without AI assessment, this AI provider transcribes them first, and the transcript is then graded by the target phrases. The provider must be one that receives the audio or video file itself.';
$string['err_transcriptionproviderrequired'] = 'Uploaded files need an AI provider to transcribe them. Please choose one.';
$string['err_uploadneedsprovider'] = 'Uploaded files need an AI provider to transcribe them, but none are configured yet. Choose "Record", or "Manual" grading, or ask an administrator to add an AI provider.';
$string['err_uploadneedsmultimodal'] = 'Uploaded files have no live transcript, so they need an AI provider that receives the audio or video file itself. Choose a different provider, or "Record".';
$string['audioprompt'] = 'Audio prompt (optional)';
$string['audioprompt_help'] = 'An optional audio file the student hears instead of, or as well as, the written question text.';
$string['gradingmethod'] = 'Grading method';
$string['gradingmethod_help'] = 'How the response is graded:

* **Automated** - the grade is made up of the target phrases found in a transcript of the response, plus (if used) the AI assessment\'s score multiplied by its weighting. The total is capped at 100%. A teacher can still change the grade afterwards.
* **Manual** - a teacher listens and grades by hand; no automatic grading is attempted.';
$string['gradingmethod_auto'] = 'Automated';
$string['gradingmethod_manual'] = 'Manual';
$string['feedbackmode'] = 'Feedback mode';
$string['feedbackmode_help'] = 'Formative feedback is shown to the student to help them improve, without necessarily contributing to a final grade. Summative feedback is shown together with a final grade for the attempt.';
$string['feedbackmode_formative'] = 'Formative (practice)';
$string['feedbackmode_summative'] = 'Summative (assessed)';
$string['expectedanswers'] = 'Target phrases';
$string['expectedanswers_help'] = 'Each target phrase that is used in the response awards its own share of the question grade. The shares add up, capped at 100%. The phrases are checked in a transcript of the response: the live transcript from the browser for recordings, or a transcript made by the AI provider.';
$string['phrasegroup'] = 'Target phrase [{no}]';
$string['phrasegroup_help'] = 'The share of the question grade awarded if this phrase is used in the response. The shares of all the target phrases that are used add up, together with the target items and the AI assessment (if used), and the total is capped at 100%.

A phrase matches anywhere in the transcript. Spaces in the phrase match any spaces in the transcript. To use a regular expression instead, start the phrase with `/` and end it with `/` plus optional flags, e.g. `/\\bgood (morning|afternoon)\\b/i`.

"Divided by" makes shares that are not whole numbers, e.g. 100% divided by 3 is 33.33%.';
$string['phrasetext1'] = 'If';
$string['phrasetext2'] = 'is used, award';
$string['phrasetext3'] = 'of the question grade.';
$string['phrasedivisor1'] = 'exactly';
$string['phrasedivisorn'] = 'divided by {$a}';
$string['phraseoptions'] = 'Target phrase [{no}] options';
$string['phrasefullmatch0'] = 'Match full or partial words.';
$string['phrasefullmatch1'] = 'Match full words only.';
$string['phrasecasesensitive0'] = 'Match is case-insensitive.';
$string['phrasecasesensitive1'] = 'Match is case-sensitive.';
$string['addmorephrase1'] = '1 more target phrase';
$string['addmorephrases'] = '{$a} more target phrases';
$string['sttlanguage'] = 'Response language';
$string['sttlanguage_help'] = 'The language the student is expected to speak, as a language tag, e.g. `en-US`, `en-GB`, `ja-JP`.

For live recordings, the browser\'s built-in speech recognition (if available) uses it to make an instant transcript. If the student\'s browser does not support speech recognition, or this language, live transcription is skipped, and the response is still recorded normally.

For uploaded files, it is given to the AI provider as a hint when transcribing.';
$string['itemsheader'] = 'Target items';
$string['itemsheader_help'] = 'Target items award part of the question grade for how much the student says, e.g. the number of words, or the speech rate. They are counted in a transcript of the response.';
$string['itemgroup'] = 'Target item [{no}]';
$string['itemgroup_help'] = 'Below the first number, this item awards nothing. From the first number, it awards a share of the percent in proportion to the value, up to the full percent at the second number. For example, "up to 20% for 50 to 100 Words" awards nothing for 40 words, 10% for 50 words, 15% for 75 words, and 20% for 100 words or more.

The scores of all the target items add up, together with the target phrases and the AI assessment (if used), and the total is capped at 100%.

* **Characters** - letters and digits, not counting spaces or punctuation.
* **Words** - separated by spaces.
* **Sentences** - ended by a full stop, question mark or exclamation mark.
* **Unique words** - different words, ignoring capital letters.
* **Long words** - words with three or more syllables.
* **Characters / Words / Long words per sentence** - divided by the number of sentences.
* **Lexical density** - unique words as a percentage of words.
* **Speech rate** - words per minute of recording.

Items that count sentences need a transcript with punctuation, which only an AI provider makes. So they can only be used with AI assessment, or with uploaded files.';
$string['itemtext1'] = 'Proportionally award up to';
$string['itemtext2'] = 'of the question grade for';
$string['itemtext3'] = 'to';
$string['itemchars'] = 'Characters';
$string['itemwords'] = 'Words';
$string['itemsentences'] = 'Sentences';
$string['itemuniquewords'] = 'Unique words';
$string['itemlongwords'] = 'Long words';
$string['itemcharspersentence'] = 'Characters per sentence';
$string['itemwordspersentence'] = 'Words per sentence';
$string['itemlongwordspersentence'] = 'Long words per sentence';
$string['itemlexicaldensity'] = 'Lexical density (%)';
$string['itemspeechrate'] = 'Speech rate (words per minute)';
$string['addmoreitem1'] = '1 more target item';
$string['addmoreitems'] = '{$a} more target items';
$string['itemscore'] = '{$a->item}: {$a->value} ({$a->percent}% of the question grade).';
$string['itemunknown'] = 'unknown';
$string['noitemtranscript'] = 'No transcript is available for this response, so the target items could not be counted.';
$string['err_itemmax'] = 'Enter numbers for this item, and a second number greater than 0.';
$string['err_itemrange'] = 'The first number must not be greater than the second number.';
$string['err_itemneedsai'] = 'This item counts sentences, which needs a transcript with punctuation. The browser\'s live transcript has none, so use AI assessment, or "Upload", or choose a different item.';
$string['showheader'] = 'Grading details';
$string['showheader_help'] = 'Choose who sees each detail of an automated grade, when the response is reviewed. Viewers who can grade quizzes count as teachers; everyone else counts as a student.';
$string['showphrases'] = 'Show target phrases';
$string['showphrases_help'] = 'Show which target phrases were used in the response, and the share of the question grade each one awards.';
$string['showitems'] = 'Show target items';
$string['showitems_help'] = 'Show the value of each target item for the response (e.g. the number of words), and the share of the question grade it awards.';
$string['showcalculation'] = 'Show grade calculation';
$string['showcalculation_help'] = 'Show how the grade was calculated: the target phrases, the target items and the holistic (AI) assessment, and the total.';
$string['showcomment'] = 'Show grading comment';
$string['showcomment_help'] = 'Show the grading comment, e.g. the feedback from the AI assessment, or a comment added by a teacher.';
$string['showtostudentsonly'] = 'Yes, show to students only';
$string['showtoteachersonly'] = 'Yes, show to teachers only';
$string['showtoteachersandstudents'] = 'Yes, show to teachers and students';
$string['phraseresultused'] = '✓ {$a->phrase} ({$a->percent}% of the question grade)';
$string['phraseresultnotused'] = '✗ {$a->phrase} (not used)';
$string['calculation'] = 'Grade calculation';
$string['calcphrases'] = 'Target phrases: {$a}%';
$string['calcitems'] = 'Target items: {$a}%';
$string['calcholistic'] = 'Holistic assessment: {$a->score} out of 100, weighted at {$a->weighting}% = {$a->percent}%';
$string['calctotal'] = 'Total: {$a}%';
$string['calctotalcapped'] = 'Total: {$a}% (capped at 100%)';
$string['aisettings'] = 'AI assessment settings';
$string['aisettings_help'] = 'Settings for assessing the response by AI, as part of automated grading.';
$string['aiassessment'] = 'Use AI assessment';
$string['aiassessment_help'] = 'If checked, the response is also assessed by AI, using the rubric and provider below. This happens in the background, after the response is submitted. The AI\'s feedback is shown as the grading comment, and its score counts towards the grade according to the weighting below.';
$string['aiweighting'] = 'AI weighting';
$string['aiweighting_help'] = 'The percentage of the question grade given by the AI assessment. For example, with a weighting of 50%, an AI score of 80 adds 40% to the grade. With a weighting of 0%, the AI\'s feedback is shown, but it does not affect the grade.';
$string['airubric'] = 'AI assessment rubric / instructions';
$string['airubric_help'] = 'Instructions sent to the AI assessment engine together with the transcript, e.g. what aspects of fluency, grammar, vocabulary or pronunciation to evaluate, and how to score them.

You can include all or part of the question text, as plain text, with these placeholders:

* `{{questiontext}}` - the whole question text
* `{{questiontext.rubric}}` - the first element with `class="rubric"`
* `{{questiontext#task}}` - the element with `id="task"`
* `{{questiontext div.rubric}}` - the first `div` with `class="rubric"`

Multilang content (`lang="xx"`) is sent to the AI in English, so every student is graded against the same rubric. For another language, add it after a colon, e.g. `{{questiontext.rubric:ja}}`. `<summary>` labels and media players are left out. To keep part of the question text away from students, put it in an element with the `d-none` class, e.g. `<div class="rubric d-none">...</div>`.';
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
$string['aiassessmentpending'] = 'Your response is waiting to be assessed. Your grade and feedback will appear here when they are ready.';
$string['aiassessmentfailed'] = 'Your response could not be processed. It will be graded manually by your teacher.';
$string['transcript'] = 'Transcript';
$string['browsertranscript'] = 'Live transcript (from browser speech recognition)';
$string['phrasesmatched'] = 'Target phrases used: {$a->matched} of {$a->total} ({$a->percent}% of the question grade).';
$string['suggestedmatch_none'] = 'No transcript is available for this response, so the target phrases could not be checked.';
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
$string['requesttemplate_help'] = 'The JSON request body to send, exactly as this provider expects it, with these tokens substituted at call time: `{{RUBRIC}}` (the assessment instructions), `{{TRANSCRIPT}}` (the transcript, if any), `{{MODEL}}` (the model name, from the Model field below), `{{APIKEY}}` (the API key, from the field above), `{{AUDIO_BASE64}}` (the recording, base64-encoded, for multimodal providers using inline upload), `{{AUDIO_MIMETYPE}}` (its MIME type, e.g. audio/webm), `{{AUDIO_FILE_URI}}` (for providers using the "upload first, then reference" style below - the reference the provider gave back after uploading, in place of {{AUDIO_BASE64}}). `{{MODEL}}` and `{{APIKEY}}` also work in the URL and extra-headers fields above - useful for a provider that puts the model in its URL path, or the key in a header rather than the request body. Example for an OpenAI-style chat completion: `{"model": "{{MODEL}}", "messages": [{"role": "system", "content": "{{RUBRIC}}"}, {"role": "user", "content": "{{TRANSCRIPT}}"}]}`. Example for Gemini using upload-then-reference: `{"contents": [{"parts": [{"text": "{{RUBRIC}}"}, {"file_data": {"mime_type": "{{AUDIO_MIMETYPE}}", "file_uri": "{{AUDIO_FILE_URI}}"}}]}]}`.';
$string['err_requesttemplaterequired'] = 'A request body template is required for an external provider.';

// AI calling (classes/ai_caller.php).
$string['aipromptinstructions'] = 'Assess the response according to the instructions above, out of 100. If the recording or transcript does not actually attempt the task (e.g. it is silent, off-topic, or the student is asking about the task itself rather than doing it), still give a score reflecting that (typically 0, or low if a partial genuine attempt is present) and explain why in the feedback - never omit the score. End your reply with a line reading exactly "SCORE: N" where N is a whole number from 0 to 100, after any explanatory feedback. This line is mandatory in every response, with no exceptions.';
$string['aiprompttranscriptlabel'] = 'Transcript of the student\'s spoken response:';
$string['errorcoreaiunavailable'] = 'Moodle\'s core AI subsystem is not available on this site.';
$string['errornotranscript'] = 'No transcript is available to assess (the student\'s browser may not have supported live transcription).';
$string['erroraiprovider'] = 'The AI provider returned an error: {$a}';
$string['errorproviderconfig'] = 'No AI provider is configured for this question, or the configured provider could not be found.';
$string['uploadprotocol'] = 'Upload protocol';
$string['uploadprotocol_help'] = 'Which provider-specific "upload the file first" protocol to use. A real upload-then-reference flow works differently for every provider that offers one, so each supported protocol is implemented separately - if the provider you need is not listed, use "Inline" upload instead (works for any provider, up to that provider\'s own request-size limit).';
$string['uploadprotocol_gemini'] = 'Google Gemini (resumable upload)';
$string['uploadurl'] = 'Upload URL';
$string['uploadurl_help'] = 'The endpoint used to upload the file itself, before referencing it in the main request - separate from the main API URL above, since the upload and assessment endpoints are often different paths on the same host. Supports the `{{APIKEY}}` token. For Gemini: `https://generativelanguage.googleapis.com/upload/v1beta/files?key={{APIKEY}}`.';
$string['err_uploadprotocolrequired'] = 'Please choose which upload protocol this provider uses.';
$string['err_uploadurlrequired'] = 'An upload URL is required for the "upload first, then reference" style.';
$string['erroruploadprotocolunknown'] = 'This provider\'s configured upload protocol ({$a}) is not recognised.';
$string['err_templateneedsfileuri'] = 'With the "upload first, then reference" style, the request template must include the {{AUDIO_FILE_URI}} token, or the uploaded recording will never be sent.';
$string['err_templateneedsbase64'] = 'With the "inline" upload style, the request template must include the {{AUDIO_BASE64}} token, or the recording will never be sent.';
$string['errortemplatemissingaudio'] = 'This provider is configured as multimodal, but its request template does not include the token for its upload style ({$a}), so the recording would not be sent. Edit the provider\'s request template.';
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

// Privacy API (classes/privacy/provider.php).
$string['privacy:metadata:qtype_speakup_response'] = 'Per-attempt information about a spoken response: its transcript, and the AI\'s assessment of it.';
$string['privacy:metadata:qtype_speakup_response:questionattemptid'] = 'The question attempt this response belongs to.';
$string['privacy:metadata:qtype_speakup_response:transcript'] = 'A text transcript of the student\'s spoken response.';
$string['privacy:metadata:qtype_speakup_response:aifeedback'] = 'Feedback generated by the AI provider about this specific response.';
$string['privacy:metadata:qtype_speakup_response:aiscore'] = 'The score (0-1) generated by the AI provider for this response.';
$string['privacy:metadata:qtype_speakup_response:timecreated'] = 'The time this record was created.';
$string['privacy:metadata:qtype_speakup_response:timemodified'] = 'The time this record was last updated.';
$string['privacy:metadata:ai_provider'] = 'For questions using AI assessment, the student\'s recording and/or its transcript is sent to an external AI service (either Moodle\'s own core AI subsystem, or an external provider such as OpenAI or Google Gemini, as configured by the site or question administrator) for transcription, assessment, and/or feedback generation.';
$string['privacy:metadata:ai_provider:transcript'] = 'A transcript of the student\'s spoken response, sent to the AI provider to be assessed.';
$string['privacy:metadata:ai_provider:recording'] = 'The student\'s audio/video recording, sent to the AI provider for providers that can process audio/video directly.';
