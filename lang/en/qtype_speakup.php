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
$string['mediatype_help'] = 'What students give as their response:

* **Audio only**: they speak.
* **Audio + video (webcam)**: they speak on camera, so AI assessment can also consider non-verbal communication, such as facial expressions and gestures.
* **Image**: they upload one or more images or PDFs, e.g. scans or photos of their handwriting, or a drawing. Images are always uploaded, and need a multimodal AI provider to read them for automated grading.';
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
$string['audioprompt_help'] = 'An optional audio prompt the student hears instead of, or as well as, the written question text. Record it here, or upload an audio file. It is shown as an audio player under the question text.';
$string['audioprompttranscript'] = 'Transcript of the audio prompt';
$string['audioprompttranscript_help'] = 'A transcript of the audio prompt, e.g. for students who cannot hear it. While you record the prompt, the browser\'s speech recognition (if available) writes the transcript here. Please check it, and correct any mistakes.

If the site has a speech provider, and there is no audio prompt when the question is saved, the audio prompt is generated from this transcript.';
$string['prompttranscriptdisplay'] = 'Show audio prompt transcript';
$string['prompttranscriptdisplay_help'] = 'How the transcript of the audio prompt is shown to students:

* **No** - the transcript is not shown.
* **Yes, collapsed** - students see a "Show transcript" link, and can choose to show it.
* **Yes, expanded** - the transcript is always shown under the audio prompt.';
$string['prompttranscriptcollapsed'] = 'Yes, collapsed';
$string['prompttranscriptexpanded'] = 'Yes, expanded';
$string['transcriptpenalty'] = 'Penalty for showing the transcript';
$string['transcriptpenalty_help'] = 'The percentage of the question grade taken off if a student shows the collapsed transcript of the audio prompt.

Students who need the transcript, e.g. because they cannot hear the prompt, can be exempted from the penalty: give them the capability "qtype/speakup:viewtranscriptwithoutpenalty", e.g. with a role for those students in the course. Teachers can see whether a student showed the transcript.

Questions with a penalty are graded after the response is submitted, in the background.';
$string['showtranscriptpenalty'] = '(penalty -{$a}%)';
$string['confirmtranscripttitle'] = 'Show the transcript?';
$string['confirmtranscript'] = 'Viewing the transcript will reduce your grade by {$a}%. Are you sure you want to see it?';
$string['calctranscriptpenalty'] = 'Penalty for showing the transcript: -{$a}%';
$string['transcriptviewed'] = 'Transcript viewed.';
$string['transcriptviewedexempt'] = 'Transcript viewed (exempt: no penalty).';
$string['speakup:viewtranscriptwithoutpenalty'] = 'Show the audio prompt transcript without the penalty';
$string['recordafterprompt'] = 'Start recording when the prompt finishes';
$string['recordafterprompt_help'] = 'If checked, recording starts as soon as the prompt has finished playing, so the student must respond straight away. The prompt is the audio prompt above, if there is one, or else the first audio player in the question text. Students can still start recording themselves before the prompt finishes.';
$string['silencestop'] = 'Stop recording after a silence of';
$string['silencestop_help'] = 'Recording stops automatically when the student has been silent for this long. Silence only counts once the student has started speaking, so they are not cut off while they think. Pausing the recording (if allowed) does not count as silence.';
$string['silencestopped'] = 'Recording stopped after {$a} seconds of silence.';
$string['maxpromptplays'] = 'Number of times the prompt can be played';
$string['maxpromptplays_help'] = 'How many times students can play the prompt (the audio prompt above, or else the first audio player in the question text). Pausing and resuming counts as one play.';
$string['maxresponses'] = 'Number of responses allowed';
$string['maxresponses_help'] = 'How many times students can record (or upload) a response. Once they have used them all, they can no longer record again.';
$string['recordafterpromptnote'] = 'Recording starts as soon as the prompt finishes.';
$string['maxpromptplaysnote'] = 'You can play the prompt {$a} time(s).';
$string['maxresponsesnote'] = 'You can give {$a} response(s).';
$string['listenfirst'] = 'Listen to the prompt first. Recording will start when it finishes.';
$string['promptplaysused'] = 'You have played the prompt as many times as allowed.';
$string['responsesused'] = 'You have given as many responses as allowed.';
$string['recordaudioprompt'] = 'Record the audio prompt';
$string['audiopromptdeleted'] = 'Audio prompt deleted.';
$string['uploadwrongtypeaudio'] = 'Please choose an audio file.';
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
$string['itemgroup_help'] = 'The award for this item is the percent multiplied by the multiplier. It is given in full when the response reaches the target. Below the target, the award is in proportion to the value. For example, "Target for Words is 100. Award up to 20% exactly" awards 10% for 50 words, 15% for 75 words, and 20% for 100 words or more. "Multiplied by 2" would double each of these.

The awards of all the target items add up, together with the target phrases and the AI assessment (if used), and the total is capped at 100%.

* **Characters** - letters and digits, not counting spaces or punctuation.
* **Words** - separated by spaces.
* **Long words** - words with three or more syllables.
* **Unique words** - different words, ignoring capital letters.
* **Lexical density** - unique words as a percentage of words.
* **Speech rate** - words per minute of recording.';
$string['itemaward'] = 'Target item [{no}] award';
$string['itemtext1'] = 'Target for';
$string['itemtext2'] = 'is';
$string['itemtext3'] = '.';
$string['itemtext4'] = 'Award up to';
$string['itemtext5'] = 'of the question grade.';
$string['itemmultiplier1'] = 'exactly';
$string['itemmultipliern'] = 'multiplied by {$a}';
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
$string['err_itempercent'] = 'Choose a percent for this item.';
$string['err_phrasepercent'] = 'Choose a percent for this phrase.';
$string['err_itemcount'] = 'Enter a target greater than 0 for this item.';
$string['err_itemneedsai'] = 'This item counts sentences, which needs a transcript with punctuation. The browser\'s live transcript has none, so use AI assessment, or "Upload", or choose a different item.';
$string['showheader'] = 'Grading details';
$string['showheader_help'] = 'Choose who sees each detail of an automated grade, when the response is reviewed. Viewers who can grade quizzes count as teachers; everyone else counts as a student.';
$string['showtranscript'] = 'Show transcript';
$string['showtranscript_help'] = 'Show the transcript of the response: the transcript made by the AI provider, if there is one, or else the live transcript from the browser\'s speech recognition.';
$string['showphrases'] = 'Show target phrases';
$string['showphrases_help'] = 'Show which target phrases were used in the response, and the share of the question grade each one awards.';
$string['showitems'] = 'Show target items';
$string['showitems_help'] = 'Show the value of each target item for the response (e.g. the number of words), and the share of the question grade it awards.';
$string['showcalculation'] = 'Show grade calculation';
$string['showcalculation_help'] = 'Show how the grade was calculated: the target phrases, the target items and the holistic (AI) assessment, and the total.';
$string['showcomment'] = 'Show AI feedback';
$string['showcomment_help'] = 'Show the feedback from the AI assessment. It is saved as the grading comment, so this setting also applies to a comment added by a teacher.';
$string['showstats'] = 'Show statistics';
$string['showstats_help'] = 'Show statistics about the transcript of the response, e.g. the number of words, when the response is reviewed. Choose which statistics to show below.';
$string['statsgroup'] = 'Statistics';
$string['statsgroup_help'] = 'The statistics to show, counted in the transcript of the response.';
$string['statsaigroup'] = 'If an AI transcript is available';
$string['statsaigroup_help'] = 'These statistics need sentences, which can only be counted in a transcript with punctuation. Only a transcript made by the AI provider has punctuation, so these statistics are only shown when there is one.

The fog index estimates how hard the language is to follow: 0.4 x (words per sentence + 100 x long words / words).';
$string['statistics'] = 'Statistics';
$string['statvalue'] = '{$a->label}: {$a->value}';
$string['itemparagraphs'] = 'Paragraphs';
$string['itemsentencesperparagraph'] = 'Sentences per paragraph';
$string['itemfogindex'] = 'Fog index';
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
$string['aiaudioonly'] = 'Send only the sound of videos to the AI';
$string['aiaudioonly_help'] = 'If checked, only the sound of a video response is sent to the AI provider, to be transcribed and assessed. This is quicker and costs less, and works with providers that accept audio but not video. Use it when only what the student says is assessed, and the video is there to show who is speaking.

Leave it unchecked if the AI should also assess what it can see, e.g. gestures and eye contact.

Teachers always see the whole video. Taking out the sound needs ffmpeg on the server (see the SpeakUp site settings); without it, the whole video is sent.';
$string['aiassessmentfailed'] = 'Your response could not be processed. It will be graded manually by your teacher.';
$string['aiassessmentfailedreason'] = 'The response could not be assessed, so it needs to be graded manually. Reason: {$a}';
$string['aiassessmentretrying'] = 'Assessing the response failed, and will be tried again later. Reason: {$a}';
$string['assessmentfailedsubject'] = 'A SpeakUp response could not be assessed';
$string['assessmentfailedmessage'] = 'The response of {$a->student} to the question "{$a->question}" in the quiz "{$a->quiz}" could not be assessed, so it needs to be graded manually.

Reason: {$a->reason}

Grade it here: {$a->url}';
$string['assessmentfailedpreviewmessage'] = 'Your preview of the question "{$a->question}" could not be assessed.

Reason: {$a->reason}';
$string['audiopromptfailedsubject'] = 'A SpeakUp audio prompt could not be generated';
$string['audiopromptfailedmessage'] = 'The audio prompt of the question "{$a->question}" could not be generated from its transcript. You can record or upload one on the question\'s edit page, or save the question again to try again.

Reason: {$a->reason}';
$string['gradeitnow'] = 'Grade it now';
$string['messageprovider:assessmentfailed'] = 'SpeakUp responses that could not be assessed';
$string['messageprovider:audiopromptfailed'] = 'SpeakUp audio prompts that could not be generated';
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
$string['durationtoolong'] = 'This recording is {$a->duration} seconds long. The time limit is {$a->limit} seconds.';
$string['uploadfile'] = 'Upload a file';
$string['uploaddifferentfile'] = 'Upload a different file';
$string['nofileuploaded'] = 'No file uploaded.';
$string['fileuploaded'] = 'File uploaded.';
$string['uploadwrongtype'] = 'Please choose an audio or video file.';
$string['uploadtoobig'] = 'This file is too big. The largest file you can upload is {$a}.';
$string['uploadtoobigmedia'] = 'This file is {$a->size}, but the largest file you can upload is {$a->limit}. Please upload a shorter recording, or make the file smaller, e.g. by recording at a lower quality.';
$string['uploadtoobigpdf'] = '"{$a->name}" is {$a->size}, but the largest file you can upload is {$a->limit}. Please make it smaller, e.g. by scanning at a lower resolution, or in black and white.';
$string['uploadtoolong'] = 'This file is {$a->duration} seconds long, but the time limit is {$a->limit} seconds. It has been uploaded, but it will be marked as too long.';
$string['notmediafile'] = 'The uploaded file is not an audio or video file. Please upload a different file.';
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
$string['capability_help'] = 'Whether this provider can only assess a text transcript, or can accept the files themselves: audio or video (for assessing tone, pronunciation, gesture, etc), or images and PDFs (e.g. scans of handwriting). Moodle\'s core AI subsystem is always text-only, since its generate_text action cannot accept files.

A speech provider turns text into speech, to generate audio prompts from their transcripts. Its request template uses the tokens `{{TEXT}}`, `{{VOICE}}` and `{{LANGUAGE}}` (e.g. en-US). If its reply is the audio itself (e.g. OpenAI), leave the return path empty. If its reply is JSON, set the return path to the base64-encoded audio (e.g. `audioContent` for Google Cloud, or `candidates,0,content,parts,0,inlineData,data` for Gemini).';
$string['aicapability_text'] = 'Text only (transcript)';
$string['aicapability_multimodal'] = 'Multimodal (audio, video and image files)';
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
$string['returnpath_help'] = 'Comma-separated path to the answer within the provider\'s JSON response, e.g. `choices,0,message,content` for an OpenAI-style chat completion response. For a speech provider, this is the path to the base64-encoded audio; leave it empty if the reply is the audio itself.';
$string['requesttemplate'] = 'Request body template';
$string['requesttemplate_help'] = 'The JSON request body to send, exactly as this provider expects it. The easiest way to start is to choose a preset at the top of this form.

These tokens are replaced when a request is sent:

* `{{RUBRIC}}`: the assessment instructions.
* `{{TRANSCRIPT}}`: the transcript, if any.
* `{{MODEL}}` and `{{APIKEY}}`: the model and API key from this form. These also work in the URL and headers.
* `{{FILE_PARTS}}`: for multimodal providers, the response\'s files (a recording, or up to 5 images or PDFs). The file part template below is repeated once for each file, and each copy is followed by a comma. So put `{{FILE_PARTS}}` just before another item in a list, without a comma after it, e.g. `"parts": [{{FILE_PARTS}} {"text": "{{RUBRIC}}"}]`. With no files, the list is still valid.
* `{{FILE_BASE64}}`, `{{FILE_MIMETYPE}}`, `{{FILE_URI}}` and `{{FILE_NAME}}`: for a provider that only takes one file, the file itself, without a file part template. (`{{AUDIO_BASE64}}`, `{{AUDIO_MIMETYPE}}` and `{{AUDIO_FILE_URI}}` still work too.)

For a speech provider: `{{TEXT}}`, `{{VOICE}}` and `{{LANGUAGE}}`.

Example for Gemini:
`{"contents": [{"parts": [{{FILE_PARTS}} {"text": "{{RUBRIC}}\\n\\n{{TRANSCRIPT}}"}]}]}`';
$string['err_requesttemplaterequired'] = 'A request body template is required for an external provider.';

// AI calling (classes/ai_caller.php).
$string['aipromptinstructions'] = 'Assess the response according to the instructions above, out of 100. If the recording or transcript does not actually attempt the task (e.g. it is silent, off-topic, or the student is asking about the task itself rather than doing it), still give a score reflecting that (typically 0, or low if a partial genuine attempt is present) and explain why in the feedback - never omit the score. End your reply with a line reading exactly "SCORE: N" where N is a whole number from 0 to 100, after any explanatory feedback. This line is mandatory in every response, with no exceptions.';
$string['aiprompttranscribe'] = 'First, transcribe exactly what the student says in the recording (or in the audio track of the video). The student is expected to speak {$a}. Do not correct any mistakes in grammar, vocabulary or pronunciation: write what the student actually says, with punctuation. Begin your reply with a line reading exactly "TRANSCRIPT:", followed by the transcript. Then write a line reading exactly "FEEDBACK:", followed by your feedback.';
$string['aiprompttranscribeonly'] = 'Transcribe exactly what the speaker says in this recording (or in the audio track of this video). The speaker is expected to speak {$a}. Do not correct any mistakes in grammar, vocabulary or pronunciation: write what is actually said, with punctuation. Reply with the transcript only.';
$string['aipromptoverlong'] = 'Note: the recording is {$a->duration} seconds long, but the time limit for this task was {$a->limit} seconds.';
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
$string['err_templateneedsfileuri'] = 'With the "upload first, then reference" style, the template must include the {{FILE_URI}} token (or {{FILE_PARTS}}, with {{FILE_URI}} in the file part template), or the uploaded files will never be sent.';
$string['err_templateneedsbase64'] = 'With the "inline" upload style, the template must include the {{FILE_BASE64}} token (or {{FILE_PARTS}}, with {{FILE_BASE64}} in the file part template), or the files will never be sent.';
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
$string['privacy:metadata:ai_provider'] = 'For questions using AI assessment, the student\'s recording and/or its transcript is sent to an external AI service (either Moodle\'s own core AI subsystem, or an external provider such as OpenAI or Google Gemini, as configured by the site or question administrator) for transcription, assessment, and/or feedback generation. The transcripts of audio prompts, written by teachers, are sent to the speech provider to generate the audio; these contain no data about students.';
$string['privacy:metadata:ai_provider:transcript'] = 'A transcript of the student\'s spoken response, sent to the AI provider to be assessed.';
$string['privacy:metadata:ai_provider:recording'] = 'The student\'s audio/video recording, or the images of their written work, sent to the AI provider for providers that can process them directly.';

// Text-to-speech: generating audio prompts from their transcripts.
$string['aicapability_speech'] = 'Speech (text-to-speech)';
$string['audiopromptgeneratenote'] = 'If there is no audio prompt when you save the question, it will be generated from this transcript.';
$string['audiopromptnotready'] = 'The audio prompt is being prepared. Please try again in a few minutes.';
$string['audiopromptpending'] = 'The audio prompt is being generated from this transcript. It should be ready in a few minutes.';
$string['audiopromptvoice'] = 'Voice';
$string['audiopromptvoice_help'] = 'The voice used to generate the audio prompt from its transcript, e.g. "alloy" for OpenAI or "Kore" for Gemini. Leave this empty to use the site\'s voice for the question\'s language, or else the site\'s default voice.';
$string['err_templateneedstext'] = 'A speech provider\'s request template must include the {{TEXT}} token.';
$string['errortts'] = 'Speech could not be generated: {$a}';
$string['errorttsnotaudio'] = 'the speech provider\'s reply was not audio.';
$string['errorttsnotext'] = 'there is no text.';
$string['errorttstoolong'] = 'the text is longer than {$a} characters.';
$string['taskgenerateaudioprompt'] = 'Generate audio prompts from their transcripts';
$string['taskassessresponse'] = 'Assess SpeakUp responses';
$string['taskreduceresponsefiles'] = 'Make large SpeakUp response files smaller';

// Making large response files smaller.
$string['pathtoffmpeg'] = 'Path to ffmpeg';
$string['pathtoffmpeg_desc'] = 'ffmpeg makes audio and video files smaller. On Ubuntu and Debian, install it with "sudo apt install ffmpeg"; it is then usually /usr/bin/ffmpeg.';
$string['reducecondition_exec'] = 'PHP can run programs (the exec() function is enabled)';
$string['reducecondition_ffmpeg'] = 'ffmpeg was found, for audio and video files';
$string['reducecondition_gd'] = 'PHP\'s GD library is available, for images';
$string['reducecondition_ghostscript'] = 'Ghostscript was found, for PDF files ("Path to ghostscript", in Site administration > Server > System paths)';
$string['reducefiles'] = 'Make large response files smaller';
$string['reducefiles_desc'] = 'Make students\' large audio, video, image and PDF files smaller, in the background, after the quiz is submitted. Audio becomes mono MP3, video becomes MP4 at 720p, images become JPEG at most 2000 pixels wide or tall, and PDFs have their images at 150 dpi. A file is only replaced if the result is clearly smaller. Files whose tools are missing (see above) are kept as they are.';
$string['reducefilesheading'] = 'Large response files';
$string['reducefilesheading_desc'] = 'Students often upload very large files, e.g. long videos or photos straight from a phone. Making them smaller saves space, makes them quicker for teachers to open, and means AI providers are less likely to refuse them. This uses programs on the server:';
$string['reducethreshold'] = 'Make files smaller if they are bigger than';
$string['reducethreshold_desc'] = 'Smaller files are kept as they are.';
$string['replaceoriginals'] = 'Replace the original files';
$string['replaceoriginals_desc'] = 'If checked, a large file is replaced by the smaller one, which saves space. If not, the original file is kept, and the smaller one is only made for the AI provider, when the response is assessed.';
$string['ttsheading'] = 'Text-to-speech';
$string['ttsheading_desc'] = 'A speech provider can generate audio prompts from their transcripts, e.g. for questions imported from GIFT, or questions saved with a transcript but no audio prompt. The audio is generated in the background, usually within a few minutes. Speech providers are set up on the "Manage AI providers" page, with the capability "Speech (text-to-speech)".';
$string['ttsproviderid'] = 'Speech provider';
$string['ttsproviderid_desc'] = 'The provider that generates audio prompts from their transcripts. Choose "None" to turn this off.';
$string['ttsvoice'] = 'Default voice';
$string['ttsvoice_desc'] = 'The voice used for audio prompts, if neither the question nor the voices for each language below give one, e.g. "alloy" for OpenAI or "Kore" for Gemini. Leave this empty to use the provider\'s own default.';
$string['ttsvoices'] = 'Voices for each language';
$string['ttsvoices_desc'] = 'One per line, the language code, "=", and the voice, e.g. "en-GB=fable" or "ja=Kore". A line for "en" is used for any English without a line of its own, e.g. "en-AU".';

// GIFT import and export.
$string['giftbadparts'] = 'not recognised: {$a}';
$string['giftbadvalue'] = '{$a->name}={$a->value} ({$a->error})';
$string['giftexpectlanguage'] = 'expected a language code, e.g. en-US';
$string['giftexpectnumber'] = 'expected a whole number from {$a->min} to {$a->max}';
$string['giftexpectoneof'] = 'expected one of: {$a}';
$string['giftexpecttargetitem'] = 'expected (itemtype, target, percent%, xmultiplier), e.g. (words, 50, 40%, x1)';
$string['giftexpecttargetphrase'] = 'expected ("phrase", percent%, /divisor, full|partial, case|nocase), e.g. ("I have played", 10%, /1, partial, nocase)';
$string['giftexpectyesno'] = 'expected yes or no';
$string['giftnearestvalue'] = 'not allowed, so {$a} was used';
$string['giftnoaudioprompt'] = 'This question has an audio prompt file but no transcript, so the audio prompt is not included.';
$string['giftnoprovider'] = 'there is no AI provider called "{$a}" on this site';
$string['giftunknownline'] = 'This line was not recognised, and was skipped: {$a}';
$string['giftunknownsetting'] = 'This setting was not recognised, and was skipped: {$a}';
$string['giftwarning'] = 'SpeakUp question "{$a->name}": {$a->message}';
$string['subtotal'] = 'Up to {$a}%';

// Image responses, e.g. scans of handwriting.
$string['aiprompttranscribeimage'] = 'The student\'s response is their handwritten (or drawn) work in the attached file(s), which may be photos, scans or PDFs, with one or more pages. The student is expected to write {$a}. First, transcribe exactly what the student wrote, page by page, under headings such as "Page 1:". Do not correct any mistakes in spelling, grammar or vocabulary: write what the student actually wrote. Describe any drawings briefly in [square brackets]. If a word cannot be read, write [unclear]. Begin your reply with a line reading exactly "TRANSCRIPT:", followed by the transcript. Then write a line reading exactly "FEEDBACK:", followed by your feedback.';
$string['aiprompttranscribeonlyimage'] = 'Transcribe exactly what is written in the attached file(s), which may be photos, scans or PDFs of handwriting, with one or more pages, page by page under headings such as "Page 1:". The writer is expected to write {$a}. Do not correct any mistakes in spelling, grammar or vocabulary: write what is actually written. Describe any drawings briefly in [square brackets]. If a word cannot be read, write [unclear]. Reply with the transcript only.';
$string['err_speechrateimage'] = 'Speech rate cannot be used for image responses.';
$string['maxfiles'] = 'Maximum number of files';
$string['maxfiles_help'] = 'For image responses: the most files a student can upload, e.g. one for each page of their handwriting. A PDF counts as one file, however many pages it has.';
$string['maxfilesnote'] = 'You can upload up to {$a} files, e.g. one for each page.';
$string['mediatype_image'] = 'Image (e.g. a handwriting scan)';
$string['notimagefile'] = 'An uploaded file is not an image or a PDF. Please remove it, and upload a different file.';
$string['pageremoved'] = 'Page removed.';
$string['pagex'] = 'Page {$a}';
$string['removepage'] = 'Remove';
$string['removepagex'] = 'Remove page {$a}';
$string['imageshrunk'] = '"{$a->name}" was too big to upload ({$a->before}), so it was made smaller ({$a->after}).';
$string['imagetoobig'] = '"{$a->name}" is {$a->size}, and could not be made small enough to upload. The largest file you can upload is {$a->limit}.';
$string['pagerotated'] = 'The page was rotated.';
$string['rotateleft'] = 'Rotate left';
$string['rotateleftx'] = 'Rotate page {$a} left';
$string['rotateright'] = 'Rotate right';
$string['rotaterightx'] = 'Rotate page {$a} right';
$string['toomanyfiles'] = 'You can upload up to {$a} files.';
$string['uploadanotherimage'] = 'Upload another page';
$string['uploadimage'] = 'Upload an image or PDF';
$string['uploadwrongtypeimage'] = 'Please choose an image or a PDF file.';

// AI provider presets, and file part templates.
$string['err_filetemplaterequired'] = 'The request template includes {{FILE_PARTS}}, so a file part template is needed.';
$string['errorfiletemplatejson'] = 'The AI provider\'s file part template is not valid JSON.';
$string['errorfiletemplatemissing'] = 'The AI provider\'s request template includes {{FILE_PARTS}}, but it has no file part template.';
$string['errorproviderfiletype'] = 'The AI provider\'s file part template has no part for {$a} files, so it cannot be sent this response.';
$string['errortemplatemultiplefiles'] = 'This response has more than one file, but the AI provider\'s request template only takes one. Use {{FILE_PARTS}} and a file part template instead.';
$string['filetemplate'] = 'File part template';
$string['filetemplate_help'] = 'For multimodal providers: the JSON for one file, e.g. one image. It is repeated once for each of the response\'s files, at `{{FILE_PARTS}}` in the request template. In it, these tokens are replaced by each file\'s details: `{{FILE_BASE64}}` (the file, base64-encoded), `{{FILE_MIMETYPE}}` (e.g. image/jpeg), `{{FILE_NAME}}`, and, if files are uploaded first, `{{FILE_URI}}`.

Example for Gemini, which takes audio, video, images and PDFs in the same way:
`{"inline_data": {"mime_type": "{{FILE_MIMETYPE}}", "data": "{{FILE_BASE64}}"}}`

Some providers need a different part for different kinds of file. Then give an object of MIME types and parts. `image/*` matches any image, and `*` matches any file. Example for Claude:
`{"image/*": {"type": "image", "source": {"type": "base64", "media_type": "{{FILE_MIMETYPE}}", "data": "{{FILE_BASE64}}"}}, "application/pdf": {"type": "document", "source": {"type": "base64", "media_type": "application/pdf", "data": "{{FILE_BASE64}}"}}}`

A file with no matching part cannot be sent to this provider.';
$string['preset'] = 'Preset';
$string['preset_help'] = 'Fill in this form with the settings for a common AI provider. Then add your API key, and change anything you need to, e.g. the model. Models change often, so check the provider\'s documentation for the latest ones.

* **Gemini**: assesses audio, video, images and PDFs.
* **Gemini (large files)**: uploads files to Gemini first, for long recordings.
* **ChatGPT** and **Claude**: assess images and PDFs (e.g. handwriting), and transcripts, but not audio files.
* **Gemini speech** and **OpenAI speech**: generate audio prompts from their transcripts.';
$string['preset_claude'] = 'Claude (Anthropic)';
$string['preset_gemini'] = 'Gemini (Google)';
$string['preset_geminitts'] = 'Gemini speech (text-to-speech)';
$string['preset_geminiupload'] = 'Gemini (Google), large files';
$string['preset_openai'] = 'ChatGPT (OpenAI)';
$string['preset_openaitts'] = 'OpenAI speech (text-to-speech)';

// The Moodle app.
$string['mobileapp'] = 'Allow SpeakUp questions in the Moodle app';
$string['mobileapp_desc'] = 'If this is on, and the site meets the conditions above, students can answer SpeakUp questions in the Moodle app. Otherwise, the app offers to open quizzes with SpeakUp questions in the browser.';
$string['mobilebadkey'] = 'This page can only be opened from the Moodle app. Please go back to the question, and try again.';
$string['mobilecondition_framembedding'] = '"Allow frame embedding" is on (Site administration > General > Security > HTTP security).';
$string['mobilecondition_mobileapp'] = '"Allow SpeakUp questions in the Moodle app" (below) is on.';
$string['mobilecondition_mobileservice'] = 'Web services for mobile devices are enabled (Site administration > General > Mobile app > Mobile settings).';
$string['mobileheading'] = 'Moodle app';
$string['mobileheading_desc'] = 'In the Moodle app, students answer SpeakUp questions in a frame from this site, so that the recorder and uploader work just as they do in a browser. The frame does not need cookies: the app opens it with a single-use key, and it uses the app\'s own login for files. This needs all of these:';
$string['mobileloading'] = 'Loading...';
$string['mobilenoattempt'] = 'This question cannot be answered here: it is not in one of your quiz attempts that is still in progress.';
$string['mobilenotsupported'] = 'Not all of these are met, so the Moodle app offers to open quizzes with SpeakUp questions in the browser.';
$string['mobilesupported'] = 'All of these are met, so students can answer SpeakUp questions in the Moodle app (version 4.2 or later).';
